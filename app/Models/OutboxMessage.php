<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\OutboxMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A message waiting to be published to the broker (transactional outbox).
 *
 * @property int $id
 * @property string $message_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property CarbonImmutable $available_at
 * @property CarbonImmutable|null $published_at
 * @property string|null $last_error
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class OutboxMessage extends Model
{
    /** @use HasFactory<OutboxMessageFactory> */
    use HasFactory;
    use HasUuids;

    private const int MAX_ERROR_LENGTH = 2000;

    protected $fillable = [
        'type',
        'payload',
        'available_at',
    ];

    /**
     * Only message_id is generated; the primary key stays auto-increment.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['message_id'];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function markPublished(CarbonInterface $publishedAt): void
    {
        $this->attempts++;
        $this->published_at = CarbonImmutable::instance($publishedAt);
        $this->last_error = null;
        $this->save();
    }

    /**
     * Records a failed publish attempt; the message is retried not earlier than $retryAt.
     */
    public function markFailed(string $error, CarbonInterface $retryAt): void
    {
        $this->attempts++;
        $this->last_error = mb_substr($error, 0, self::MAX_ERROR_LENGTH);
        $this->available_at = CarbonImmutable::instance($retryAt);
        $this->save();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * Unpublished messages that are due for (re)publishing, oldest first.
     *
     * @param Builder<self> $query
     */
    #[Scope]
    protected function pending(Builder $query, ?CarbonInterface $now = null): void
    {
        $query->whereNull('published_at')
            ->where('available_at', '<=', $now ?? CarbonImmutable::now())
            ->orderBy('id');
    }
}
