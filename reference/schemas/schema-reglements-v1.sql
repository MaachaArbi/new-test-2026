-- ============================================================================
-- schema-reglements-v1.sql — Module Règlements Client/Fournisseur
-- ERP Tourisme. PostgreSQL 16+.
--
-- Statut     : V1.0 — 16 juillet 2026
-- Dépend de  : schema-party-account-v1.sql, schema-ref-common.sql,
--              schema-booking-v1.sql (lu, jamais modifié)
-- Ordre      : 5ème script à exécuter (après booking_)
--
-- PRINCIPE   : grand livre append-only, immuable, à écritures signées,
--              scopé par (party_account_id, party_role, currency_code).
--              Simple partie — crochet posé pour le futur Cash Management.
--              Toute correction = écriture nouvelle. Jamais d'UPDATE/DELETE.
-- ============================================================================

-- ============================================================
-- RÉFÉRENTIELS
-- ============================================================

-- Mode de règlement. 'is_cash_like' = transit physique caisse/banque
-- (chèque, espèce, lettre de change) vs engagement scriptural (virement,
-- CB, autorisation de débit). Crochet pour le futur module Cash Management
-- qui voudra distinguer les flux physiques des flux scripturaux.
CREATE TABLE reglement_payment_method (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id     UUID NOT NULL DEFAULT gen_random_uuid(),
    code          VARCHAR(4)  NOT NULL UNIQUE,
    label         VARCHAR(60) NOT NULL,
    is_cash_like  BOOLEAN NOT NULL DEFAULT false,
    is_active     BOOLEAN NOT NULL DEFAULT true,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE reglement_payment_method IS
'Modes de règlement (table, jamais ENUM). is_cash_like = crochet Cash Management.
 Valeurs initiales : AD (Autorisation débit), CB, C (Chèque), E (Espèce),
 V (Virement), VE (Versement espèce), LC (Lettre de change), PC (Bon commande),
 RC (Retenue à la source), RI (Ristourne).
 NB : AD est conservé pour les pièces historiques de migration mais ne doit plus
 être utilisé en création (l''obligation non payée est exprimée par le solde).';

CREATE UNIQUE INDEX uq_reglement_payment_method_public_id ON reglement_payment_method(public_id);

-- Nature d''une écriture dans le grand livre.
-- normal_sign : signe attendu (+1 débit = le tiers nous doit,
--               -1 crédit = payé ou on lui doit).
-- Le signe réel fait foi sur amount_minor ; normal_sign est documentaire
-- et sert aux vues de contrôle.
CREATE TABLE reglement_entry_type (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id     UUID NOT NULL DEFAULT gen_random_uuid(),
    code          VARCHAR(30) NOT NULL UNIQUE,
    label         VARCHAR(80) NOT NULL,
    normal_sign   SMALLINT NOT NULL CHECK (normal_sign IN (-1, 1)),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE reglement_entry_type IS
'Nature des écritures du grand livre. Extensible sans migration.
 Valeurs initiales :
   obligation_vente      (+1) : obligation client projetée depuis booking_payer_split
   obligation_achat      (+1) : obligation fournisseur (ex-impayé fournisseur)
   reglement_client      (-1) : pièce reçue du client
   reglement_fournisseur (-1) : pièce versée au fournisseur
   reversal              (±1) : contre-passation (signe opposé à l''écriture annulée)
   deposit               (-1) : dépôt/avance B2B sans réservation en face
   remboursement_client  (+1) : remboursement sortant vers client
   transfert_solde       (±1) : jambe d''un transfert inter-livres';

CREATE UNIQUE INDEX uq_reglement_entry_type_public_id ON reglement_entry_type(public_id);

-- ============================================================
-- INSTRUMENT DE PAIEMENT (la "pièce")
-- Instrument physique/scriptural avec son cycle de vie propre.
-- Ce n''est PAS une écriture : une pièce produit des écritures
-- (crédits dans le grand livre) via le lettrage.
-- ============================================================
CREATE TABLE reglement_instrument (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),

    -- Tiers porteur. Un seul tiers par pièce (client ou fournisseur).
    -- L''amicale et l''employé sont deux party_account distincts dans Party —
    -- la répartition entre eux est portée par booking_payer_split, pas ici.
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    party_role         VARCHAR(20) NOT NULL
                         CHECK (party_role IN ('client','fournisseur')),

    -- Devise native. Une pièce DT ne règle qu''un livre DT.
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),

    payment_method_id  BIGINT NOT NULL REFERENCES reglement_payment_method(id),

    -- Montant nominal, IMMUABLE, en minor units (convention projet BIGINT).
    -- Le "restant à allouer" est un dérivé du lettrage, jamais stocké :
    --   amount_minor - SUM(matched_amount_minor) sur reglement_matching actif.
    -- Résout l''écueil legacy (ost_com_piece.montant se décrémentait et
    -- se désynchronisait de ost_com_piece.montant_alloue).
    amount_minor       BIGINT NOT NULL CHECK (amount_minor > 0),

    -- Référence externe de l''instrument (n° chèque, n° autorisation CB...).
    -- Champ dédié — ne pas réutiliser un provider_reference Booking.
    instrument_ref     VARCHAR(100),

    -- Métadonnées d''instrument selon le mode (banque, guichet, titulaire,
    -- bordereau de remise). Verbeux et propre à chaque type -> JSONB.
    -- Ne pas coloniser : bank_name et due_date sont promus en colonne
    -- car utiles au futur module Cash Management.
    bank_name          VARCHAR(150),
    due_date           DATE,                     -- échéance (ex DateEcheance legacy)
    issued_on          DATE,                     -- date d''émission
    metadata           JSONB NOT NULL DEFAULT '{}'::jsonb,

    -- Cycle de vie. Un retour/annulation NE MUTE PAS le grand livre :
    -- une écriture inverse datée est postée (voir règles applicatives).
    status_code        VARCHAR(20) NOT NULL DEFAULT 'active'
                         CHECK (status_code IN ('active','returned','cancelled')),
    status_changed_at  TIMESTAMPTZ,
    status_reason      TEXT,

    office_account_id  BIGINT REFERENCES party_account(id), -- bureau encaisseur

    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE reglement_instrument IS
'Pièce de règlement (chèque, virement, espèces, CB, bon de commande...).
 Un instrument produit des crédits dans le grand livre via reglement_matching.
 amount_minor est immuable ; le restant est un dérivé du lettrage.
 L''autorisation de débit (AD) ne doit plus être créée comme instrument :
 l''obligation non payée est exprimée par l''absence de crédit dans le grand livre.
 Les AD historiques migrées conservent leur DateEcheance dans due_date.';

CREATE UNIQUE INDEX uq_reglement_instrument_public_id ON reglement_instrument(public_id);
CREATE INDEX idx_reglement_instrument_party ON reglement_instrument(party_account_id, currency_code);
CREATE INDEX idx_reglement_instrument_status ON reglement_instrument(status_code)
    WHERE status_code <> 'active';

-- ============================================================
-- TRANSFERT DE SOLDE
-- Déclaré avant reglement_ledger_entry car celle-ci le référence.
-- ============================================================
CREATE TABLE reglement_transfer (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id            UUID NOT NULL DEFAULT gen_random_uuid(),

    -- Les deux livres concernés. Aucune contrainte de parenté (n''importe
    -- quels deux comptes distincts dans la même devise).
    source_account_id    BIGINT NOT NULL REFERENCES party_account(id),
    source_role          VARCHAR(20) NOT NULL CHECK (source_role IN ('client','fournisseur')),
    target_account_id    BIGINT NOT NULL REFERENCES party_account(id),
    target_role          VARCHAR(20) NOT NULL CHECK (target_role IN ('client','fournisseur')),

    currency_code        VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    -- Partiel autorisé : amount_minor peut être inférieur au solde source.
    amount_minor         BIGINT NOT NULL CHECK (amount_minor > 0),
    effective_date       DATE NOT NULL,
    reason               TEXT,                   -- "report solde employé Ahmed"

    -- Annulation en bloc : un transfert annulé pointe l''original.
    -- L''annulation passe aussi par reglement_post_transfer_reversal()
    -- pour créer les deux jambes inverses.
    reverses_transfer_id BIGINT REFERENCES reglement_transfer(id),

    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by           BIGINT REFERENCES party_account(id),

    CONSTRAINT chk_transfer_distinct CHECK (
        NOT (source_account_id = target_account_id AND source_role = target_role)
    )
);

COMMENT ON TABLE reglement_transfer IS
'Transfert de solde entre deux livres quelconques (même devise).
 Usage primaire : report de dette employé -> amicale.
 Partiel autorisé. Annulable en bloc via reverses_transfer_id.
 Toujours créé via reglement_post_transfer() — jamais d''INSERT direct.
 La FK transfer_id sur reglement_ledger_entry empêche toute jambe orpheline.';

CREATE UNIQUE INDEX uq_reglement_transfer_public_id ON reglement_transfer(public_id);
CREATE INDEX idx_reglement_transfer_source ON reglement_transfer(source_account_id, currency_code);
CREATE INDEX idx_reglement_transfer_target ON reglement_transfer(target_account_id, currency_code);

-- ============================================================
-- GRAND LIVRE — cœur immuable du module.
-- Une ligne = un fait économique daté. Append-only.
-- ============================================================
CREATE TABLE reglement_ledger_entry (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),

    -- Clé de livre : (tiers, rôle, devise). Un tiers a autant de livres
    -- que de devises. La devise est native : jamais de conversion dans le livre.
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    party_role         VARCHAR(20) NOT NULL
                         CHECK (party_role IN ('client','fournisseur')),
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),

    entry_type_id      BIGINT NOT NULL REFERENCES reglement_entry_type(id),

    -- Montant SIGNÉ, minor units. + débit (le tiers nous doit),
    -- − crédit (payé / on lui doit). Jamais 0 (une écriture nulle n''est
    -- pas un fait économique — les lettrages à 0 du legacy sont filtrés).
    amount_minor       BIGINT NOT NULL CHECK (amount_minor <> 0),

    -- Date comptable (effet économique) vs created_at (saisie système).
    -- Annulation aujourd''hui d''une résa de mars → effective_date = aujourd''hui.
    -- Le solde de mars ne bouge jamais. Modèle Amadeus.
    effective_date     DATE NOT NULL,

    -- Origines — au moins une obligatoire (contrainte ci-dessous).
    booking_id         BIGINT,       -- obligation projetée depuis Booking
    instrument_id      BIGINT REFERENCES reglement_instrument(id),
    invoice_id         BIGINT,       -- facture (futur module Facturation, lu par id)
    credit_note_id     BIGINT,       -- avoir  (futur module Facturation, lu par id)
    transfer_id        BIGINT REFERENCES reglement_transfer(id),

    -- Contre-passation : pointe l''écriture annulée.
    -- Modification de montant : reverse de l''ancienne obligation + nouvelle.
    reverses_entry_id  BIGINT REFERENCES reglement_ledger_entry(id),

    memo               TEXT,         -- libellé lisible sur relevé

    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by         BIGINT REFERENCES party_account(id),

    CONSTRAINT chk_entry_has_origin CHECK (
        booking_id        IS NOT NULL OR
        instrument_id     IS NOT NULL OR
        invoice_id        IS NOT NULL OR
        credit_note_id    IS NOT NULL OR
        reverses_entry_id IS NOT NULL OR
        transfer_id       IS NOT NULL
    )
);

