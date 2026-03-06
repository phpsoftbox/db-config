<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpSoftBox\DbConfig\Value\DateRange;
use PhpSoftBox\DbConfig\Value\NumberRange;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\FormFieldTypesEnum;
use PhpSoftBox\Forms\FormIntervalTypesEnum;
use PhpSoftBox\Forms\FormValueTypesEnum;
use Throwable;

use function is_array;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class SettingsValueCaster
{
    public function toStorage(mixed $value, FormFieldDefinition $definition): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeToStorageValue($value, $definition);

        try {
            return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Unable to encode settings value.', 0, $exception);
        }
    }

    public function fromStorage(?string $value, FormFieldDefinition $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        if ($definition->fieldType === FormFieldTypesEnum::INTERVAL) {
            return $this->hydrateInterval($decoded, $definition);
        }

        return $this->castValue($decoded, $definition);
    }

    private function normalizeToStorageValue(mixed $value, FormFieldDefinition $definition): mixed
    {
        if ($definition->fieldType === FormFieldTypesEnum::INTERVAL) {
            return $this->normalizeInterval($value, $definition);
        }

        return $this->castValue($value, $definition, true);
    }

    private function castValue(mixed $value, FormFieldDefinition $definition, bool $forStorage = false): mixed
    {
        if ($value === null) {
            return null;
        }

        $valueType = $this->resolveValueType($definition);
        $format    = $definition->format;

        return match ($valueType) {
            FormValueTypesEnum::STRING   => (string) $value,
            FormValueTypesEnum::INT      => $this->castInt($value),
            FormValueTypesEnum::FLOAT    => $this->castFloat($value),
            FormValueTypesEnum::BOOL     => (bool) $value,
            FormValueTypesEnum::ARRAY    => is_array($value) ? $value : [$value],
            FormValueTypesEnum::JSON     => $value,
            FormValueTypesEnum::DATE     => $this->castDate($value, $format ?? 'Y-m-d', $forStorage),
            FormValueTypesEnum::DATETIME => $this->castDate($value, $format ?? 'Y-m-d H:i:s', $forStorage),
        };
    }

    private function resolveValueType(FormFieldDefinition $definition): FormValueTypesEnum
    {
        if ($definition->valueType !== null) {
            return $definition->valueType;
        }

        if ($definition->multiple) {
            return FormValueTypesEnum::ARRAY;
        }

        return match ($definition->fieldType) {
            FormFieldTypesEnum::CHECKBOX => FormValueTypesEnum::BOOL,
            FormFieldTypesEnum::NUMBER   => FormValueTypesEnum::FLOAT,
            FormFieldTypesEnum::DATE     => FormValueTypesEnum::DATE,
            default                      => FormValueTypesEnum::STRING,
        };
    }

    private function castInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function castFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value) || is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function castDate(mixed $value, string $format, bool $forStorage): string|DateTimeImmutable|null
    {
        if ($value instanceof DateTimeInterface) {
            return $forStorage ? $value->format($format) : DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            if ($forStorage) {
                return $value;
            }

            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return array{from: float|string|null, to: float|string|null}
     */
    private function normalizeInterval(mixed $value, FormFieldDefinition $definition): array
    {
        $intervalType = $definition->intervalType;
        if ($intervalType === null) {
            throw new InvalidArgumentException('Interval field requires intervalType.');
        }

        $format = $definition->format ?? 'Y-m-d';

        if ($intervalType === FormIntervalTypesEnum::NUMBER) {
            if ($value instanceof NumberRange) {
                return $value->toArray();
            }

            if (is_array($value)) {
                return [
                    'from' => $this->castFloat($value['from'] ?? null),
                    'to'   => $this->castFloat($value['to'] ?? null),
                ];
            }

            return [
                'from' => null,
                'to'   => null,
            ];
        }

        if ($value instanceof DateRange) {
            return $value->toArray($format);
        }

        if (is_array($value)) {
            return [
                'from' => $this->castDate($value['from'] ?? null, $format, true),
                'to'   => $this->castDate($value['to'] ?? null, $format, true),
            ];
        }

        return [
            'from' => null,
            'to'   => null,
        ];
    }

    private function hydrateInterval(mixed $decoded, FormFieldDefinition $definition): NumberRange|DateRange
    {
        $intervalType = $definition->intervalType;
        if ($intervalType === null) {
            throw new InvalidArgumentException('Interval field requires intervalType.');
        }

        $data   = is_array($decoded) ? $decoded : [];
        $format = $definition->format ?? 'Y-m-d';

        if ($intervalType === FormIntervalTypesEnum::NUMBER) {
            return new NumberRange(
                isset($data['from']) ? $this->castFloat($data['from']) : null,
                isset($data['to']) ? $this->castFloat($data['to']) : null,
            );
        }

        return new DateRange(
            $this->parseDateValue($data['from'] ?? null, $format),
            $this->parseDateValue($data['to'] ?? null, $format),
        );
    }

    private function parseDateValue(mixed $value, string $format): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return null;
    }
}
