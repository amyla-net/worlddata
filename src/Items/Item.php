<?php

namespace Amyla\Worlddata\Items;

use Amyla\Worlddata\World;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, string> */
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

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name];
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
