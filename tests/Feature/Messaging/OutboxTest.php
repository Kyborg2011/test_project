<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Messaging\Outbox;
use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class OutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    }

    public function test_records_message_with_envelope(): void
    {
        $message = (new Outbox())->record('file.deleted', ['version' => 1, 'payload' => ['file_id' => 'abc']]);
        $message->refresh();

        $this->assertTrue(Str::isUuid($message->message_id));
        $this->assertSame('file.deleted', $message->type);
        $this->assertEquals([
            'message_id' => $message->message_id,
            'type' => 'file.deleted',
            'occurred_at' => '2026-11-06T12:00:00+00:00',
            'version' => 1,
            'payload' => ['file_id' => 'abc'],
        ], $message->payload);
        $this->assertEquals(CarbonImmutable::now(), $message->available_at);
        $this->assertSame(1, OutboxMessage::query()->pending()->count());
    }

    public function test_each_message_gets_its_own_id(): void
    {
        $first = (new Outbox())->record('file.deleted', []);
        $second = (new Outbox())->record('file.deleted', []);

        $this->assertNotSame($first->message_id, $second->message_id);
    }

    public function test_message_is_not_stored_when_transaction_is_rolled_back(): void
    {
        try {
            DB::transaction(function (): void {
                (new Outbox())->record('file.deleted', []);

                throw new RuntimeException('Business change failed.');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, OutboxMessage::query()->count());
    }
}
