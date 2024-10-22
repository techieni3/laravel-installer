<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\ConfigFiles;

use JsonException;
use Override;
use TechieNi3\LaravelInstaller\AbstractClasses\JsonFile;

class ComposerJson extends JsonFile
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
}
