<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\ListPartyAccounts;

use App\Shared\Application\ListPagination;

/**
 * Query de liste paginée party_account (lecture — ADR-003 / DBAL).
 */
final readonly class ListPartyAccountsQuery
{
    public function __construct(
        public int $page = ListPagination::DEFAULT_PAGE,
        public int $limit = ListPagination::DEFAULT_LIMIT,
        public ?string $nature = null,
        public ?string $search = null,
    ) {
    }
}
