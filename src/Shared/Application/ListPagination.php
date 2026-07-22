<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Bornes et méta de pagination liste (ADR-003) — partagé Party / Booking
 * pour éviter la duplication phpcpd des Result/Query.
 */
final class ListPagination
{
    public const DEFAULT_PAGE = 1;

    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 100;

    public static function totalPages(int $total, int $limit): int
    {
        if ($limit < 1) {
            return 0;
        }

        return (int) ceil($total / $limit);
    }

    /**
     * @return array{page: int, limit: int, total: int, totalPages: int}
     */
    public static function meta(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => self::totalPages($total, $limit),
        ];
    }
}
