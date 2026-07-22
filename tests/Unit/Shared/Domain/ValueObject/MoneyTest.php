<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\CurrencyMismatchException;
use App\Shared\Domain\Exception\InvalidCurrencyCodeException;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function add_same_currency_ok(): void
    {
        $sum = Money::fromMinorUnits(1000, 'TND')
            ->add(Money::fromMinorUnits(250, 'tnd'));

        self::assertSame(1250, $sum->amount());
        self::assertSame('TND', $sum->currencyCode());
    }

    #[Test]
    public function add_different_currencies_throws(): void
    {
        $this->expectException(CurrencyMismatchException::class);
        $this->expectExceptionMessage('EUR');

        Money::fromMinorUnits(100, 'TND')
            ->add(Money::fromMinorUnits(50, 'EUR'));
    }

    #[Test]
    public function rejects_invalid_currency_code(): void
    {
        $this->expectException(InvalidCurrencyCodeException::class);

        Money::fromMinorUnits(1, 'TN');
    }
}
