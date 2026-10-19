<?php

namespace App\Support;

/** One ingredient line after scaling and unit conversion, ready to display. */
final class ScaledLine
{
    public function __construct(
        public readonly ?string $quantity,
        public readonly ?string $unit,
        public readonly string $name,
        public readonly ?string $note,
        public readonly bool $scaled,
    ) {}

    public function text(): string
    {
        $line = trim(implode(' ', array_filter([$this->quantity, $this->unit, $this->name], fn ($p) => $p !== null && $p !== '')));

        return $this->note ? $line.', '.$this->note : $line;
    }
}
