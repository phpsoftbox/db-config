<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Tests;

use PhpSoftBox\DbConfig\SettingsSchema;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\FormFieldTypesEnum;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettingsSchemaTest extends TestCase
{
    #[Test]
    public function testSchemaIncludesSelectOptions(): void
    {
        $definition = new FormFieldDefinition(
            key: 'timezone',
            label: 'Часовой пояс',
            fieldType: FormFieldTypesEnum::SELECT,
            options: [
                ['label' => 'UTC', 'value' => 'UTC'],
                ['label' => 'Europe/Moscow', 'value' => 'Europe/Moscow'],
            ],
            searchable: true,
        );

        $schema = new SettingsSchema('general', [$definition]);

        $data = $schema->toArray();

        $this->assertSame('general', $data['group']);
        $this->assertSame('timezone', $data['fields'][0]['key']);
        $this->assertTrue($data['fields'][0]['searchable']);
        $this->assertCount(2, $data['fields'][0]['options']);
    }

    #[Test]
    public function testSchemaIncludesDynamicSelectMeta(): void
    {
        $definition = new FormFieldDefinition(
            key: 'manager_id',
            label: 'Менеджер',
            fieldType: FormFieldTypesEnum::SELECT,
            meta: [
                'optionsSource' => [
                'endpoint' => '/api/users',
                'valueKey' => 'id',
                'labelKey' => 'name',
                ],
            ],
            searchable: true,
        );

        $schema = new SettingsSchema('general', [$definition]);

        $data = $schema->toArray();

        $this->assertSame('/api/users', $data['fields'][0]['meta']['optionsSource']['endpoint']);
        $this->assertSame('id', $data['fields'][0]['meta']['optionsSource']['valueKey']);
    }

    #[Test]
    public function testSchemaIncludesRequiredFlagWhenEnabled(): void
    {
        $definition = new FormFieldDefinition(
            key: 'timezone',
            label: 'Часовой пояс',
            fieldType: FormFieldTypesEnum::TEXT,
            required: true,
        );

        $schema = new SettingsSchema('general', [$definition]);

        $data = $schema->toArray();

        $this->assertArrayHasKey('required', $data['fields'][0]);
        $this->assertTrue($data['fields'][0]['required']);
    }

    #[Test]
    public function testSchemaOmitsRequiredFlagWhenDisabled(): void
    {
        $definition = new FormFieldDefinition(
            key: 'timezone',
            label: 'Часовой пояс',
            fieldType: FormFieldTypesEnum::TEXT,
            required: false,
        );

        $schema = new SettingsSchema('general', [$definition]);

        $data = $schema->toArray();

        $this->assertArrayHasKey('required', $data['fields'][0]);
        $this->assertFalse($data['fields'][0]['required']);
    }

    #[Test]
    public function testSchemaExportsFormArray(): void
    {
        $definition = new FormFieldDefinition(
            key: 'timezone',
            label: 'Часовой пояс',
            fieldType: FormFieldTypesEnum::SELECT,
            options: [
                ['label' => 'UTC', 'value' => 'UTC'],
            ],
            searchable: true,
            required: true,
        );

        $schema = new SettingsSchema('general', [$definition]);

        $form = $schema->toFormArray();

        $this->assertSame('settings.general', $form['id']);
        $this->assertSame('general', $form['title']);
        $this->assertSame('timezone', $form['fields'][0]['key']);
        $this->assertSame('select', $form['fields'][0]['fieldType']);
        $this->assertTrue((bool) ($form['fields'][0]['required'] ?? false));
    }

    #[Test]
    public function testSchemaBuildsFormDefinition(): void
    {
        $schema = new SettingsSchema('general', [
            new FormFieldDefinition(
                key: 'timezone',
                label: 'Timezone',
                fieldType: FormFieldTypesEnum::TEXT,
            ),
        ]);

        $formDefinition = $schema->toFormDefinition();

        $this->assertIsObject($formDefinition);
        $this->assertSame('settings.general', $formDefinition->id);
        $this->assertSame('general', $formDefinition->meta['group'] ?? null);
    }
}
