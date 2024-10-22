<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Contracts;

interface ShouldInstall
{
    public function install(): void;
}
