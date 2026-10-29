<?php

use Amyla\Worlddata\World;

if (! function_exists('world')) {
    function world(?string $locale = null): World
    {
        return app()->makeWith(World::class, ['locale' => $locale ?? app()->getLocale()]);
    }
}
