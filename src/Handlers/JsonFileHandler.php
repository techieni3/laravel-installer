<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Handlers;

use Illuminate\Support\Collection;
use JsonException;
use RuntimeException;
use TechieNi3\LaravelInstaller\AbstractClasses\JsonFile;
use TechieNi3\LaravelInstaller\ConfigFiles\PackageJson;
use TechieNi3\LaravelInstaller\Contracts\Script;
use TechieNi3\LaravelInstaller\ValueObjects\Scripts\NpmScript;

class JsonFileHandler
{
    private JsonFile $file;

    private array $currentContent;

    /**
     * @var Collection<Script>
     */
    private Collection $scripts;

    public function __construct(JsonFile $file)
    {
        $this->file = $file;
        $this->scripts = collect();
        $this->loadContent();
    }

    public static function init(JsonFile $file): self
    {

        return new self($file);
    }

    public function addScript(Script $script): self
    {
        $this->scripts->push($script);

        return $this;
    }

    /**
     * @throws JsonException
     */
    public function save(): void
    {
        $this->scripts->each($this->appendScript(...));

        $this->file->update($this->currentContent);
    }

    /**
     * @throws JsonException
     */
    public function appendToPackageJson(NpmScript $script): void
    {
        if ($this->file instanceof PackageJson) {
            $this->file->append($script);
        }
    }

    private function loadContent(): void
    {
        try {
            $this->currentContent = $this->file->getJsonContent();
            $this->currentContent['scripts'] ??= [];
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to read file: {$this->file->getPath()}");
        }
    }

    private function appendScript(Script $script): void
    {
        if (array_key_exists($script->name, $this->currentContent['scripts'])) {
            return;
        }

        $this->currentContent['scripts'][$script->name] = $script->command;
    }
}
