<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Enums;

use TechieNi3\LaravelInstaller\Contracts\HasOptions;

enum StarterKit: string implements HasOptions
{
    case None = 'none';
    case Api = 'api';
    case Breeze = 'breeze';
    case Filament = 'filament';

    public static function toArray(): array
    {
        // Map the cases to an array where key is value and value is the description
        return array_column(
            array_map(
                static fn ($case) => ['value' => $case->value, 'description' => $case->description()],
                self::cases()
            ),
            'description',
            'value'
        );
    }

    public function description(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Api => 'API only',
            self::Breeze => 'Laravel Breeze',
            self::Filament => 'Filament',
        };
    }
}