COMMENT ON TABLE reglement_ledger_entry IS
'Grand livre append-only. Une écriture = un fait économique daté et immuable.
 Toute correction est une nouvelle écriture (contre-passation), jamais un UPDATE.
 Le solde d''un compte = SUM(amount_minor) sur ses lignes.
 Le solde d''une période passée est stable par construction (effective_date).
 RÈGLE APPLICATIVE : toujours passer par les fonctions de posting quand
 elles existent (reglement_post_transfer ; à terme reglement_post_obligation,
 reglement_post_credit, reglement_post_reversal — voir NOTES D''IMPLÉMENTATION
 ci-dessous). Tant qu''une fonction dédiée n''existe pas, un INSERT
 Domain-contrôlé respectant les mêmes invariants (transaction, signe,
 cohérence des jambes) est acceptable — la règle interdit le contournement
 de ces invariants, pas l''absence de fonction SQL. Jamais d''INSERT qui
 ignore le signe attendu (reglement_entry_type.normal_sign) ou la
 correspondance transfer_id/jambes.';

-- Index principal : lecture par livre, trié par date.
-- Sert le relevé d''un compte (solde progressif) et l''alimentation du snapshot.
CREATE INDEX idx_reglement_ledger_book ON reglement_ledger_entry
    (party_account_id, party_role, currency_code, effective_date, id);

