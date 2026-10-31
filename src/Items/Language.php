<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class Language extends Item
{
    public readonly ?string $nativeName;

    public function __construct(World $world, string $code, string $name)
    {
        parent::__construct($world, $code, $name);
        $this->nativeName = $world->nativeLanguageName($code);
    }

    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('languages', $this->code);
    }

    public function toArray(): array
    {
        return parent::toArray() + ['nativeName' => $this->nativeName];
    }
}
