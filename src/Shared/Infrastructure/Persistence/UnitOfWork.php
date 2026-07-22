<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Seul point d’accès autorisé à EntityManagerInterface (Deptrac + PHPStan).
 *
 * persist() n’écrit pas en base ; commit() = flush unique en fin d’opération métier.
 *
 * find() / createQueryBuilder() : délégations ORM nécessaires pour le chemin
 * load→mutate→persist→commit (L1–L10). Signalé explicitement — hors API
 * minimale persist/commit du prompt, indispensable tant que des lectures ORM
 * légitimes existent. Pas de flush ici.
 */
final class UnitOfWork
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function commit(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @return T|null
     */
    public function find(string $entityClass, mixed $id): ?object
    {
        return $this->entityManager->find($entityClass, $id);
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder();
    }
}
