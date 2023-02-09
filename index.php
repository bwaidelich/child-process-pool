<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wwwision\ChildProcessPool\Model\ClientMessageType;
use Wwwision\ChildProcessPool\Model\ServerEventType;
use Wwwision\ChildProcessPool\Model\Status;


require __DIR__ . '/vendor/autoload.php';

const URI_DEFAULT = '127.0.0.1:8085';

$application = new Application();
$application->register('listen')
    ->addOption('uri', 'u', InputOption::VALUE_OPTIONAL, 'uri foo bar', URI_DEFAULT)
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $server = new Server($input->getOption('uri'));
        $server->on(ServerEventType::LISTENING, fn (string $uri) => $output->writeln("Listening on $uri", OutputInterface::VERBOSITY_NORMAL));
        $server->on(ServerEventType::CLIENT_CONNECTED, fn (string $remoteAddress) => $output->writeln("Client \"$remoteAddress\" connected", OutputInterface::VERBOSITY_VERY_VERBOSE));
        $server->on(ServerEventType::CLIENT_DISCONNECTED, fn (string $remoteAddress) => $output->writeln("Client \"$remoteAddress\" disconnected", OutputInterface::VERBOSITY_VERY_VERBOSE));
        $server->on(ServerEventType::CLIENT_DATA_RECEIVED, fn (string $data) => $output->writeln("Data received: \"$data\"", OutputInterface::VERBOSITY_DEBUG));
        $server->on(ServerEventType::PROCESSING_MESSAGE, fn (ClientMessageType $messageType) => $output->writeln("Processing message of type \"{$messageType->name}\"", OutputInterface::VERBOSITY_VERBOSE));
        $server->on(ServerEventType::PROCESS_STARTED, fn (string $cmd) => $output->writeln("Starting process \"$cmd\"", OutputInterface::VERBOSITY_VERBOSE));
        $server->on(ServerEventType::PROCESS_TERMINATED, fn (string $cmd) => $output->writeln("Process \"$cmd\" terminated", OutputInterface::VERBOSITY_VERBOSE));
        $server->on(ServerEventType::PROCESS_ALREADY_RUNNING, fn (string $cmd) => $output->writeln("Process \"$cmd\" already running, queued", OutputInterface::VERBOSITY_VERY_VERBOSE));
        $server->on(ServerEventType::PROCESS_ALREADY_QUEUED, fn (string $cmd) => $output->writeln("Process \"$cmd\" already queued, skipped", OutputInterface::VERBOSITY_VERY_VERBOSE));
        $server->on(ServerEventType::ERROR, fn (string $message) => $output->writeln("<error>$message</error>", OutputInterface::VERBOSITY_NORMAL));
        $server->start();
        return Command::SUCCESS;
    });
$application->register('status')
    ->addOption('uri', 'u', InputOption::VALUE_OPTIONAL, 'uri foo bar', URI_DEFAULT)
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $client = new Client($input->getOption('uri'));
        $client->fetchStatus()->then(
            function (Status $status) use ($output) {
                $table = new Table($output);
                $table->setRows([
                    ['Uptime', Utils::formatSeconds($status->uptime)],
                    ['Running', $status->running],
                    ['Queued', $status->queued],
                    ['Failed', $status->failed],
                    ['Succeeded', $status->succeeded],
                ]);
                $table->render();
            },
            fn (string $message) => $output->writeln("<error>$message</error>")
        );
        return Command::SUCCESS;
    });
$application->register('run')
    ->addArgument('cmd', InputArgument::REQUIRED, 'cmd foo bar')
    ->addOption('uri', 'u', InputOption::VALUE_OPTIONAL, 'uri foo bar', URI_DEFAULT)
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $client = new Client($input->getOption('uri'));
        $client->run($input->getArgument('cmd'));
        return Command::SUCCESS;
    });
$application->setDefaultCommand('listen');
$application->setVersion('1.0');
$application->run();
