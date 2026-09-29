<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

use DateTimeImmutable;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;

use function array_key_exists;
use function is_array;
use function is_string;

final class DatabaseSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $table = 'config',
        private readonly string $connection = 'default',
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getGroup(string $groupKey): array
    {
        $connection = $this->connections->read($this->connection);
        $table      = $connection->table($this->table);

        $rows = $connection
            ->query()
            ->select(['key', 'value'])
            ->from($table)
            ->where('group_key = :group_key', ['group_key' => $groupKey])
            ->fetchAll();

        $values = [];
        foreach ($rows as $row) {
            $key = $row['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }
            $values[$key] = array_key_exists('value', $row) ? $row['value'] : null;
        }

        return $values;
    }

    /**
     * @param array<string, string|null> $values
     */
    public function upsert(string $groupKey, array $values): void
    {
        if ($values === []) {
            return;
        }

        $connection = $this->connections->write($this->connection);
        $table      = $connection->table($this->table);
        $now        = new DateTimeImmutable()->format('Y-m-d H:i:s');

        $connection->transaction(function (ConnectionInterface $conn) use ($groupKey, $values, $table, $now): void {
            foreach ($values as $key => $value) {
                $updated = $conn
                    ->query()
                    ->update($table, [
                        'value'            => $value,
                        'updated_datetime' => $now,
                    ])
                    ->where('group_key = :group_key', ['group_key' => $groupKey])
                    ->where('key = :key', ['key' => $key])
                    ->execute();

                // MySQL/MariaDB считают затронутыми только изменившиеся строки: повторное сохранение того же
                // значения в ту же секунду даёт 0, и без проверки существования INSERT упадёт на UNIQUE.
                if ($updated === 0 && !$this->exists($conn, $table, $groupKey, (string) $key)) {
                    $conn
                        ->query()
                        ->insert($table, [
                            'group_key'        => $groupKey,
                            'key'              => (string) $key,
                            'value'            => $value,
                            'created_datetime' => $now,
                            'updated_datetime' => $now,
                        ])
                        ->execute();
                }
            }
        });
    }

    private function exists(ConnectionInterface $connection, string $table, string $groupKey, string $key): bool
    {
        return is_array($connection
            ->query()
            ->select(['group_key'])
            ->from($table)
            ->where('group_key = :group_key', ['group_key' => $groupKey])
            ->where('key = :key', ['key' => $key])
            ->limit(1)
            ->fetchOne());
    }
}
