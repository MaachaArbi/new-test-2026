<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\UpdateBookingWorkflow;

/**
 * Mise à jour partielle du workflow booking (flags / assignation / label).
 * Pattern has* + valeur — aligné sur UpdatePartyAccount (PATCH partiel).
 *
 * hasAssignment + assignedAgentAccountId null → unassign().
 * hasSupplierStatusLabel + supplierStatusLabel null → clear label.
 */
final readonly class UpdateBookingWorkflowCommand
{
    public function __construct(
        public int $bookingId,
        public bool $hasOnRequest = false,
        public ?bool $isOnRequest = null,
        public bool $hasAssignment = false,
        public ?int $assignedAgentAccountId = null,
        public bool $hasLocked = false,
        public ?bool $isLocked = null,
        public bool $hasDisputed = false,
        public ?bool $isDisputed = null,
        public bool $hasSupplierStatusLabel = false,
        public ?string $supplierStatusLabel = null,
    ) {
    }
}
