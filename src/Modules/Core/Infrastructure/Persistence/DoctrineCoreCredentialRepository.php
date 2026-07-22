<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Persistence;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class DoctrineCoreCredentialRepository implements CoreCredentialRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function findById(int $id): ?CoreCredential
    {
        return $this->unitOfWork->find(CoreCredential::class, $id);
    }

    public function findByProviderIdentity(
        CredentialProvider $provider,
        string $providerUserId,
    ): ?CoreCredential {
        /** @var CoreCredential|null $credential */
        $credential = $this->unitOfWork->createQueryBuilder()
            ->select('credential')
            ->from(CoreCredential::class, 'credential')
            ->andWhere('credential.provider = :provider')
            ->andWhere('credential.providerUserId = :providerUserId')
            ->setParameter('provider', $provider)
            ->setParameter('providerUserId', $providerUserId)
            ->getQuery()
            ->getOneOrNullResult();

        return $credential;
    }

    public function findActiveByAccountId(int $accountId): array
    {
        /** @var list<CoreCredential> $credentials */
        $credentials = $this->unitOfWork->createQueryBuilder()
            ->select('credential')
            ->from(CoreCredential::class, 'credential')
            ->andWhere('credential.accountId = :accountId')
            ->andWhere('credential.isEnabled = true')
            ->setParameter('accountId', $accountId)
            ->orderBy('credential.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $credentials;
    }

    public function save(CoreCredential $credential): void
    {
        $this->unitOfWork->persist($credential);
    }
}
