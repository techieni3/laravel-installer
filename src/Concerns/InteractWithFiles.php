<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

trait InteractWithFiles
{
    protected function copyFile(string $sourceFile, string $destination): void
    {
        $stubs = dirname(__DIR__) . '/stubs';

        copy("{$stubs}/{$sourceFile}", $destination);
    }

    protected function replaceFile(string $replace, string $file): void
    {
        $stubs = dirname(__DIR__, 2) . '/stubs';

        file_put_contents(
            $file,
            file_get_contents("{$stubs}/{$replace}"),
        );
    }

    protected function pregReplaceInFile(string $pattern, string $replace, string $file): void
    {
        file_put_contents(
            $file,
            preg_replace($pattern, $replace, file_get_contents($file))
        );
    }

    protected function appendInFile(string $file, string $data): void
    {
        file_put_contents($file, $data, FILE_APPEND);
    }

    protected function replaceInFile(string|array $search, string|array $replace, string $file): void
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }
}
