<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

use DateTimeImmutable;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Database;

use function array_key_exists;
use function is_string;

final class DatabaseSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(
        private readonly Database $database,
        private readonly string $table = 'config',
        private readonly string $connection = 'default',
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getGroup(string $groupKey): array
    {
        $connection = $this->database->connection($this->connection);
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

        $connection = $this->database->connection($this->connection);
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

                if ($updated === 0) {
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
}
