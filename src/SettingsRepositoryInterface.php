<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

interface SettingsRepositoryInterface
{
    /**
     * @return array<string, string|null>
     */
    public function getGroup(string $groupKey): array;

    /**
     * @param array<string, string|null> $values
     */
    public function upsert(string $groupKey, array $values): void;
}
