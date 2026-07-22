<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccountGroup;
use App\Modules\Party\Domain\Repository\PartyAccountGroupRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountGroupRepository implements PartyAccountGroupRepositoryInterface
{
    private readonly UnitOfWork $unitOfWork;

    private readonly Connection $connection;

    public function __construct(UnitOfWork $unitOfWork, Connection $connection)
    {
        $this->unitOfWork = $unitOfWork;
        $this->connection = $connection;
    }

    public function existsByTypeAndName(PartyAccountGroupTypeCode $type, string $name): bool
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_group
             WHERE group_type_code = :type AND name = :name
             LIMIT 1',
            ['type' => $type->toString(), 'name' => $name],
        );

        return $raw !== false && $raw !== null;
    }

    public function findById(int $id): ?PartyAccountGroup
    {
        $found = $this->unitOfWork->find(PartyAccountGroup::class, $id);

        return $found instanceof PartyAccountGroup ? $found : null;
    }

    public function save(PartyAccountGroup $group): void
    {
        $this->unitOfWork->persist($group);
    }
}
