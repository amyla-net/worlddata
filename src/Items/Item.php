<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, string|int|null> */
abstract class Item implements Arrayable, JsonSerializable
{
    public function __construct(
        protected readonly World $world,
        public readonly string $code,
        public readonly string $name,
    ) {}

    public function matches(string $code): bool
    {
        return strcasecmp($this->code, $code) === 0;
    }

    /** @internal */
    public function displayLocale(): string
    {
        return $this->world->displayLocale();
    }

    /**
     * @internal
     *
     * @param  list<string>  $locales
     * @return list<string>
     */
    public function searchTerms(array $locales = []): array
    {
        return $this->world->searchTerms($this, $locales);
    }

    /** @return array<string, string|int|null> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name];
    }

    /** @return array<string, string|int|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
