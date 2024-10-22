<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Handlers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use TechieNi3\LaravelInstaller\InstallerContext;

class ComposerTaskHandler
{
    private string $composerBinary;

    public function __construct(private readonly InstallerContext $context)
    {
        $this->composerBinary = $this->initializeComposer();
    }

    public function getComposerBinary(): string
    {
        return $this->composerBinary;
    }

    public function updateDependencies(): void
    {
        $composer = $this->getComposerBinary();

        $this->context->runCommands([
            "{$composer} update",
            "{$composer} bump",
            "{$composer} update",
        ], $this->context->getDirectory());
    }

    private function initializeComposer(): string
    {
        $composer = new Composer(new Filesystem(), $this->context->getDirectory());

        return implode(' ', $composer->findComposer());
    }
}
