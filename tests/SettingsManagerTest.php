<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Tests;

use PhpSoftBox\DbConfig\SettingsManager;
use PhpSoftBox\DbConfig\SettingsRepositoryInterface;
use PhpSoftBox\DbConfig\SettingsValueCaster;
use PhpSoftBox\DbConfig\Tests\Fixtures\DemoSettings;
use PhpSoftBox\Validator\Exception\ValidationException;
use PhpSoftBox\Validator\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_replace;

final class SettingsManagerTest extends TestCase
{
    #[Test]
    public function testLoadAndSaveSettings(): void
    {
        $repository = new class () implements SettingsRepositoryInterface {
            public array $storage = [];

            public function getGroup(string $groupKey): array
            {
                return $this->storage[$groupKey] ?? [];
            }

            public function upsert(string $groupKey, array $values): void
            {
                $this->storage[$groupKey] = array_replace($this->storage[$groupKey] ?? [], $values);
            }
        };

        $caster    = new SettingsValueCaster();
        $validator = new Validator();

        $manager = new SettingsManager($repository, $caster, $validator);

        $definitions = DemoSettings::definitions();

        $repository->storage['demo'] = [
            'name'    => $caster->toStorage('Initial', $definitions[0]),
            'enabled' => $caster->toStorage(true, $definitions[1]),
        ];

        $settings = $manager->load(DemoSettings::class);
        $this->assertSame('Initial', $settings->name);
        $this->assertTrue($settings->enabled);
        $this->assertSame('Default title', $settings->title);

        $settings->name    = 'Updated';
        $settings->enabled = false;
        $manager->save($settings);

        $this->assertSame('Updated', $caster->fromStorage($repository->storage['demo']['name'], $definitions[0]));
        $this->assertFalse($caster->fromStorage($repository->storage['demo']['enabled'], $definitions[1]));
    }

    #[Test]
    public function testSaveAppliesFilters(): void
    {
        $repository = new class () implements SettingsRepositoryInterface {
            public array $storage = [];

            public function getGroup(string $groupKey): array
            {
                return $this->storage[$groupKey] ?? [];
            }

            public function upsert(string $groupKey, array $values): void
            {
                $this->storage[$groupKey] = array_replace($this->storage[$groupKey] ?? [], $values);
            }
        };

        $caster    = new SettingsValueCaster();
        $validator = new Validator();

        $manager = new SettingsManager($repository, $caster, $validator);

        $settings = new DemoSettings();

        $settings->name    = '  Trimmed  ';
        $settings->enabled = true;

        $manager->save($settings);

        $definitions = DemoSettings::definitions();
        $this->assertSame('Trimmed', $caster->fromStorage($repository->storage['demo']['name'], $definitions[0]));
    }

    #[Test]
    public function testSaveThrowsOnValidationError(): void
    {
        $repository = new class () implements SettingsRepositoryInterface {
            public array $storage = [];

            public function getGroup(string $groupKey): array
            {
                return $this->storage[$groupKey] ?? [];
            }

            public function upsert(string $groupKey, array $values): void
            {
                $this->storage[$groupKey] = array_replace($this->storage[$groupKey] ?? [], $values);
            }
        };

        $caster    = new SettingsValueCaster();
        $validator = new Validator();

        $manager = new SettingsManager($repository, $caster, $validator);

        $settings = new DemoSettings();

        $settings->name = '';

        $this->expectException(ValidationException::class);
        $manager->save($settings);
    }
}
