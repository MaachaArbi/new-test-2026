<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\Exception\InvalidBookingChannelCodeException;
use App\Modules\Booking\Domain\Exception\InvalidBookingServiceTypeCodeException;
use App\Modules\Booking\Domain\Exception\InvalidBookingStatusCodeException;
use App\Modules\Booking\Domain\ValueObject\BookingChannelCode;
use App\Modules\Booking\Domain\ValueObject\BookingServiceTypeCode;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookingOpenReferentialCodesTest extends TestCase
{
    #[Test]
    public function service_type_code_accepts_and_rejects(): void
    {
        self::assertSame('hotel', BookingServiceTypeCode::fromString(' hotel ')->toString());

        try {
            BookingServiceTypeCode::fromString('');
            self::fail('Expected InvalidBookingServiceTypeCodeException');
        } catch (InvalidBookingServiceTypeCodeException $e) {
            self::assertSame('booking_service_type_code.invalid', $e->errorCode());
            self::assertSame(['reason' => 'empty'], $e->context());
        }

        try {
            BookingServiceTypeCode::fromString(str_repeat('x', 31));
            self::fail('Expected InvalidBookingServiceTypeCodeException');
        } catch (InvalidBookingServiceTypeCodeException $e) {
            self::assertSame('too_long', $e->context()['reason']);
        }
    }

    #[Test]
    public function status_code_accepts_and_rejects(): void
    {
        self::assertSame('draft', BookingStatusCode::fromString('draft')->toString());

        try {
            BookingStatusCode::fromString(' ');
            self::fail('Expected InvalidBookingStatusCodeException');
        } catch (InvalidBookingStatusCodeException $e) {
            self::assertSame('booking_status_code.invalid', $e->errorCode());
        }
    }

    #[Test]
    public function channel_code_accepts_and_rejects(): void
    {
        self::assertSame('backoffice', BookingChannelCode::fromString('backoffice')->toString());

        try {
            BookingChannelCode::fromString(str_repeat('c', 31));
            self::fail('Expected InvalidBookingChannelCodeException');
        } catch (InvalidBookingChannelCodeException $e) {
            self::assertSame('booking_channel_code.invalid', $e->errorCode());
            self::assertSame('too_long', $e->context()['reason']);
        }
    }
}
