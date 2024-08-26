<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\text;

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
        // initialize git repository
        $this->runCommands(['git init -q'], $input, $output, workingPath: $directory);

        $this->ensureGitUserConfig($directory, $input, $output);

        $branch = $this->defaultBranch();

        $commands = [
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

    private function ensureGitUserConfig($directory, InputInterface $input, OutputInterface $output): void
    {
        // Check if username is set
        $gitUsernameCheckProcess = new Process(command: ['git', 'config', '--get', 'user.name'], cwd: $directory);
        $gitUsernameCheckProcess->run();

        $username = text(
            label: 'Please enter your Git username',
            placeholder: 'techieni3',
            default: trim($gitUsernameCheckProcess->getOutput()),
            required: 'Git username is required.',
            validate: fn ($value) => preg_match('/[^\pL\pN\-_.\s]/u', trim($value)) !== 0
                ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                : null,
        );

        $this->runCommands(['git config --local user.name "' . trim($username) . '"'], $input, $output, workingPath: $directory);

        // Check if email is set
        $gitEmailCheckProcess = new Process(['git', 'config', '--get', 'user.email']);
        $gitEmailCheckProcess->run();

        $email = text(
            label: 'Please enter your Git email',
            placeholder: 'techieni3@example.com',
            default: trim($gitEmailCheckProcess->getOutput()),
            required: 'Git email is required.',
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'The email is invalid.'
                : null,
        );

        $this->runCommands(['git config --local user.email "' . trim($email) . '"'], $input, $output, workingPath: $directory);
    }
}
