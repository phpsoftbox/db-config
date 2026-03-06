<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

use InvalidArgumentException;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Validator\Exception\ValidationException;
use PhpSoftBox\Validator\Rule\PresentValidation;
use PhpSoftBox\Validator\ValidationOptions;
use PhpSoftBox\Validator\ValidationResult;
use PhpSoftBox\Validator\ValidatorInterface;

use function array_key_exists;
use function is_callable;
use function is_string;
use function is_subclass_of;
use function property_exists;
use function sprintf;

final class SettingsManager
{
    public function __construct(
        private readonly SettingsRepositoryInterface $repository,
        private readonly SettingsValueCaster $caster,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T of SettingsGroupInterface
     * @param class-string<T> $class
     * @return T
     */
    public function load(string $class): SettingsGroupInterface
    {
        if (!is_subclass_of($class, SettingsGroupInterface::class)) {
            throw new InvalidArgumentException('Settings group must implement SettingsGroupInterface.');
        }

        $definitions = $class::definitions();
        $groupKey    = $class::groupKey();
        $values      = $this->repository->getGroup($groupKey);

        /** @var SettingsGroupInterface $settings */
        $settings = new $class();

        foreach ($definitions as $definition) {
            $property = $this->resolveProperty($definition);

            if (!property_exists($settings, $property)) {
                throw new InvalidArgumentException(sprintf(
                    'Settings property "%s" does not exist in %s.',
                    $property,
                    $class,
                ));
            }

            $defaultValue = $definition->server?->default;
            if ($defaultValue === null && isset($settings->{$property})) {
                $defaultValue = $settings->{$property};
            }

            if (array_key_exists($definition->key, $values)) {
                $raw = $values[$definition->key];
                if ($raw !== null) {
                    $decoded = $this->caster->fromStorage($raw, $definition);
                    if ($decoded !== null || $defaultValue === null) {
                        $settings->{$property} = $decoded;
                        continue;
                    }
                }
            }

            $defaultStorage        = $this->caster->toStorage($defaultValue, $definition);
            $settings->{$property} = $this->caster->fromStorage($defaultStorage, $definition);
        }

        return $settings;
    }

    public function schema(string $class): SettingsSchema
    {
        if (!is_subclass_of($class, SettingsGroupInterface::class)) {
            throw new InvalidArgumentException('Settings group must implement SettingsGroupInterface.');
        }

        if (is_callable([$class, 'schema'])) {
            $schema = $class::schema();
            if (!$schema instanceof SettingsSchema) {
                throw new InvalidArgumentException('Settings schema must be an instance of SettingsSchema.');
            }

            return $schema;
        }

        return new SettingsSchema($class::groupKey(), $class::definitions());
    }

    public function validate(SettingsGroupInterface $settings, ?ValidationOptions $options = null): ValidationResult
    {
        $definitions = $settings::definitions();
        $data        = $this->extractValues($settings, $definitions);
        $filtered    = $this->applyFilters($data, $definitions);

        $rules = [];
        foreach ($definitions as $definition) {
            $rules[$definition->key] = $this->normalizeRules($definition);
        }

        return $this->validator->validate($filtered, $rules, [], [], $options, $settings);
    }

    public function save(SettingsGroupInterface $settings, ?ValidationOptions $options = null): void
    {
        $result = $this->validate($settings, $options);

        if ($result->hasErrors()) {
            throw new ValidationException($result);
        }

        $definitions = $settings::definitions();
        $values      = $result->filteredData();

        $storage = [];
        foreach ($definitions as $definition) {
            $key           = $definition->key;
            $storageValue  = $this->caster->toStorage($values[$key] ?? null, $definition);
            $storage[$key] = $storageValue;

            $property              = $this->resolveProperty($definition);
            $settings->{$property} = $this->caster->fromStorage($storageValue, $definition);
        }

        $this->repository->upsert($settings::groupKey(), $storage);
    }

    /**
     * @param list<FormFieldDefinition> $definitions
     * @return array<string, mixed>
     */
    private function extractValues(SettingsGroupInterface $settings, array $definitions): array
    {
        $data = [];

        foreach ($definitions as $definition) {
            $property = $this->resolveProperty($definition);
            if (!property_exists($settings, $property)) {
                continue;
            }

            $data[$definition->key] = $settings->{$property} ?? null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<FormFieldDefinition> $definitions
     * @return array<string, mixed>
     */
    private function applyFilters(array $data, array $definitions): array
    {
        foreach ($definitions as $definition) {
            $filters = $definition->server?->filters ?? [];
            if ($filters === []) {
                $data[$definition->key] = $this->normalizeComponentValue($data[$definition->key] ?? null, $definition);
                continue;
            }

            $value = $data[$definition->key] ?? null;
            foreach ($filters as $filter) {
                if (is_callable($filter)) {
                    $value = $filter($value);
                }
            }

            $data[$definition->key] = $this->normalizeComponentValue($value, $definition);
        }

        return $data;
    }

    /**
     * @return list<mixed>
     */
    private function normalizeRules(FormFieldDefinition $definition): array
    {
        $normalized = [new PresentValidation()];
        $rules      = $definition->server?->rules ?? [];

        foreach ($rules as $rule) {
            $normalized[] = $rule;
        }

        return $normalized;
    }

    private function resolveProperty(FormFieldDefinition $definition): string
    {
        return $definition->server?->resolveProperty($definition->key) ?? $definition->key;
    }

    private function normalizeComponentValue(mixed $value, FormFieldDefinition $definition): mixed
    {
        $component = $definition->meta['component'] ?? null;
        if (!is_string($component) || $component !== 'markdown') {
            return $value;
        }

        if ($value === null) {
            return '';
        }

        return is_string($value) ? $value : (string) $value;
    }
}
