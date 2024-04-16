<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

trait InteractWithPackageJson
{
    public function getPackageJsonContent(string $path): mixed
    {
        return json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    private function updatePackageJsonFile(string $path, mixed $configuration): void
    {
        file_put_contents(
            $path,
            (string) str(json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->append(PHP_EOL),
        );
    }

    private function addNPMScript(string $directory, string $name, string $command): void
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'package.json';

        if ( ! file_exists($path)) {
            return;
        }

        $configuration = $this->getPackageJsonContent($path);

        if (array_key_exists($name, $configuration['scripts'] ?? [])) {
            return;
        }

        $configuration['scripts'] ??= [];
        $configuration['scripts'][$name] = $command;

        $this->updatePackageJsonFile($path, $configuration);
    }

    private function appendToPackageJson(string $directory, string $name, string|array $command): void
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'package.json';

        if ( ! file_exists($path)) {
            return;
        }

        $configuration = $this->getPackageJsonContent($path);

        $configuration[$name] = $command;

        $this->updatePackageJsonFile($path, $configuration);
    }
}
