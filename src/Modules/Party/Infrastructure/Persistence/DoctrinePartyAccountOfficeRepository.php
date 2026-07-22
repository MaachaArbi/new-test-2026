<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccountOffice;
use App\Modules\Party\Domain\Repository\PartyAccountOfficeRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountOfficeRepository implements PartyAccountOfficeRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findByAccountId(int $accountId): ?PartyAccountOffice
    {
        $row = $this->unitOfWork->find(PartyAccountOffice::class, $accountId);

        return $row instanceof PartyAccountOffice ? $row : null;
    }

    public function existsByOfficeCode(string $code): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_office WHERE office_code = :code LIMIT 1',
            ['code' => $code],
        );

        return $raw !== false && $raw !== null;
    }

    public function existsByAccountId(int $accountId): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_office WHERE account_id = :accountId LIMIT 1',
            ['accountId' => $accountId],
        );

        return $raw !== false && $raw !== null;
    }

    public function save(PartyAccountOffice $office): void
    {
        $this->unitOfWork->persist($office);
    }
}
