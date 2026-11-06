<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeletionReason;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoredFile>
 */
final class StoredFileFactory extends Factory
{
    protected $model = StoredFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'docx']);

        return [
            'original_name' => $this->faker->slug(3) . '.' . $extension,
            'disk' => 'local',
            'path' => 'uploads/' . Str::uuid() . '.' . $extension,
            'extension' => $extension,
            'mime_type' => $extension === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => $this->faker->numberBetween(1024, 10 * 1024 * 1024),
            'expires_at' => CarbonImmutable::now()->addDay(),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => CarbonImmutable::now()->subMinute(),
        ]);
    }

    public function deleted(DeletionReason $reason = DeletionReason::Manual): self
    {
        return $this->state(fn (): array => [
            'deleted_at' => CarbonImmutable::now(),
            'deletion_reason' => $reason,
        ]);
    }
}
