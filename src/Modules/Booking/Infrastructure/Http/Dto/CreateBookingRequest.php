<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/bookings — validation d'input (pas de règle métier Domain).
 */
final class CreateBookingRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $folderId = null;

    #[Assert\NotBlank]
    public mixed $serviceTypeCode = null;

    #[Assert\NotBlank]
    public mixed $statusCode = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $customerAccountId = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $supplierAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $officeAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public mixed $startDate = null;

    #[Assert\Date]
    public mixed $endDate = null;

    #[Assert\NotBlank]
    public mixed $channelCode = null;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    public mixed $achatCurrencyCode = null;

    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    public mixed $venteCurrencyCode = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public mixed $achatExchangeRate = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public mixed $venteExchangeRate = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $totalAchatAmount = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $totalVenteAmount = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $margeAgenceAmount = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $margeDistributeurAmount = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $paidAmount = null;

    #[Assert\Type('string')]
    public mixed $paymentStatus = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->folderId = $data['folderId'] ?? null;
        $request->serviceTypeCode = $data['serviceTypeCode'] ?? null;
        $request->statusCode = $data['statusCode'] ?? null;
        $request->customerAccountId = $data['customerAccountId'] ?? null;
        $request->supplierAccountId = $data['supplierAccountId'] ?? null;
        $request->officeAccountId = $data['officeAccountId'] ?? null;
        $request->startDate = $data['startDate'] ?? null;

        $endDate = $data['endDate'] ?? null;
        $request->endDate = $endDate === '' ? null : $endDate;

        $request->channelCode = $data['channelCode'] ?? null;
        $request->achatCurrencyCode = $data['achatCurrencyCode'] ?? null;
        $request->venteCurrencyCode = $data['venteCurrencyCode'] ?? null;
        $request->achatExchangeRate = $data['achatExchangeRate'] ?? null;
        $request->venteExchangeRate = $data['venteExchangeRate'] ?? null;
        $request->totalAchatAmount = $data['totalAchatAmount'] ?? null;
        $request->totalVenteAmount = $data['totalVenteAmount'] ?? null;
        $request->margeAgenceAmount = $data['margeAgenceAmount'] ?? null;
        $request->margeDistributeurAmount = $data['margeDistributeurAmount'] ?? null;
        $request->paidAmount = $data['paidAmount'] ?? null;

        $paymentStatus = $data['paymentStatus'] ?? null;
        $request->paymentStatus = $paymentStatus === '' ? null : $paymentStatus;

        return $request;
    }
}
