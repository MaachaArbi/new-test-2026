<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\PostSettlementTransfer;

use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementTransferAmountException;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementTransferPartyRoleException;
use App\Modules\Settlement\Domain\Exception\SettlementTransferPostingFailedException;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use Doctrine\DBAL\Connection;
use ValueError;

/**
 * Appelle settlement_post_transfer() via DBAL.
 *
 * Pas de UnitOfWork / persist ORM : la fonction SQL crée atomiquement
 * le transfert + les 2 jambes du grand livre.
 */
final class PostSettlementTransferHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SettlementReferentialValidator $referentialValidator,
    ) {
    }

    /**
     * @return int id du settlement_transfer créé
     */
    public function __invoke(PostSettlementTransferCommand $command): int
    {
        if ($command->amountMinor <= 0) {
            throw InvalidSettlementTransferAmountException::amountMustBePositive($command->amountMinor);
        }

        try {
            $sourceRole = InstrumentPartyRole::from($command->sourceRole);
        } catch (ValueError) {
            throw InvalidSettlementTransferPartyRoleException::forValue($command->sourceRole);
        }

        try {
            $targetRole = InstrumentPartyRole::from($command->targetRole);
        } catch (ValueError) {
            throw InvalidSettlementTransferPartyRoleException::forValue($command->targetRole);
        }

        $this->referentialValidator->assertCurrencyExists($command->currencyCode);

        $raw = $this->connection->fetchOne(
            'SELECT settlement_post_transfer(
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
            throw SettlementTransferPostingFailedException::emptyResult();
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        throw SettlementTransferPostingFailedException::nonNumericResult($raw);
    }
}
