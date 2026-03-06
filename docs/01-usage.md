# DbConfig

`DbConfig` хранит настройки в БД и использует `Forms` как единственный source-of-truth для полей.

## База данных

Таблица по умолчанию: `config`.

```sql
id BIGINT PRIMARY KEY
group_key VARCHAR(100)
key VARCHAR(150)
value TEXT NULL
created_datetime DATETIME
updated_datetime DATETIME
UNIQUE (group_key, key)
```

Значение хранится как JSON-строка (`value`).

## Описание группы настроек

```php
use PhpSoftBox\DbConfig\AbstractSettingsGroup;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\DTO\FormFieldServerDefinition;
use PhpSoftBox\Forms\FormFieldTypesEnum;
use PhpSoftBox\Forms\FormValueTypesEnum;
use PhpSoftBox\Filter\TrimFilter;
use PhpSoftBox\Validator\Rule\FilledValidation;

final class SystemSettings extends AbstractSettingsGroup
{
    public string $site_name = '';
    public bool $maintenance_mode = false;

    public static function groupKey(): string
    {
        return 'system';
    }

    public static function definitions(): array
    {
        return [
            new FormFieldDefinition(
                key: 'site_name',
                label: 'Название сайта',
                fieldType: FormFieldTypesEnum::TEXT,
                valueType: FormValueTypesEnum::STRING,
                server: new FormFieldServerDefinition(
                    default: '',
                    rules: [new FilledValidation()],
                    filters: [new TrimFilter()],
                ),
            ),
            new FormFieldDefinition(
                key: 'maintenance_mode',
                label: 'Режим обслуживания',
                fieldType: FormFieldTypesEnum::CHECKBOX,
                valueType: FormValueTypesEnum::BOOL,
                server: new FormFieldServerDefinition(default: false),
            ),
        ];
    }
}
```

`server.property` используется для маппинга ключа поля в имя свойства класса настроек.

## Загрузка и сохранение

```php
/** @var \PhpSoftBox\DbConfig\SettingsManager $manager */
$settings = $manager->load(SystemSettings::class);

$settings->site_name = 'New name';
$manager->save($settings);
```

## Схема для UI

```php
$schema = $manager->schema(SystemSettings::class)->toArray();
$form = $manager->schema(SystemSettings::class)->toFormArray();
$definition = $manager->schema(SystemSettings::class)->toFormDefinition();
```

`toFormDefinition()` возвращает `PhpSoftBox\Forms\DTO\FormDefinition`.

