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
        $this->ensureGitUserConfig();

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

    private function ensureGitUserConfig(): void
    {
        // Check if username is set
        $process = new Process(['git', 'config', '--get', 'user.name']);
        $process->run();
        $username = trim($process->getOutput());

        if (empty($username)) {
            $username = text(
                label: 'Please enter your Git username',
                placeholder: 'techieni3',
                required: 'Git username is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            );

            $process = new Process(['git', 'config', '--local', 'user.name', $username]);
            $process->run();
        }

        // Check if email is set
        $process = new Process(['git', 'config', '--get', 'user.email']);
        $process->run();
        $email = trim($process->getOutput());

        if (empty($email)) {

            $email = text(
                label: 'Please enter your Git email',
                placeholder: 'techieni3@example.com',
                required: 'Git email is required.',
                validate: fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                    ? 'The email is invalid.'
                    : null,
            );

            $process = new Process(['git', 'config', '--local', 'user.email', $email]);
            $process->run();
        }
    }
}
