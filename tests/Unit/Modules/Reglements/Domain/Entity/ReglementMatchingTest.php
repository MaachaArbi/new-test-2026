<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Reglements\Domain\Entity;

use App\Modules\Reglements\Domain\Entity\ReglementMatching;
use App\Modules\Reglements\Domain\Exception\InvalidReglementMatchingException;
use PHPUnit\Framework\TestCase;

final class ReglementMatchingTest extends TestCase
{
    public function test_match_rejects_non_positive_amount(): void
    {
        try {
            ReglementMatching::match(1, 2, 0);
            self::fail('Expected InvalidReglementMatchingException');
        } catch (InvalidReglementMatchingException $exception) {
            self::assertSame('reglement_matching.invalid', $exception->errorCode());
        }
    }

    public function test_match_rejects_same_entries(): void
    {
        try {
            ReglementMatching::match(5, 5, 100);
            self::fail('Expected InvalidReglementMatchingException');
        } catch (InvalidReglementMatchingException $exception) {
            self::assertSame('reglement_matching.invalid', $exception->errorCode());
        }
    }

    public function test_unmatch_rejects_double_unmatch(): void
    {
        $matching = ReglementMatching::match(1, 2, 50);
        $matching->unmatch(null);

        try {
            $matching->unmatch(null);
            self::fail('Expected InvalidReglementMatchingException');
        } catch (InvalidReglementMatchingException $exception) {
            self::assertSame('reglement_matching.invalid', $exception->errorCode());
        }
    }
}
