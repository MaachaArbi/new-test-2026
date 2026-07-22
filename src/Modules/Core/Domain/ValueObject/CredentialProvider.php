<?php

declare(strict_types=1);

namespace App\Modules\Core\Domain\ValueObject;

/**
 * Providers d'authentification supportés (core_credential.provider).
 *
 * Enum PHP volontaire (pas un VO string ouvert) : chaque nouveau provider
 * OAuth exige du code neuf (config, redirection) — ce n'est pas un
 * référentiel de pure donnée.
 */
enum CredentialProvider: string
{
    case Local = 'local';
    case Google = 'google';
    case Facebook = 'facebook';
    case ApiKey = 'api_key';
    case SsoInterne = 'sso_interne';
}
