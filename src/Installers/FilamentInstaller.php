<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Installers;

use TechieNi3\LaravelInstaller\Contracts\ShouldInstall;
use TechieNi3\LaravelInstaller\Handlers\FileHandler;
use TechieNi3\LaravelInstaller\InstallerContext;
use TechieNi3\LaravelInstaller\ValueObjects\Replacements\Replacement;

readonly class FilamentInstaller implements ShouldInstall
{
    public function __construct(private InstallerContext $context)
    {
    }

    public function install(): void
    {
        $this->printMessage();

        $this->context->runCommands($this->getInstallCommand(), workingPath: $this->context->getDirectory());

        $this->configureFilament();

        $this->context->commitChanges('Install Filament panel.');
    }

    private function printMessage(): void
    {
        $this->context->getOutput()->writeln([
            '',
            '<comment>Configuring filament...</comment>',
            '',
        ]);
    }

    private function getInstallCommand(): array
    {
        return [
            $this->context->composer() . ' require filament/filament -W',
            $this->context->php() . ' artisan filament:install --panels',
        ];
    }

    private function configureFilament(): void
    {
        $directory = $this->context->getDirectory();

        // add filament assets to gitignore
        $this->addFilamentAssetsToGitignore($directory);

        // copy filament stubs
        $this->copyStubs($directory);

        // copy filament base classes
        $this->copyBaseClasses($directory);

        // configure vite for hot reloading
        $this->configureVite($directory);
    }

    private function addFilamentAssetsToGitignore(string $directory): void
    {
        $assets = [
            '/public/css/filament',
            '/public/js/filament',
        ];

        (FileHandler::init($directory . '/.gitignore'))
            ->append(implode(PHP_EOL, $assets) . PHP_EOL);
    }

    private function copyStubs($directory): void
    {
        $stubs = __DIR__ . '/../../stubs/filament/stubs';
        $destination = $directory . DIRECTORY_SEPARATOR . 'stubs/filament';

        FileHandler::copyDirectory($stubs, $destination);
    }

    private function copyBaseClasses(string $directory): void
    {
        $stubs = __DIR__ . '/../../stubs/filament/Base';
        $destination = $directory . DIRECTORY_SEPARATOR . 'app/Filament/Base';

        FileHandler::copyDirectory($stubs, $destination);
    }

    private function configureVite(string $directory): void
    {
        (FileHandler::init($directory . '/vite.config.js'))
            ->replaceWith(__DIR__ . '/../../stubs/filament/vite.config.js');

        $handler = FileHandler::init($directory . '/app/Providers/AppServiceProvider.php');

        $handler->queueReplacement($this->getFilamentNameSpaces());
        $handler->queueReplacement($this->getServiceProviderRegisterMethodUpdateBody());

        $handler->applyReplacements();
    }

    private function getFilamentNameSpaces(): Replacement
    {
        return new Replacement(
            search: 'namespace App\Providers;',
            replace: <<<'EOT'
namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Filament\Support\Facades\FilamentView;

EOT,
        );
    }

    private function getServiceProviderRegisterMethodUpdateBody(): Replacement
    {
        return new Replacement(
            search: 'public function register(): void {',
            replace: <<<'EOT'
    public function register(): void
    {
        FilamentView::registerRenderHook('panels::body.end', fn (): string => Blade::render("@vite('resources/js/app.js')"));

EOT,
        );
    }
}
