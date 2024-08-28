<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use TechieNi3\LaravelInstaller\Actions\ReplaceContents;
use TechieNi3\LaravelInstaller\Replacement;

trait ConfigureFilament
{
    private function configureFilament(string $directory): void
    {
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
        $this->appendInFile($directory . '/.gitignore', '/public/css/filament' . PHP_EOL);
        $this->appendInFile($directory . '/.gitignore', '/public/js/filament' . PHP_EOL);
    }

    private function copyStubs($directory): void
    {
        $stubs = __DIR__ . '/../../stubs/filament/stubs';
        $destination = $directory . DIRECTORY_SEPARATOR . 'stubs/filament';

        $this->copyDir($stubs, $destination);
    }

    private function copyBaseClasses(string $directory): void
    {
        $stubs = __DIR__ . '/../../stubs/filament/Base';
        $destination = $directory . DIRECTORY_SEPARATOR . 'app/Filament/Base';

        $this->copyDir($stubs, $destination);
    }

    private function configureVite(string $directory): void
    {
        $this->replaceFile(
            'filament/vite.config.js',
            $directory . '/vite.config.js',
        );

        $replace = new ReplaceContents(file: $directory . '/app/Providers/AppServiceProvider.php');

        $replace->addReplacement($this->getFilamentNameSpaces());
        $replace->addReplacement($this->getServiceProviderRegisterMethodUpdateBody());

        $replace();
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
