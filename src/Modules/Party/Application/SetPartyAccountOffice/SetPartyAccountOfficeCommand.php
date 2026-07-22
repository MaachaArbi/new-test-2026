<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\SetPartyAccountOffice;

/**
 * Pose l'extension office d'un compte organisation.
 */
final readonly class SetPartyAccountOfficeCommand
{
    public function __construct(
        public int $accountId,
        public string $officeCode,
        public string $defaultCurrencyCode,
    ) {
    }
}
