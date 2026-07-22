<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Interdit tout ->flush() sur EntityManager(Interface) hors UnitOfWork::commit().
 *
 * @implements Rule<MethodCall>
 */
final class NoEntityManagerFlushOutsideUnitOfWorkRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->toString() !== 'flush') {
            return [];
        }

        if ($this->isInsideUnitOfWorkCommit($scope)) {
            return [];
        }

        if (!$this->isEntityManagerFlush($scope, $node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Appel interdit à EntityManager::flush() hors UnitOfWork::commit(). '
                .'Utiliser UnitOfWork::persist() puis un seul commit() en fin d\'opération métier.',
            )
                ->identifier('ostravel.emFlushOutsideUnitOfWork')
                ->build(),
        ];
    }

    private function isInsideUnitOfWorkCommit(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || $classReflection->getName() !== UnitOfWork::class) {
            return false;
        }

        $function = $scope->getFunction();
        if (!$function instanceof MethodReflection) {
            return false;
        }

        return $function->getName() === 'commit';
    }

    private function isEntityManagerFlush(Scope $scope, MethodCall $node): bool
    {
        $calledOn = $scope->getType($node->var);
        $interfaceType = new ObjectType(EntityManagerInterface::class);
        $concreteType = new ObjectType(EntityManager::class);

        return !$interfaceType->isSuperTypeOf($calledOn)->no()
            || !$concreteType->isSuperTypeOf($calledOn)->no();
    }
}
