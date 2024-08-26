<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;

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

        $this->updateProvider('AppServiceProvider', $directory)
            ->addNamespaces([
                'Filament\Support\Facades\FilamentView',
                'Illuminate\Support\Facades\Blade',
            ])
            ->updateRegisterMethod($this->getServiceProviderRegisterMethodUpdateBody())
            ->save();
    }

    private function getServiceProviderRegisterMethodUpdateBody(): array
    {
        return [
            new Expression(
                new StaticCall(new Name('FilamentView'), 'registerRenderHook', [
                    new Arg(new String_('panels::body.end')),
                    new Node\Expr\ArrowFunction([
                        'params' => [],
                        'returnType' => new Name('string'),
                        'expr' => new StaticCall(
                            new Name('Blade'),
                            'render',
                            [
                                new Arg(new Node\Scalar\Encapsed([
                                    new Node\Scalar\EncapsedStringPart("@vite('resources/js/app.js')"),
                                ])),
                            ]
                        ),
                    ]),
                ])
            ),
        ];
    }
}
