<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Controller;

use App\Modules\Booking\Application\ListBookings\ListBookingsHandler;
use App\Modules\Booking\Application\ListBookings\ListBookingsQuery;
use App\Shared\Infrastructure\Http\ListPaginationRequestSupport;
use App\Shared\Infrastructure\Http\ValidationFailedJsonResponseFactory;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/v1/bookings — liste paginée (DBAL / ADR-003).
 */
final class ListBookingsController
{
    public function __construct(
        private readonly ListBookingsHandler $listBookingsHandler,
        private readonly ValidationFailedJsonResponseFactory $validationFailedJsonResponseFactory,
        private readonly CorrelationIdHolder $correlationIdHolder,
    ) {
    }

    #[Route(
        path: '/api/v1/bookings',
        name: 'api_v1_bookings_list',
        methods: ['GET'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $violations = [];
        $pagination = ListPaginationRequestSupport::parsePageAndLimit($request, $violations);

        $folderId = $this->optionalPositiveInt($request, 'folderId', $violations);
        $customerAccountId = $this->optionalPositiveInt($request, 'customerAccountId', $violations);
        $serviceTypeCode = $this->optionalNonEmptyString($request, 'serviceTypeCode');
        $statusCode = $this->optionalNonEmptyString($request, 'statusCode');
        $bookingDateFrom = $this->optionalDateYmd($request, 'bookingDateFrom', $violations);
        $bookingDateTo = $this->optionalDateYmd($request, 'bookingDateTo', $violations);

        if ($violations !== []) {
            return $this->validationFailedJsonResponseFactory->create($violations);
        }

        $result = ($this->listBookingsHandler)(new ListBookingsQuery(
            page: $pagination['page'],
            limit: $pagination['limit'],
            folderId: $folderId,
            customerAccountId: $customerAccountId,
            serviceTypeCode: $serviceTypeCode,
            statusCode: $statusCode,
            bookingDateFrom: $bookingDateFrom,
            bookingDateTo: $bookingDateTo,
        ));

        $response = new JsonResponse($result->toArray(), Response::HTTP_OK);
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    private function optionalPositiveInt(Request $request, string $field, array &$violations): ?int
    {
        $raw = $request->query->get($field);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw) || (string) (int) $raw !== (string) $raw || (int) $raw < 1) {
            $violations[] = [
                'field' => $field,
                'message' => $field.' must be a positive integer.',
            ];

            return null;
        }

        return (int) $raw;
    }

    private function optionalNonEmptyString(Request $request, string $field): ?string
    {
        $raw = $request->query->get($field);
        if ($raw === null || $raw === '') {
            return null;
        }

        return (string) $raw;
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    private function optionalDateYmd(Request $request, string $field, array &$violations): ?string
    {
        $raw = $request->query->get($field);
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (string) $raw;
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            $violations[] = [
                'field' => $field,
                'message' => $field.' must be a valid date (Y-m-d).',
            ];

            return null;
        }

        return $value;
    }
}
