<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Violations d'invariants d'état sur l'agrégat CoreCredential.
 */
final class InvalidCoreCredentialStateException extends DomainException
{
    public function errorCode(): string
    {
        return 'core_credential.oauth_provider_cannot_be_local';
    }

    public static function oauthProviderCannotBeLocal(int $accountId): self
    {
        return new self(
            'createOAuth() cannot be called with CredentialProvider::Local; use createLocal() instead.',
            [
                'account_id' => $accountId,
                'provider' => 'local',
            ],
        );
    }
}
