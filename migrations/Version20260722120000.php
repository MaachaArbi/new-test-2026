<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use RuntimeException;

/**
 * Slice booking_cancellation_policy + booking_cancellation_tier.
 */
final class Version20260722120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import booking_cancellation_policy + booking_cancellation_tier + index uniques partiels';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $sql = <<<'SQL'
CREATE TABLE booking_cancellation_policy (
    id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_id  BIGINT NOT NULL,
    room_id     BIGINT REFERENCES booking_hotel_room(id),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_booking_cancellation_policy_booking ON booking_cancellation_policy(booking_id) WHERE room_id IS NULL;
CREATE UNIQUE INDEX uq_booking_cancellation_policy_room ON booking_cancellation_policy(room_id) WHERE room_id IS NOT NULL;
CREATE INDEX idx_booking_cancellation_policy_booking ON booking_cancellation_policy(booking_id);

COMMENT ON TABLE booking_cancellation_policy IS 'Barème d''annulation. Confirmé : rattaché PAR CHAMBRE en général pour l''hôtel (room_id renseigné), pas à la réservation entière -- correction du 15/07 après cas réel.';

CREATE TABLE booking_cancellation_tier (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    policy_id         BIGINT NOT NULL REFERENCES booking_cancellation_policy(id),
    days_before_start INT NOT NULL,
    threshold_time    TIME,
    min_stay_nights   SMALLINT,
    max_stay_nights   SMALLINT,
    penalty_type      VARCHAR(20) NOT NULL
                          CHECK (penalty_type IN ('free', 'percentage', 'fixed_amount')),
    penalty_value     NUMERIC(14,3),
    sort_order        SMALLINT NOT NULL DEFAULT 0,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE booking_cancellation_tier IS 'Paliers du barème (ex: >30j = free, 15-30j = 30%, <15j = 100%). Consommé aussi pour déterminer l''éligibilité BOOK_NOW_PAY_LATER (annulation gratuite à l''instant T). NatureAnnulation/Type du legacy non repris tels quels (sémantique ambiguë, sans libellé) -- à clarifier si un vrai besoin de distinction apparaît au-delà de penalty_type.';

CREATE INDEX idx_booking_cancellation_tier_policy ON booking_cancellation_tier(policy_id);
SQL;

        $this->execSqlFile($this->connection, $sql);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Les migrations SQL de référence ne sont pas réversibles automatiquement.'
        );
    }

    private function execSqlFile(Connection $connection, string $sql): void
    {
        $native = $connection->getNativeConnection();
        if (!$native instanceof \PDO) {
            throw new RuntimeException('Connexion PDO native attendue pour exécuter le SQL multi-statements.');
        }

        $native->exec($sql);
    }
}
