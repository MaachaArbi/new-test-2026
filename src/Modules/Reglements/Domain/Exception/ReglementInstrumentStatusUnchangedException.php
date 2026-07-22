<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Transition vers le statut déjà actuel — refusée (pas de no-op silencieux).
 */
final class ReglementInstrumentStatusUnchangedException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_instrument.status_unchanged';
    }

    public static function forStatus(string $statusCode): self
    {
        return new self(
            sprintf('Reglement instrument is already in status "%s".', $statusCode),
            ['status_code' => $statusCode],
        );
    }
}
