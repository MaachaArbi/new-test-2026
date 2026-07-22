<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\ListBookings;

use App\Shared\Application\ListPagination;

/**
 * Query de liste paginée booking (lecture — ADR-003 / DBAL).
 */
final readonly class ListBookingsQuery
{
    public function __construct(
        public int $page = ListPagination::DEFAULT_PAGE,
        public int $limit = ListPagination::DEFAULT_LIMIT,
        public ?int $folderId = null,
        public ?int $customerAccountId = null,
        public ?string $serviceTypeCode = null,
        public ?string $statusCode = null,
        public ?string $bookingDateFrom = null,
        public ?string $bookingDateTo = null,
    ) {
    }
}
