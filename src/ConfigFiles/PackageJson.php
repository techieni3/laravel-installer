<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\ConfigFiles;

use JsonException;
use Override;
use TechieNi3\LaravelInstaller\AbstractClasses\JsonFile;
use TechieNi3\LaravelInstaller\ValueObjects\Scripts\NpmScript;

class PackageJson extends JsonFile
{
    /**
     * @throws JsonException
     */
    #[Override]
    public function update(array $content): void
    {
        file_put_contents(
            $this->filePath,
            (string) str(json_encode($content, flags: JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->append(PHP_EOL),
        );
    }

    /**
     * @throws JsonException
     */
    public function append(NpmScript $script): void
    {
        $configuration = $this->getJsonContent();

        $configuration[$script->name] = $script->command;

        $this->update($configuration);
    }
}
