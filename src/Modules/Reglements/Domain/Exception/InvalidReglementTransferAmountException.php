<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidReglementTransferAmountException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_transfer.invalid_amount';
    }

    public static function amountMustBePositive(int $amountMinor): self
    {
        return new self(
            'Transfer amount_minor must be greater than 0.',
            ['amount_minor' => $amountMinor],
        );
    }
}
