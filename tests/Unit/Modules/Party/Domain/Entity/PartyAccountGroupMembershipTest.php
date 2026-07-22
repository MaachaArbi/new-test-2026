<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccountGroupMembership;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountGroupMembershipException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountGroupMembershipTest extends TestCase
{
    #[Test]
    public function assign_creates_an_active_membership(): void
    {
        $membership = PartyAccountGroupMembership::assign(
            accountId: 42,
            groupId: 7,
            createdBy: 3,
        );

        self::assertNull($membership->id());
        self::assertSame(42, $membership->accountId());
        self::assertSame(7, $membership->groupId());
        self::assertSame(3, $membership->createdBy());
        self::assertTrue($membership->isActive());
        self::assertNull($membership->validTo());
        self::assertInstanceOf(\DateTimeImmutable::class, $membership->validFrom());
    }

    #[Test]
    public function revoke_closes_the_membership_via_valid_to(): void
    {
        $membership = PartyAccountGroupMembership::assign(42, 7, null);
        self::assertTrue($membership->isActive());

        $membership->revoke();

        self::assertFalse($membership->isActive());
        self::assertNotNull($membership->validTo());
        self::assertGreaterThanOrEqual(
            $membership->validFrom()->getTimestamp(),
            $membership->validTo()->getTimestamp(),
        );
    }

    #[Test]
    public function revoke_on_already_closed_membership_is_rejected(): void
    {
        $membership = PartyAccountGroupMembership::assign(42, 7, 1);
        $membership->revoke();

        try {
            $membership->revoke();
            self::fail('Expected InvalidPartyAccountGroupMembershipException');
        } catch (InvalidPartyAccountGroupMembershipException $exception) {
            self::assertSame('party_account_group_member.already_revoked', $exception->errorCode());
            self::assertSame(42, $exception->context()['account_id']);
            self::assertSame(7, $exception->context()['group_id']);
        }
    }
}
