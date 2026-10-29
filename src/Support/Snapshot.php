<?php

namespace Amyla\Worlddata\Support;

use RuntimeException;

/**
 * @phpstan-type TldRecord array{name: string, type: string, countryCode: string|null}
 * @phpstan-type SnapshotData array{schema: 1, meta: array<string, mixed>, continents: array<string, array<string, string>>, countryContinents: array<string, string>, countryLanguages: array<string, list<string>>, extraLanguages: array<string, string>, tlds: array<string, TldRecord>}
 *
 * @internal
 */
final class Snapshot
{
    /** @var SnapshotData */
    public readonly array $data;

    public function __construct(string $path)
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Cannot read the worlddata snapshot.');
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['schema'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported worlddata snapshot schema.');
        }
        /** @var SnapshotData $data */
        $this->data = $data;
    }
}
