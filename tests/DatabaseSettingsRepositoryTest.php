<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Tests;

use PDO;
use PhpSoftBox\Database\Database;
use PhpSoftBox\DbConfig\DatabaseSettingsRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function str_starts_with;

#[CoversClass(DatabaseSettingsRepository::class)]
#[CoversMethod(DatabaseSettingsRepository::class, 'upsert')]
#[CoversMethod(DatabaseSettingsRepository::class, 'getGroup')]
final class DatabaseSettingsRepositoryTest extends TestCase
{
    private const string TABLE = 'db_config_repository_test';

    /**
     * @return iterable<string, array{string}>
     */
    public static function databases(): iterable
    {
        yield 'sqlite' => ['sqlite:///:memory:'];
        yield 'mysql' => ['mysql://phpsoftbox:phpsoftbox@mysql:3306/phpsoftbox'];
        yield 'mariadb' => ['mariadb://phpsoftbox:phpsoftbox@mariadb:3306/phpsoftbox'];
        yield 'postgres' => ['postgres://phpsoftbox:phpsoftbox@postgres:5432/phpsoftbox'];
    }

    /**
     * Проверим, что повторное сохранение того же значения не пытается вставить дубликат: MySQL/MariaDB
     * возвращают 0 затронутых строк для UPDATE без изменений, и раньше это приводило к INSERT и ошибке UNIQUE.
     *
     * @see DatabaseSettingsRepository::upsert()
     * @see DatabaseSettingsRepository::getGroup()
     */
    #[Test]
    #[DataProvider('databases')]
    public function upsertOfUnchangedValueUpdatesExistingRow(string $dsn): void
    {
        $database   = $this->database($dsn);
        $repository = new DatabaseSettingsRepository($database->manager(), self::TABLE);

        // Два сохранения подряд в одну секунду: второе UPDATE ничего не меняет.
        $repository->upsert('demo', ['name' => '"same"']);
        $repository->upsert('demo', ['name' => '"same"']);
        $repository->upsert('demo', ['name' => '"changed"', 'title' => null]);

        self::assertSame(['name' => '"changed"', 'title' => null], $repository->getGroup('demo'));
        self::assertSame(2, (int) $database->fetchOne('SELECT COUNT(*) AS cnt FROM ' . self::TABLE)['cnt']);
    }

    private function database(string $dsn): Database
    {
        try {
            $database = Database::fromConfig([
                'connections' => [
                    'default' => 'main',
                    'main'    => [
                        'dsn'     => $dsn,
                        'options' => [PDO::ATTR_TIMEOUT => 2],
                    ],
                ],
            ]);
            $database->execute('DROP TABLE IF EXISTS ' . self::TABLE);
        } catch (Throwable $exception) {
            self::markTestSkipped('Database is not available: ' . $exception->getMessage());
        }

        $quote = str_starts_with($dsn, 'mysql') || str_starts_with($dsn, 'mariadb') ? '`' : '"';
        $database->execute(
            'CREATE TABLE ' . self::TABLE . ' (group_key VARCHAR(100) NOT NULL, '
            . $quote . 'key' . $quote . ' VARCHAR(150) NOT NULL, '
            . $quote . 'value' . $quote . ' TEXT NULL, created_datetime VARCHAR(19) NOT NULL, '
            . 'updated_datetime VARCHAR(19) NOT NULL, UNIQUE (group_key, ' . $quote . 'key' . $quote . '))',
        );

        return $database;
    }
}
