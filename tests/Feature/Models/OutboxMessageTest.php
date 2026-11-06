<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OutboxMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_generates_message_id_and_stores_payload_as_json(): void
    {
        $message = OutboxMessage::factory()->create(['payload' => ['file_id' => 7, 'name' => 'a.pdf']]);
        $message->refresh();

        $this->assertTrue(Str::isUuid($message->message_id));
        // MySQL JSON columns do not preserve key order, so compare regardless of it.
        $this->assertEquals(['file_id' => 7, 'name' => 'a.pdf'], $message->payload);
        $this->assertSame(0, $message->attempts);
        $this->assertFalse($message->isPublished());
    }

    public function test_pending_scope_returns_due_unpublished_messages_oldest_first(): void
    {
        $first = OutboxMessage::factory()->create();
        OutboxMessage::factory()->published()->create();
        OutboxMessage::factory()->availableAt(CarbonImmutable::now()->addMinute())->create();
        $second = OutboxMessage::factory()->availableAt(CarbonImmutable::now()->subMinute())->create();

        $this->assertSame([$first->id, $second->id], OutboxMessage::query()->pending()->pluck('id')->all());
    }

    public function test_mark_published(): void
    {
        $message = OutboxMessage::factory()->create();
        $message->markFailed('broker is down', CarbonImmutable::now());

        $message->markPublished(CarbonImmutable::now());
        $message->refresh();

        $this->assertTrue($message->isPublished());
        $this->assertSame(2, $message->attempts);
        $this->assertNull($message->last_error);
        $this->assertSame(0, OutboxMessage::query()->pending()->count());
    }

    public function test_mark_failed_postpones_message(): void
    {
        $message = OutboxMessage::factory()->create();
        $retryAt = CarbonImmutable::now()->addSeconds(30);

        $message->markFailed('broker is down', $retryAt);
        $message->refresh();

        $this->assertFalse($message->isPublished());
        $this->assertSame(1, $message->attempts);
        $this->assertSame('broker is down', $message->last_error);
        $this->assertEquals($retryAt, $message->available_at);
        $this->assertSame(0, OutboxMessage::query()->pending()->count());
        $this->assertSame(1, OutboxMessage::query()->pending($retryAt)->count());
    }

    public function test_mark_failed_truncates_long_errors(): void
    {
        $message = OutboxMessage::factory()->create();

        $message->markFailed(str_repeat('x', 5000), CarbonImmutable::now());

        $this->assertSame(2000, mb_strlen((string) $message->refresh()->last_error));
    }
}
