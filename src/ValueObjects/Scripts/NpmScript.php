<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\ValueObjects\Scripts;

use TechieNi3\LaravelInstaller\Contracts\Script;

readonly class NpmScript implements Script
{
    public function __construct(public string $name, public string|array $command)
    {

    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommand(): string
    {
        return $this->command;
    }
}
