<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\WorldCollection;

final class Language extends Item
{
    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('languages', $this->code);
    }
}
