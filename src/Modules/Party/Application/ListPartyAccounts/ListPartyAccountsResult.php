<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\ListPartyAccounts;

use App\Shared\Application\ListPagination;

/**
 * Résultat de liste — lignes brutes déjà au format API, pas d'agrégats Domain.
 */
final readonly class ListPartyAccountsResult
{
    /**
     * @param list<array{publicId: string, nature: string, displayName: string, email: string|null}> $data
     */
    public function __construct(
        public array $data,
        public int $page,
        public int $limit,
        public int $total,
    ) {
    }

    /**
     * @return array{
     *     data: list<array{publicId: string, nature: string, displayName: string, email: string|null}>,
     *     meta: array{page: int, limit: int, total: int, totalPages: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => ListPagination::meta($this->page, $this->limit, $this->total),
        ];
    }
}
