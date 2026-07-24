-- ============================================================================
-- schema-invoicing-v1.sql — Module Facturation / Avoirs
-- ERP Tourisme. PostgreSQL 16+.
--
-- Statut     : V1.0 — proposition, en cours de validation sandbox
-- Dépend de  : schema-party-account-v1.sql, schema-ref-common.sql,
--              schema-ref-static-v1.sql (ref_country),
--              schema-booking-v1.sql (lu, jamais modifié),
--              schema-settlement-v1.sql (invoice_id/credit_note_id branchés),
--              schema-sales_point-v1.sql (FK reporting nullable)
-- Ordre      : 9ème script à exécuter (après ref-static)
--
-- PRINCIPE   : un seul grand livre (Règlements). Facturation ne recalcule
--              jamais un solde — elle documente une obligation déjà projetée
--              (ligne ANCRÉE) ou pose une écriture nouvelle uniquement quand
--              aucune obligation n'existe encore ailleurs (ligne LIBRE).
--              Voir modele-conceptuel-facturation.md pour le raisonnement complet.
-- ============================================================================

-- ============================================================
-- RÉFÉRENTIELS FISCAUX
-- ============================================================

-- Nature d'une taxe/prélèvement calculé par ligne (TVA, FODEC...).
-- Table, pas ENUM. Extensible sans migration (cf. principe déjà acté
-- ailleurs dans le projet). Le timbre N'EST PAS ici : montant fixe par
-- document, pas un taux par ligne — voir invoicing_stamp_duty_rate.
CREATE TABLE invoicing_tax_type (
    code        VARCHAR(20) PRIMARY KEY,
    label       VARCHAR(80) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO invoicing_tax_type (code, label) VALUES
    ('vat',   'Value Added Tax'),
    ('fodec', 'Fonds de Développement de la Compétitivité (fournisseur uniquement)');

-- Taux par pays, historisé dans le temps (rejoint sujets-reportes.md #34).
-- Un taux couvre une période [valid_from, valid_to). valid_to NULL = taux
-- courant. Le système doit supporter tous les pays, pas seulement la
-- Tunisie (confirmé explicitement par l'utilisateur).
CREATE TABLE invoicing_tax_rate (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id     UUID NOT NULL DEFAULT gen_random_uuid(),
    tax_type_code VARCHAR(20) NOT NULL REFERENCES invoicing_tax_type(code),
    country_id    BIGINT NOT NULL REFERENCES ref_country(id),
    rate_percent  NUMERIC(6,3) NOT NULL CHECK (rate_percent >= 0),
    label         VARCHAR(100), -- ex: "TVA tourisme 7% (sur le total)" — libellé libre, informatif
    valid_from    DATE NOT NULL,
    valid_to      DATE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_invoicing_tax_rate_period CHECK (valid_to IS NULL OR valid_to > valid_from)
);

COMMENT ON TABLE invoicing_tax_rate IS
'Taux de taxe (TVA ou FODEC) par pays, historisé. Une ligne facture snapshotte
 l''id du taux appliqué au moment de la validation — jamais recalculé après coup
 si le taux change dans le temps.';

CREATE INDEX idx_invoicing_tax_rate_lookup
    ON invoicing_tax_rate(tax_type_code, country_id, valid_from);
CREATE UNIQUE INDEX uq_invoicing_tax_rate_public_id ON invoicing_tax_rate(public_id);

-- Le timbre fiscal : montant FIXE par document (pas un taux), historisé
-- dans le temps, par pays (1 DT en Tunisie actuellement, variable).
CREATE TABLE invoicing_stamp_duty_rate (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id      UUID NOT NULL DEFAULT gen_random_uuid(),
    country_id     BIGINT NOT NULL REFERENCES ref_country(id),
    amount_minor   BIGINT NOT NULL CHECK (amount_minor >= 0),
    currency_code  VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    valid_from     DATE NOT NULL,
    valid_to       DATE,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_invoicing_stamp_rate_period CHECK (valid_to IS NULL OR valid_to > valid_from)
);

COMMENT ON TABLE invoicing_stamp_duty_rate IS
'Montant fixe du timbre fiscal par pays, historisé. Source lue par Booking
 pour créer une ligne booking_charge de type fiscal_stamp à la réservation
 (collecte commerciale), et par Facturation pour connaître le montant
 déductible au moment de désigner la ligne porteuse dans une facture.';

CREATE INDEX idx_invoicing_stamp_duty_rate_lookup
    ON invoicing_stamp_duty_rate(country_id, valid_from);
CREATE UNIQUE INDEX uq_invoicing_stamp_duty_rate_public_id ON invoicing_stamp_duty_rate(public_id);

-- ============================================================
-- NUMÉROTATION LÉGALE — séquence globale annuelle, sans gap.
-- Concerne UNIQUEMENT les documents ÉMIS par l'agence (facture et avoir
-- client). Les documents fournisseurs portent une référence libre (le
-- numéro du document reçu), jamais une séquence générée ici — on ne
-- s'auto-numérote pas ce qu'on reçoit.
-- ============================================================
CREATE TABLE invoicing_sequence_counter (
    sequence_type  VARCHAR(20) NOT NULL CHECK (sequence_type IN ('invoice', 'credit_note')),
    seq_year       SMALLINT NOT NULL,
    last_number    INTEGER NOT NULL DEFAULT 0,
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (sequence_type, seq_year)
);

COMMENT ON TABLE invoicing_sequence_counter IS
'Compteur par (type, année), remis à zéro chaque année. Toute lecture/incrément
 passe par invoicing_next_number() avec SELECT FOR UPDATE dans la même
 transaction que la validation du document — garantit l''absence de gap même
 en cas d''échec technique concurrent (contrainte plus stricte qu''une SEQUENCE
 PostgreSQL classique).';

CREATE OR REPLACE FUNCTION invoicing_next_number(p_sequence_type VARCHAR, p_year SMALLINT)
RETURNS INTEGER LANGUAGE plpgsql AS $$
DECLARE
    v_next INTEGER;
BEGIN
    INSERT INTO invoicing_sequence_counter (sequence_type, seq_year, last_number)
    VALUES (p_sequence_type, p_year, 0)
    ON CONFLICT (sequence_type, seq_year) DO NOTHING;

    UPDATE invoicing_sequence_counter
    SET last_number = last_number + 1, updated_at = now()
    WHERE sequence_type = p_sequence_type AND seq_year = p_year
    RETURNING last_number INTO v_next;

    RETURN v_next;
END;
$$;

COMMENT ON FUNCTION invoicing_next_number IS
'Verrouille implicitement la ligne du compteur (UPDATE ... WHERE) dans la
 transaction appelante — deux validations concurrentes sur la même
 (type, année) se sérialisent, jamais de doublon ni de gap. À appeler
 UNIQUEMENT depuis invoicing_post_validate_invoice/credit_note, jamais
 directement par l''application.';

-- ============================================================
-- FACTURE CLIENT — invoicing_invoice / invoicing_invoice_line
-- ============================================================

CREATE TABLE invoicing_invoice (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),

    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    -- party_role toujours 'client' ici — table dédiée par rôle (comme le
    -- legacy ost_com_facture / ost_com_facture_fournisseur), pas une
    -- colonne de rôle partagée : les deux faces ont des mécaniques de
    -- numérotation, d'avoir et de saisie trop différentes pour une table
    -- unique (voir modele-conceptuel-facturation.md).

    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    country_id         BIGINT NOT NULL REFERENCES ref_country(id), -- pays fiscal de référence (détermine les taux applicables)

    sales_point_id      BIGINT REFERENCES sales_point(id), -- reporting uniquement, jamais utilisé pour la numérotation

    status_code        VARCHAR(20) NOT NULL DEFAULT 'draft'
                          CHECK (status_code IN ('draft', 'validated', 'cancelled')),

    seq_year           SMALLINT,      -- renseigné uniquement à la validation
    invoice_number     INTEGER,       -- renseigné uniquement à la validation (invoicing_next_number)

    -- Totaux dénormalisés, recalculés par l'application à chaque mutation
    -- de ligne (même convention que booking_charge / booking.total_vente_amount
    -- — jamais un trigger, cf. ADR-002), pas une contrainte SQL.
    total_net_minor     BIGINT NOT NULL DEFAULT 0,
    total_tax_minor    BIGINT NOT NULL DEFAULT 0,
    total_stamp_minor  BIGINT NOT NULL DEFAULT 0,
    total_gross_minor    BIGINT NOT NULL DEFAULT 0,

    validated_at       TIMESTAMPTZ,
    validated_by       BIGINT REFERENCES party_account(id),
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE invoicing_invoice IS
'Facture client. Peut regrouper N réservations (sélection libre par
 l''utilisateur), toujours dans une seule devise. Le numéro n''existe qu''après
 validation (brouillon = pas de numéro consommé).';

CREATE UNIQUE INDEX uq_invoicing_invoice_public_id ON invoicing_invoice(public_id);
CREATE UNIQUE INDEX uq_invoicing_invoice_number
    ON invoicing_invoice(seq_year, invoice_number) WHERE status_code = 'validated';
CREATE INDEX idx_invoicing_invoice_party ON invoicing_invoice(party_account_id);
CREATE INDEX idx_invoicing_invoice_status ON invoicing_invoice(status_code) WHERE status_code = 'draft';

CREATE TRIGGER trg_invoicing_invoice_updated_at BEFORE UPDATE ON invoicing_invoice
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE invoicing_invoice_line (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id             UUID NOT NULL DEFAULT gen_random_uuid(),
    invoice_id            BIGINT NOT NULL REFERENCES invoicing_invoice(id),

    origin_type           VARCHAR(10) NOT NULL CHECK (origin_type IN ('anchored', 'free')),

    -- Ligne ANCRÉE : référence le split exact (payeur + montant), jamais
    -- booking_id directement — gère nativement le cas amicale/employé.
    -- FK applicative (booking_ est partitionné, même convention que
    -- booking_payment.payer_split_id ailleurs dans le projet).
    booking_payer_split_id BIGINT,
    booking_id             BIGINT, -- dénormalisé depuis le split au moment de l'ancrage — sert au regroupement "1 ligne porteuse timbre par réservation distincte" sans re-jointure

    -- Ligne LIBRE : désignation manuelle, aucune réservation derrière.
    free_label             TEXT,

    amount_minor           BIGINT NOT NULL CHECK (amount_minor > 0), -- montant TTC facturé sur cette ligne

    -- TVA : deux formules possibles selon le service/mode de vente,
    -- stockée par ligne (informatif + base de recalcul), jamais recalculée
    -- dynamiquement depuis Booking après coup.
    tax_calc_method         VARCHAR(12) CHECK (tax_calc_method IN ('total', 'commission')),
    tax_rate_id              BIGINT REFERENCES invoicing_tax_rate(id),
    tax_base_minor           BIGINT NOT NULL DEFAULT 0,  -- = amount_minor - stamp_deducted_minor si porteuse
    tax_amount_minor         BIGINT NOT NULL DEFAULT 0,

    -- Timbre : porté PAR COUPLE (facture, réservation). is_stamp_bearer=true
    -- sur au plus 1 ligne par (invoice_id, booking_id). Réassignation
    -- automatique si la ligne porteuse est supprimée (voir trigger plus bas).
    is_stamp_bearer           BOOLEAN NOT NULL DEFAULT false,
    stamp_deducted_minor      BIGINT NOT NULL DEFAULT 0,

    sort_order                SMALLINT NOT NULL DEFAULT 0,
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_invoicing_invoice_line_origin CHECK (
        (origin_type = 'anchored' AND booking_payer_split_id IS NOT NULL AND booking_id IS NOT NULL AND free_label IS NULL)
        OR
        (origin_type = 'free' AND booking_payer_split_id IS NULL AND booking_id IS NULL AND free_label IS NOT NULL)
    ),
    CONSTRAINT chk_invoicing_invoice_line_stamp_anchored CHECK (
        NOT is_stamp_bearer OR origin_type = 'anchored'
    )
);

COMMENT ON TABLE invoicing_invoice_line IS
'Ligne ANCRÉE (booking_payer_split_id) : documente une obligation déjà
 projetée dans Règlements, ne crée AUCUNE écriture. Ligne LIBRE (free_label) :
 aucune réservation en face, pose une écriture nouvelle dans Règlements à la
 validation (origine invoice_id). Les deux peuvent coexister dans la même
 facture.';

CREATE INDEX idx_invoicing_invoice_line_invoice ON invoicing_invoice_line(invoice_id, sort_order);
CREATE INDEX idx_invoicing_invoice_line_split ON invoicing_invoice_line(booking_payer_split_id) WHERE booking_payer_split_id IS NOT NULL;
CREATE INDEX idx_invoicing_invoice_line_booking ON invoicing_invoice_line(invoice_id, booking_id) WHERE booking_id IS NOT NULL;
CREATE UNIQUE INDEX uq_invoicing_invoice_line_stamp_bearer
    ON invoicing_invoice_line(invoice_id, booking_id) WHERE is_stamp_bearer = true;

-- Réassignation automatique de la ligne porteuse du timbre si elle est
-- supprimée (uniquement possible tant que la facture est en brouillon —
-- règle applicative, pas une contrainte SQL portée ici).
CREATE OR REPLACE FUNCTION invoicing_reassign_stamp_bearer()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_next_line_id BIGINT;
BEGIN
    IF OLD.is_stamp_bearer THEN
        SELECT id INTO v_next_line_id
        FROM invoicing_invoice_line
        WHERE invoice_id = OLD.invoice_id AND booking_id = OLD.booking_id
        ORDER BY sort_order, id
        LIMIT 1;

        IF v_next_line_id IS NOT NULL THEN
            UPDATE invoicing_invoice_line
            SET is_stamp_bearer = true
            WHERE id = v_next_line_id;
        END IF;
        -- Si aucune ligne restante pour cette réservation, le timbre de cette
        -- réservation disparaît simplement de cette facture (l'application
        -- devra recalculer total_stamp_minor en conséquence).
    END IF;
    RETURN OLD;
END;
$$;

CREATE TRIGGER trg_invoicing_invoice_line_reassign_stamp
    AFTER DELETE ON invoicing_invoice_line
    FOR EACH ROW EXECUTE FUNCTION invoicing_reassign_stamp_bearer();

-- Plafond de facturation cumulée sur un split : SUM(amount_minor facturé sur
-- ce split, toutes factures/statuts confondus) <= booking_payer_split.amount.
-- RÈGLE ABSOLUE, jamais renégociée par un avoir (le split reste figé — voir
-- modele-conceptuel-facturation.md). Appliquée en base, pas seulement en
-- discipline applicative (cohérent avec le reste du projet). Le plafond
-- couvre aussi les factures en brouillon, pas seulement validées : on ne
-- laisse pas créer un brouillon déjà invalide.
CREATE OR REPLACE FUNCTION invoicing_check_invoice_line_split_cap()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_split_amount BIGINT;
    v_already_invoiced BIGINT;
BEGIN
    IF NEW.origin_type <> 'anchored' THEN
        RETURN NEW;
    END IF;

    SELECT amount INTO v_split_amount
    FROM booking_payer_split WHERE id = NEW.booking_payer_split_id;

    IF v_split_amount IS NULL THEN
        RAISE EXCEPTION 'booking_payer_split % introuvable', NEW.booking_payer_split_id;
    END IF;

    SELECT COALESCE(SUM(amount_minor), 0) INTO v_already_invoiced
    FROM invoicing_invoice_line
    WHERE booking_payer_split_id = NEW.booking_payer_split_id AND id <> NEW.id;

    IF v_already_invoiced + NEW.amount_minor > v_split_amount THEN
        RAISE EXCEPTION
            'Plafond de facturation depasse sur booking_payer_split % : deja facture % + nouvelle ligne % > montant du split %',
            NEW.booking_payer_split_id, v_already_invoiced, NEW.amount_minor, v_split_amount;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_invoicing_invoice_line_check_split_cap
    BEFORE INSERT OR UPDATE ON invoicing_invoice_line
    FOR EACH ROW EXECUTE FUNCTION invoicing_check_invoice_line_split_cap();

-- ============================================================
-- AVOIR CLIENT — invoicing_credit_note / invoicing_credit_note_line
-- ============================================================

CREATE TABLE invoicing_credit_note (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id           UUID NOT NULL DEFAULT gen_random_uuid(),

    party_account_id    BIGINT NOT NULL REFERENCES party_account(id),
    currency_code       VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    country_id          BIGINT NOT NULL REFERENCES ref_country(id),
    sales_point_id       BIGINT REFERENCES sales_point(id),

    -- Homogénéité stricte : un avoir est SOIT généré automatiquement
    -- depuis une annulation Booking (lignes ancrées uniquement), SOIT créé
    -- manuellement (lignes libres uniquement) — jamais les deux mélangés.
    -- Choix délibéré de robustesse (voir modele-conceptuel-facturation.md) :
    -- l'avoir ancré ne doit JAMAIS pouvoir être créé à la main.
    generation_origin    VARCHAR(30) NOT NULL
                            CHECK (generation_origin IN ('automatic_cancellation', 'manual_free')),

    status_code          VARCHAR(20) NOT NULL DEFAULT 'draft'
                            CHECK (status_code IN ('draft', 'validated', 'cancelled')),

    seq_year             SMALLINT,
    credit_note_number   INTEGER,

    total_net_minor       BIGINT NOT NULL DEFAULT 0,
    total_tax_minor      BIGINT NOT NULL DEFAULT 0,
    total_gross_minor      BIGINT NOT NULL DEFAULT 0,

    reason               TEXT,

    validated_at         TIMESTAMPTZ,
    validated_by         BIGINT REFERENCES party_account(id),
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by           BIGINT REFERENCES party_account(id),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE invoicing_credit_note IS
'Avoir client. generation_origin=automatic_cancellation : déclenché
 exclusivement depuis une annulation Booking, jamais par saisie manuelle.
 generation_origin=manual_free : correction d''une facture libre, seul cas où
 la création manuelle est autorisée. Ne rouvre JAMAIS la capacité de
 facturation d''un booking_payer_split déjà consommée.';

CREATE UNIQUE INDEX uq_invoicing_credit_note_public_id ON invoicing_credit_note(public_id);
CREATE UNIQUE INDEX uq_invoicing_credit_note_number
    ON invoicing_credit_note(seq_year, credit_note_number) WHERE status_code = 'validated';
CREATE INDEX idx_invoicing_credit_note_party ON invoicing_credit_note(party_account_id);

CREATE TRIGGER trg_invoicing_credit_note_updated_at BEFORE UPDATE ON invoicing_credit_note
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE invoicing_credit_note_line (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id           UUID NOT NULL DEFAULT gen_random_uuid(),
    credit_note_id       BIGINT NOT NULL REFERENCES invoicing_credit_note(id),

    origin_type           VARCHAR(10) NOT NULL CHECK (origin_type IN ('anchored', 'free')),

    -- Toujours rattaché à une ligne facture précise, ancrée ou libre —
    -- un avoir corrige toujours une portion d'une invoicing_invoice_line
    -- existante, jamais un montant flottant sans origine. Partiel autorisé.
    invoice_line_id       BIGINT NOT NULL REFERENCES invoicing_invoice_line(id),

    amount_minor           BIGINT NOT NULL CHECK (amount_minor > 0), -- portion TTC annulée, <= reliquat de la ligne facture

    tax_rate_id             BIGINT REFERENCES invoicing_tax_rate(id),
    tax_base_minor           BIGINT NOT NULL DEFAULT 0,
    tax_amount_minor         BIGINT NOT NULL DEFAULT 0,

    sort_order               SMALLINT NOT NULL DEFAULT 0,
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE invoicing_credit_note_line IS
'Toujours ancrée sur une invoicing_invoice_line précise (montant partiel
 autorisé). origin_type doit être cohérent avec invoicing_credit_note.generation_origin
 (contrôlé par trigger, pas seulement une convention applicative).';

CREATE INDEX idx_invoicing_credit_note_line_note ON invoicing_credit_note_line(credit_note_id, sort_order);
CREATE INDEX idx_invoicing_credit_note_line_invoice_line ON invoicing_credit_note_line(invoice_line_id);

-- Garde-fou dur : une ligne d'avoir 'anchored' ne peut exister que sous un
-- en-tête generation_origin='automatic_cancellation', et une ligne 'free'
-- que sous generation_origin='manual_free'. Empêche structurellement un
-- avoir ancré créé à la main, même si l'application se trompe de chemin.
CREATE OR REPLACE FUNCTION invoicing_check_credit_note_line_origin()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_origin VARCHAR(30);
    v_line_origin VARCHAR(10);
BEGIN
    SELECT generation_origin INTO v_origin
    FROM invoicing_credit_note WHERE id = NEW.credit_note_id;

    SELECT origin_type INTO v_line_origin
    FROM invoicing_invoice_line WHERE id = NEW.invoice_line_id;

    IF NEW.origin_type <> v_line_origin THEN
        RAISE EXCEPTION 'invoicing_credit_note_line.origin_type (%) doit correspondre à celui de la ligne facture référencée (%)', NEW.origin_type, v_line_origin;
    END IF;

    IF (v_origin = 'automatic_cancellation' AND NEW.origin_type <> 'anchored')
       OR (v_origin = 'manual_free' AND NEW.origin_type <> 'free') THEN
        RAISE EXCEPTION 'Incohérence : avoir % ne peut porter que des lignes %',
            v_origin, CASE WHEN v_origin = 'automatic_cancellation' THEN 'anchored' ELSE 'free' END;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_invoicing_credit_note_line_check_origin
    BEFORE INSERT ON invoicing_credit_note_line
    FOR EACH ROW EXECUTE FUNCTION invoicing_check_credit_note_line_origin();

-- Plafond sur le reliquat de la ligne facture : SUM(amount_minor déjà avoiré
-- sur cette invoicing_invoice_line) <= invoicing_invoice_line.amount_minor.
-- Même principe que le plafond de split ci-dessus : appliqué en base, pas
-- seulement documenté en commentaire.
CREATE OR REPLACE FUNCTION invoicing_check_credit_note_line_remaining_cap()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_invoice_line_amount BIGINT;
    v_already_credited     BIGINT;
BEGIN
    SELECT amount_minor INTO v_invoice_line_amount
    FROM invoicing_invoice_line WHERE id = NEW.invoice_line_id;

    IF v_invoice_line_amount IS NULL THEN
        RAISE EXCEPTION 'invoicing_invoice_line % introuvable', NEW.invoice_line_id;
    END IF;

    SELECT COALESCE(SUM(amount_minor), 0) INTO v_already_credited
    FROM invoicing_credit_note_line
    WHERE invoice_line_id = NEW.invoice_line_id AND id <> NEW.id;

    IF v_already_credited + NEW.amount_minor > v_invoice_line_amount THEN
        RAISE EXCEPTION
            'Avoir depasse le reliquat de la ligne facture % : deja avoire % + nouvel avoir % > montant ligne %',
            NEW.invoice_line_id, v_already_credited, NEW.amount_minor, v_invoice_line_amount;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_invoicing_credit_note_line_check_remaining_cap
    BEFORE INSERT OR UPDATE ON invoicing_credit_note_line
    FOR EACH ROW EXECUTE FUNCTION invoicing_check_credit_note_line_remaining_cap();

-- ============================================================
-- FACTURE FOURNISSEUR — invoicing_supplier_invoice / _line
-- Pas de séquence générée : supplier_reference porte le numéro du
-- document reçu, tel quel. Deux modes de saisie coexistants (entry_mode).
-- ============================================================

CREATE TABLE invoicing_supplier_invoice (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id             UUID NOT NULL DEFAULT gen_random_uuid(),

    party_account_id      BIGINT NOT NULL REFERENCES party_account(id), -- rôle fournisseur
    currency_code         VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    country_id            BIGINT NOT NULL REFERENCES ref_country(id),

    supplier_reference    VARCHAR(100) NOT NULL, -- numéro du document reçu, tel quel — pas de séquence interne

    entry_mode              VARCHAR(20) NOT NULL
                               CHECK (entry_mode IN ('free_entry', 'reconciliation')),

    status_code            VARCHAR(20) NOT NULL DEFAULT 'draft'
                              CHECK (status_code IN ('draft', 'validated', 'cancelled')),

    total_net_minor          BIGINT NOT NULL DEFAULT 0,
    total_tax_minor         BIGINT NOT NULL DEFAULT 0,
    total_fodec_minor       BIGINT NOT NULL DEFAULT 0,
    stamp_duty_minor        BIGINT NOT NULL DEFAULT 0, -- valeur lue telle quelle sur le document reçu, purement informative
    total_gross_minor         BIGINT NOT NULL DEFAULT 0,

    document_date           DATE, -- date portée par le document fournisseur lui-même
    attachment_path          VARCHAR(500), -- scan/PDF, cohérent avec ost_com_facture_fournisseur.path

    validated_at             TIMESTAMPTZ,
    validated_by             BIGINT REFERENCES party_account(id),
    created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                 BIGINT REFERENCES party_account(id),
    updated_at                  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE invoicing_supplier_invoice IS
'Facture fournisseur. entry_mode=free_entry : saisie libre type gestion
 commerciale généraliste, aucune réservation en face. entry_mode=reconciliation :
 issue du rapprochement (liste des booking_settlement non/partiellement
 facturés), montant reçu saisi par l''utilisateur sur la pièce physique.
 supplier_reference N''EST PAS une séquence générée — c''est le numéro du
 document reçu, copié tel quel.';

CREATE UNIQUE INDEX uq_invoicing_supplier_invoice_public_id ON invoicing_supplier_invoice(public_id);
CREATE INDEX idx_invoicing_supplier_invoice_party ON invoicing_supplier_invoice(party_account_id);
CREATE INDEX idx_invoicing_supplier_invoice_status ON invoicing_supplier_invoice(status_code) WHERE status_code = 'draft';

CREATE TRIGGER trg_invoicing_supplier_invoice_updated_at BEFORE UPDATE ON invoicing_supplier_invoice
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE invoicing_supplier_invoice_line (
    id                     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id              UUID NOT NULL DEFAULT gen_random_uuid(),
    supplier_invoice_id     BIGINT NOT NULL REFERENCES invoicing_supplier_invoice(id),

    origin_type              VARCHAR(10) NOT NULL CHECK (origin_type IN ('anchored', 'free')),

    -- Ligne ANCRÉE : référence le bénéficiaire exact d'une réservation.
    -- Symétrique de booking_payer_split_id côté vente. FK applicative.
    booking_settlement_id     BIGINT,
    booking_id                 BIGINT, -- dénormalisé, pour rapprochement/reporting

    free_label                  TEXT,

    amount_minor                 BIGINT NOT NULL CHECK (amount_minor > 0), -- montant reçu/saisi sur la pièce physique, peut être partiel vs amount_owed

    tax_rate_id                   BIGINT REFERENCES invoicing_tax_rate(id),
    tax_base_minor                 BIGINT NOT NULL DEFAULT 0,
    tax_amount_minor                 BIGINT NOT NULL DEFAULT 0,

    fodec_rate_id                     BIGINT REFERENCES invoicing_tax_rate(id),
    fodec_amount_minor                  BIGINT NOT NULL DEFAULT 0,

    sort_order                            SMALLINT NOT NULL DEFAULT 0,
    created_at                             TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_invoicing_supplier_invoice_line_origin CHECK (
        (origin_type = 'anchored' AND booking_settlement_id IS NOT NULL AND booking_id IS NOT NULL AND free_label IS NULL)
        OR
        (origin_type = 'free' AND booking_settlement_id IS NULL AND booking_id IS NULL AND free_label IS NOT NULL)
    )
);

COMMENT ON TABLE invoicing_supplier_invoice_line IS
'Symétrique de invoicing_invoice_line côté achat. Ancrage sur
 booking_settlement_id (pas booking_id) — le bénéficiaire exact. FODEC en
 taux par ligne, jamais un montant fixe par document (contrairement au
 timbre, qui n''a pas de mécanique de ligne porteuse côté fournisseur : la
 valeur lue sur le document reçu est portée globalement sur l''en-tête,
 purement informative).';

CREATE INDEX idx_invoicing_supplier_invoice_line_invoice ON invoicing_supplier_invoice_line(supplier_invoice_id, sort_order);
CREATE INDEX idx_invoicing_supplier_invoice_line_settlement ON invoicing_supplier_invoice_line(booking_settlement_id) WHERE booking_settlement_id IS NOT NULL;

-- Plafond symétrique du plafond de split côté vente : SUM(amount_minor
-- facturé sur ce settlement, toutes factures/statuts confondus) <=
-- booking_settlement.amount_owed. Même principe, même raison d'être.
CREATE OR REPLACE FUNCTION invoicing_check_supplier_invoice_line_settlement_cap()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
DECLARE
    v_settlement_amount BIGINT;
    v_already_invoiced   BIGINT;
BEGIN
    IF NEW.origin_type <> 'anchored' THEN
        RETURN NEW;
    END IF;

    SELECT amount_owed INTO v_settlement_amount
    FROM booking_settlement WHERE id = NEW.booking_settlement_id;

    IF v_settlement_amount IS NULL THEN
        RAISE EXCEPTION 'booking_settlement % introuvable', NEW.booking_settlement_id;
    END IF;

    SELECT COALESCE(SUM(amount_minor), 0) INTO v_already_invoiced
    FROM invoicing_supplier_invoice_line
    WHERE booking_settlement_id = NEW.booking_settlement_id AND id <> NEW.id;

    IF v_already_invoiced + NEW.amount_minor > v_settlement_amount THEN
        RAISE EXCEPTION
            'Plafond de facturation depasse sur booking_settlement % : deja facture % + nouvelle ligne % > montant du settlement %',
            NEW.booking_settlement_id, v_already_invoiced, NEW.amount_minor, v_settlement_amount;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_invoicing_supplier_invoice_line_check_settlement_cap
    BEFORE INSERT OR UPDATE ON invoicing_supplier_invoice_line
    FOR EACH ROW EXECUTE FUNCTION invoicing_check_supplier_invoice_line_settlement_cap();

-- ============================================================
-- AVOIR FOURNISSEUR — invoicing_supplier_credit_note / _line
-- JAMAIS généré automatiquement (asymétrie assumée et documentée) :
-- l'agence ne peut pas anticiper le document que le fournisseur émettra.
-- Toujours une saisie manuelle du document reçu du fournisseur.
-- ============================================================

CREATE TABLE invoicing_supplier_credit_note (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id            UUID NOT NULL DEFAULT gen_random_uuid(),

    party_account_id     BIGINT NOT NULL REFERENCES party_account(id),
    currency_code        VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    country_id           BIGINT NOT NULL REFERENCES ref_country(id),

    supplier_reference   VARCHAR(100) NOT NULL, -- numéro de l'avoir tel qu'émis par le fournisseur

    status_code           VARCHAR(20) NOT NULL DEFAULT 'draft'
                             CHECK (status_code IN ('draft', 'validated', 'cancelled')),

    total_net_minor         BIGINT NOT NULL DEFAULT 0,
    total_tax_minor        BIGINT NOT NULL DEFAULT 0,
    total_gross_minor        BIGINT NOT NULL DEFAULT 0,

    document_date            DATE,
    attachment_path            VARCHAR(500),

    validated_at               TIMESTAMPTZ,
    validated_by                 BIGINT REFERENCES party_account(id),
    created_at                    TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                      BIGINT REFERENCES party_account(id),
    updated_at                        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX uq_invoicing_supplier_credit_note_public_id ON invoicing_supplier_credit_note(public_id);
CREATE INDEX idx_invoicing_supplier_credit_note_party ON invoicing_supplier_credit_note(party_account_id);

CREATE TRIGGER trg_invoicing_supplier_credit_note_updated_at BEFORE UPDATE ON invoicing_supplier_credit_note
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE invoicing_supplier_credit_note_line (
    id                     BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id              UUID NOT NULL DEFAULT gen_random_uuid(),
    supplier_credit_note_id BIGINT NOT NULL REFERENCES invoicing_supplier_credit_note(id),

    supplier_invoice_line_id BIGINT REFERENCES invoicing_supplier_invoice_line(id), -- NULL toléré : le fournisseur peut avoir avoiré une pièce jamais saisie côté agence

    amount_minor             BIGINT NOT NULL CHECK (amount_minor > 0),
    tax_rate_id                BIGINT REFERENCES invoicing_tax_rate(id),
    tax_base_minor              BIGINT NOT NULL DEFAULT 0,
    tax_amount_minor              BIGINT NOT NULL DEFAULT 0,

    sort_order                     SMALLINT NOT NULL DEFAULT 0,
    created_at                      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_invoicing_supplier_credit_note_line_note ON invoicing_supplier_credit_note_line(supplier_credit_note_id, sort_order);

-- ============================================================
-- FONCTIONS DE POSTING — validation (numérotation + branchement Règlements)
-- ============================================================

-- Valide une facture client : assigne le numéro (sans gap), poste les
-- écritures Règlements pour les lignes LIBRES uniquement (les lignes
-- ancrées ne créent jamais d'écriture — l'obligation existe déjà).
CREATE OR REPLACE FUNCTION invoicing_post_validate_invoice(p_invoice_id BIGINT, p_by BIGINT DEFAULT NULL)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE
    v_year          SMALLINT := EXTRACT(YEAR FROM now());
    v_number        INTEGER;
    v_party_id      BIGINT;
    v_currency      VARCHAR(3);
    v_type_id       BIGINT;
    r               RECORD;
BEGIN
    SELECT party_account_id, currency_code INTO v_party_id, v_currency
    FROM invoicing_invoice WHERE id = p_invoice_id AND status_code = 'draft';

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Facture % introuvable ou déjà validée', p_invoice_id;
    END IF;

    v_number := invoicing_next_number('invoice', v_year);

    UPDATE invoicing_invoice
    SET status_code = 'validated', seq_year = v_year, invoice_number = v_number,
        validated_at = now(), validated_by = p_by
    WHERE id = p_invoice_id;

    SELECT id INTO v_type_id FROM settlement_entry_type WHERE code = 'obligation_vente';

    FOR r IN
        SELECT id, amount_minor FROM invoicing_invoice_line
        WHERE invoice_id = p_invoice_id AND origin_type = 'free'
    LOOP
        INSERT INTO settlement_ledger_entry
            (party_account_id, party_role, currency_code, entry_type_id,
             amount_minor, effective_date, invoice_id, memo, created_by)
        VALUES
            (v_party_id, 'client', v_currency, v_type_id,
             r.amount_minor, CURRENT_DATE, p_invoice_id,
             'Facture libre #' || v_number, p_by);
    END LOOP;
END;
$$;

COMMENT ON FUNCTION invoicing_post_validate_invoice IS
'Seul chemin de validation d''une facture client. Numérotation garantie sans
 gap (verrou implicite via invoicing_next_number). Ne pose une écriture
 Règlements QUE pour les lignes libres — les lignes ancrées documentent une
 obligation déjà projetée à la validation de la réservation.';

-- Valide un avoir client : assigne le numéro, poste une contre-passation
-- Règlements dans TOUS les cas (ancré ou libre) puisqu'un avoir EST une
-- correction, donc toujours une écriture nouvelle (jamais un UPDATE).
CREATE OR REPLACE FUNCTION invoicing_post_validate_credit_note(p_credit_note_id BIGINT, p_by BIGINT DEFAULT NULL)
RETURNS VOID LANGUAGE plpgsql AS $$
DECLARE
    v_year      SMALLINT := EXTRACT(YEAR FROM now());
    v_number    INTEGER;
    v_party_id  BIGINT;
    v_currency  VARCHAR(3);
    v_type_id   BIGINT;
    v_total     BIGINT;
BEGIN
    SELECT party_account_id, currency_code, total_gross_minor
    INTO v_party_id, v_currency, v_total
    FROM invoicing_credit_note WHERE id = p_credit_note_id AND status_code = 'draft';

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Avoir % introuvable ou déjà validé', p_credit_note_id;
    END IF;

    v_number := invoicing_next_number('credit_note', v_year);

    UPDATE invoicing_credit_note
    SET status_code = 'validated', seq_year = v_year, credit_note_number = v_number,
        validated_at = now(), validated_by = p_by
    WHERE id = p_credit_note_id;

    SELECT id INTO v_type_id FROM settlement_entry_type WHERE code = 'reversal';

    INSERT INTO settlement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, credit_note_id, memo, created_by)
    VALUES
        (v_party_id, 'client', v_currency, v_type_id,
         -v_total, CURRENT_DATE, p_credit_note_id,
         'Avoir #' || v_number, p_by);
END;
$$;

COMMENT ON FUNCTION invoicing_post_validate_credit_note IS
'Seul chemin de validation d''un avoir client. Poste TOUJOURS une écriture
 de contre-passation (type reversal), ancré ou libre — un avoir est par
 nature une correction, donc toujours une écriture nouvelle en append-only.
 Ne rouvre jamais booking_payer_split.';

-- ============================================================
-- VUE DE RAPPROCHEMENT FOURNISSEUR
-- Étend le mécanisme de rapprochement (liste des settlements non/
-- partiellement facturés) en y ajoutant la détection d'incohérence
-- (montant facturé qui ne correspond plus à l'obligation projetée après
-- une correction Booking).
-- ============================================================
CREATE OR REPLACE VIEW invoicing_supplier_reconciliation AS
SELECT
    bs.id                                  AS booking_settlement_id,
    bs.booking_id,
    bs.beneficiary_account_id,
    bs.amount_owed,
    bs.currency_code,
    COALESCE(SUM(sil.amount_minor), 0)     AS amount_invoiced,
    COALESCE(SUM(scnl.amount_minor), 0)    AS amount_credited,
    bs.amount_owed
        - COALESCE(SUM(sil.amount_minor), 0)
        + COALESCE(SUM(scnl.amount_minor), 0) AS amount_remaining
FROM booking_settlement bs
LEFT JOIN invoicing_supplier_invoice_line sil
    ON sil.booking_settlement_id = bs.id
LEFT JOIN invoicing_supplier_invoice si
    ON si.id = sil.supplier_invoice_id AND si.status_code = 'validated'
LEFT JOIN invoicing_supplier_credit_note_line scnl
    ON scnl.supplier_invoice_line_id = sil.id
LEFT JOIN invoicing_supplier_credit_note scn
    ON scn.id = scnl.supplier_credit_note_id AND scn.status_code = 'validated'
WHERE bs.valid_to IS NULL AND bs.beneficiary_role = 'fournisseur'
GROUP BY bs.id, bs.booking_id, bs.beneficiary_account_id, bs.amount_owed, bs.currency_code;

COMMENT ON VIEW invoicing_supplier_reconciliation IS
'amount_remaining > 0 : réservation non/partiellement facturée (le cas normal
 du rapprochement, présenté à l''utilisateur). amount_remaining < 0 : la
 facture fournisseur reçue dépasse l''obligation projetée — alerte
 d''incohérence, potentiellement un avoir fournisseur attendu et non encore
 reçu.';

-- ============================================================
-- BRANCHEMENT DES CROCHETS RÈGLEMENTS (ADDITIF — n'altère aucune donnée
-- existante, ne rouvre pas la conception figée de Règlements, formalise
-- juste les FK laissées non déclarées volontairement à l'époque).
-- ============================================================
ALTER TABLE settlement_ledger_entry
    ADD CONSTRAINT fk_settlement_ledger_entry_invoice
    FOREIGN KEY (invoice_id) REFERENCES invoicing_invoice(id);

ALTER TABLE settlement_ledger_entry
    ADD CONSTRAINT fk_settlement_ledger_entry_credit_note
    FOREIGN KEY (credit_note_id) REFERENCES invoicing_credit_note(id);

-- ============================================================
-- DONNÉES DE RÉFÉRENCE INITIALES (Tunisie, exemple)
-- ============================================================
-- À adapter : country_id lu depuis ref_country (référentiel figé,
-- alimenté par OctaSoft). Exemple ci-dessous suppose la Tunisie déjà présente.

-- ============================================================
-- NOTES D'IMPLÉMENTATION
-- ============================================================
-- 1. invoicing_post_credit_from_cancellation() : signature à définir avec
--    l'équipe dev (dépend de l'API applicative Booking/Symfony), suit le
--    même patron que invoicing_post_validate_credit_note ci-dessus.
--    Non implémentée en V1 pour la même raison que settlement_post_obligation()
--    dans schema-settlement-v1.sql.
--
-- 2. total_stamp_minor recalculé par l'application à chaque mutation de
--    ligne (jamais par trigger) — cohérent avec ADR-002 (booking_charge).
--
-- 3. Réémission après avoir sur split figé : hors mécanisme natif, à traiter
--    en exception documentée (voir modele-conceptuel-facturation.md).
