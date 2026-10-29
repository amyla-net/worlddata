<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\WorldCollection;

final class Continent extends Item
{
    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->countries()->where('continentCode', $this->code);
    }
}