CREATE UNIQUE INDEX uq_reglement_ledger_entry_public_id ON reglement_ledger_entry(public_id);
CREATE INDEX idx_reglement_ledger_booking   ON reglement_ledger_entry(booking_id)   WHERE booking_id   IS NOT NULL;
CREATE INDEX idx_reglement_ledger_instrument ON reglement_ledger_entry(instrument_id) WHERE instrument_id IS NOT NULL;
CREATE INDEX idx_reglement_ledger_transfer  ON reglement_ledger_entry(transfer_id)  WHERE transfer_id  IS NOT NULL;

-- IMMUABILITÉ : garantie structurelle, pas seulement par convention.
CREATE OR REPLACE FUNCTION reglement_ledger_block_mutation()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION
        'reglement_ledger_entry est append-only : % interdit (id=%). '
        'Toute correction est une écriture nouvelle (contre-passation).',
        TG_OP, OLD.id;
END;
$$;

CREATE TRIGGER trg_reglement_ledger_no_mutation
    BEFORE UPDATE OR DELETE ON reglement_ledger_entry
    FOR EACH ROW EXECUTE FUNCTION reglement_ledger_block_mutation();

-- ============================================================
-- SNAPSHOT DE SOLDE
-- Maintenu incrémentalement à chaque INSERT dans le grand livre.
-- "Grand livre de tous les comptes" = scan O(comptes) sur cette table.
-- ============================================================
CREATE TABLE reglement_balance (
    party_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    party_role         VARCHAR(20) NOT NULL,
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    balance_minor      BIGINT NOT NULL DEFAULT 0,
    -- + tiers nous doit / − avance ou créditeur
    last_entry_id      BIGINT REFERENCES reglement_ledger_entry(id),
    entry_count        BIGINT NOT NULL DEFAULT 0,
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (party_account_id, party_role, currency_code)
);

