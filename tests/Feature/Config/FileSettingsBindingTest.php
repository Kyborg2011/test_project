<?php

declare(strict_types=1);

namespace Tests\Feature\Config;

use App\Services\Files\FileSettings;
use Tests\TestCase;

final class FileSettingsBindingTest extends TestCase
{
    public function test_container_builds_settings_from_files_config(): void
    {
        config([
            'files.ttl_minutes' => '5',
            'files.max_size_kb' => '512',
            'files.allowed_extensions' => 'pdf',
        ]);

        $settings = $this->app->make(FileSettings::class);

        $this->assertSame(5, $settings->ttlMinutes);
        $this->assertSame(512, $settings->maxSizeKb);
        $this->assertSame(['pdf'], $settings->allowedExtensions);
    }

    public function test_settings_are_a_singleton(): void
    {
        $this->assertSame($this->app->make(FileSettings::class), $this->app->make(FileSettings::class));
    }
}
