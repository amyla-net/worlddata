<?php

namespace Amyla\Worlddata\Rules;

use Amyla\Worlddata\Items\Country;
use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class CountryCode extends CodeRule
{
    /** @return WorldCollection<string, Country> */
    protected function items(World $world): WorldCollection
    {
        return $world->countries();
    }
}
