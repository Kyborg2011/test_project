<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Transactional outbox: messages are written in the same transaction as the business change
 * and published to RabbitMQ later by a separate relay process.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->id();
            // Sent to the broker as the AMQP message_id; lets consumers drop duplicates.
            $table->uuid('message_id')->unique();
            $table->string('type', 64);
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            // The relay does not pick the message up before this moment (retry backoff).
            $table->dateTime('available_at');
            $table->dateTime('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
