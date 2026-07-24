<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidSettlementMatchingException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_matching.invalid';
    }

    public static function amountMustBePositive(int $amountMinor): self
    {
        return new self(
            'Matched amount_minor must be greater than 0.',
            ['matched_amount_minor' => $amountMinor],
        );
    }

    public static function entriesMustBeDistinct(): self
    {
        return new self(
            'Debit and credit ledger entries must be distinct.',
            [],
        );
    }

    public static function alreadyUnmatched(int $id): self
    {
        return new self(
            sprintf('Matching %d is already unmatched.', $id),
            ['id' => $id],
        );
    }
}
