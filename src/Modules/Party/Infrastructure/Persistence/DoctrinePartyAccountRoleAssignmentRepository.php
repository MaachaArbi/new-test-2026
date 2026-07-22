<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccountRoleAssignment;
use App\Modules\Party\Domain\Repository\PartyAccountRoleAssignmentRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountRoleAssignmentRepository implements PartyAccountRoleAssignmentRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function hasActiveRole(int $accountId, PartyRoleCode $roleCode): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_role
             WHERE account_id = :accountId
               AND role_code = :roleCode
               AND valid_to IS NULL
             LIMIT 1',
            ['accountId' => $accountId, 'roleCode' => $roleCode->toString()],
        );

        return $raw !== false && $raw !== null;
    }

    public function findById(int $id): ?PartyAccountRoleAssignment
    {
        $found = $this->unitOfWork->find(PartyAccountRoleAssignment::class, $id);

        return $found instanceof PartyAccountRoleAssignment ? $found : null;
    }

    public function assign(PartyAccountRoleAssignment $assignment): void
    {
        $this->unitOfWork->persist($assignment);
    }

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     *
     * Commit (flush) is the caller's responsibility.
     */
    public function revoke(PartyAccountRoleAssignment $assignment): void
    {
    }
}
