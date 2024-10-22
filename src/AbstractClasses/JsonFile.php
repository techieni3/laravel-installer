<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\AbstractClasses;

use JsonException;

abstract class JsonFile
{
    public function __construct(public string $filePath)
    {
    }

    /**
     * @throws JsonException
     */
    abstract public function update(array $content): void;

    public function getPath(): string
    {
        return $this->filePath;
    }

    /**
     * @throws JsonException
     */
    public function getJsonContent(): array
    {
        return json_decode(file_get_contents($this->filePath), associative: true, flags: JSON_THROW_ON_ERROR);
    }
}
