<?php

namespace App\Enums;

enum Rag: string
{
    case Green = 'green';
    case Amber = 'amber';
    case Red = 'red';

    public static function fromPct(?float $pct, float $greenMin, float $amberMin): ?self
    {
        if ($pct === null) {
            return null;
        }

        return match (true) {
            $pct >= $greenMin => self::Green,
            $pct >= $amberMin => self::Amber,
            default => self::Red,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Green => '#2F855A',
            self::Amber => '#B7791F',
            self::Red => '#B83A33',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
