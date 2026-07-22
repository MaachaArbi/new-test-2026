<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\PublicId;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountRepository implements PartyAccountRepositoryInterface
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    public function __construct(UnitOfWork $unitOfWork, Connection $connection)
    {
        $this->unitOfWork = $unitOfWork;
        $this->connection = $connection;
    }

    public function findById(int $id): ?PartyAccount
    {
        /** @var PartyAccount|null $account */
        $account = $this->unitOfWork->find(PartyAccount::class, $id);

        return $account;
    }

    public function findNatureById(int $id): ?string
    {
        $raw = $this->connection->fetchOne(
            'SELECT nature FROM party_account WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return $raw;
    }

    public function findByPublicId(PublicId $publicId): ?PartyAccount
    {
        /** @var PartyAccount|null $account */
        $account = $this->unitOfWork->createQueryBuilder()
            ->select('account')
            ->from(PartyAccount::class, 'account')
            ->andWhere('account.publicId = :publicId')
            ->andWhere('account.deletedAt IS NULL')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }

    public function findByPublicIdIncludingDeleted(PublicId $publicId): ?PartyAccount
    {
        /** @var PartyAccount|null $account */
        $account = $this->unitOfWork->createQueryBuilder()
            ->select('account')
            ->from(PartyAccount::class, 'account')
            ->andWhere('account.publicId = :publicId')
            ->setParameter('publicId', $publicId, 'public_id')
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }

    public function findByEmail(Email $email): ?PartyAccount
    {
        /** @var PartyAccount|null $account */
        $account = $this->unitOfWork->createQueryBuilder()
            ->select('account')
            ->from(PartyAccount::class, 'account')
            ->andWhere('account.email = :email')
            ->andWhere('account.deletedAt IS NULL')
            ->setParameter('email', $email, 'email')
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }

    public function save(PartyAccount $account): void
    {
        $this->unitOfWork->persist($account);
    }

    public function delete(PartyAccount $account): void
    {
        // Commit (flush) is the caller's responsibility.
    }
}
