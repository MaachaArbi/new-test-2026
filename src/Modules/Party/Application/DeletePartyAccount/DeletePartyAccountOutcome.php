<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\DeletePartyAccount;

/**
 * Résultat DELETE soft — distingue premier soft-delete et rappel idempotent.
 */
enum DeletePartyAccountOutcome
{
    case SoftDeleted;
    case AlreadyDeleted;
}
