<?php

namespace Amyla\Worlddata\Rules;

use Amyla\Worlddata\Items\Timezone;
use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class TimezoneCode extends CodeRule
{
    /** @return WorldCollection<string, Timezone> */
    protected function items(World $world): WorldCollection
    {
        return $world->timezones();
    }
}
