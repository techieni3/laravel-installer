<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller;

class Replacement
{
    public function __construct(
        public string $search,
        public string $replace,
    ) {
    }
}
