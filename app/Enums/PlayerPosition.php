<?php

namespace App\Enums;

enum PlayerPosition: string
{
    case Defender = 'defender';
    case Midfielder = 'midfielder';
    case Forward = 'forward';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
