<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\DomainException;
use App\Shared\Infrastructure\Logging\CorrelationIdHolder;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Transforme les exceptions en réponses JSON API (DomainException traduites).
 */
final class ExceptionListener implements EventSubscriberInterface
{
    /** @var list<string> */
    private const NOT_FOUND_ERROR_CODES = [
        'party_account.not_found',
        'party_account_role.assignment_not_found',
        'party_account_function.assignment_not_found',
        'party_account_group_member.membership_not_found',
        'booking.not_found',
        'booking_cancellation_policy.not_found',
        'reglement_instrument.not_found',
        'reglement_matching.not_found',
        'reglement_ledger_entry.not_found',
        'reglement_entry_type.not_found',
    ];

    /** @var list<string> */
    private const CONFLICT_ERROR_CODES = [
        'party_account_role.already_active',
        'party_account_function.already_active',
        'party_account_group_member.already_active',
        'party_account_office.code_already_used',
        'party_account_group.name_already_used',
        // Même sémantique Party AlreadyActive : état conflictuel (1 pax leader / booking).
        'booking_traveler.pax_leader_already_set',
        // Extension incompatible avec le service_type courant du booking (état ressource).
        'booking.service_type_mismatch',
        'booking_cancellation_policy.already_exists',
        'booking_settlement.already_active',
        'booking_payer_split.already_active',
        'reglement_instrument.status_unchanged',
        // État ressource incompatible avec l'action (même sémantique que
        // booking.service_type_mismatch).
        'reglement_instrument.not_active',
    ];

    /** @var list<string> */
    private const UNPROCESSABLE_ERROR_CODES = [
        'booking.unknown_service_type',
        'booking.unknown_status',
        'booking.unknown_channel',
        'booking.unknown_currency',
        'booking.unknown_charge_type',
        'booking_cancellation_policy.room_mismatch',
        'booking_cancellation_tier.invalid_penalty',
        'booking_charge.traveler_mismatch',
        'booking_charge.segment_mismatch',
        // Contenu requête / état existant — pas une transition de statut.
        'booking_payer_split.exceeds_total',
        'booking_payer_split.currency_mismatch',
        'booking_settlement.invalid_rate',
        'booking_settlement.invalid',
        'money.invalid_currency_code',
        'reglement.unknown_currency',
        'reglement_instrument.invalid_amount',
        'reglement_instrument.invalid_party_role',
        'reglement_instrument.invalid_status',
        'reglement_payment_method.inactive_or_unknown',
        'reglement_ledger_entry.invalid',
        'reglement_matching.invalid',
        'reglement_matching.book_mismatch',
        'reglement_matching.exceeds_credit',
        'reglement_matching.exceeds_debit',
        'reglement_transfer.invalid_amount',
        'reglement_transfer.invalid_party_role',
        'reglement_transfer.posting_failed',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CorrelationIdHolder $correlationIdHolder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Avant ErrorListener (-128) pour remplacer la réponse HTML/debug.
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();

        // Laisser Symfony Security / Lexik produire les 401/403 JSON.
        if ($throwable instanceof AuthenticationException || $throwable instanceof AccessDeniedException) {
            return;
        }

        if ($throwable instanceof DomainException) {
            $event->setResponse($this->domainExceptionResponse($event, $throwable));

            return;
        }

        $this->logger->error('Unhandled exception', [
            'exception' => $throwable,
        ]);

        $response = new JsonResponse(
            [
                'error' => [
                    'code' => 'internal_error',
                    'message' => 'An unexpected error occurred.',
                ],
            ],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );
        $event->setResponse($response);
    }

    private function domainExceptionResponse(ExceptionEvent $event, DomainException $exception): JsonResponse
    {
        $locale = $this->resolveLocale($event);
        $message = $this->translator->trans(
            $exception->errorCode(),
            [],
            'errors',
            $locale,
        );

        $response = new JsonResponse(
            [
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $message,
                    'context' => $exception->context(),
                ],
            ],
            $this->statusFor($exception),
        );
        $response->headers->set(
            RequestIdSubscriber::HEADER_NAME,
            $this->correlationIdHolder->get(),
        );

        return $response;
    }

    private function statusFor(DomainException $exception): int
    {
        $code = $exception->errorCode();

        if (in_array($code, self::NOT_FOUND_ERROR_CODES, true)) {
            return Response::HTTP_NOT_FOUND;
        }

        if (in_array($code, self::CONFLICT_ERROR_CODES, true)) {
            return Response::HTTP_CONFLICT;
        }

        if (in_array($code, self::UNPROCESSABLE_ERROR_CODES, true)) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_BAD_REQUEST;
    }

    private function resolveLocale(ExceptionEvent $event): string
    {
        $header = $event->getRequest()->headers->get('Accept-Language');
        if ($header === null || $header === '') {
            return 'en';
        }

        $preferred = AcceptHeader::fromString($header)->first();
        if ($preferred === null) {
            return 'en';
        }

        $value = strtolower($preferred->getValue());
        $primary = explode('-', $value)[0];

        return in_array($primary, ['en', 'fr', 'ar'], true) ? $primary : 'en';
    }
}
