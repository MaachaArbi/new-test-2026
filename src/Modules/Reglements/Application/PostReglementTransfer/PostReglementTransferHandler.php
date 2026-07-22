<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\PostReglementTransfer;

use App\Modules\Reglements\Application\ReglementReferentialValidator;
use App\Modules\Reglements\Domain\Exception\InvalidReglementTransferAmountException;
use App\Modules\Reglements\Domain\Exception\InvalidReglementTransferPartyRoleException;
use App\Modules\Reglements\Domain\Exception\ReglementTransferPostingFailedException;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use Doctrine\DBAL\Connection;
use ValueError;

/**
 * Appelle reglement_post_transfer() via DBAL.
 *
 * Pas de UnitOfWork / persist ORM : la fonction SQL crée atomiquement
 * le transfert + les 2 jambes du grand livre.
 */
final class PostReglementTransferHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ReglementReferentialValidator $referentialValidator,
    ) {
    }

    /**
     * @return int id du reglement_transfer créé
     */
    public function __invoke(PostReglementTransferCommand $command): int
    {
        if ($command->amountMinor <= 0) {
            throw InvalidReglementTransferAmountException::amountMustBePositive($command->amountMinor);
        }

        try {
            $sourceRole = InstrumentPartyRole::from($command->sourceRole);
        } catch (ValueError) {
            throw InvalidReglementTransferPartyRoleException::forValue($command->sourceRole);
        }

        try {
            $targetRole = InstrumentPartyRole::from($command->targetRole);
        } catch (ValueError) {
            throw InvalidReglementTransferPartyRoleException::forValue($command->targetRole);
        }

        $this->referentialValidator->assertCurrencyExists($command->currencyCode);

        $raw = $this->connection->fetchOne(
            'SELECT reglement_post_transfer(
                :source_id,
                :source_role,
                :target_id,
                :target_role,
                :currency,
                :amount,
                :effective_date,
                :reason,
                :created_by
            )',
            [
                'source_id' => $command->sourceAccountId,
                'source_role' => $sourceRole->value,
                'target_id' => $command->targetAccountId,
                'target_role' => $targetRole->value,
                'currency' => strtoupper(trim($command->currencyCode)),
                'amount' => $command->amountMinor,
                'effective_date' => $command->effectiveDate,
                'reason' => $command->reason,
                'created_by' => $command->createdBy,
            ],
        );

        if ($raw === false || $raw === null) {
            throw ReglementTransferPostingFailedException::emptyResult();
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        throw ReglementTransferPostingFailedException::nonNumericResult($raw);
    }
}
