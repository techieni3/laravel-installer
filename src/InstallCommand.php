<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller;

use InvalidArgumentException;
use JsonException;
use Override;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TechieNi3\LaravelInstaller\Concerns\ConfiguresPrompts;
use TechieNi3\LaravelInstaller\Concerns\ConfiguringEloquentStrictness;
use TechieNi3\LaravelInstaller\ConfigFiles\ComposerJson;
use TechieNi3\LaravelInstaller\ConfigFiles\PackageJson;
use TechieNi3\LaravelInstaller\Enums\DatabaseType;
use TechieNi3\LaravelInstaller\Enums\StarterKit;
use TechieNi3\LaravelInstaller\Handlers\FileHandler;
use TechieNi3\LaravelInstaller\Handlers\JsonFileHandler;
use TechieNi3\LaravelInstaller\Installers\FilamentInstaller;
use TechieNi3\LaravelInstaller\ValueObjects\Replacements\PregReplacement;
use TechieNi3\LaravelInstaller\ValueObjects\Replacements\Replacement;
use TechieNi3\LaravelInstaller\ValueObjects\Scripts\ComposerScript;
use TechieNi3\LaravelInstaller\ValueObjects\Scripts\NpmScript;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    use ConfiguresPrompts;
    use ConfiguringEloquentStrictness;

    protected string $breezeStack;

    protected bool $enabledDarkMode = false;

    protected bool $enabledSSRMode = false;

    protected bool $enabledTypeScript = false;

    protected InstallerContext $installerContext;

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
        $this->displayWelcomeMessage($output);

        if ( ! $input->getArgument('name')) {
            $input->setArgument('name', $this->promptForProjectName());
        }

        if ( ! $input->getOption('breeze')) {
            $this->handleStarterKitOption($input);
        }

        if ($input->getOption('breeze')) {
            $this->promptForBreezeOptions();
        }
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->validateStackOption($input);

        $projectName = $input->getArgument('name');

        $directory = $this->getProjectDirectory($projectName);

        $this->installerContext = new InstallerContext(
            directory: $directory,
            input: $input,
            output: $output
        );

        if ( ! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($directory === '.' && $input->getOption('force')) {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $commands = $this->getInstallCommands($directory, $input);

        if (($process = $this->installerContext->runCommands($commands))->isSuccessful()) {

            $envFileHandler = FileHandler::init($directory . '/.env');

            $envFileHandler->queueReplacement(
                new Replacement(
                    search: 'APP_URL=http://localhost',
                    replace: 'APP_URL=' . $this->generateAppUrl($projectName)
                )
            );

            $envFileHandler->applyReplacements();

            [$database, $migrate] = $this->promptForDatabaseOptions();

            $this->configureDefaultDatabaseConnection($directory, $database, $projectName);

            if ($migrate) {
                $this->installerContext->runCommands([
                    $this->installerContext->php() . ' artisan migrate',
                ]);
            }

            if ( ! $input->getOption('breeze')) {
                $this->cleanUpDefaultLaravelFiles($directory, $projectName);
            }

            // Update composer dependencies and bump versions to latest
            $this->installerContext->updateComposerDependencies();

            $this->updateReadme($directory, $projectName);

            $this->updateEditorConfig($directory);

            $this->installerContext->createRepository();

            $this->configurePint($directory, $input, $output);

            $this->installGitPreCommitHooks($directory, $input, $output);

            $this->updateDatabaseSeederToRunWithoutModelEvents($directory);

            $this->configuringEloquentStrictness($directory, $input, $output);

            $this->installStubs($directory, $input, $output);

            $this->installPest($directory, $input, $output);

            if ($input->getOption('breeze')) {
                $this->installBreeze($directory, $input, $output);
            }

            if ($input->getOption('api')) {
                $this->installApi($directory, $input, $output);
            }

            if ($input->getOption('filament')) {
                (new FilamentInstaller($this->installerContext))->install();
            }

            $this->displayInstallationCompleteMessage($output, $projectName);
        }

        return $process->getExitCode();
    }

    private function displayWelcomeMessage(OutputInterface $output): void
    {
        $output->write(PHP_EOL . '  <fg=red> _                               _
  | |                             | |
  | |     __ _ _ __ __ ___   _____| |
  | |    / _` | \'__/ _` \ \ / / _ \ |
  | |___| (_| | | | (_| |\ V /  __/ |
  |______\__,_|_|  \__,_| \_/ \___|_|</>' . PHP_EOL . PHP_EOL);
    }

    private function displayInstallationCompleteMessage(OutputInterface $output, mixed $projectName): void
    {
        $output->writeln('');
        $output->writeln('');

        $output->writeln("  <bg=blue;fg=white> INFO </> Application ready in <options=bold>[{$projectName}]</>. You can start your local development using:" . PHP_EOL);

        $output->writeln('<fg=gray>➜</> <options=bold>cd ' . $projectName . '</>');
        $output->writeln('<fg=gray>➜</> <options=bold>php artisan serve</>');
        $output->writeln('');
    }

    private function promptForProjectName(): string
    {
        return text(
            label: 'What is the name of your project?',
            placeholder: 'E.g. example-app',
            required: 'The project name is required.',
            validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/u', $value) !== 0
                ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                : null,
        );
    }

    private function handleStarterKitOption(InputInterface $input): void
    {
        match (select(
            label: 'Would you like to install a starter kit?',
            options: StarterKit::toArray(),
            default: StarterKit::None->value,
        )) {
            'breeze' => $input->setOption('breeze', true),
            'api' => $input->setOption('api', true),
            'filament' => $input->setOption('filament', true),
            default => null,
        };
    }

    private function getProjectDirectory(string $name): string
    {
        if ($name !== '.') {
            return getcwd() . DIRECTORY_SEPARATOR . $name;
        }

        return '.';
    }

    private function validateStackOption(InputInterface $input): void
    {
        if ($input->getOption('breeze') && ! in_array($this->breezeStack, $stacks = ['blade', 'livewire', 'livewire-functional', 'react', 'vue'])) {
            throw new InvalidArgumentException("Invalid Breeze stack [{$input->getOption('stack')}]. Valid options are: " . implode(', ', $stacks) . '.');
        }
    }

    private function getInstallCommands(string $directory, InputInterface $input): array
    {
        $composer = $this->installerContext->composer();
        $phpBinary = $this->installerContext->php();
        $version = $this->getVersion($input);

        $commands = [
            $composer . " create-project laravel/laravel \"{$directory}\" {$version} --remove-vcs --prefer-dist --no-scripts",
            $composer . " run post-root-package-install -d \"{$directory}\"",
            $phpBinary . " \"{$directory}/artisan\" key:generate --ansi",
        ];

        if ($directory !== '.' && $input->getOption('force')) {
            $commands = array_merge($this->getForceInstallCommand($directory), $commands);
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $commands[] = "chmod 755 \"{$directory}/artisan\"";
        }

        return $commands;
    }

    private function getForceInstallCommand(string $directory): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return ["(if exist \"{$directory}\" rd /s /q \"{$directory}\")"];
        }

        return ["rm -rf \"{$directory}\""];
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
            options: DatabaseType::toArray(),
            default: DatabaseType::SQLite->value,
        );

        if (in_array($defaultDatabase, [DatabaseType::MySQL->value, DatabaseType::SQLite->value], true)) {
            $migrate = confirm(label: 'Would you like to run the default database migrations?', default: false);
        }

        return [$defaultDatabase, $migrate ?? false];
    }

    private function configureDefaultDatabaseConnection(string $directory, string $database, string $name): void
    {
        (FileHandler::init($directory . '/.env'))
            ->queuePregReplacement(
                new PregReplacement(
                    regex: '/DB_CONNECTION=.*/',
                    replace: 'DB_CONNECTION=' . $database
                )
            )
            ->applyReplacements();

        (FileHandler::init($directory . '/.env.example'))
            ->queuePregReplacement(
                new PregReplacement(
                    regex: '/DB_CONNECTION=.*/',
                    replace: 'DB_CONNECTION=' . $database,
                )
            )
            ->applyReplacements();

        if ($database === DatabaseType::SQLite->value) {
            $environment = file_get_contents($directory . '/.env');

            // If database options aren't commented, comment them for SQLite...
            if ( ! str_contains($environment, '# DB_HOST=127.0.0.1')) {
                $this->commentDatabaseConfigurationForSqlite($directory);
            }

            // create database.sqlite file if doesn't exist
            if ( ! file_exists($directory . '/database/database.sqlite')) {
                touch($directory . '/database/database.sqlite');
            }

            return;
        }

        $envHandler = FileHandler::init($directory . '/.env');
        $envExampleHandler = FileHandler::init($directory . '/.env.example');

        // Any commented database configuration options should be uncommented when not on SQLite...
        $unCommentReplacement = $this->uncommentDatabaseConfiguration($directory);

        $envHandler->queueReplacement($unCommentReplacement);
        $envExampleHandler->queueReplacement($unCommentReplacement);

        // delete default database.sqlite file if exists
        if (file_exists($directory . '/database/database.sqlite')) {
            @unlink($directory . '/database/database.sqlite');
        }

        $defaultPorts = [
            'pgsql' => '5432',
            'sqlsrv' => '1433',
        ];

        if (isset($defaultPorts[$database])) {
            $portReplacement = new Replacement(
                search: 'DB_PORT=3306',
                replace: 'DB_PORT=' . $defaultPorts[$database],
            );

            $envHandler->queueReplacement($portReplacement);
            $envExampleHandler->queueReplacement($portReplacement);
        }

        $dbNameReplacement = new Replacement(
            search: 'DB_DATABASE=laravel',
            replace: 'DB_DATABASE=' . str_replace('-', '_', mb_strtolower($name)),
        );

        $envHandler->queueReplacement($dbNameReplacement);
        $envExampleHandler->queueReplacement($dbNameReplacement);

        $envHandler->applyReplacements();
        $envExampleHandler->applyReplacements();
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

        $commentedDefaults = collect($defaults)->map(static fn ($default) => "# {$default}")->all();

        $commentReplacement = new Replacement(
            search: $defaults,
            replace: $commentedDefaults
        );

        (FileHandler::init($directory . '/.env'))
            ->queueReplacement($commentReplacement)
            ->applyReplacements();

        (FileHandler::init($directory . '/.env.example'))
            ->queueReplacement($commentReplacement)
            ->applyReplacements();
    }

    private function uncommentDatabaseConfiguration(string $directory): Replacement
    {
        $defaults = [
            '# DB_HOST=127.0.0.1',
            '# DB_PORT=3306',
            '# DB_DATABASE=laravel',
            '# DB_USERNAME=root',
            '# DB_PASSWORD=',
        ];

        $commentedDefaults = collect($defaults)->map(static fn ($default) => mb_substr($default, 2))->all();

        return new Replacement(
            search: $defaults,
            replace: $commentedDefaults
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

    private function installBreeze(): void
    {
        $this->installerContext->getOutput()->writeln([
            '',
            '<comment>Installing laravel breeze...</comment>',
            '',
        ]);

        $commands = array_filter([
            $this->installerContext->composer() . ' require laravel/breeze',
            trim(sprintf(
                $this->installerContext->php() . ' artisan breeze:install %s %s %s %s %s',
                $this->breezeStack,
                '--pest',
                $this->enabledTypeScript ? '--typescript' : '',
                $this->enabledDarkMode ? '--dark' : '',
                $this->enabledSSRMode ? '--ssr' : '',
            )),
        ]);

        $this->installerContext->runCommands($commands);

        $this->installerContext->commitChanges('Install Breeze.');
    }

    private function installGitPreCommitHooks(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $this->installerContext->getOutput()->writeln([
            '',
            '<comment>Configuring git pre commit hooks...</comment>',
            '',
        ]);

        $nodeBinary = $this->installerContext->node();

        if ($nodeBinary === null) {
            return;
        }

        $commands = [
            $nodeBinary . ' install --save-dev husky lint-staged',
            'npx husky init',
        ];

        $this->installerContext->runCommands($commands, $directory);

        FileHandler::copyFile(
            sourceFile: __DIR__ . '/../stubs/husky/pre-commit',
            destination: $directory . '/.husky/pre-commit',
        );

        $packageJsonHandler = JsonFileHandler::init(new PackageJson($directory . DIRECTORY_SEPARATOR . 'package.json'));

        $packageJsonHandler->addScript(new NpmScript(
            name: 'format-php',
            command: 'composer pint',
        ));

        try {
            $packageJsonHandler->save();

            $packageJsonHandler->appendToPackageJson(new NpmScript(
                name: 'lint-staged',
                command: [
                    '*.php' => [
                        'npm run format-php',
                    ],
                ]
            ));
        } catch (JsonException $exception) {
            $this->installerContext->getOutput()->writeln('Failed to update package.json.');
        }

        $this->installerContext->commitChanges('Install husky and configure pre commit hooks.');
    }

    private function configurePint(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring pint coding styling...</comment>',
            '',
        ]);

        FileHandler::copyFile(
            sourceFile: __DIR__ . '/../stubs/pint/pint.json',
            destination: $directory . '/pint.json',
        );

        $composerJsonHandler = JsonFileHandler::init(new ComposerJson($directory . DIRECTORY_SEPARATOR . 'composer.json'));

        $composerJsonHandler->addScript(new ComposerScript(
            name: 'pint',
            command: 'pint',
        ));

        try {
            $composerJsonHandler->save();
        } catch (JsonException $exception) {
            $this->installerContext->getOutput()->writeln('Failed to update composer.json.');
        }

        $commands = [
            $this->installerContext->composer() . ' pint',
        ];

        $this->installerContext->runCommands($commands, workingPath: $directory);

        $this->installerContext->commitChanges('Configure pint formatting.');
    }

    private function installStubs(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring opinionated stubs...</comment>',
            '',
        ]);

        $composerBinary = $this->installerContext->composer();

        $commands = [
            $composerBinary . ' require techieni3/laravel-stubs --dev',
            $this->installerContext->php() . ' artisan publish:stubs --force',
        ];

        $this->installerContext->runCommands($commands, $directory);

        $this->installerContext->commitChanges('Install opinionated stubs.');
    }

    private function installPest(string $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring pest...</comment>',
            '',
        ]);

        $composerBinary = $this->installerContext->composer();

        $commands = [
            $composerBinary . ' remove phpunit/phpunit --dev --no-update',
            $composerBinary . ' require pestphp/pest pestphp/pest-plugin-laravel --no-update --dev',
            $composerBinary . ' update',
            $this->installerContext->php() . ' ./vendor/bin/pest --init',
        ];

        $this->installerContext->runCommands($commands, workingPath: $directory, env: [
            'PEST_NO_SUPPORT' => 'true',
        ]);

        FileHandler::copyFile(
            sourceFile: __DIR__ . '/../stubs/pest/Feature.php',
            destination: $directory . '/tests/Feature/ExampleTest.php',
        );

        FileHandler::copyFile(
            sourceFile: __DIR__ . '/../stubs/pest/Unit.php',
            destination: $directory . '/tests/Unit/ExampleTest.php',
        );

        $this->installerContext->commitChanges('Install Pest.');
    }

    private function installApi(mixed $directory, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln([
            '',
            '<comment>Configuring api...</comment>',
            '',
        ]);

        $commands = array_filter([
            trim($this->installerContext->php() . ' artisan install:api'),
        ]);

        $this->installerContext->runCommands($commands);

        $this->installerContext->commitChanges('Install Api.');
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

    private function cleanUpDefaultLaravelFiles(mixed $directory, string $name): void
    {
        $newBodyContent = <<<HTML
          <div style="height: 100vh; display: grid; place-items: center; font-size: 3rem;">
            <h1>{$name}</h1>
          </div>
       HTML;

        file_put_contents($directory . '/resources/views/welcome.blade.php', $newBodyContent);
    }

    private function updateReadme(mixed $directory, string $name): void
    {
        $title = ucwords(str_replace(['_', '-'], ' ', $name));

        $newReadmeContent = <<<EOF
       # {$title}

       EOF;

        file_put_contents($directory . '/README.md', $newReadmeContent);
    }

    private function updateEditorConfig(mixed $directory): void
    {
        $jsonRule = <<<'EOT'

[*.json]
indent_size = 2

[composer.json]
indent_size = 4
EOT;

        (FileHandler::init($directory . '/.editorconfig'))->append($jsonRule);
    }

    private function updateDatabaseSeederToRunWithoutModelEvents(mixed $directory): void
    {
        $databaseSeederHandler = FileHandler::init($directory . '/database/seeders/DatabaseSeeder.php');

        $databaseSeederHandler->queueReplacement(
            new Replacement(
                search: '// use Illuminate\Database\Console\Seeds\WithoutModelEvents;',
                replace: 'use Illuminate\Database\Console\Seeds\WithoutModelEvents;',
            )
        );

        $databaseSeederHandler->queueReplacement(
            new Replacement(
                search: <<<'EOT'
class DatabaseSeeder extends Seeder
{
EOT,
                replace: <<<'EOT'
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
EOT,
            )
        );

        $databaseSeederHandler->applyReplacements();
    }
}
