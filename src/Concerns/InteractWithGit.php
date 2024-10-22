<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use Symfony\Component\Process\Process;

use function Laravel\Prompts\text;

trait InteractWithGit
{
    public function commitChanges(string $message): void
    {
        $commands = [
            'git add .',
            "git commit -q -m \"{$message}\"",
        ];

        $this->runCommands($commands, workingPath: $this->getDirectory());
    }

    public function createRepository(): void
    {
        // initialize git repository
        $this->runCommands(['git init -q'], workingPath: $this->getDirectory());

        $this->ensureGitUserConfig();

        $branch = $this->defaultBranch();

        $commands = [
            'git add .',
            'git commit -q -m "Set up a fresh Laravel app"',
            "git branch -M {$branch}",
        ];

        $this->runCommands($commands, workingPath: $this->getDirectory());
    }

    private function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    private function ensureGitUserConfig(): void
    {
        // Check if username is set
        $gitUsernameCheckProcess = new Process(command: ['git', 'config', '--get', 'user.name'], cwd: $this->getDirectory());
        $gitUsernameCheckProcess->run();

        $username = $this->promptForGitUserName($gitUsernameCheckProcess->getOutput());

        $this->runCommands(['git config --local user.name "' . trim($username) . '"'], workingPath: $this->getDirectory());

        // Check if email is set
        $gitEmailCheckProcess = new Process(['git', 'config', '--get', 'user.email'], cwd: $this->getDirectory());
        $gitEmailCheckProcess->run();

        $email = $this->promptForGitEmail($gitEmailCheckProcess->getOutput());

        $this->runCommands(['git config --local user.email "' . trim($email) . '"'], workingPath: $this->getDirectory());
    }

    private function promptForGitUserName(string $defaultUserName): string
    {
        return text(
            label: 'Please enter your Git username',
            placeholder: 'techieni3',
            default: trim($defaultUserName),
            required: 'Git username is required.',
            validate: static fn ($value) => preg_match('/[^\pL\pN\-_.\s]/u', trim($value)) !== 0
                ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                : null,
        );
    }

    private function promptForGitEmail(string $defaultEmail): string
    {
        return text(
            label: 'Please enter your Git email',
            placeholder: 'techieni3@example.com',
            default: trim($defaultEmail),
            required: 'Git email is required.',
            validate: static fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'The email is invalid.'
                : null,
        );
    }
}
