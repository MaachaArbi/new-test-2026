<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ReglementEntryTypeNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'reglement_entry_type.not_found';
    }

    public static function forCode(string $code): self
    {
        return new self(
            sprintf('Reglement entry type "%s" was not found.', $code),
            ['code' => $code],
        );
    }
}
