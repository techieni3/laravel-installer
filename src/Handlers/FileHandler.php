<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Handlers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use RuntimeException;
use TechieNi3\LaravelInstaller\ValueObjects\Replacements\PregReplacement;
use TechieNi3\LaravelInstaller\ValueObjects\Replacements\Replacement;

class FileHandler
{
    protected string $filePath;

    protected bool $isChanged = false;

    protected Stringable $currentContent;

    /**
     * @var Collection<Replacement>
     */
    protected Collection $replacements;

    /**
     * @var Collection<PregReplacement>
     */
    protected Collection $pregReplacements;

    public function __construct()
    {
        $this->replacements = collect();
        $this->pregReplacements = collect();
    }

    public static function copyFile(string $sourceFile, string $destination): void
    {
        (new Filesystem())->copy($sourceFile, $destination);
    }

    public static function copyDirectory(string $directory, string $destination): void
    {
        (new Filesystem)->copyDirectory($directory, $destination);
    }

    public static function init(string $file): self
    {
        $content = self::readFileContent($file);

        $instance = new self();

        $instance->filePath = $file;
        $instance->currentContent = Str::of($content);

        return $instance;
    }

    public function queueReplacement(Replacement $replacement): self
    {
        $this->replacements->push($replacement);

        return $this;
    }

    public function queuePregReplacement(PregReplacement $replacement): self
    {
        $this->pregReplacements->push($replacement);

        return $this;
    }

    public function applyReplacements(): bool
    {
        $this->replacements->each($this->processReplacement(...));

        $this->pregReplacements->each($this->processPregReplacement(...));

        file_put_contents($this->filePath, $this->currentContent);

        return $this->isChanged;
    }

    public function append(string $data): void
    {
        (new Filesystem)->append($this->filePath, $data);
    }

    public function prepend(string $data): void
    {
        (new Filesystem)->prepend($this->filePath, $data);
    }

    public function replaceWith(string $file): void
    {
        $newContent = self::readFileContent($file);

        $this->writeFileContent($this->filePath, $newContent);
    }

    private static function readFileContent(string $file): string
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$file}");
        }

        return $content;
    }

    private function writeFileContent(string $file, string $content): void
    {
        file_put_contents($file, $content);
    }

    private function processReplacement(Replacement $replacement): void
    {
        $replaced = str_replace(
            search: $replacement->search,
            replace: $replacement->replace,
            subject: $this->currentContent->toString()
        );

        $this->currentContent = Str::of($replaced);
        $this->isChanged = true;
    }

    private function processPregReplacement(PregReplacement $replacement): void
    {
        $replaced = preg_replace(
            pattern: $replacement->regex,
            replacement: $replacement->replace,
            subject: $this->currentContent->toString()
        );

        $this->currentContent = Str::of($replaced);
        $this->isChanged = true;
    }
}
