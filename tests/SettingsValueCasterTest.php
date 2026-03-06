<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Tests;

use DateTimeImmutable;
use PhpSoftBox\DbConfig\SettingsValueCaster;
use PhpSoftBox\DbConfig\Value\DateRange;
use PhpSoftBox\DbConfig\Value\NumberRange;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\FormFieldTypesEnum;
use PhpSoftBox\Forms\FormIntervalTypesEnum;
use PhpSoftBox\Forms\FormValueTypesEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettingsValueCasterTest extends TestCase
{
    #[Test]
    public function testCastsStringAndBool(): void
    {
        $caster = new SettingsValueCaster();

        $stringDef = new FormFieldDefinition(
            key: 'title',
            label: 'Title',
            fieldType: FormFieldTypesEnum::TEXT,
            valueType: FormValueTypesEnum::STRING,
        );

        $stored = $caster->toStorage('Hello', $stringDef);
        $this->assertSame('Hello', $caster->fromStorage($stored, $stringDef));

        $boolDef = new FormFieldDefinition(
            key: 'enabled',
            label: 'Enabled',
            fieldType: FormFieldTypesEnum::CHECKBOX,
            valueType: FormValueTypesEnum::BOOL,
        );

        $storedBool = $caster->toStorage(true, $boolDef);
        $this->assertTrue($caster->fromStorage($storedBool, $boolDef));
    }

    #[Test]
    public function testCastsDate(): void
    {
        $caster = new SettingsValueCaster();
        $def    = new FormFieldDefinition(
            key: 'start',
            label: 'Start',
            fieldType: FormFieldTypesEnum::DATE,
            valueType: FormValueTypesEnum::DATE,
            format: 'Y-m-d',
        );

        $date = new DateTimeImmutable('2026-02-18');

        $stored   = $caster->toStorage($date, $def);
        $hydrated = $caster->fromStorage($stored, $def);

        $this->assertInstanceOf(DateTimeImmutable::class, $hydrated);
        $this->assertSame('2026-02-18', $hydrated->format('Y-m-d'));
    }

    #[Test]
    public function testCastsNumberRange(): void
    {
        $caster = new SettingsValueCaster();
        $def    = new FormFieldDefinition(
            key: 'price_range',
            label: 'Price range',
            fieldType: FormFieldTypesEnum::INTERVAL,
            intervalType: FormIntervalTypesEnum::NUMBER,
        );

        $range = new NumberRange(10.0, 20.5);

        $stored   = $caster->toStorage($range, $def);
        $hydrated = $caster->fromStorage($stored, $def);

        $this->assertInstanceOf(NumberRange::class, $hydrated);
        $this->assertSame(10.0, $hydrated->from);
        $this->assertSame(20.5, $hydrated->to);
    }

    #[Test]
    public function testCastsDateRange(): void
    {
        $caster = new SettingsValueCaster();
        $def    = new FormFieldDefinition(
            key: 'period',
            label: 'Period',
            fieldType: FormFieldTypesEnum::INTERVAL,
            intervalType: FormIntervalTypesEnum::DATE,
            format: 'Y-m-d',
        );

        $range = new DateRange(
            new DateTimeImmutable('2026-02-01'),
            new DateTimeImmutable('2026-02-10'),
        );

        $stored   = $caster->toStorage($range, $def);
        $hydrated = $caster->fromStorage($stored, $def);

        $this->assertInstanceOf(DateRange::class, $hydrated);
        $this->assertSame('2026-02-01', $hydrated->from?->format('Y-m-d'));
        $this->assertSame('2026-02-10', $hydrated->to?->format('Y-m-d'));
    }

    #[Test]
    public function testCastsNumberTypes(): void
    {
        $caster = new SettingsValueCaster();

        $intDef = new FormFieldDefinition(
            key: 'limit',
            label: 'Limit',
            fieldType: FormFieldTypesEnum::NUMBER,
            valueType: FormValueTypesEnum::INT,
        );

        $storedInt = $caster->toStorage('42', $intDef);
        $this->assertSame(42, $caster->fromStorage($storedInt, $intDef));

        $floatDef = new FormFieldDefinition(
            key: 'ratio',
            label: 'Ratio',
            fieldType: FormFieldTypesEnum::NUMBER,
            valueType: FormValueTypesEnum::FLOAT,
        );

        $storedFloat = $caster->toStorage('3.14', $floatDef);
        $this->assertSame(3.14, $caster->fromStorage($storedFloat, $floatDef));
    }

    #[Test]
    public function testSelectMultipleUsesArray(): void
    {
        $caster = new SettingsValueCaster();
        $def    = new FormFieldDefinition(
            key: 'roles',
            label: 'Roles',
            fieldType: FormFieldTypesEnum::SELECT,
            valueType: FormValueTypesEnum::ARRAY,
            multiple: true,
        );

        $stored   = $caster->toStorage(['admin', 'editor'], $def);
        $hydrated = $caster->fromStorage($stored, $def);

        $this->assertSame(['admin', 'editor'], $hydrated);
    }
}
