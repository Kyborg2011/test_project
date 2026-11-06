<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\DeletionReason;
use App\Models\StoredFile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class StoredFileTest extends TestCase
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

    public function test_generates_public_uuid_and_keeps_incrementing_id(): void
    {
        $file = StoredFile::factory()->create();

        $this->assertGreaterThan(0, $file->id);
        $this->assertTrue(Str::isUuid($file->uuid));
        $this->assertSame('uuid', $file->getRouteKeyName());
    }

    public function test_casts_attributes(): void
    {
        $file = StoredFile::factory()->deleted(DeletionReason::Expired)->create(['size' => 2048]);
        $file->refresh();

        $this->assertSame(2048, $file->size);
        $this->assertInstanceOf(CarbonImmutable::class, $file->expires_at);
        $this->assertInstanceOf(CarbonImmutable::class, $file->deleted_at);
        $this->assertSame(DeletionReason::Expired, $file->deletion_reason);
    }

    public function test_available_scope_excludes_expired_and_deleted_files(): void
    {
        $available = StoredFile::factory()->create();
        StoredFile::factory()->expired()->create();
        StoredFile::factory()->deleted()->create();

        $this->assertSame([$available->id], StoredFile::query()->available()->pluck('id')->all());
    }

    public function test_expired_scope_returns_only_expired_not_deleted_files(): void
    {
        StoredFile::factory()->create();
        $expired = StoredFile::factory()->expired()->create();
        StoredFile::factory()->expired()->deleted(DeletionReason::Expired)->create();

        $this->assertSame([$expired->id], StoredFile::query()->expired()->pluck('id')->all());
    }

    public function test_file_expires_exactly_at_expiration_moment(): void
    {
        $file = StoredFile::factory()->create(['expires_at' => CarbonImmutable::now()]);

        $this->assertTrue($file->isExpired());
        $this->assertSame(0, StoredFile::query()->available()->count());
        $this->assertSame(1, StoredFile::query()->expired()->count());
    }

    public function test_scopes_accept_explicit_moment(): void
    {
        StoredFile::factory()->create(['expires_at' => CarbonImmutable::now()->addHour()]);
        $later = CarbonImmutable::now()->addHours(2);

        $this->assertSame(0, StoredFile::query()->available($later)->count());
        $this->assertSame(1, StoredFile::query()->expired($later)->count());
    }

    public function test_availability_helpers(): void
    {
        $available = StoredFile::factory()->make();
        $expired = StoredFile::factory()->expired()->make();
        $deleted = StoredFile::factory()->deleted()->make();

        $this->assertTrue($available->isAvailable());
        $this->assertFalse($expired->isAvailable());
        $this->assertTrue($expired->isExpired());
        $this->assertFalse($deleted->isAvailable());
        $this->assertTrue($deleted->isDeleted());
    }

    public function test_deletion_fields_are_not_mass_assignable(): void
    {
        $this->assertFalse((new StoredFile())->isFillable('deleted_at'));
        $this->assertFalse((new StoredFile())->isFillable('deletion_reason'));
    }
}
