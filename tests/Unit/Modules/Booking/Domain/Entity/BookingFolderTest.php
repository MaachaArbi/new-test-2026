<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Shared\Domain\ValueObject\PublicId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class BookingFolderTest extends TestCase
{
    #[Test]
    public function create_ok(): void
    {
        $folder = BookingFolder::create('DOS-001', 10, 20);

        self::assertNull($folder->id());
        self::assertSame('DOS-001', $folder->referenceCode());
        self::assertSame(10, $folder->partyAccountId());
        self::assertSame(20, $folder->officeAccountId());
        self::assertFalse($folder->isDeleted());
        self::assertNull($folder->deletedAt());
        self::assertInstanceOf(PublicId::class, $folder->publicId());
        self::assertTrue(Uuid::isValid($folder->publicId()->toString()));
        self::assertSame(4, Uuid::fromString($folder->publicId()->toString())->getVersion());
    }

    #[Test]
    public function delete_is_idempotent(): void
    {
        $folder = BookingFolder::create('DOS-002', 1, 2);
        self::assertFalse($folder->isDeleted());

        $folder->delete();

        self::assertTrue($folder->isDeleted());
        self::assertNotNull($folder->deletedAt());
        $firstDeletedAt = $folder->deletedAt();

        $folder->delete();
        self::assertSame($firstDeletedAt, $folder->deletedAt());
    }
}
