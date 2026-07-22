<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidExchangeRateException;
use App\Shared\Domain\ValueObject\ExchangeRate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExchangeRateTest extends TestCase
{
    #[Test]
    #[DataProvider('validRates')]
    public function accepts_valid_formats(string $raw): void
    {
        $rate = ExchangeRate::fromString($raw);

        self::assertSame($raw, $rate->toString());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validRates(): iterable
    {
        yield 'one' => ['1'];
        yield 'one with scale' => ['1.000000'];
        yield 'fraction' => ['0.123456'];
        yield 'large' => ['12345678.123456'];
    }

    #[Test]
    #[DataProvider('invalidRates')]
    public function rejects_invalid_formats(string $raw): void
    {
        $this->expectException(InvalidExchangeRateException::class);

        ExchangeRate::fromString($raw);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidRates(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'too many decimals' => ['1.1234567'];
        yield 'too many integer digits' => ['123456789'];
        yield 'letters' => ['abc'];
        yield 'floatish e' => ['1e2'];
    }
}
