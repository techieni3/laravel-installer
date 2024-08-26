<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use TechieNi3\LaravelInstaller\Parsers\ServiceProviderParser;

trait InteractWithServiceProviders
{
    private string $provider;

    private string $workingDir;

    private array $registerMethodBody = [];

    private array $bootMethodBody = [];

    private array $useStatements = [];

    private function updateProvider(string $provider, string $workingDir): self
    {
        $this->provider = $provider;
        $this->workingDir = $workingDir;

        return $this;
    }

    private function addNamespaces(array $namespaces): self
    {
        $this->useStatements = $namespaces;

        return $this;

    }

    private function updateRegisterMethod(array $methodBody): self
    {
        $this->registerMethodBody = $methodBody;

        return $this;
    }

    private function updateBootMethod(array $methodBody): self
    {
        $this->bootMethodBody = $methodBody;

        return $this;
    }

    private function save(): void
    {
        // check file exists
        $file = $this->workingDir . DIRECTORY_SEPARATOR . 'app/Providers/' . $this->provider . '.php';

        if ( ! file_exists($file)) {
            echo "File {$file} not found\n";

            return;
        }

        $code = file_get_contents($file);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser;
        $prettyPrinter = new PrettyPrinter\Standard;

        $traverser->addVisitor(new ServiceProviderParser(
            $this->useStatements,
            $this->registerMethodBody,
            $this->bootMethodBody
        ));
        try {
            $ast = $parser->parse($code);

            $stmts = $traverser->traverse($ast);

            $modifiedCode = $prettyPrinter->prettyPrintFile($stmts);

            file_put_contents($file, $modifiedCode);

            echo PHP_EOL;
            echo "{$this->provider}.php has been successfully updated." . PHP_EOL;
            echo PHP_EOL;
        } catch (Error $error) {
            echo "Parse error: {$error->getMessage()}\n";

            return;
        }
    }
}
