<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use RuntimeException;

trait InteractWithFiles
{
    protected function copyFile(string $sourceFile, string $destination): void
    {
        $stubs = dirname(__DIR__) . '/stubs';

        copy("{$stubs}/{$sourceFile}", $destination);
    }

    protected function copyDir($source, $destination): void
    {
        if ( ! is_dir($source)) {
            return;
        }

        // Open the source directory
        $dir = opendir($source);

        if ($dir === false) {
            throw new RuntimeException("Failed to open directory: {$source}");
        }

        // Loop through the source directory
        while (($file = readdir($dir)) !== false) {
            // Skip the special . and .. folders
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $file;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $file;

            if ( ! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $destination));
            }

            if ( ! copy($sourcePath, $destinationPath)) {
                throw new RuntimeException("Failed to copy file: {$sourcePath} to {$destinationPath}");
            }

        }

        // Close the source directory
        closedir($dir);
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
