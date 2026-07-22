<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Command;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Soft-delete (Domain) des PartyAccount créés par les tests d'intégration.
 *
 * JAMAIS exposer via HTTP. JAMAIS appeler automatiquement.
 * Autorisé uniquement si APP_ENV ∈ {dev, test}.
 *
 * Critère SQL (voir docs/journal/2026-07-21-purge-test-accounts.md) :
 *   email ILIKE '%@example.com'
 *   OR (email IS NULL AND display_name ~ '^(LocalCred|OAuthCred|FindByIdentity|MultiCred) [0-9a-f]{8}$')
 * Exclusion absolue : display_name = 'myGO' OR email = 'booking@mygo.pro'
 *
 * Mutation : PartyAccount::delete() + repository->delete() uniquement.
 * Aucun hard delete, aucune touche aux tables d'assignation.
 */
#[AsCommand(
    name: 'app:party:purge-test-accounts',
    description: 'Soft-delete les PartyAccount de test (dev/test uniquement, dry-run par défaut)',
)]
final class PurgeTestPartyAccountsCommand extends Command
{
    private const AGENCY_DISPLAY_NAME = 'myGO';

    private const AGENCY_EMAIL = 'booking@mygo.pro';

    private const CANDIDATE_IDS_SQL = <<<'SQL'
SELECT pa.id
FROM party_account pa
WHERE pa.deleted_at IS NULL
  AND pa.display_name <> :agency_name
  AND (pa.email IS DISTINCT FROM :agency_email)
  AND (
    pa.email ILIKE '%@example.com'
    OR (
      pa.email IS NULL
      AND pa.display_name ~ '^(LocalCred|OAuthCred|FindByIdentity|MultiCred) [0-9a-f]{8}$'
    )
  )
SQL;

    public function __construct(
        private readonly Connection $connection,
        private readonly PartyAccountRepositoryInterface $partyAccountRepository,
        private readonly UnitOfWork $unitOfWork,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'execute',
                null,
                InputOption::VALUE_NONE,
                'Exécute réellement le soft-delete (sinon dry-run : compte uniquement)',
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Saute la confirmation interactive (nécessite encore --execute)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\in_array($this->environment, ['dev', 'test'], true)) {
            $io->error(sprintf(
                'Refusé : APP_ENV="%s". Cette commande n\'est autorisée qu\'en "dev" ou "test". Aucun contournement.',
                $this->environment,
            ));

            return Command::FAILURE;
        }

        $params = [
            'agency_name' => self::AGENCY_DISPLAY_NAME,
            'agency_email' => self::AGENCY_EMAIL,
        ];

        $toPurgeRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ('.self::CANDIDATE_IDS_SQL.') AS candidates',
            $params,
        );
        $toPurge = is_numeric($toPurgeRaw) ? (int) $toPurgeRaw : 0;
        $totalBeforeRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM party_account WHERE deleted_at IS NULL',
        );
        $totalBefore = is_numeric($totalBeforeRaw) ? (int) $totalBeforeRaw : 0;

        $io->section('Dry-run / sélection');
        $io->writeln(sprintf('Environnement      : %s', $this->environment));
        $io->writeln(sprintf('Comptes non soft-deleted : %d', $totalBefore));
        $io->writeln(sprintf('Candidats à soft-delete  : %d', $toPurge));
        $io->writeln(sprintf(
            'Protégés : display_name=%s + email=%s (jamais soft-deleted)',
            self::AGENCY_DISPLAY_NAME,
            self::AGENCY_EMAIL,
        ));

        $execute = (bool) $input->getOption('execute');
        if (!$execute) {
            $io->note('Mode dry-run (défaut). Relancer avec --execute pour soft-deleter.');

            return Command::SUCCESS;
        }

        if ($toPurge === 0) {
            $io->success('Rien à soft-deleter.');

            return Command::SUCCESS;
        }

        if (!(bool) $input->getOption('force')) {
            $answer = $io->ask(sprintf(
                'Confirmer le soft-delete de %d compte(s) ? Tapez exactement "yes"',
                $toPurge,
            ));
            if ($answer !== 'yes') {
                $io->warning('Annulé (confirmation différente de "yes").');

                return Command::FAILURE;
            }
        }

        /** @var list<int|string> $rawIds */
        $rawIds = $this->connection->fetchFirstColumn(self::CANDIDATE_IDS_SQL, $params);
        $ids = array_map(static fn (int|string $id): int => (int) $id, $rawIds);

        $this->connection->beginTransaction();
        try {
            $softDeleted = 0;
            foreach ($ids as $id) {
                $account = $this->partyAccountRepository->findById($id);
                if (!$account instanceof PartyAccount || $account->isDeleted()) {
                    continue;
                }
                $account->delete();
                $this->partyAccountRepository->delete($account);
                ++$softDeleted;
            }
            $this->unitOfWork->commit();
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $io->error('Échec soft-delete — transaction annulée : '.$e->getMessage());

            return Command::FAILURE;
        }

        $totalAfterRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM party_account WHERE deleted_at IS NULL',
        );
        $totalAfter = is_numeric($totalAfterRaw) ? (int) $totalAfterRaw : 0;
        $agencyLeftRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM party_account WHERE deleted_at IS NULL AND display_name = :agency_name',
            ['agency_name' => self::AGENCY_DISPLAY_NAME],
        );
        $agencyLeft = is_numeric($agencyLeftRaw) ? (int) $agencyLeftRaw : 0;

        $io->success(sprintf(
            'Soft-delete OK : %d compte(s). Restants visibles (deleted_at IS NULL) : %d (dont myGO=%d).',
            $softDeleted,
            $totalAfter,
            $agencyLeft,
        ));

        return Command::SUCCESS;
    }
}