COMMENT ON TABLE reglement_balance IS
'Snapshot de solde par (compte, rôle, devise), maintenu par trigger.
 balance_minor > 0 : le tiers nous doit.
 balance_minor < 0 : avance ou excédent créditeur (ex: annulation avec remboursement en attente).
 Reconcilie toujours avec SUM(amount_minor) à froid sur reglement_ledger_entry.
 Robuste au backdating (SUM est ordre-indépendant).
 NB prod : en cas de forte concurrence sur un même compte, préférer le maintien
 applicatif transactionnel (UPDATE + SELECT FOR UPDATE) au trigger.';

CREATE OR REPLACE FUNCTION reglement_balance_apply()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO reglement_balance AS b
        (party_account_id, party_role, currency_code, balance_minor, last_entry_id, entry_count)
    VALUES
        (NEW.party_account_id, NEW.party_role, NEW.currency_code,
         NEW.amount_minor, NEW.id, 1)
    ON CONFLICT (party_account_id, party_role, currency_code) DO UPDATE
        SET balance_minor = b.balance_minor + NEW.amount_minor,
            last_entry_id  = NEW.id,
            entry_count    = b.entry_count + 1,
            updated_at     = now();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_reglement_balance_apply
    AFTER INSERT ON reglement_ledger_entry
    FOR EACH ROW EXECUTE FUNCTION reglement_balance_apply();

