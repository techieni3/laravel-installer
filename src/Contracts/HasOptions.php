<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Contracts;

interface HasOptions
{
    public static function toArray(): array;
}
