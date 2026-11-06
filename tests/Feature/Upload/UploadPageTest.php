<?php

declare(strict_types=1);

namespace Tests\Feature\Upload;

use App\Services\Files\FileSettings;
use Tests\TestCase;

final class UploadPageTest extends TestCase
{
    public function test_shows_upload_form_with_limits_from_config(): void
    {
        config([
            'files.max_size_kb' => 5120,
            'files.ttl_minutes' => 1440,
            'files.allowed_extensions' => 'pdf,docx',
        ]);
        $this->app->forgetInstance(FileSettings::class);

        $this->get(route('upload.create'))
            ->assertOk()
            ->assertSee('id="upload-form"', false)
            ->assertSee('action="' . route('files.store') . '"', false)
            ->assertSee('data-max-size="5242880"', false)
            ->assertSee('data-extensions="pdf,docx"', false)
            ->assertSee('accept=".pdf,.docx,application/pdf,', false)
            ->assertSee('Maximum size: 5 MB.')
            ->assertSee('deleted automatically after 1 day')
            ->assertSee('js/upload.js', false);
    }

    public function test_shows_ttl_in_human_form(): void
    {
        config(['files.ttl_minutes' => 90]);
        $this->app->forgetInstance(FileSettings::class);

        $this->get(route('upload.create'))->assertSee('deleted automatically after 1 hour 30 minutes');
    }
}
