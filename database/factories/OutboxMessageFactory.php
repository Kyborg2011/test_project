<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxMessage>
 */
final class OutboxMessageFactory extends Factory
{
    protected $model = OutboxMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'file.deleted',
            'payload' => ['file_id' => $this->faker->randomNumber()],
            'available_at' => CarbonImmutable::now(),
        ];
    }

    public function availableAt(CarbonImmutable $at): self
    {
        return $this->state(fn (): array => ['available_at' => $at]);
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'attempts' => 1,
            'published_at' => CarbonImmutable::now(),
        ]);
    }
}
