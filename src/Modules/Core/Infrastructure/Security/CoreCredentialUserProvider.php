<?php

declare(strict_types=1);

namespace App\Modules\Core\Infrastructure\Security;

use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\ValueObject\CredentialProvider;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\Exception\InvalidEmailException;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Résout un SecurityUser pour le login local : email PartyAccount → CoreCredential local.
 *
 * L'identifiant Symfony n'est PAS provider_user_id (NULL en local) mais l'email du compte.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final class CoreCredentialUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly CoreCredentialRepositoryInterface $coreCredentialRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $email = Email::fromString($identifier);
        } catch (InvalidEmailException) {
            throw $this->notFound($identifier);
        }

        $account = $this->partyAccountRepository->findByEmail($email);
        if ($account === null || $account->id() === null || $account->isDisabled()) {
            throw $this->notFound($identifier);
        }

        $localCredential = null;
        foreach ($this->coreCredentialRepository->findActiveByAccountId((int) $account->id()) as $credential) {
            if (
                CredentialProvider::Local === $credential->provider()
                && $credential->passwordHash() !== null
            ) {
                $localCredential = $credential;
                break;
            }
        }

        if ($localCredential === null || $localCredential->id() === null) {
            throw $this->notFound($identifier);
        }

        return SecurityUser::fromLocalCredential($account, $localCredential);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf(
                'Unsupported user class "%s".',
                $user::class,
            ));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class || is_subclass_of($class, SecurityUser::class);
    }

    private function notFound(string $identifier): UserNotFoundException
    {
        $exception = new UserNotFoundException('User not found.');
        $exception->setUserIdentifier($identifier);

        return $exception;
    }
}
