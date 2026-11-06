<?php

declare(strict_types=1);

namespace Tests\Feature\Files;

use App\Models\StoredFile;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FileListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    }

    public function test_lists_only_available_files_newest_first(): void
    {
        $older = StoredFile::factory()->create(['original_name' => 'older.pdf']);
        $newer = StoredFile::factory()->create([
            'original_name' => 'newer.docx',
            'expires_at' => CarbonImmutable::now()->addHours(3)->addMinutes(5),
        ]);
        StoredFile::factory()->expired()->create(['original_name' => 'expired.pdf']);
        StoredFile::factory()->deleted()->create(['original_name' => 'deleted.pdf']);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSeeInOrder(['newer.docx', 'older.pdf'])
            ->assertDontSee('expired.pdf')
            ->assertDontSee('deleted.pdf')
            ->assertSee('3 h 5 min')
            ->assertSee(route('files.download', $newer), false)
            ->assertSee('data-delete-url="' . route('files.destroy', $older) . '"', false)
            ->assertSee('js/files.js', false);
    }

    public function test_escapes_file_names(): void
    {
        StoredFile::factory()->create(['original_name' => '<script>alert(1)</script>.pdf']);

        $this->get(route('files.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;.pdf', false);
    }

    public function test_shows_empty_state(): void
    {
        $this->get(route('files.index'))->assertOk()->assertSee('No files yet.');
    }

    public function test_paginates_by_configured_page_size(): void
    {
        config(['files.per_page' => 2]);
        $this->app->forgetInstance(FileSettings::class);

        foreach (['a.pdf', 'b.pdf', 'c.pdf'] as $name) {
            StoredFile::factory()->create(['original_name' => $name]);
        }

        $this->get(route('files.index'))
            ->assertOk()
            ->assertSee('c.pdf')
            ->assertSee('b.pdf')
            ->assertDontSee('a.pdf')
            ->assertSee('page=2', false);

        $this->get(route('files.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('a.pdf')
            ->assertDontSee('c.pdf');
    }
}
