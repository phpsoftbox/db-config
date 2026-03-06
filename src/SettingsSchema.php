<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig;

use PhpSoftBox\Forms\DTO\FormDefinition;
use PhpSoftBox\Forms\DTO\FormFieldDefinition;
use PhpSoftBox\Forms\Renderer\JsonFormRenderer;

use function array_map;

final readonly class SettingsSchema
{
    /**
     * @param list<FormFieldDefinition> $definitions
     */
    public function __construct(
        private string $groupKey,
        private array $definitions,
    ) {
    }

    public function groupKey(): string
    {
        return $this->groupKey;
    }

    /**
     * @return list<FormFieldDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'group'  => $this->groupKey,
            'fields' => array_map(static fn (FormFieldDefinition $field): array => $field->toArray(), $this->definitions),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormArray(): array
    {
        return new JsonFormRenderer()->render($this->toFormDefinition());
    }

    public function toFormDefinition(): FormDefinition
    {
        return new FormDefinition(
            id: $this->formId(),
            title: $this->groupKey,
            fields: $this->definitions,
            meta: ['group' => $this->groupKey],
        );
    }

    private function formId(): string
    {
        return 'settings.' . $this->groupKey;
    }
}
