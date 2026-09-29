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

Значение хранится как JSON-строка (`value`). Значения не шифруются: секреты (пароли, токены) в DbConfig хранятся
в открытом виде и попадают в ответ `load()`, поэтому их лучше держать в окружении или шифровать в приложении.

## Репозиторий

`DatabaseSettingsRepository` читает группу через `read`-подключение и сохраняет через `write`-подключение
`ConnectionManagerInterface`:

```php
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\DbConfig\DatabaseSettingsRepository;
use PhpSoftBox\DbConfig\SettingsManager;
use PhpSoftBox\DbConfig\SettingsValueCaster;

$repository = new DatabaseSettingsRepository(
    connections: $container->get(ConnectionManagerInterface::class),
    table: 'config',
    connection: 'default',
);

$manager = new SettingsManager($repository, new SettingsValueCaster(), $validator);
```

`upsert()` в одной транзакции обновляет строку по `(group_key, key)` и вставляет её, только если строки нет. Проверка
существования нужна MySQL/MariaDB: для `UPDATE` без изменений они возвращают 0 затронутых строк. Компонент не кеширует
настройки: каждый `load()` читает БД, поэтому в долгоживущих процессах сброс состояния не нужен.

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

