<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeletionReason;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An uploaded file. The row is kept after deletion (deleted_at + deletion_reason) for history.
 *
 * @property int $id
 * @property string $uuid
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $deleted_at
 * @property DeletionReason|null $deletion_reason
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'original_name',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size',
        'expires_at',
    ];

    /**
     * Only the public uuid is generated; the primary key stays auto-increment.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isExpired(?CarbonInterface $now = null): bool
    {
        return $this->expires_at->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    /**
     * The file can be listed and downloaded.
     */
    public function isAvailable(?CarbonInterface $now = null): bool
    {
        return ! $this->isDeleted() && ! $this->isExpired($now);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'expires_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'deletion_reason' => DeletionReason::class,
        ];
    }

    /**
     * Files that are not deleted and not expired yet.
     *
     * @param Builder<self> $query
     */
    #[Scope]
    protected function available(Builder $query, ?CarbonInterface $now = null): void
    {
        $query->whereNull('deleted_at')->where('expires_at', '>', $now ?? CarbonImmutable::now());
    }

    /**
     * Files whose lifetime is over but which have not been deleted yet.
     *
     * @param Builder<self> $query
     */
    #[Scope]
    protected function expired(Builder $query, ?CarbonInterface $now = null): void
    {
        $query->whereNull('deleted_at')->where('expires_at', '<=', $now ?? CarbonImmutable::now());
    }
}
