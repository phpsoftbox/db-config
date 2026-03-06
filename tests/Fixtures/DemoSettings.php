<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Tests\Fixtures;

use PhpSoftBox\DbConfig\AbstractSettingsGroup;
use PhpSoftBox\Filter\TrimFilter;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\DTO\FormFieldServerDefinition;
use PhpSoftBox\Forms\FormFieldTypesEnum;
use PhpSoftBox\Forms\FormValueTypesEnum;
use PhpSoftBox\Validator\Rule\BoolValidation;
use PhpSoftBox\Validator\Rule\FilledValidation;

final class DemoSettings extends AbstractSettingsGroup
{
    public string $name  = '';
    public bool $enabled = false;
    public string $title = '';

    public static function groupKey(): string
    {
        return 'demo';
    }

    public static function definitions(): array
    {
        return [
            new FormFieldDefinition(
                key: 'name',
                label: 'Name',
                fieldType: FormFieldTypesEnum::TEXT,
                valueType: FormValueTypesEnum::STRING,
                server: new FormFieldServerDefinition(
                    default: '',
                    rules: [new FilledValidation()],
                    filters: [new TrimFilter()],
                ),
            ),
            new FormFieldDefinition(
                key: 'enabled',
                label: 'Enabled',
                fieldType: FormFieldTypesEnum::CHECKBOX,
                valueType: FormValueTypesEnum::BOOL,
                server: new FormFieldServerDefinition(
                    default: false,
                    rules: [new BoolValidation()],
                ),
            ),
            new FormFieldDefinition(
                key: 'title',
                label: 'Title',
                fieldType: FormFieldTypesEnum::TEXT,
                valueType: FormValueTypesEnum::STRING,
                server: new FormFieldServerDefinition(
                    default: 'Default title',
                ),
            ),
        ];
    }
}
