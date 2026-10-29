<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Lee tests/Fixtures/cajero-escenarios.json (E4-02).
 */
final class CajeroTestData
{
    /**
     * @return array<string, mixed>
     */
    public static function fixture(): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/cajero-escenarios.json'), true);

        return is_array($fixture) ? $fixture : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixtureCase(string $criterion): array
    {
        $cases = self::fixture()['cases'] ?? [];

        foreach (is_array($cases) ? $cases : [] as $case) {
            if (is_array($case) && ($case['_e4_02_criterion'] ?? null) === $criterion) {
                return $case;
            }
        }

        throw new RuntimeException("Fixture case not found: {$criterion}");
    }
}
