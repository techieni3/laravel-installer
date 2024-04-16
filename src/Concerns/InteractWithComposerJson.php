<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

trait InteractWithComposerJson
{
    public function getComposerJsonContent(string $path): mixed
    {
        return json_decode(file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
    }

    private function updateComposerJsonFile(string $path, mixed $configuration): void
    {
        file_put_contents(
            $path,
            (string) str(json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->append(PHP_EOL)
                ->replace(
                    search: "    \"keywords\": [\n        \"laravel\",\n        \"framework\"\n    ],",
                    replace: '    "keywords": ["laravel", "framework"],',
                )
                ->replace(
                    search: "    \"keywords\": [\n        \"framework\",\n        \"laravel\"\n    ],",
                    replace: '    "keywords": ["framework", "laravel"],',
                ),
        );
    }

    private function addComposerScript(string $directory, string $name, string $command): void
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'composer.json';

        if ( ! file_exists($path)) {
            return;
        }

        $configuration = $this->getComposerJsonContent($path);

        if (array_key_exists($name, $configuration['scripts'] ?? [])) {
            return;
        }

        $configuration['scripts'] ??= [];
        $configuration['scripts'][$name] = $command;

        $this->updateComposerJsonFile($path, $configuration);
    }
}
