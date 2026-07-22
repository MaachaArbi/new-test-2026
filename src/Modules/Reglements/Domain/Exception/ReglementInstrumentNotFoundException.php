<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ReglementInstrumentNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_instrument.not_found';
    }

    public static function forId(int $id): self
    {
        return new self(
            sprintf('Reglement instrument with id %d was not found.', $id),
            ['id' => $id],
        );
    }

    public static function forPublicId(string $publicId): self
    {
        return new self(
            sprintf('Reglement instrument with public_id "%s" was not found.', $publicId),
            ['public_id' => $publicId],
        );
    }
}
