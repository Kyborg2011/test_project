<?php

declare(strict_types=1);

namespace App\Providers;

use App\Messaging\Amqp\AmqpChannelProvider;
use App\Messaging\Amqp\AmqpPublisher;
use App\Messaging\Amqp\AmqpSettings;
use App\Messaging\Contracts\MessagePublisher;
use App\Messaging\Messages\FileDeletedMessageFactory;
use App\Messaging\OutboxSettings;
use App\Services\Files\FileSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
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
        // Settings objects validate .env values and fail fast with a clear message.
        $this->app->singleton(
            FileSettings::class,
            static fn (Application $app): FileSettings => FileSettings::fromConfig(self::section($app, 'files')),
        );
        $this->app->singleton(
            AmqpSettings::class,
            static fn (Application $app): AmqpSettings => AmqpSettings::fromConfig(self::section($app, 'rabbitmq')),
        );
        $this->app->singleton(
            OutboxSettings::class,
            static fn (Application $app): OutboxSettings => OutboxSettings::fromConfig(self::section($app, 'outbox')),
        );

        $this->app->singleton(
            FileDeletedMessageFactory::class,
            static fn (Application $app): FileDeletedMessageFactory => new FileDeletedMessageFactory(
                $app->make(ViewFactory::class),
                (string) $app->make(Config::class)->get('notifications.recipient'),
                (string) $app->make(Config::class)->get('app.name'),
            ),
        );

        // One broker connection per process.
        $this->app->singleton(AmqpChannelProvider::class);
        $this->app->singleton(
            MessagePublisher::class,
            static fn (Application $app): MessagePublisher => new AmqpPublisher(
                $app->make(AmqpChannelProvider::class),
                (string) $app->make(Config::class)->get('app.name'),
            ),
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

    /**
     * @return array<string, mixed>
     */
    private static function section(Application $app, string $name): array
    {
        $config = $app->make(Config::class)->get($name, []);

        return is_array($config) ? $config : [];
    }
}
