<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccountOffice;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountOfficeTest extends TestCase
{
    #[Test]
    public function create_stores_account_code_and_currency(): void
    {
        $office = PartyAccountOffice::create(
            accountId: 9,
            officeCode: 'MYGO-2023',
            defaultCurrencyCode: 'TND',
        );

        self::assertSame(9, $office->accountId());
        self::assertSame('MYGO-2023', $office->officeCode());
        self::assertSame('TND', $office->defaultCurrencyCode());
    }
}
