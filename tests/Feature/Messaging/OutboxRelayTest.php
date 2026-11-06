<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Messaging\Contracts\MessagePublisher;
use App\Messaging\OutboxRelay;
use App\Messaging\OutboxSettings;
use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeMessagePublisher;
use Tests\TestCase;

final class OutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    private FakeMessagePublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
        config([
            'outbox.batch_size' => 10,
            'outbox.retry_base_seconds' => 5,
            'outbox.retry_max_seconds' => 300,
        ]);
        $this->app->forgetInstance(OutboxSettings::class);

        $this->publisher = new FakeMessagePublisher();
        $this->app->instance(MessagePublisher::class, $this->publisher);
    }

    public function test_publishes_pending_messages_oldest_first_and_marks_them(): void
    {
        $first = OutboxMessage::factory()->create(['payload' => ['n' => 1]]);
        $second = OutboxMessage::factory()->create(['payload' => ['n' => 2]]);

        $result = $this->relay()->relayBatch();

        $this->assertSame(2, $result->published);
        $this->assertFalse($result->failed);
        $this->assertSame([$first->message_id, $second->message_id], $this->publisher->publishedIds());
        $this->assertSame('file.deleted', $this->publisher->published[0]['type']);
        $this->assertSame(['n' => 1], $this->publisher->published[0]['body']);
        $this->assertTrue($first->refresh()->isPublished());
        $this->assertSame(1, $first->attempts);
        $this->assertTrue($second->refresh()->isPublished());
    }

    public function test_skips_published_and_not_yet_due_messages(): void
    {
        OutboxMessage::factory()->published()->create();
        OutboxMessage::factory()->availableAt(CarbonImmutable::now()->addMinute())->create();

        $result = $this->relay()->relayBatch();

        $this->assertSame(0, $result->published);
        $this->assertSame([], $this->publisher->published);
    }

    public function test_respects_batch_size(): void
    {
        config(['outbox.batch_size' => 2]);
        $this->app->forgetInstance(OutboxSettings::class);
        OutboxMessage::factory()->count(3)->create();

        $this->assertSame(2, $this->relay()->relayBatch()->published);
        $this->assertSame(1, $this->relay()->relayBatch()->published);
        $this->assertSame(0, $this->relay()->relayBatch()->published);
    }

    public function test_broker_failure_postpones_message_and_stops_the_batch(): void
    {
        $sent = OutboxMessage::factory()->create();
        $failed = OutboxMessage::factory()->create();
        $untouched = OutboxMessage::factory()->create();
        $this->publisher->failAfter(1);

        $result = $this->relay()->relayBatch();

        $this->assertSame(1, $result->published);
        $this->assertTrue($result->failed);
        $this->assertTrue($sent->refresh()->isPublished());

        $failed->refresh();
        $this->assertFalse($failed->isPublished());
        $this->assertSame(1, $failed->attempts);
        $this->assertSame('Connection refused', $failed->last_error);
        $this->assertEquals(CarbonImmutable::now()->addSeconds(5), $failed->available_at);

        $untouched->refresh();
        $this->assertSame(0, $untouched->attempts);
        $this->assertNull($untouched->last_error);
    }

    public function test_retry_delay_grows_with_each_failure(): void
    {
        $message = OutboxMessage::factory()->create();
        $this->publisher->failAfter(0);

        foreach ([5, 10, 20] as $expectedDelay) {
            $this->relay()->relayBatch();
            $message->refresh();

            $this->assertEquals(CarbonImmutable::now()->addSeconds($expectedDelay), $message->available_at);
            CarbonImmutable::setTestNow($message->available_at);
        }

        $this->assertSame(3, $message->attempts);
    }

    public function test_message_is_published_after_broker_recovers(): void
    {
        $message = OutboxMessage::factory()->create();
        $this->publisher->failAfter(0);
        $this->relay()->relayBatch();

        $this->publisher->recover();
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(5));
        $result = $this->relay()->relayBatch();

        $this->assertSame(1, $result->published);
        $message->refresh();
        $this->assertTrue($message->isPublished());
        $this->assertSame(2, $message->attempts);
        $this->assertNull($message->last_error);
    }

    public function test_command_once_publishes_a_batch(): void
    {
        OutboxMessage::factory()->count(2)->create();

        $this->command('outbox:relay', ['--once' => true])
            ->expectsOutputToContain('Published: 2.')
            ->assertSuccessful();

        $this->assertSame(0, OutboxMessage::query()->pending()->count());
    }

    public function test_command_once_fails_when_broker_is_unavailable(): void
    {
        OutboxMessage::factory()->create();
        $this->publisher->failAfter(0);

        $this->command('outbox:relay', ['--once' => true])
            ->expectsOutputToContain('Publishing failed')
            ->assertFailed();
    }

    private function relay(): OutboxRelay
    {
        return $this->app->make(OutboxRelay::class);
    }
}
