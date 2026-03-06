<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

abstract class AbstractSettingsGroup implements SettingsGroupInterface
{
    public static function schema(): SettingsSchema
    {
        return new SettingsSchema(static::groupKey(), static::definitions());
    }
}
