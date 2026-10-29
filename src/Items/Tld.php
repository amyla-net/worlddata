<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;

final class Tld extends Item
{
    public readonly string $ascii;

    public function __construct(World $world, string $code, string $name, public readonly string $type)
    {
        parent::__construct($world, $code, '.'.$name);
        $this->ascii = '.'.$code;
    }

    public function matches(string $code): bool
    {
        $code = mb_strtolower(str_starts_with($code, '.') ? substr($code, 1) : $code);

        return $code === $this->code || '.'.$code === $this->name;
    }

    /** @return WorldCollection<string, Country> */
    public function countries(): WorldCollection
    {
        return $this->world->relatedCountries('tlds', $this->code);
    }

    public function toArray(): array
    {
        return parent::toArray() + ['ascii' => $this->ascii, 'type' => $this->type];
    }
}
