<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Security;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Party\Domain\Entity\PartyAccount;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapter Infrastructure : enveloppe Domain pour Symfony Security / Lexik JWT.
 *
 * Ne pas faire hériter CoreCredential de UserInterface (ADR-002 / pureté Domain).
 */
final class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $email
     */
    private function __construct(
        private readonly string $email,
        private readonly string $passwordHash,
        private readonly int $accountId,
        private readonly string $publicId,
        private readonly int $credentialId,
    ) {
    }

    public static function fromLocalCredential(
        PartyAccount $account,
        CoreCredential $credential,
    ): self {
        $email = $account->email();
        $accountId = $account->id();
        $credentialId = $credential->id();
        $passwordHash = $credential->passwordHash();
        $emailValue = $email?->toString();

        if (
            $emailValue === null
            || $emailValue === ''
            || $accountId === null
            || $credentialId === null
            || $passwordHash === null
        ) {
            throw new \InvalidArgumentException(
                'SecurityUser requires account email/id, credential id and password hash.',
            );
        }

        return new self(
            email: $emailValue,
            passwordHash: $passwordHash,
            accountId: $accountId,
            publicId: $account->publicId()->toString(),
            credentialId: $credentialId,
        );
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function publicId(): string
    {
        return $this->publicId;
    }

    public function credentialId(): int
    {
        return $this->credentialId;
    }
}
