<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

use PhpSoftBox\Forms\DTO\FormFieldDefinition;

interface SettingsGroupInterface
{
    public static function groupKey(): string;

    /**
     * @return list<FormFieldDefinition>
     */
    public static function definitions(): array;
}
