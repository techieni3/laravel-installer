<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\ValueObjects\Replacements;

class PregReplacement
{
    public function __construct(
        public string $regex,
        public string $replace,
    ) {
    }
}
