<?php

declare(strict_types=1);

namespace App\Providers;

use App\Messaging\Messages\FileDeletedMessageFactory;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
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

        $this->app->singleton(
            FileDeletedMessageFactory::class,
            static function (Application $app): FileDeletedMessageFactory {
                $config = $app->make('config');

                return new FileDeletedMessageFactory(
                    $app->make(ViewFactory::class),
                    (string) $config->get('notifications.recipient'),
                    (string) $config->get('app.name'),
                );
            },
        );
    }

    public function boot(): void
    {
        // now(), model dates and casts return immutable instances.
        Date::use(CarbonImmutable::class);

        // Catch lazy loading, unknown attributes and silently discarded fills during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Paginator::useBootstrapFive();
    }
}
