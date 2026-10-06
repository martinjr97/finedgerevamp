<?php

namespace App\PaymentPlatform\Providers\Kazang;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class KazangAmqpPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(array $payload, string $queueName): void
    {
        if (! (bool) config('kazang.enabled')) {
            throw new KazangException('Kazang payments are disabled.');
        }

        $connection = new AMQPStreamConnection(
            (string) config('kazang.amqp.host'),
            (int) config('kazang.amqp.port'),
            (string) config('kazang.amqp.username'),
            (string) config('kazang.amqp.password'),
        );

        try {
            $channel = $connection->channel();
            $channel->queue_declare($queueName, false, true, false, false);

            $message = new AMQPMessage(json_encode($payload, JSON_THROW_ON_ERROR));
            $channel->basic_publish($message, '', $queueName);

            $channel->close();
        } finally {
            $connection->close();
        }
    }
}
