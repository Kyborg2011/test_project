<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Mail\NotificationEmail;
use App\Messaging\Amqp\AmqpChannelProvider;
use App\Messaging\Amqp\AmqpSettings;
use App\Messaging\Contracts\MessagePublisher;
use App\Messaging\Exceptions\PublishFailed;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Runs against the real RabbitMQ container, on its own uniquely named exchanges and queues,
 * so it never interferes with the running consumer.
 */
#[Group('integration')]
final class RabbitMqTest extends TestCase
{
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix = 'test.' . Str::lower(Str::random(8)) . '.';
        config([
            'rabbitmq.exchange' => $this->prefix . 'notifications',
            'rabbitmq.queue' => $this->prefix . 'email',
            'rabbitmq.dead_letter_exchange' => $this->prefix . 'dlx',
            'rabbitmq.dead_letter_queue' => $this->prefix . 'email.dead',
        ]);

        foreach ([AmqpSettings::class, AmqpChannelProvider::class, MessagePublisher::class] as $service) {
            $this->app->forgetInstance($service);
        }
    }

    protected function tearDown(): void
    {
        $channels = $this->app->make(AmqpChannelProvider::class);
        $channel = $channels->channel();
        $settings = $channels->settings();
        $channel->queue_delete($settings->queue);
        $channel->queue_delete($settings->deadLetterQueue);
        $channel->exchange_delete($settings->exchange);
        $channel->exchange_delete($settings->deadLetterExchange);
        $channels->close();

        parent::tearDown();
    }

    public function test_published_message_is_persistent_json_with_metadata(): void
    {
        $this->publisher()->publish('msg-1', 'file.deleted', ['email' => ['subject' => 'Привет']]);

        $message = $this->getFromQueue($this->settings()->queue);

        $this->assertNotNull($message);
        $this->assertSame('{"email":{"subject":"Привет"}}', $message->getBody());
        $this->assertSame('msg-1', $message->get('message_id'));
        $this->assertSame('file.deleted', $message->get('type'));
        $this->assertSame('application/json', $message->get('content_type'));
        $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $message->get('delivery_mode'));
    }

    public function test_unroutable_message_is_reported_as_failure(): void
    {
        $channels = $this->app->make(AmqpChannelProvider::class);
        $channels->channel()->queue_unbind($this->settings()->queue, $this->settings()->exchange, $this->settings()->routingKey);

        $this->expectException(PublishFailed::class);
        $this->expectExceptionMessage('could not route the message');

        $this->publisher()->publish('msg-2', 'file.deleted', []);
    }

    public function test_consumer_sends_email_and_acknowledges_message(): void
    {
        Mail::fake();
        $this->publisher()->publish('msg-3', 'file.deleted', [
            'message_id' => 'msg-3',
            'email' => ['to' => 'ops@example.com', 'subject' => 'Deleted', 'body' => 'Body'],
        ]);

        $this->command('notifications:consume', ['--max-messages' => 1])->assertSuccessful();

        Mail::assertSent(NotificationEmail::class, fn (NotificationEmail $mail): bool => $mail->hasTo('ops@example.com'));
        $this->assertNull($this->getFromQueue($this->settings()->queue), 'The message must be acknowledged.');
    }

    public function test_consumer_moves_invalid_message_to_dead_letter_queue(): void
    {
        Mail::fake();
        $this->publisher()->publish('msg-4', 'file.deleted', ['message_id' => 'msg-4', 'email' => ['to' => 'broken']]);

        $this->command('notifications:consume', ['--max-messages' => 1])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($this->getFromQueue($this->settings()->queue));
        $dead = $this->getFromQueue($this->settings()->deadLetterQueue);
        $this->assertNotNull($dead);
        $this->assertSame('msg-4', $dead->get('message_id'));
    }

    private function publisher(): MessagePublisher
    {
        return $this->app->make(MessagePublisher::class);
    }

    private function settings(): AmqpSettings
    {
        return $this->app->make(AmqpSettings::class);
    }

    private function getFromQueue(string $queue): ?AMQPMessage
    {
        // Dead-lettering is asynchronous: give the broker a moment.
        for ($i = 0; $i < 20; $i++) {
            $message = $this->app->make(AmqpChannelProvider::class)->channel()->basic_get($queue, true);

            if ($message !== null) {
                return $message;
            }

            usleep(50_000);
        }

        return null;
    }
}
