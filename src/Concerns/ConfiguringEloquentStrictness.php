<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Concerns;

use PhpParser\Comment;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConfiguringEloquentStrictness
{
    protected function configuringEloquentStrictness(mixed $directory, InputInterface $input, OutputInterface $output): void
    {
        $this->updateProvider('AppServiceProvider', $directory)
            ->addNamespaces([
                'Illuminate\Database\Eloquent\Model',
                'Illuminate\Support\Facades\App',
                'Illuminate\Support\Facades\DB',

            ])
            ->updateBootMethod($this->getModelStrictnessUpdateBody())
            ->save();

        $this->commitChanges('Configure Eloquent strictness.', $directory, $input, $output);
    }

    protected function getModelStrictnessUpdateBody(): array
    {
        return [
            new Expression(
                new StaticCall(new Name('Model'), 'unguard'),
                [
                    'comments' => [
                        new Comment('// Disable mass assignment protection'),
                    ],
                ]
            ),
            new Expression(
                new StaticCall(
                    new Name('Model'),
                    'shouldBeStrict',
                    [
                        new Arg(new StaticCall(
                            new Name('App'),
                            'isLocal'
                        )),
                    ]
                ),
                [
                    'comments' => [
                        new Comment('// Add strict mode if the environment is local'),
                    ],
                ]
            ),
            new Expression(
                new StaticCall(
                    new Name('DB'),
                    'prohibitDestructiveCommands',
                    [
                        new Arg(new StaticCall(
                            new Name('App'),
                            'isProduction'
                        )),
                    ]
                ),
                [
                    'comments' => [
                        new Comment('// Prohibits: db:wipe, migrate:fresh, migrate:refresh, and migrate:reset'),
                    ],
                ]
            ),
        ];
    }
}
