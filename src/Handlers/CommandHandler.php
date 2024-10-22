<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Handlers;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

readonly class CommandHandler
{
    public function __construct(
        private InputInterface $input,
        private OutputInterface $output
    ) {
    }

    public function run(array $commands, ?string $workingPath = null, array $env = []): Process
    {
        $commands = $this->prepareCommands($commands);

        $process = $this->createProcess($commands, $workingPath, $env);

        $this->configureProcessTty($process);

        $this->runProcess($process);

        return $process;
    }

    private function prepareCommands(array $commands): array
    {
        if ( ! $this->output->isDecorated()) {
            $commands = $this->addNoAnsiOption($commands);
        }

        if ($this->input->getOption('quiet')) {
            $commands = $this->addQuietOption($commands);
        }

        return $commands;
    }

    private function addNoAnsiOption(array $commands): array
    {
        return array_map(fn ($value) => $this->shouldAddOption($value) ? "{$value} --no-ansi" : $value, $commands);
    }

    private function addQuietOption(array $commands): array
    {
        return array_map(fn ($value) => $this->shouldAddOption($value) ? "{$value} --quiet" : $value, $commands);
    }

    private function shouldAddOption(string $value): bool
    {
        return ! str_starts_with($value, 'chmod') && ! str_starts_with($value, 'git');
    }

    private function createProcess(array $commands, ?string $workingPath, array $env): Process
    {
        return Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);
    }

    private function configureProcessTty(Process $process): void
    {
        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }
    }

    private function runProcess(Process $process): void
    {
        $process->run(function ($type, $line): void {
            $this->output->write('    ' . $line);
        });
    }
}
