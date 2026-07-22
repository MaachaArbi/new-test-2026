<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccountFunctionAssignment;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountFunctionAssignmentException;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountFunctionAssignmentTest extends TestCase
{
    #[Test]
    public function assign_creates_an_active_assignment(): void
    {
        $functionCode = PartyFunctionCode::fromString('member');
        $assignment = PartyAccountFunctionAssignment::assign(
            personAccountId: 42,
            organizationAccountId: 9,
            functionCode: $functionCode,
            createdBy: 7,
        );

        self::assertNull($assignment->id());
        self::assertSame(42, $assignment->personAccountId());
        self::assertSame(9, $assignment->organizationAccountId());
        self::assertSame('member', $assignment->functionCode()->toString());
        self::assertSame(7, $assignment->createdBy());
        self::assertTrue($assignment->isActive());
        self::assertNull($assignment->validTo());
        self::assertInstanceOf(\DateTimeImmutable::class, $assignment->validFrom());
    }

    #[Test]
    public function revoke_closes_the_assignment_via_valid_to(): void
    {
        $assignment = PartyAccountFunctionAssignment::assign(
            42,
            9,
            PartyFunctionCode::fromString('gerant'),
            null,
        );
        self::assertTrue($assignment->isActive());

        $assignment->revoke();

        self::assertFalse($assignment->isActive());
        self::assertNotNull($assignment->validTo());
        self::assertGreaterThanOrEqual(
            $assignment->validFrom()->getTimestamp(),
            $assignment->validTo()->getTimestamp(),
        );
    }

    #[Test]
    public function revoke_on_already_closed_assignment_is_rejected(): void
    {
        $assignment = PartyAccountFunctionAssignment::assign(
            42,
            9,
            PartyFunctionCode::fromString('financier'),
            1,
        );
        $assignment->revoke();

        try {
            $assignment->revoke();
            self::fail('Expected InvalidPartyAccountFunctionAssignmentException');
        } catch (InvalidPartyAccountFunctionAssignmentException $exception) {
            self::assertSame('party_account_function.already_revoked', $exception->errorCode());
            self::assertSame(42, $exception->context()['person_account_id']);
            self::assertSame(9, $exception->context()['organization_account_id']);
            self::assertSame('financier', $exception->context()['function_code']);
        }
    }
}
