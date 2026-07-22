<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\BookingCancellationTier;
use App\Modules\Booking\Domain\Exception\InvalidBookingCancellationTierException;
use App\Modules\Booking\Domain\ValueObject\PenaltyType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingCancellationTierTest extends TestCase
{
    #[Test]
    public function free_rejects_non_null_penalty_value(): void
    {
        try {
            BookingCancellationTier::create(
                policyId: 1,
                daysBeforeStart: 30,
                penaltyType: PenaltyType::Free,
                penaltyValue: '0',
            );
            self::fail('Expected InvalidBookingCancellationTierException');
        } catch (InvalidBookingCancellationTierException $exception) {
            self::assertSame('booking_cancellation_tier.invalid_penalty', $exception->errorCode());
        }
    }

    #[Test]
    public function percentage_rejects_value_above_100(): void
    {
        try {
            BookingCancellationTier::create(
                policyId: 1,
                daysBeforeStart: 7,
                penaltyType: PenaltyType::Percentage,
                penaltyValue: '100.001',
            );
            self::fail('Expected InvalidBookingCancellationTierException');
        } catch (InvalidBookingCancellationTierException $exception) {
            self::assertSame('booking_cancellation_tier.invalid_penalty', $exception->errorCode());
        }
    }

    #[Test]
    public function percentage_allows_zero_and_hundred(): void
    {
        $zero = BookingCancellationTier::create(1, 30, PenaltyType::Percentage, '0');
        $hundred = BookingCancellationTier::create(1, 0, PenaltyType::Percentage, '100');

        self::assertSame('0', $zero->penaltyValue());
        self::assertSame('100', $hundred->penaltyValue());
    }

    #[Test]
    public function free_allows_null_value(): void
    {
        $tier = BookingCancellationTier::create(1, 30, PenaltyType::Free);

        self::assertSame(PenaltyType::Free, $tier->penaltyType());
        self::assertNull($tier->penaltyValue());
    }
}
