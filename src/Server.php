<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool;

use React\ChildProcess\Process;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use Wwwision\ChildProcessPool\Model\ClientMessageType;
use Wwwision\ChildProcessPool\Model\ServerEventType;
use Wwwision\ChildProcessPool\Model\Status;

final class Server
{
    /**
     * @var array<string, \Closure[]>
     */
    private array $listeners = [];

    private ?int $uptimeStart = null;

    /** @var array<string, string> */
    private array $running = [];

    /** @var array<string, string> */
    private array $queued = [];
    private int $numberOfFailedProcesses = 0;
    private int $numberOfSucceededProcesses = 0;

    public function __construct(
        private readonly string $uri,
    ) {}

    public function start(): void
    {
        $socket = new SocketServer($this->uri);
        $this->dispatch(ServerEventType::LISTENING, $this->uri);
        $this->uptimeStart = (int)round(microtime(true));
        $socket->on('connection', $this->onClientConnected(...));
    }

    public function on(ServerEventType $event, \Closure $callback): void
    {
        if (!isset($this->listeners[$event->name])) {
            $this->listeners[$event->name] = [];
        }
        $this->listeners[$event->name][] = $callback;
    }

    private function dispatch(ServerEventType $event, mixed ...$payload): void
    {
        foreach ($this->listeners[$event->name] ?? [] as $callback) {
            $callback(...$payload);
        }
    }

    private function onClientConnected(ConnectionInterface $connection): void
    {
        $this->dispatch(ServerEventType::CLIENT_CONNECTED, $connection->getRemoteAddress() ?? '');
        $connection->on('data', fn (string $data) => $this->onData($connection, $data));
        $connection->on('close', fn () => $this->dispatch(ServerEventType::CLIENT_DISCONNECTED, $connection->getRemoteAddress() ?? ''));
    }

    private function onData(ConnectionInterface $connection, string $data): void
    {
        $this->dispatch(ServerEventType::CLIENT_DATA_RECEIVED, $data);
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->dispatch(ServerEventType::ERROR, "Failed to decode JSON data: {$e->getMessage()}");
            return;
        }
        if (!is_array($decoded)) {
            $this->dispatch(ServerEventType::ERROR, sprintf('Expected data to be array, got %s', get_debug_type($decoded)));
            return;
        }
        if (!isset($decoded['type']) || !is_string($decoded['type'])) {
            $this->dispatch(ServerEventType::ERROR, 'Missing/invalid message "type"');
            return;
        }
        try {
            $messageType = ClientMessageType::from($decoded['type']);
        } catch (\ValueError $_) {
            $this->dispatch(ServerEventType::ERROR, sprintf('Unsupported message type "%s"', $decoded['type']));
            return;
        }
        $this->dispatch(ServerEventType::PROCESSING_MESSAGE, $messageType, $decoded);
        switch ($messageType) {
            case ClientMessageType::STATUS:
                $this->status($connection);
                break;
            case ClientMessageType::RUN:
                if (!isset($decoded['cmd'])) {
                    $this->dispatch(ServerEventType::ERROR, 'Missing "cmd"');
                    return;
                }
                $this->run($decoded['cmd']);
                $connection->close();
                break;
        }

    }

    private function status(ConnectionInterface $connection): void
    {
        $now = (int)round(microtime(true));
        $status = new Status(
            uptime: $now - $this->uptimeStart,
            running: count($this->running),
            queued: count($this->queued),
            failed: $this->numberOfFailedProcesses,
            succeeded: $this->numberOfSucceededProcesses,
        );
        try {
            $connection->write($status->toJson());
        } catch (\Exception $exception) {
            $this->dispatch(ServerEventType::ERROR, $exception->getMessage());
        }
    }

    private function run(string $cmd): void
    {
        $cmdHash = md5($cmd);
        if (array_key_exists($cmdHash, $this->queued)) {
            $this->dispatch(ServerEventType::PROCESS_ALREADY_QUEUED, $cmd);
            return;
        }
        if (array_key_exists($cmdHash, $this->running)) {
            $this->queued[$cmdHash] = $cmd;
            $this->dispatch(ServerEventType::PROCESS_ALREADY_RUNNING, $cmd);
            return;
        }
        $this->running[$cmdHash] = $cmd;
        $process = new Process($cmd);
        $process->start();
        assert($process->stdout !== null);
        $process->on('exit', function (int $exitCode) use ($cmd, $cmdHash) {
            unset($this->running[$cmdHash]);
            if ($exitCode !== 0) {
                $this->numberOfFailedProcesses++;
                $this->dispatch(ServerEventType::ERROR, sprintf('command failed with exit code %d', $exitCode));
            } else {
                $this->numberOfSucceededProcesses++;
                $this->dispatch(ServerEventType::PROCESS_TERMINATED, $cmd, $exitCode);
            }
            if (array_key_exists($cmdHash, $this->queued)) {
                unset($this->queued[$cmdHash]);
                $this->run($cmd);
            }
        });
    }

}