-- ============================================================
-- LETTRAGE
-- Overlay N-N optionnel. Ne touche jamais le solde.
-- ============================================================
CREATE TABLE reglement_matching (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id             UUID NOT NULL DEFAULT gen_random_uuid(),

    -- Les deux écritures lettrées doivent appartenir au même livre
    -- (même party_account_id + party_role + currency_code).
    -- Cette cohérence est applicative (pas une contrainte SQL transversale).
    debit_entry_id        BIGINT NOT NULL REFERENCES reglement_ledger_entry(id),
    credit_entry_id       BIGINT NOT NULL REFERENCES reglement_ledger_entry(id),

    -- Montant lettré (partiel autorisé des deux côtés).
    matched_amount_minor  BIGINT NOT NULL CHECK (matched_amount_minor > 0),

    is_automatic          BOOLEAN NOT NULL DEFAULT false,
    -- Groupe visuel sur le relevé (lettre A, B, C... comme dans le legacy)
    match_group           VARCHAR(30),

    matched_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    matched_by            BIGINT REFERENCES party_account(id),
    -- Défaisable (soft). L''historique reste, les écritures du grand livre
    -- ne sont pas touchées.
    unmatched_at          TIMESTAMPTZ,
    unmatched_by          BIGINT REFERENCES party_account(id),

    CONSTRAINT chk_matching_distinct CHECK (debit_entry_id <> credit_entry_id)
);

COMMENT ON TABLE reglement_matching IS
'Lettrage N-N optionnel entre écritures de débit (obligations) et de crédit
 (règlements). Ne touche pas le solde — le solde vient du grand livre seul.
 Modes coexistants :
   - Lettrage direct (B2C, CB automatique) : is_automatic=true, généré à la saisie.
   - Débit/crédit sans lettrage (B2B compte courant) : absence de lignes ici.
 Une pièce peut payer N réservations, une réservation peut être payée par N pièces.
 Le montant restant à allouer sur une pièce = instrument.amount_minor
   − SUM(matched_amount_minor) WHERE credit_entry_id = <écriture de la pièce>
     AND unmatched_at IS NULL.';

CREATE INDEX idx_reglement_matching_debit  ON reglement_matching(debit_entry_id)  WHERE unmatched_at IS NULL;
CREATE INDEX idx_reglement_matching_credit ON reglement_matching(credit_entry_id) WHERE unmatched_at IS NULL;
CREATE UNIQUE INDEX uq_reglement_matching_public_id ON reglement_matching(public_id);

-- ============================================================
-- FONCTIONS DE POSTING — seuls chemins autorisés en écriture.
-- L''application n''insère jamais directement dans reglement_ledger_entry.
-- ============================================================

-- Transfert atomique (+ ses deux jambes) — voir modele-conceptuel-reglements.md
CREATE OR REPLACE FUNCTION reglement_post_transfer(
    p_source_id    BIGINT,
    p_source_role  VARCHAR,
    p_target_id    BIGINT,
    p_target_role  VARCHAR,
    p_currency     VARCHAR(3),
    p_amount       BIGINT,
    p_date         DATE,
    p_reason       TEXT,
    p_created_by   BIGINT DEFAULT NULL
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_transfer_id BIGINT;
    v_type_id     BIGINT;
BEGIN
    SELECT id INTO v_type_id
    FROM reglement_entry_type WHERE code = 'transfert_solde';

    INSERT INTO reglement_transfer
        (source_account_id, source_role, target_account_id, target_role,
         currency_code, amount_minor, effective_date, reason, created_by)
    VALUES
        (p_source_id, p_source_role, p_target_id, p_target_role,
         p_currency, p_amount, p_date, p_reason, p_created_by)
    RETURNING id INTO v_transfer_id;

    -- Jambe SOURCE : on retire du dû (crédit, signe négatif)
    INSERT INTO reglement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, transfer_id, memo, created_by)
    VALUES
        (p_source_id, p_source_role, p_currency, v_type_id,
         -p_amount, p_date, v_transfer_id,
         'Transfert sortant : ' || coalesce(p_reason, ''), p_created_by);

    -- Jambe CIBLE : on ajoute du dû (débit, signe positif)
    INSERT INTO reglement_ledger_entry
        (party_account_id, party_role, currency_code, entry_type_id,
         amount_minor, effective_date, transfer_id, memo, created_by)
    VALUES
        (p_target_id, p_target_role, p_currency, v_type_id,
         p_amount, p_date, v_transfer_id,
         'Transfert entrant : ' || coalesce(p_reason, ''), p_created_by);

    RETURN v_transfer_id;
END;
$$;

