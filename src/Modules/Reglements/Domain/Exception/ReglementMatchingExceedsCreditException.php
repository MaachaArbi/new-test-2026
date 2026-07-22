<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ReglementMatchingExceedsCreditException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_matching.exceeds_credit';
    }

    public static function forCredit(
        int $creditEntryId,
        int $creditCapacity,
        int $alreadyMatched,
        int $requested,
    ): self {
        return new self(
            'Matching would exceed remaining credit entry capacity.',
            [
                'credit_entry_id' => $creditEntryId,
                'credit_capacity' => $creditCapacity,
                'already_matched' => $alreadyMatched,
                'requested' => $requested,
            ],
        );
    }
}
