<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Value;

use DateTimeImmutable;

final readonly class DateRange
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }

    /**
     * @return array{from: string|null, to: string|null}
     */
    public function toArray(string $format): array
    {
        return [
            'from' => $this->from?->format($format),
            'to'   => $this->to?->format($format),
        ];
    }
}
