<?php

namespace Amyla\Worlddata\Rules;

use Amyla\Worlddata\Items\Item;
use Amyla\Worlddata\World;
use Amyla\Worlddata\WorldCollection;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** @internal */
abstract class CodeRule implements ValidationRule
{
    /** @param list<string> $only */
    public function __construct(protected readonly array $only = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            $items = $this->items(world());
            $item = $items->find($value);
            if ($item !== null && $this->accepts($item)) {
                if ($this->only === []) {
                    return;
                }
                foreach ($this->only as $code) {
                    if ($items->find($code)?->code === $item->code) {
                        return;
                    }
                }
            }
        }

        $fail('validation.in')->translate();
    }

    /** @return WorldCollection<string, covariant Item> */
    abstract protected function items(World $world): WorldCollection;

    protected function accepts(Item $item): bool
    {
        return true;
    }
}
