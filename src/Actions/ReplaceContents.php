<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use TechieNi3\LaravelInstaller\Replacement;

class ReplaceContents
{
    protected bool $isChanged = false;

    protected Stringable $currentContent;

    /**
     * @var Collection<Replacement>
     */
    protected Collection $replacements;

    public function __construct(
        protected string $file
    ) {
        $this->currentContent = Str::of(
            file_get_contents($file)
        );

        $this->replacements = collect();
    }

    public function __invoke(): bool
    {
        $this->replacements->each($this->replace(...));

        file_put_contents($this->file, $this->currentContent);

        return $this->isChanged;
    }

    public function addReplacement(Replacement $replacement): self
    {
        $this->replacements->push($replacement);

        return $this;
    }

    private function replace(Replacement $replacement): void
    {
        if ($this->currentContent->contains($replacement->replace)) {
            return;
        }

        $replaced = str_replace(
            search: $replacement->search,
            replace: $replacement->replace,
            subject: $this->currentContent->toString()
        );

        $this->currentContent = Str::of($replaced);

        $this->isChanged = true;
    }
}
