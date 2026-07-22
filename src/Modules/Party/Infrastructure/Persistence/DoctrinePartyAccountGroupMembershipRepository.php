<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccountGroupMembership;
use App\Modules\Party\Domain\Repository\PartyAccountGroupMembershipRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountGroupMembershipRepository implements PartyAccountGroupMembershipRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?PartyAccountGroupMembership
    {
        $found = $this->unitOfWork->find(PartyAccountGroupMembership::class, $id);

        return $found instanceof PartyAccountGroupMembership ? $found : null;
    }

    public function hasActiveMembership(int $accountId, int $groupId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_group_member
             WHERE account_id = :accountId
               AND group_id = :groupId
               AND valid_to IS NULL
             LIMIT 1',
            ['accountId' => $accountId, 'groupId' => $groupId],
        );

        return $raw !== false && $raw !== null;
    }

    public function assign(PartyAccountGroupMembership $membership): void
    {
        $this->unitOfWork->persist($membership);
    }

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     *
     * Commit (flush) is the caller's responsibility.
     */
    public function revoke(PartyAccountGroupMembership $assignment): void
    {
    }
}
