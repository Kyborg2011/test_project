<?php

declare(strict_types=1);

/*
 * RabbitMQ connection and topology. Validated by App\Messaging\Amqp\AmqpSettings.
 *
 * exchange (direct) --routing_key--> queue --(rejected)--> dead_letter_exchange (fanout) --> dead_letter_queue
 */
return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'app'),
    'password' => env('RABBITMQ_PASSWORD', 'secret'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    // Seconds.
    'connection_timeout' => env('RABBITMQ_CONNECTION_TIMEOUT', 3),
    'read_write_timeout' => env('RABBITMQ_READ_WRITE_TIMEOUT', 10),
    'heartbeat' => env('RABBITMQ_HEARTBEAT', 30),
    // How long the publisher waits for the broker to confirm a message.
    'publish_timeout' => env('RABBITMQ_PUBLISH_TIMEOUT', 5),

    'exchange' => env('RABBITMQ_EXCHANGE', 'notifications'),
    'routing_key' => env('RABBITMQ_ROUTING_KEY', 'email.send'),
    'queue' => env('RABBITMQ_QUEUE', 'notifications.email'),
    'dead_letter_exchange' => env('RABBITMQ_DEAD_LETTER_EXCHANGE', 'notifications.dlx'),
    'dead_letter_queue' => env('RABBITMQ_DEAD_LETTER_QUEUE', 'notifications.email.dead'),

    // How many unacknowledged messages the consumer may hold at once.
    'prefetch' => env('RABBITMQ_CONSUMER_PREFETCH', 10),
];
