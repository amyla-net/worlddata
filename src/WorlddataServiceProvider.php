<?php

namespace Amyla\Worlddata;

use Amyla\Worlddata\Support\Snapshot;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class WorlddataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Snapshot::class, fn () => new Snapshot(__DIR__.'/../resources/data.json'));

        $this->app->bind(World::class, function (Application $app, array $parameters): World {
            return new World(
                $app->make(Snapshot::class),
                $parameters['locale'] ?? $app->getLocale(),
                $app->make('config')->get('app.fallback_locale', 'en'),
                now('UTC')->startOfDay()->toDateTimeImmutable(),
            );
        });
    }
}
