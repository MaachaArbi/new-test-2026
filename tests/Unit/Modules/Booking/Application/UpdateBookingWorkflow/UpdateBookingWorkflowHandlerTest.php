<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Booking\Application\UpdateBookingWorkflow;

use App\Modules\Booking\Application\UpdateBookingWorkflow\UpdateBookingWorkflowCommand;
use App\Modules\Booking\Application\UpdateBookingWorkflow\UpdateBookingWorkflowHandler;
use App\Modules\Booking\Domain\Exception\BookingNoChangesException;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Documente le comportement « aucun changement » du Handler workflow.
 */
final class UpdateBookingWorkflowHandlerTest extends TestCase
{
    #[Test]
    public function empty_command_raises_no_changes(): void
    {
        $repository = $this->createMock(BookingRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::never())->method('save');

        $handler = new UpdateBookingWorkflowHandler($repository, new UnitOfWork(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
        ));

        try {
            ($handler)(new UpdateBookingWorkflowCommand(bookingId: 1));
            self::fail('Expected BookingNoChangesException');
        } catch (BookingNoChangesException $exception) {
            self::assertSame('booking.no_changes_provided', $exception->errorCode());
        }
    }

    #[Test]
    public function has_on_request_true_with_null_value_counts_as_no_change(): void
    {
        // Documente le choix actuel : hasOnRequest=true + isOnRequest=null
        // n'est PAS un changement (ni une erreur de requête malformée) —
        // même filtre que hasLocked/hasDisputed (null = pas demandé).
        $repository = $this->createMock(BookingRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::never())->method('save');

        $handler = new UpdateBookingWorkflowHandler($repository, new UnitOfWork(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
        ));

        try {
            ($handler)(new UpdateBookingWorkflowCommand(
                bookingId: 1,
                hasOnRequest: true,
                isOnRequest: null,
            ));
            self::fail('Expected BookingNoChangesException');
        } catch (BookingNoChangesException $exception) {
            self::assertSame('booking.no_changes_provided', $exception->errorCode());
        }
    }
}
