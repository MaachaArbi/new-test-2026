<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Settlement\Domain\Entity;

use App\Modules\Settlement\Domain\Entity\SettlementMatching;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementMatchingException;
use PHPUnit\Framework\TestCase;

final class SettlementMatchingTest extends TestCase
{
    public function test_match_rejects_non_positive_amount(): void
    {
        try {
            SettlementMatching::match(1, 2, 0);
            self::fail('Expected InvalidSettlementMatchingException');
        } catch (InvalidSettlementMatchingException $exception) {
            self::assertSame('settlement_matching.invalid', $exception->errorCode());
        }
    }

    public function test_match_rejects_same_entries(): void
    {
        try {
            SettlementMatching::match(5, 5, 100);
            self::fail('Expected InvalidSettlementMatchingException');
        } catch (InvalidSettlementMatchingException $exception) {
            self::assertSame('settlement_matching.invalid', $exception->errorCode());
        }
    }

    public function test_unmatch_rejects_double_unmatch(): void
    {
        $matching = SettlementMatching::match(1, 2, 50);
        $matching->unmatch(null);

        try {
            $matching->unmatch(null);
            self::fail('Expected InvalidSettlementMatchingException');
        } catch (InvalidSettlementMatchingException $exception) {
            self::assertSame('settlement_matching.invalid', $exception->errorCode());
        }
    }
}
