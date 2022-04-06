<?php

use App\MyMessage;
use App\MyMessageHandler;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;

require __DIR__.'/vendor/autoload.php';

$sqliteFile = sys_get_temp_dir().'/symfony.messenger.sqlite';
$dsn = getenv('MESSENGER_DOCTRINE_DSN') ?: 'sqlite:///'.$sqliteFile;
$driverConnection = DriverManager::getConnection(['url' => $dsn]);
$connection = new Connection([], $driverConnection);

$doctrineTransport = new DoctrineTransport($connection, new PhpSerializer());
$handler = new MyMessageHandler();

// simple sender locator that always sends messages to the same transport
$sendersLocator = new class($doctrineTransport) implements SendersLocatorInterface
{
    public function __construct(private DoctrineTransport $doctrineTransport)
    {
    }
    public function getSenders(Envelope $envelope): iterable
    {
        yield $this->doctrineTransport;
    }
};

// simple handler locator that always handles messages via the same handler
$handlersLocator = new class($handler) implements HandlersLocatorInterface
{
    public function __construct(private MyMessageHandler $myMessageHandler)
    {}

    public function getHandlers(Envelope $envelope): iterable
    {
        yield new HandlerDescriptor($this->myMessageHandler);
    }
};

$messageBus = new MessageBus([
    new SendMessageMiddleware($sendersLocator),
    new HandleMessageMiddleware($handlersLocator),
]);

$i = 0;
while ($i < 5) {
    $i++;
    $messageBus->dispatch(
        new MyMessage('Message #'.$i),
        // delay each message by 1 second more
        [new DelayStamp($i * 1000)],
    );
}

$worker = new Worker(
    [$doctrineTransport],
    $messageBus
);
//sleep(2);
$worker->run();
