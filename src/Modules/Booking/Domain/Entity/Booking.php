<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Exception\BookingStatusUnchangedException;
use App\Modules\Booking\Domain\Exception\InvalidBookingStateException;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\Exception\CurrencyMismatchException;
use App\Shared\Domain\ValueObject\ExchangeRate;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Agrégat pivot booking — montants/devises + mutations workflow (flags).
 *
 * PK SQL composite (id, booking_date). bookingDate est stocké en string Y-m-d
 * (pas DateTimeImmutable en propriété id) : Doctrine UnitOfWork exige des
 * identifiants string/int pour le hash d'identité.
 *
 * Money : montants stockés en int (unités mineures) + codes devise séparés ;
 * les getters reconstruisent le VO Money. Pas de Type Doctrine composite.
 *
 * Workflow : aucune contrainte croisée avec status_code (décision #3 —
 * is_on_request indépendant ; status_code non mutable ici).
 */
final class Booking
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private string $bookingDate,
        private int $folderId,
        private BookingServiceTypeCode $serviceTypeCode,
        private BookingStatusCode $statusCode,
        private int $customerAccountId,
        private ?int $supplierAccountId,
        private int $officeAccountId,
        private DateTimeImmutable $startDate,
        private ?DateTimeImmutable $endDate,
        private BookingChannelCode $channelCode,
        private string $achatCurrencyCode,
        private string $venteCurrencyCode,
        private ExchangeRate $achatExchangeRate,
        private ExchangeRate $venteExchangeRate,
        private int $totalAchatAmount,
        private int $totalVenteAmount,
        private int $margeAgenceAmount,
        private int $margeDistributeurAmount,
        private int $paidAmount,
        private PaymentStatus $paymentStatus,
        private bool $isOnRequest,
        private ?int $assignedAgentAccountId,
        private ?DateTimeImmutable $assignedAt,
        private bool $isLocked,
        private bool $isDisputed,
        private ?string $supplierStatusLabel,
    ) {
    }

    public static function create(
        int $folderId,
        BookingServiceTypeCode $serviceTypeCode,
        BookingStatusCode $statusCode,
        int $customerAccountId,
        ?int $supplierAccountId,
        int $officeAccountId,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $endDate,
        BookingChannelCode $channelCode,
        string $achatCurrencyCode,
        string $venteCurrencyCode,
        ExchangeRate $achatExchangeRate,
        ExchangeRate $venteExchangeRate,
        Money $totalAchatAmount,
        Money $totalVenteAmount,
        Money $margeAgenceAmount,
        Money $margeDistributeurAmount,
        Money $paidAmount,
        PaymentStatus $paymentStatus,
    ): self {
        if ($endDate !== null && $endDate->format('Y-m-d') < $startDate->format('Y-m-d')) {
            throw InvalidBookingStateException::endDateBeforeStartDate($startDate, $endDate);
        }

        $achat = strtoupper(trim($achatCurrencyCode));
        $vente = strtoupper(trim($venteCurrencyCode));

        self::assertMoneyCurrency('totalAchatAmount', $totalAchatAmount, $achat);
        self::assertMoneyCurrency('totalVenteAmount', $totalVenteAmount, $vente);
        self::assertMoneyCurrency('margeAgenceAmount', $margeAgenceAmount, $vente);
        self::assertMoneyCurrency('margeDistributeurAmount', $margeDistributeurAmount, $vente);
        self::assertMoneyCurrency('paidAmount', $paidAmount, $vente);

        return new self(
            id: null,
            publicId: PublicId::generate(),
            bookingDate: (new DateTimeImmutable('today'))->format('Y-m-d'),
            folderId: $folderId,
            serviceTypeCode: $serviceTypeCode,
            statusCode: $statusCode,
            customerAccountId: $customerAccountId,
            supplierAccountId: $supplierAccountId,
            officeAccountId: $officeAccountId,
            startDate: $startDate,
            endDate: $endDate,
            channelCode: $channelCode,
            achatCurrencyCode: $achat,
            venteCurrencyCode: $vente,
            achatExchangeRate: $achatExchangeRate,
            venteExchangeRate: $venteExchangeRate,
            totalAchatAmount: $totalAchatAmount->amount(),
            totalVenteAmount: $totalVenteAmount->amount(),
            margeAgenceAmount: $margeAgenceAmount->amount(),
            margeDistributeurAmount: $margeDistributeurAmount->amount(),
            paidAmount: $paidAmount->amount(),
            paymentStatus: $paymentStatus,
            isOnRequest: false,
            assignedAgentAccountId: null,
            assignedAt: null,
            isLocked: false,
            isDisputed: false,
            supplierStatusLabel: null,
        );
    }

    private static function assertMoneyCurrency(string $field, Money $money, string $expected): void
    {
        if ($money->currencyCode() !== $expected) {
            throw CurrencyMismatchException::amountDoesNotMatchExpected(
                $field,
                $expected,
                $money->currencyCode(),
            );
        }
    }

    /**
     * Remplace les totaux (règle SUM booking_charge — Application).
     * Les marges ne sont pas recalculées ici.
     */
    public function recalculateTotals(Money $totalAchatAmount, Money $totalVenteAmount): void
    {
        self::assertMoneyCurrency('totalAchatAmount', $totalAchatAmount, $this->achatCurrencyCode);
        self::assertMoneyCurrency('totalVenteAmount', $totalVenteAmount, $this->venteCurrencyCode);

        $this->totalAchatAmount = $totalAchatAmount->amount();
        $this->totalVenteAmount = $totalVenteAmount->amount();
    }

    public function markAsOnRequest(): void
    {
        $this->isOnRequest = true;
    }

    public function clearOnRequest(): void
    {
        $this->isOnRequest = false;
    }

    /**
     * Assigne (ou réassigne) un agent. Une réassignation est un cas normal —
     * pas d'erreur si déjà assigné.
     */
    public function assignToAgent(int $agentAccountId): void
    {
        $this->assignedAgentAccountId = $agentAccountId;
        $this->assignedAt = new DateTimeImmutable('now');
    }

    public function unassign(): void
    {
        $this->assignedAgentAccountId = null;
        $this->assignedAt = null;
    }

    public function lock(): void
    {
        $this->isLocked = true;
    }

    public function unlock(): void
    {
        $this->isLocked = false;
    }

    public function markAsDisputed(): void
    {
        $this->isDisputed = true;
    }

    public function clearDispute(): void
    {
        $this->isDisputed = false;
    }

    public function updateSupplierStatusLabel(?string $label): void
    {
        $this->supplierStatusLabel = $label;
    }

    /**
     * Change le statut métier. Aucune matrice de transitions : tout statut
     * peut mener à tout autre (y compris depuis un statut final). Seule
     * règle : refuser une « transition » vers le statut déjà actuel.
     */
    public function transitionTo(BookingStatusCode $newStatus): void
    {
        if ($this->statusCode->toString() === $newStatus->toString()) {
            throw BookingStatusUnchangedException::forStatus($newStatus->toString());
        }

        $this->statusCode = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function bookingDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->bookingDate);
    }

    public function folderId(): int
    {
        return $this->folderId;
    }

    public function serviceTypeCode(): BookingServiceTypeCode
    {
        return $this->serviceTypeCode;
    }

    public function statusCode(): BookingStatusCode
    {
        return $this->statusCode;
    }

    public function customerAccountId(): int
    {
        return $this->customerAccountId;
    }

    public function supplierAccountId(): ?int
    {
        return $this->supplierAccountId;
    }

    public function officeAccountId(): int
    {
        return $this->officeAccountId;
    }

    public function startDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function endDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function channelCode(): BookingChannelCode
    {
        return $this->channelCode;
    }

    public function achatCurrencyCode(): string
    {
        return $this->achatCurrencyCode;
    }

    public function venteCurrencyCode(): string
    {
        return $this->venteCurrencyCode;
    }

    public function achatExchangeRate(): ExchangeRate
    {
        return $this->achatExchangeRate;
    }

    public function venteExchangeRate(): ExchangeRate
    {
        return $this->venteExchangeRate;
    }

    public function totalAchatAmount(): Money
    {
        return Money::fromMinorUnits($this->totalAchatAmount, $this->achatCurrencyCode);
    }

    public function totalVenteAmount(): Money
    {
        return Money::fromMinorUnits($this->totalVenteAmount, $this->venteCurrencyCode);
    }

    public function margeAgenceAmount(): Money
    {
        return Money::fromMinorUnits($this->margeAgenceAmount, $this->venteCurrencyCode);
    }

    public function margeDistributeurAmount(): Money
    {
        return Money::fromMinorUnits($this->margeDistributeurAmount, $this->venteCurrencyCode);
    }

    public function paidAmount(): Money
    {
        return Money::fromMinorUnits($this->paidAmount, $this->venteCurrencyCode);
    }

    public function paymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function isOnRequest(): bool
    {
        return $this->isOnRequest;
    }

    public function assignedAgentAccountId(): ?int
    {
        return $this->assignedAgentAccountId;
    }

    public function assignedAt(): ?DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function isDisputed(): bool
    {
        return $this->isDisputed;
    }

    public function supplierStatusLabel(): ?string
    {
        return $this->supplierStatusLabel;
    }
}
