<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\ValueObjects\Replacements;

class Replacement
{
    public function __construct(
        public string|array $search,
        public string|array $replace,
    ) {
    }
}
