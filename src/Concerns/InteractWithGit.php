<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

trait InteractWithGit
{
    private function commitChanges(string $message, string $directory, InputInterface $input, OutputInterface $output): void
    {

        $commands = [
            'git add .',
            "git commit -q -m \"{$message}\"",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);
    }

    private function createRepository(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $branch = $this->defaultBranch();

        $commands = [
            'git init -q',
            'git add .',
            'git commit -q -m "Set up a fresh Laravel app"',
            "git branch -M {$branch}",
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);
    }

    private function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }
}
