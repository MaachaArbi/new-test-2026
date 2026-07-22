<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Entity;

use App\Modules\Core\Domain\Exception\InvalidCoreCredentialStateException;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use DateTimeImmutable;

/**
 * Agrégat racine CoreCredential (table core_credential V1.1).
 *
 * Invariants imposés par construction via factories :
 * - Local  => password_hash renseigné, provider_user_id NULL
 * - OAuth  => provider_user_id renseigné, password_hash NULL, provider != Local
 *
 * Le Domain ne hash jamais : passwordHash est une string opaque déjà hashée
 * en amont (Application / PasswordHasherInterface).
 *
 * Hors périmètre V1.1 : session, MFA, tentatives de login, soft delete.
 */
final class CoreCredential
{
    private function __construct(
        private ?int $id,
        private int $accountId,
        private CredentialProvider $provider,
        private ?string $providerUserId,
        private ?string $passwordHash,
        private bool $isPrimary,
        private bool $isEnabled,
        private ?DateTimeImmutable $lastLoginAt,
    ) {
    }

    public static function createLocal(
        int $accountId,
        string $passwordHash,
        bool $isPrimary,
    ): self {
        return new self(
            id: null,
            accountId: $accountId,
            provider: CredentialProvider::Local,
            providerUserId: null,
            passwordHash: $passwordHash,
            isPrimary: $isPrimary,
            isEnabled: true,
            lastLoginAt: null,
        );
    }

    public static function createOAuth(
        int $accountId,
        CredentialProvider $provider,
        string $providerUserId,
        bool $isPrimary,
    ): self {
        if (CredentialProvider::Local === $provider) {
            throw InvalidCoreCredentialStateException::oauthProviderCannotBeLocal($accountId);
        }

        return new self(
            id: null,
            accountId: $accountId,
            provider: $provider,
            providerUserId: $providerUserId,
            passwordHash: null,
            isPrimary: $isPrimary,
            isEnabled: true,
            lastLoginAt: null,
        );
    }

    public function disable(): void
    {
        $this->isEnabled = false;
    }

    public function enable(): void
    {
        $this->isEnabled = true;
    }

    public function markAsPrimary(): void
    {
        $this->isPrimary = true;
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function provider(): CredentialProvider
    {
        return $this->provider;
    }

    public function providerUserId(): ?string
    {
        return $this->providerUserId;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }
}
