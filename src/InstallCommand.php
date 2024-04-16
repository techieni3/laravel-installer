<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use TechieNi3\LaravelInstaller\Concerns\ConfiguresPrompts;
use TechieNi3\LaravelInstaller\Concerns\InteractWithComposerJson;
use TechieNi3\LaravelInstaller\Concerns\InteractWithGit;
use TechieNi3\LaravelInstaller\Concerns\InteractWithPackageJson;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    use ConfiguresPrompts;
    use InteractWithComposerJson;
    use InteractWithGit;
    use InteractWithPackageJson;

    protected string $breezeStack;

    protected bool $enabledDarkMode = false;

    protected bool $enabledSSRMode = false;

    protected bool $enabledTypeScript = false;

    protected Composer $composer;

    #[Override]
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Laravel application')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('breeze', null, InputOption::VALUE_NONE, 'Installs the Laravel Breeze scaffolding')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Installs Laravel Sanctum scaffolded with API support')
            ->addOption('filament', null, InputOption::VALUE_NONE, 'Installs Filament panel')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    #[Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $output->write(PHP_EOL . '  <fg=red> _                               _
  | |                             | |
  | |     __ _ _ __ __ ___   _____| |
  | |    / _` | \'__/ _` \ \ / / _ \ |
  | |___| (_| | | | (_| |\ V /  __/ |
  |______\__,_|_|  \__,_| \_/ \___|_|</>' . PHP_EOL . PHP_EOL);

        if ( ! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: 'The project name is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }

        if ( ! $input->getOption('breeze')) {
            match (select(
                label: 'Would you like to install a starter kit?',
                options: [
                    'none' => 'No starter kit',
                    'api' => 'API only',
                    'breeze' => 'Laravel Breeze',
                    'filament' => 'Filament',
                ],
                default: 'none',
            )) {
                'breeze' => $input->setOption('breeze', true),
                'api' => $input->setOption('api', true),
                'filament' => $input->setOption('filament', true),
                default => null,
            };
        }

        if ($input->getOption('breeze')) {
            $this->promptForBreezeOptions();
        }

    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateStackOption($input);

        $name = $input->getArgument('name');

        $directory = $name !== '.' ? getcwd() . DIRECTORY_SEPARATOR . $name : '.';

        $this->composer = new Composer(new Filesystem(), $directory);

        $version = $this->getVersion($input);

        if ( ! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($directory === '.' && $input->getOption('force')) {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $composer = $this->findComposer();

        $commands = [
            $composer . " create-project laravel/laravel \"{$directory}\" {$version} --remove-vcs --prefer-dist",
        ];

        if ($directory !== '.' && $input->getOption('force')) {
            if (PHP_OS_FAMILY === 'Windows') {
                array_unshift($commands, "(if exist \"{$directory}\" rd /s /q \"{$directory}\")");
            } else {
                array_unshift($commands, "rm -rf \"{$directory}\"");
            }
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $commands[] = "chmod 755 \"{$directory}/artisan\"";
        }

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {

            $this->replaceInFile(
                'APP_URL=http://localhost',
                'APP_URL=' . $this->generateAppUrl($name),
                $directory . '/.env'
            );

            [$database, $migrate] = $this->promptForDatabaseOptions();

            $this->configureDefaultDatabaseConnection($directory, $database, $name);

            if ($migrate) {
                $this->runCommands([
                    $this->phpBinary() . ' artisan migrate',
                ], $input, $output, workingPath: $directory);
            }

            $this->createRepository($directory, $input, $output);

            $this->configurePint($directory, $input, $output);

            $this->installGitPreCommitHooks($directory, $input, $output);

            $this->installStubs($directory, $input, $output);

            $this->installPest($directory, $input, $output);

            if ($input->getOption('breeze')) {
                $this->installBreeze($directory, $input, $output);
            } elseif ($input->getOption('api')) {
                $this->installApi($directory, $input, $output);
            } elseif ($input->getOption('filament')) {
                $this->installFilament($directory, $input, $output);
            }

            $output->writeln('');
            $output->writeln('');

            $output->writeln("  <bg=blue;fg=white> INFO </> Application ready in <options=bold>[{$name}]</>. You can start your local development using:" . PHP_EOL);

            $output->writeln('<fg=gray>➜</> <options=bold>cd ' . $name . '</>');
            $output->writeln('<fg=gray>➜</> <options=bold>php artisan serve</>');
            $output->writeln('');
        }

        return $process->getExitCode();
    }

    private function validateStackOption(InputInterface $input): void
    {
        if ($input->getOption('breeze') && ! in_array($this->breezeStack, $stacks = ['blade', 'livewire', 'livewire-functional', 'react', 'vue'])) {
            throw new InvalidArgumentException("Invalid Breeze stack [{$input->getOption('stack')}]. Valid options are: " . implode(', ', $stacks) . '.');
        }
    }

    private function getVersion(InputInterface $input): string
    {
        if ($input->getOption('dev')) {
            return 'dev-master';
        }

        return '';
    }

    private function verifyApplicationDoesntExist(string $directory): void
    {
        if ($directory !== getcwd() && (is_dir($directory) || is_file($directory))) {
            throw new RuntimeException('Application already exists!');
        }
    }

    private function promptForDatabaseOptions(): array
    {

        $defaultDatabase = select(
            label: 'Which database will your application use?',
            options: [
                'mysql' => 'MySQL',
                'mariadb' => 'MariaDB',
                'pgsql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
                'sqlsrv' => 'SQL Server',
            ],
            default: 'sqlite'
        );

        if ($defaultDatabase !== 'sqlite') {
            $migrate = confirm(label: 'Default database updated. Would you like to run the default database migrations?', default: true);
        }

        return [$defaultDatabase, $migrate ?? false];
    }

    private function configureDefaultDatabaseConnection(string $directory, string $database, string $name): void
    {
        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION=' . $database,
            $directory . '/.env'
        );

        $this->pregReplaceInFile(
            '/DB_CONNECTION=.*/',
            'DB_CONNECTION=' . $database,
            $directory . '/.env.example'
        );

        if ($database === 'sqlite') {
            $environment = file_get_contents($directory . '/.env');

            // If database options aren't commented, comment them for SQLite...
            if ( ! str_contains($environment, '# DB_HOST=127.0.0.1')) {
                $this->commentDatabaseConfigurationForSqlite($directory);
            }

            return;
        }

        // Any commented database configuration options should be uncommented when not on SQLite...
        $this->uncommentDatabaseConfiguration($directory);

        // delete default database.sqlite file if exists
        if (file_exists($directory . '/database/database.sqlite')) {
            unlink($directory . '/database/database.sqlite');
        }

        $defaultPorts = [
            'pgsql' => '5432',
            'sqlsrv' => '1433',
        ];

        if (isset($defaultPorts[$database])) {
            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT=' . $defaultPorts[$database],
                $directory . '/.env'
            );

            $this->replaceInFile(
                'DB_PORT=3306',
                'DB_PORT=' . $defaultPorts[$database],
                $directory . '/.env.example'
            );
        }

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE=' . str_replace('-', '_', mb_strtolower($name)),
            $directory . '/.env'
        );

        $this->replaceInFile(
            'DB_DATABASE=laravel',
            'DB_DATABASE=' . str_replace('-', '_', mb_strtolower($name)),
            $directory . '/.env.example'
        );
    }

    private function commentDatabaseConfigurationForSqlite(string $directory): void
    {
        $defaults = [
            'DB_HOST=127.0.0.1',
            'DB_PORT=3306',
            'DB_DATABASE=laravel',
            'DB_USERNAME=root',
            'DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory . '/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => "# {$default}")->all(),
            $directory . '/.env.example'
        );
    }

    private function uncommentDatabaseConfiguration(string $directory): void
    {
        $defaults = [
            '# DB_HOST=127.0.0.1',
            '# DB_PORT=3306',
            '# DB_DATABASE=laravel',
            '# DB_USERNAME=root',
            '# DB_PASSWORD=',
        ];

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => mb_substr($default, 2))->all(),
            $directory . '/.env'
        );

        $this->replaceInFile(
            $defaults,
            collect($defaults)->map(fn ($default) => mb_substr($default, 2))->all(),
            $directory . '/.env.example'
        );
    }

    private function promptForBreezeOptions(): void
    {
        // get stack for Breeze
        $this->breezeStack = select(
            label: 'Which Breeze stack would you like to install?',
            options: [
                'blade' => 'Blade with Alpine',
                'livewire' => 'Livewire (Volt Class API) with Alpine',
                'livewire-functional' => 'Livewire (Volt Functional API) with Alpine',
                'react' => 'React with Inertia',
                'vue' => 'Vue with Inertia',
            ],
            default: 'blade',
        );

        $additionalFeaturesSelected = [
            'dark' => false,
            'ssr' => false,
            'typescript' => false,
        ];

        if (in_array($this->breezeStack, ['react', 'vue'])) {
            collect(multiselect(
                label: 'Would you like any optional features?',
                options: [
                    'dark' => 'Dark mode',
                    'ssr' => 'Inertia SSR',
                    'typescript' => 'TypeScript',
                ],
                default: [],
            ))->each(static function ($option) use (&$additionalFeaturesSelected): void {
                $additionalFeaturesSelected[$option] = true;
            });
        } elseif (in_array($this->breezeStack, ['blade', 'livewire', 'livewire-functional'])) {
            $additionalFeaturesSelected['dark'] = confirm(
                label: 'Would you like dark mode support?',
                default: false,
            );
        }

        $this->setAdditionalFeaturesForBreeze($additionalFeaturesSelected);
    }

    /*
     * @param array<string, bool> $additionalFeaturesSelected
     */
    private function setAdditionalFeaturesForBreeze(array $additionalFeaturesSelected): void
    {
        if ($additionalFeaturesSelected['dark']) {
            $this->setDarkMarkMode();
        }

        if ($additionalFeaturesSelected['ssr']) {
            $this->setSSR();
        }

        if ($additionalFeaturesSelected['typescript']) {
            $this->setTypeScript();
        }
    }

    private function setDarkMarkMode(): void
    {
        $this->enabledDarkMode = true;
    }

    private function setSSR(): void
    {
        $this->enabledSSRMode = true;
    }

    private function setTypeScript(): void
    {
        $this->enabledTypeScript = true;
    }

    private function findComposer(): string
    {
        return implode(' ', $this->composer->findComposer());
    }

    private function phpBinary(): string
    {
        $phpBinary = (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    private function findNode(): ?string
    {
        $process = new Process(['which', 'npm']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? ProcessUtils::escapeArgument($output) : null;
    }

    private function runCommands(array $commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = []): Process
    {
        if ( ! $output->isDecorated()) {
            $commands = array_map(static function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value . ' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(static function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value . ' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        $process->run(function ($type, $line) use ($output): void {
            $output->write('    ' . $line);
        });

        return $process;
    }

    private function installBreeze(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Installing laravel breeze...</comment>',
            '',
        ]);

        $commands = array_filter([
            $this->findComposer() . ' require laravel/breeze',
            trim(sprintf(
                $this->phpBinary() . ' artisan breeze:install %s %s %s %s %s',
                $this->breezeStack,
                '--pest',
                $this->enabledTypeScript ? '--typescript' : '',
                $this->enabledDarkMode ? '--dark' : '',
                $this->enabledSSRMode ? '--ssr' : '',
            )),
        ]);

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->commitChanges('Install Breeze.', $directory, $input, $output);
    }

    private function installGitPreCommitHooks(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring git pre commit hooks...</comment>',
            '',
        ]);

        $nodeBinary = $this->findNode();

        if ($nodeBinary === null) {
            return;
        }

        $commands = [
            $nodeBinary . ' install --save-dev husky lint-staged',
            'npx husky init',
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->replaceFile(
            'husky/pre-commit',
            $directory . '/.husky/pre-commit',
        );

        $this->addNPMScript($directory, name: 'format-php', command: 'composer pint');

        $this->appendToPackageJson($directory, name: 'lint-staged', command: [
            '*.php' => [
                'npm run format-php',
            ],
        ]);

        $this->commitChanges('Install husky and configure pre commit hooks.', $directory, $input, $output);
    }

    private function configurePint(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring pint coding styling...</comment>',
            '',
        ]);

        $this->replaceFile(
            'pint/pint.json',
            $directory . '/pint.json',
        );

        $this->addComposerScript($directory, name: 'pint', command: 'pint');

        $commands = [
            $this->findComposer() . ' pint',
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->commitChanges('Configure pint formatting.', $directory, $input, $output);
    }

    private function installStubs(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring opinionated stubs...</comment>',
            '',
        ]);

        $composerBinary = $this->findComposer();

        $commands = [
            $composerBinary . ' require techieni3/laravel-stubs --dev',
            $this->phpBinary() . ' artisan publish:stubs --force',
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->commitChanges('Install opinionated stubs.', $directory, $input, $output);
    }

    private function installPest(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring pest...</comment>',
            '',
        ]);

        $composerBinary = $this->findComposer();

        $commands = [
            $composerBinary . ' remove phpunit/phpunit --dev --no-update',
            $composerBinary . ' require pestphp/pest pestphp/pest-plugin-laravel --no-update --dev',
            $composerBinary . ' update',
            $this->phpBinary() . ' ./vendor/bin/pest --init',
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory, env: [
            'PEST_NO_SUPPORT' => 'true',
        ]);

        $this->replaceFile(
            'pest/Feature.php',
            $directory . '/tests/Feature/ExampleTest.php',
        );

        $this->replaceFile(
            'pest/Unit.php',
            $directory . '/tests/Unit/ExampleTest.php',
        );

        $this->commitChanges('Install Pest.', $directory, $input, $output);
    }

    private function installApi(mixed $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring api...</comment>',
            '',
        ]);

        $commands = array_filter([
            trim($this->phpBinary() . ' artisan install:api'),
        ]);

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->commitChanges('Install Api.', $directory, $input, $output);
    }

    private function installFilament(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring filament...</comment>',
            '',
        ]);

        $composerBinary = $this->findComposer();

        $this->appendInFile($directory . '/.gitignore', '/public/css/filament' . PHP_EOL);
        $this->appendInFile($directory . '/.gitignore', '/public/js/filament' . PHP_EOL);

        $commands = [
            $composerBinary . ' require filament/filament -W',
            $this->phpBinary() . ' artisan filament:install --panels',
        ];

        $this->runCommands($commands, $input, $output, workingPath: $directory);

        $this->commitChanges('Install Filament panel.', $directory, $input, $output);
    }

    private function generateAppUrl($name): string
    {
        $hostname = mb_strtolower($name) . '.test';

        return $this->canResolveHostname($hostname) ? 'http://' . $hostname : 'http://localhost';
    }

    private function canResolveHostname($hostname): bool
    {
        return gethostbyname($hostname . '.') !== $hostname . '.';
    }

    private function replaceInFile(string|array $search, string|array $replace, string $file): void
    {
        file_put_contents(
            $file,
            str_replace($search, $replace, file_get_contents($file))
        );
    }

    private function appendInFile(string $file, string $data): void
    {
        file_put_contents($file, $data, FILE_APPEND);
    }

    private function replaceFile(string $replace, string $file): void
    {
        $stubs = dirname(__DIR__) . '/stubs';

        file_put_contents(
            $file,
            file_get_contents("{$stubs}/{$replace}"),
        );
    }

    private function pregReplaceInFile(string $pattern, string $replace, string $file): void
    {
        file_put_contents(
            $file,
            preg_replace($pattern, $replace, file_get_contents($file))
        );
    }
}
