<?php

declare(strict_types=1);

namespace PhpSoftBox\DbConfig\Value;

final readonly class NumberRange
{
    public function __construct(
        public ?float $from = null,
        public ?float $to = null,
    ) {
    }

    /**
     * @return array{from: float|null, to: float|null}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to'   => $this->to,
        ];
    }
}
