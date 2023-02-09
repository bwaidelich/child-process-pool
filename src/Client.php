<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool;

use React\Promise\Deferred;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Wwwision\ChildProcessPool\Model\Status;

final class Client
{

    public function __construct(
        private readonly string $uri,
    ) {

    }

    /**
     * @return Promise<Status>
     */
    public function fetchStatus(): Promise
    {
        $deferred = new Deferred();
        $connector = new Connector();
        $connector->connect($this->uri)->then(
            function (ConnectionInterface $connection) use ($deferred) {
                $connection->on('data', function ($data) use ($connection, $deferred) {
                    $connection->close();
                    $deferred->resolve(Status::fromJson($data));
                });
                $connection->write(json_encode(['type' => 'status'], JSON_THROW_ON_ERROR));
            }, fn(\Exception $exception) => $deferred->reject($exception->getMessage()),
        );
        return $deferred->promise();
    }

    public function run(string $cmd): void
    {
        $connector = new Connector();
        $connector->connect($this->uri)->then(
            function (ConnectionInterface $connection) use ($cmd) {
                $connection->write(json_encode(['type' => 'run', 'cmd' => $cmd], JSON_THROW_ON_ERROR));
            }
        );
    }
}
