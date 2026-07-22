<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\ValueObject;

use App\Modules\Booking\Domain\Exception\InvalidSettlementRateException;
use App\Modules\Booking\Domain\ValueObject\SettlementRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettlementRateTest extends TestCase
{
    #[Test]
    #[DataProvider('validRates')]
    public function accepts_numeric_6_3_formats(string $raw): void
    {
        self::assertSame($raw, SettlementRate::fromString($raw)->toString());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validRates(): iterable
    {
        yield 'integer percent' => ['50'];
        yield 'with scale' => ['50.500'];
        yield 'fraction' => ['0.125'];
        yield 'max' => ['999.999'];
    }

    #[Test]
    #[DataProvider('invalidRates')]
    public function rejects_invalid_or_out_of_precision(string $raw): void
    {
        $this->expectException(InvalidSettlementRateException::class);

        SettlementRate::fromString($raw);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidRates(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'too many decimals' => ['1.1234'];
        yield 'overflow integer' => ['1000'];
        yield 'exchange-scale too big' => ['1.123456'];
        yield 'letters' => ['abc'];
    }
}
