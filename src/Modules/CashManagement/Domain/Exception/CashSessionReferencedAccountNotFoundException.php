<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use InvalidArgumentException;

/**
 * Compte party référencé par une opération cash_session introuvable.
 *
 * Une classe, quatre errorCode() selon le champ (holder/office/opened_by/closed_by)
 * — une traduction dédiée par code, contexte machine-readable partagé.
 */
final class CashSessionReferencedAccountNotFoundException extends DomainException
{
    private function __construct(
        private readonly string $field,
        string $message,
        array $context,
    ) {
        parent::__construct($message, $context);
    }

    public function errorCode(): string
    {
        return match ($this->field) {
            'holder' => 'cash_session.holder_account_not_found',
            'office' => 'cash_session.office_account_not_found',
            'opened_by' => 'cash_session.opened_by_not_found',
            'closed_by' => 'cash_session.closed_by_not_found',
            default => throw new InvalidArgumentException(sprintf('Unknown cash session account field "%s".', $this->field)),
        };
    }

    public static function forHolder(int $accountId): self
    {
        return new self(
            'holder',
            sprintf('Holder party account %d was not found.', $accountId),
            ['field' => 'holder', 'account_id' => $accountId],
        );
    }

    public static function forOffice(int $accountId): self
    {
        return new self(
            'office',
            sprintf('Office party account %d was not found.', $accountId),
            ['field' => 'office', 'account_id' => $accountId],
        );
    }

    public static function forOpenedBy(int $accountId): self
    {
        return new self(
            'opened_by',
            sprintf('Opened-by party account %d was not found.', $accountId),
            ['field' => 'opened_by', 'account_id' => $accountId],
        );
    }

    public static function forClosedBy(int $accountId): self
    {
        return new self(
            'closed_by',
            sprintf('Closed-by party account %d was not found.', $accountId),
            ['field' => 'closed_by', 'account_id' => $accountId],
        );
    }
}
