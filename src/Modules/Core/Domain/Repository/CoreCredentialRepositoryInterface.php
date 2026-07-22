<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Repository;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;

interface CoreCredentialRepositoryInterface
{
    public function findById(int $id): ?CoreCredential;

    public function findByProviderIdentity(
        CredentialProvider $provider,
        string $providerUserId,
    ): ?CoreCredential;

    /**
     * Credentials actifs (enabled, non soft-deleted) pour un compte.
     * Un compte peut en avoir plusieurs (multi-provider).
     *
     * @return list<CoreCredential>
     */
    public function findActiveByAccountId(int $accountId): array;

    public function save(CoreCredential $credential): void;
}
