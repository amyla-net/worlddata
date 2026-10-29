<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\WorldCollection;

final class Currency extends Item
{
    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('currencies', $this->code);
    }
}
