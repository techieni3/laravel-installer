<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Contracts;

interface Script
{
    public function getName(): string;

    public function getCommand(): string;
}
