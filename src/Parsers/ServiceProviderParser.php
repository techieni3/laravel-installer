<?php

declare(strict_types=1);

namespace TechieNi3\LaravelInstaller\Parsers;

use Override;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;

class ServiceProviderParser extends NodeVisitorAbstract
{
    public function __construct(private readonly array $useStatements, private readonly array $registerMethodBody, private readonly array $bootMethodBody)
    {
    }

    #[Override]
    public function enterNode(Node $node): void
    {
        if ($this->registerMethodBody && $node instanceof ClassMethod && $node->name->toString() === 'register') {
            $stmts = $node->getStmts();
            $stmts = array_merge(
                $stmts,
                $this->registerMethodBody
            );
            $node->stmts = $stmts;
        }

        if ($this->bootMethodBody && $node instanceof ClassMethod && $node->name->toString() === 'boot') {
            $stmts = $node->getStmts();
            $stmts = array_merge(
                $stmts,
                $this->bootMethodBody
            );
            $node->stmts = $stmts;
        }

        if ($this->useStatements && $node instanceof Namespace_) {
            $existingUsesString = [];

            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Use_) {
                    foreach ($stmt->uses as $use) {
                        $existingUsesString[] = $use->name->toString();
                    }
                }
            }

            foreach ($this->useStatements as $useStatement) {
                if ( ! in_array($useStatement, $existingUsesString)) {
                    $use = new Use_([new UseUse(new Name($useStatement))]);
                    array_unshift($node->stmts, $use);
                }
            }

        }
    }
}
