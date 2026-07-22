<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Nom de groupe déjà utilisé pour ce group_type_code.
 */
final class PartyAccountGroupNameAlreadyUsedException extends DomainException
{
    public function errorCode(): string
    {
        return 'party_account_group.name_already_used';
    }

    public static function forTypeAndName(string $groupTypeCode, string $name): self
    {
        return new self(
            sprintf(
                'A party account group named "%s" already exists for type "%s".',
                $name,
                $groupTypeCode,
            ),
            [
                'group_type_code' => $groupTypeCode,
                'name' => $name,
            ],
        );
    }
}