COMMENT ON FUNCTION reglement_post_transfer IS
'Crée un transfert de solde + ses 2 jambes dans une seule transaction.
 Impossible d''obtenir un demi-transfert. La FK transfer_id empêche toute
 jambe orpheline. Partiel autorisé (p_amount < solde source).
 Résultat : id du reglement_transfer créé.';

-- ============================================================
-- DONNÉES DE RÉFÉRENCE INITIALES
-- ============================================================

INSERT INTO reglement_payment_method (code, label, is_cash_like) VALUES
    ('AD', 'Autorisation de débit',               false),
    ('CB', 'Carte Bancaire',                       false),
    ('C',  'Chèque',                               true),
    ('E',  'Espèce',                               true),
    ('V',  'Virement bancaire',                    false),
    ('VE', 'Versement espèce',                     false),
    ('LC', 'Lettre de change',                     true),
    ('PC', 'Prise en charge / Bon de Commande',    true),
    ('RC', 'Retenue à la source',                  true),
    ('PE', 'Paiement électronique',                false),
    ('RI', 'Ristourne',                            false);

INSERT INTO reglement_entry_type (code, label, normal_sign) VALUES
    ('obligation_vente',      'Obligation client (réservation validée)',         1),
    ('obligation_achat',      'Obligation fournisseur (rattachement réservation)',1),
    ('reglement_client',      'Règlement reçu du client',                       -1),
    ('reglement_fournisseur', 'Règlement versé au fournisseur',                 -1),
    ('reversal',              'Contre-passation',                                1),
    ('deposit',               'Dépôt / avance (sans réservation en face)',      -1),
    ('remboursement_client',  'Remboursement sortant vers client',               1),
    ('transfert_solde',       'Jambe de transfert inter-livres',                 1);

-- ============================================================
-- NOTES D''IMPLÉMENTATION
-- ============================================================
-- 1. FK vers party_account, ref_currency : assurées dans ce script.
--    Les références booking_id, invoice_id, credit_note_id sont
--    applicatives (FK non déclarées car les tables cibles sont dans
--    d''autres modules / partitionnées pour booking_).
--
-- 2. Concurrence sur reglement_balance : le trigger AFTER INSERT
--    (INSERT ... ON CONFLICT ... DO UPDATE) convient et reste correct sous
--    forte charge -- l'opération est atomique, verrou de ligne PostgreSQL
--    implicite, aucune race condition de type lost-update. Le seul risque
--    est une CONTENTION (attente de verrou) si plusieurs transactions
--    postent SIMULTANÉMENT sur le même triplet (party_account_id,
--    party_role, currency_code) -- des comptes différents ne se bloquent
--    jamais entre eux (PK scope par compte).
--    Volume réel confirmé (22/07/2026) : 100-200 règlements/jour max,
--    très en dessous de tout seuil de contention réaliste -- pas
--    d'anticipation nécessaire en V1 (méthode : pas d'optimisation sans
--    preuve réelle). Seuil de bascule si le volume changeait radicalement :
--    remplacer par UPDATE + SELECT FOR UPDATE côté application (transaction
--    explicite) si un monitoring de lock wait le confirme, ou en prévision
--    d'un traitement batch à très haute fréquence sur un même compte.
--
-- 3. Fonctions de posting manquantes à compléter :
--    - reglement_post_obligation()  : projection d''une obligation depuis Booking
--    - reglement_post_credit()      : crédit issu d''une pièce
--    - reglement_post_reversal()    : contre-passation d''une écriture existante
--    Ces fonctions suivent le même patron que reglement_post_transfer().
--    Non incluses en V1 car leur signature dépend de l''API applicative
--    (Symfony service layer) — à définir avec l''équipe dev. En attendant,
--    un INSERT Domain-contrôlé respectant les mêmes invariants est
--    acceptable (voir commentaire de reglement_ledger_entry).
--
-- 4. SolvencyCheckerInterface (stub Booking, toujours true) :
--    l''implémentation réelle lira reglement_balance.balance_minor
--    pour le compte concerné et le comparera au plafond du futur
--    module Pricing/Finance.
--
-- 5. pg_partman : non inclus ici. reglement_ledger_entry n''est pas
--    partitionnée en V1 (volume estimé modéré vs booking_). À réévaluer
--    si le volume dépasse 10M de lignes en production.
