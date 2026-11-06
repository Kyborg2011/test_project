<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            FileSettings::class,
            static function (Application $app): FileSettings {
                /** @var array<string, mixed> $config */
                $config = $app->make('config')->get('files', []);

                return FileSettings::fromConfig($config);
            },
        );
    }

    public function boot(): void
    {
        // now(), model dates and casts return immutable instances.
        Date::use(CarbonImmutable::class);

        // Catch lazy loading, unknown attributes and silently discarded fills during development.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
