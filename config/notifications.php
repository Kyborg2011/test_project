<?php

declare(strict_types=1);

return [
    // Address that receives "file deleted" e-mails (published to RabbitMQ).
    'recipient' => env('NOTIFICATION_EMAIL', 'admin@example.com'),
];
