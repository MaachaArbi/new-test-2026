<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\ListPagination;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validation query page/limit pour listes paginées (anti-phpcpd Party/Booking).
 */
final class ListPaginationRequestSupport
{
    /**
     * @param list<array{field: string, message: string}> $violations
     *
     * @return array{page: int, limit: int}
     */
    public static function parsePageAndLimit(Request $request, array &$violations): array
    {
        $pageRaw = $request->query->get('page', (string) ListPagination::DEFAULT_PAGE);
        $limitRaw = $request->query->get('limit', (string) ListPagination::DEFAULT_LIMIT);

        if (!is_numeric($pageRaw) || (string) (int) $pageRaw !== (string) $pageRaw || (int) $pageRaw < 1) {
            $violations[] = ['field' => 'page', 'message' => 'page must be a positive integer.'];
            $page = ListPagination::DEFAULT_PAGE;
        } else {
            $page = (int) $pageRaw;
        }

        if (!is_numeric($limitRaw) || (string) (int) $limitRaw !== (string) $limitRaw || (int) $limitRaw < 1) {
            $violations[] = ['field' => 'limit', 'message' => 'limit must be a positive integer.'];
            $limit = ListPagination::DEFAULT_LIMIT;
        } else {
            $limit = (int) $limitRaw;
            if ($limit > ListPagination::MAX_LIMIT) {
                // Comportement explicite : rejet 422 (pas de plafonnage silencieux).
                $violations[] = [
                    'field' => 'limit',
                    'message' => sprintf('limit must not exceed %d.', ListPagination::MAX_LIMIT),
                ];
            }
        }

        return ['page' => $page, 'limit' => $limit];
    }
}
