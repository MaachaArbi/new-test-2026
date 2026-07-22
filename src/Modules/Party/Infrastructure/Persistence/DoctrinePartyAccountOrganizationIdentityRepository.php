<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccountOrganizationIdentity;
use App\Modules\Party\Domain\Repository\PartyAccountOrganizationIdentityRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountOrganizationIdentityRepository implements PartyAccountOrganizationIdentityRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findByAccountId(int $accountId): ?PartyAccountOrganizationIdentity
    {
        $found = $this->unitOfWork->find(
            PartyAccountOrganizationIdentity::class,
            $accountId,
        );

        return $found instanceof PartyAccountOrganizationIdentity ? $found : null;
    }

    public function existsByAccountId(int $accountId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_organization_identity WHERE account_id = :accountId LIMIT 1',
            ['accountId' => $accountId],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(PartyAccountOrganizationIdentity $identity): void
    {
        $this->unitOfWork->persist($identity);
    }
}
