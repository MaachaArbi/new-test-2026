<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    #[Test]
    public function backed_values_match_schema_check(): void
    {
        self::assertSame('unpaid', PaymentStatus::Unpaid->value);
        self::assertSame('partial', PaymentStatus::Partial->value);
        self::assertSame('paid', PaymentStatus::Paid->value);
    }
}
