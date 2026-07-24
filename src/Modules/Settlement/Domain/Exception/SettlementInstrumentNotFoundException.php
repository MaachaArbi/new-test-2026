<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class SettlementInstrumentNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'settlement_instrument.not_found';
    }

    public static function forId(int $id): self
    {
        return new self(
            sprintf('Settlement instrument with id %d was not found.', $id),
            ['id' => $id],
        );
    }

    public static function forPublicId(string $publicId): self
    {
        return new self(
            sprintf('Settlement instrument with public_id "%s" was not found.', $publicId),
            ['public_id' => $publicId],
        );
    }
}
