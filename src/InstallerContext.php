<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller;

use Illuminate\Support\ProcessUtils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use TechieNi3\LaravelInstaller\Concerns\InteractWithGit;
use TechieNi3\LaravelInstaller\Handlers\CommandHandler;
use TechieNi3\LaravelInstaller\Handlers\ComposerTaskHandler;

readonly class InstallerContext
{
    use InteractWithGit;

    private ComposerTaskHandler $composerHandler;

    private CommandHandler $commandHandler;

    public function __construct(
        private string $directory,
        private InputInterface $input,
        private OutputInterface $output
    ) {
        $this->composerHandler = new ComposerTaskHandler($this);
        $this->commandHandler = new CommandHandler($this->input, $this->output);
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function composer(): string
    {
        return $this->composerHandler->getComposerBinary();
    }

    public function php(): string
    {
        $phpBinary = (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    public function node(): ?string
    {
        $process = new Process(['which', 'npm']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? ProcessUtils::escapeArgument($output) : null;
    }

    public function updateComposerDependencies(): void
    {
        $this->composerHandler->updateDependencies();
    }

    public function runCommands(array $commands, ?string $workingPath = null, array $env = []): Process
    {
        return $this->commandHandler->run($commands, $workingPath, $env);
    }
}
