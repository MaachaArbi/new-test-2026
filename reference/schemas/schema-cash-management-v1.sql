-- ============================================================================
-- schema-cash-management-v1.sql — Module Cash Management (Caisses & Banques)
-- ERP Tourisme. PostgreSQL 16+.
--
-- Statut     : V1.0 — 17 juillet 2026
-- Dépend de  : schema-ref-common.sql, schema-party-account-v1.sql,
--              schema-booking-v1.sql (non référencé directement),
--              schema-settlement-v1.sql (CORRIGÉ : currency_id -> currency_code,
--              voir diff livré séparément — appliquer AVANT ce script)
-- Ordre      : 6ème script à exécuter (après settlement_)
--
-- PRINCIPE   : deux journaux jumeaux append-only (sessions de caisse, comptes
--              bancaires), reliés par le bordereau de remise et la
--              transmission externe. Cash Management ne recalcule JAMAIS un
--              solde tiers — il consomme settlement_payment_method via la
--              table compagnon cash_payment_method_routing pour savoir où
--              router physiquement chaque pièce, sans aucun code en dur.
--              La garde physique d''une pièce est dérivée (cash_instrument_
--              location), jamais saisie manuellement.
-- ============================================================================

-- ============================================================
-- 1. ROUTING — table compagnon de settlement_payment_method
--    Décision : conception neuve, aucun mode de règlement en dur.
-- ============================================================

CREATE TABLE cash_routing_type (
    code        VARCHAR(40) PRIMARY KEY,
    label       VARCHAR(80) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_routing_type IS
'Destination physique d''un mode de règlement une fois la pièce créée dans Règlements.
 caisse = transite par une session utilisateur ; direct_bank = atterrit
 directement en banque sans jamais passer par une caisse (ex: virement reçu,
 versement espèce au guichet bancaire) ; external_transmission = doit être
 physiquement transmis à un tiers émetteur avant remboursement (ex: bon de
 commande amicale) ; aucun = scriptural pur, Cash Management ne le voit jamais.';

INSERT INTO cash_routing_type (code, label) VALUES
    ('cash_session',               'Passe par une session de caisse'),
    ('direct_bank',       'Atterrit directement en banque, sans caisse'),
    ('external_transmission', 'Transmis physiquement à un tiers émetteur'),
    ('none',                'Scriptural pur, hors périmètre Cash Management');

CREATE TABLE cash_payment_method_routing (
    payment_method_id        BIGINT PRIMARY KEY REFERENCES settlement_payment_method(id),
    routing_type_code        VARCHAR(40) NOT NULL REFERENCES cash_routing_type(code),

    -- Fongibilité : 'individual' = la pièce garde son lien vers l'instrument/
    -- le client jusqu'au dépôt en banque ou à la transmission (résout la
    -- perte de traçabilité espèces legacy). 'aggregate' = fondu en un seul
    -- montant par devise (comportement legacy historique). 'not_applicable'
    -- pour routing_type_code='none'.
    instrument_tracking_mode VARCHAR(20) NOT NULL
                                CHECK (instrument_tracking_mode IN ('individual','aggregate','not_applicable')),

    -- Option rare (1 déploiement sur ~100 à ce jour) : l'espèce individuellement
    -- trackée de ce mode de règlement ne peut JAMAIS financer un décaissement
    -- (paiement fournisseur, frais...) — seule une caisse alimentée par une
    -- autre source (transfert, alimentation libre) peut le faire. Cohérent
    -- avec ADR-004 (1 base = 1 client) : ce flag est global au déploiement,
    -- pas besoin de le scoper par bureau.
    strict_source_isolation  BOOLEAN NOT NULL DEFAULT false,

    requires_custody_check   BOOLEAN NOT NULL DEFAULT true,  -- "on ne verse que ce qu'on détient"
    is_active                BOOLEAN NOT NULL DEFAULT true,

    created_at                TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at                TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_routing_tracking_consistency CHECK (
        (routing_type_code = 'none' AND instrument_tracking_mode = 'not_applicable')
        OR (routing_type_code <> 'none' AND instrument_tracking_mode <> 'not_applicable')
    )
);

COMMENT ON TABLE cash_payment_method_routing IS
'Extension 1-1 de settlement_payment_method (même pattern que party_account_office
 sur party_account). Créer un nouveau mode de règlement = ajouter une ligne
 settlement_payment_method + une ligne ici. Le moteur ne connaît plus aucun
 code de mode de règlement en dur : il lit routing_type_code et
 instrument_tracking_mode. is_cash_like (Règlements) répond à "transite
 physiquement" ; routing_type_code répond à "vers où" — les deux peuvent
 diverger (ex: virement V est is_cash_like=false côté Règlements mais
 routing_type_code=direct_bank ici, car il doit être rapproché sur relevé
 même sans jamais toucher une caisse).';

CREATE TRIGGER trg_cash_payment_method_routing_updated_at BEFORE UPDATE ON cash_payment_method_routing
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Seed initial, aligné sur les modes de règlement Règlements V1.0.
-- Choix marqués (*) à confirmer avec l'utilisateur — posés par défaut raisonnable.
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'none', 'not_applicable', false FROM settlement_payment_method WHERE code IN ('AD','CB','PE');
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'cash_session', 'individual', false FROM settlement_payment_method WHERE code IN ('C','LC');
-- Espèce : individual + isolation stricte activée (décision actée pour ce déploiement).
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'cash_session', 'individual', true FROM settlement_payment_method WHERE code = 'E';
-- Bon de commande / prise en charge : transmission externe vers l'amicale émettrice.
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'external_transmission', 'individual', false FROM settlement_payment_method WHERE code = 'PC';
-- (*) Virement : jamais de caisse, mais doit être rapproché sur relevé -> direct_bank.
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'direct_bank', 'individual', false FROM settlement_payment_method WHERE code = 'V';
-- (*) Versement espèce : le client dépose lui-même au guichet bancaire, jamais notre caisse.
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'direct_bank', 'individual', false FROM settlement_payment_method WHERE code = 'VE';
-- (*) Retenue à la source, Ristourne : déduction scripturale, pas de flux physique.
INSERT INTO cash_payment_method_routing (payment_method_id, routing_type_code, instrument_tracking_mode, strict_source_isolation)
SELECT id, 'none', 'not_applicable', false FROM settlement_payment_method WHERE code IN ('RC','RI');

-- ============================================================
-- 2. RÉFÉRENTIEL DES TYPES DE MOUVEMENT DE CAISSE
-- ============================================================

CREATE TABLE cash_movement_type (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code         VARCHAR(40) NOT NULL UNIQUE,
    label        VARCHAR(150) NOT NULL,
    normal_sign  VARCHAR(1) NOT NULL CHECK (normal_sign IN ('C','D')),  -- informatif, pas contraint sur amount_minor
    is_system    BOOLEAN NOT NULL DEFAULT false,  -- généré uniquement par une fonction de posting
    is_active    BOOLEAN NOT NULL DEFAULT true,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_movement_type IS
'Table de référence, jamais ENUM (cohérent avec settlement_payment_method).
 normal_sign est informatif pour le reporting ; amount_minor sur cash_movement
 est toujours signé et fait foi (une contre-passation peut porter un type
 "normalement crédit" avec un montant négatif).';

INSERT INTO cash_movement_type (code, label, normal_sign, is_system) VALUES
    ('instrument_receipt',    'Encaissement lié à une pièce de règlement', 'C', true),
    ('supplier_disbursement',   'Paiement fournisseur en espèces',           'D', true),
    ('free_credit',     'Mouvement libre - crédit',                  'C', false),
    ('free_debit',      'Mouvement libre - débit',                   'D', false),
    ('transfer_out',          'Transfert vers une autre session',          'D', true),
    ('transfer_in',          'Transfert reçu d''une autre session',       'C', true),
    ('conversion_out',        'Sortie devise convertie',                   'D', true),
    ('conversion_in',        'Entrée devise convertie',                   'C', true),
    ('bank_deposit_out',        'Sortie caisse pour dépôt en banque',        'D', true),
    ('external_transmission_out','Sortie caisse pour transmission externe',   'D', true),
    ('session_validation_out',  'Sortie - validation caissier central',      'D', true),
    ('session_validation_in',  'Entrée caissier central - validation',      'C', true),
    ('closing_variance',              'Écart de clôture (signe variable)',         'C', true),
    ('returned_instrument_out', 'Sortie - pièce retournée impayée',          'D', true),
    ('generic_correction',       'Correction / contre-passation générique',   'C', true);

-- ============================================================
-- 3. SESSIONS DE CAISSE — la caisse EST la session (décision actée)
-- ============================================================

CREATE TABLE cash_session (
    id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id           UUID NOT NULL DEFAULT gen_random_uuid(),

    holder_account_id   BIGINT NOT NULL REFERENCES party_account(id),
    office_account_id   BIGINT REFERENCES party_account(id),  -- bureau de rattachement, informatif

    status_code         VARCHAR(20) NOT NULL DEFAULT 'open'
                           CHECK (status_code IN ('open','closed','validated')),

    opened_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    opened_by           BIGINT REFERENCES party_account(id),

    closed_at           TIMESTAMPTZ,
    closed_by            BIGINT REFERENCES party_account(id),

    validated_at         TIMESTAMPTZ,
    validated_by          BIGINT REFERENCES party_account(id),  -- le caissier central

    created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),

    CONSTRAINT chk_session_lifecycle CHECK (
        (status_code = 'open'      AND closed_at IS NULL     AND validated_at IS NULL)
        OR (status_code = 'closed'    AND closed_at IS NOT NULL AND validated_at IS NULL)
        OR (status_code = 'validated' AND closed_at IS NOT NULL AND validated_at IS NOT NULL)
    )
);

COMMENT ON TABLE cash_session IS
'La caisse EST la session (pas d''entité caisse persistante séparée). Le fond
 de caisse ne persiste PAS automatiquement d''une session à l''autre du même
 utilisateur (décision actée, conforme au principe legacy "enveloppe" —
 l''argent retourne au caissier central à chaque validation). Une session
 validated pèse zéro par construction (voir cash_validate_session).';

CREATE UNIQUE INDEX uq_cash_session_one_open_per_holder ON cash_session(holder_account_id) WHERE status_code = 'open';
CREATE INDEX idx_cash_session_office ON cash_session(office_account_id);
CREATE UNIQUE INDEX uq_cash_session_public_id ON cash_session(public_id);

-- ============================================================
-- 4. MOUVEMENTS DE CAISSE — journal append-only
-- ============================================================

CREATE TABLE cash_movement (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                UUID NOT NULL DEFAULT gen_random_uuid(),

    session_id               BIGINT NOT NULL REFERENCES cash_session(id),
    movement_type_id         BIGINT NOT NULL REFERENCES cash_movement_type(id),

    currency_code             VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    -- SIGNÉ : positif = entrée, négatif = sortie. Jamais 0.
    amount_minor               BIGINT NOT NULL CHECK (amount_minor <> 0),

    -- NULL = mouvement libre/interne (frais, transfert, conversion, écart).
    instrument_id                BIGINT REFERENCES settlement_instrument(id),

    effective_date                 DATE NOT NULL DEFAULT CURRENT_DATE,
    memo                              TEXT,
    reference                          VARCHAR(100),

    reversal_of_movement_id              BIGINT REFERENCES cash_movement(id),

    created_at                             TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                               BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE cash_movement IS
'Journal append-only des sessions. Chaque mouvement porte sa devise propre
 (une session peut encaisser plusieurs devises). Toujours créé via une
 fonction de posting (cash_receive_instrument, cash_post_outflow,
 cash_post_transfer, cash_post_conversion...), jamais d''INSERT direct.';

CREATE INDEX idx_cash_movement_session ON cash_movement(session_id, currency_code, effective_date, id);
CREATE INDEX idx_cash_movement_instrument ON cash_movement(instrument_id) WHERE instrument_id IS NOT NULL;
CREATE UNIQUE INDEX uq_cash_movement_public_id ON cash_movement(public_id);

-- Invariant structurel (réouverture 23/07/2026, retour chat Backend) : un même
-- settlement_instrument ne peut être encaissé deux fois dans LA MÊME session --
-- jamais légitime (doublon de saisie). Volontairement scopé aux encaissements
-- (amount_minor > 0, hors contre-passation) : une sortie (dépôt/transmission)
-- et sa contre-passation réutilisent légitimement le même instrument_id (§67).
-- Un même instrument peut aussi réapparaître dans une session DIFFÉRENTE
-- (ex. migration chèque agent -> caissier central -> banque).
CREATE UNIQUE INDEX uq_cash_movement_instrument_per_session
    ON cash_movement(session_id, instrument_id)
    WHERE instrument_id IS NOT NULL
      AND amount_minor > 0
      AND reversal_of_movement_id IS NULL;

-- IMMUABILITÉ + verrou de session : bloque toute écriture sur une session non
-- 'open'. Résout structurellement "annulation sur caisse déjà clôturée".
CREATE OR REPLACE FUNCTION cash_movement_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    IF TG_OP IN ('UPDATE','DELETE') THEN
        RAISE EXCEPTION 'cash_movement est append-only : UPDATE/DELETE interdits (id=%)', OLD.id;
    END IF;

    SELECT status_code INTO v_status FROM cash_session WHERE id = NEW.session_id FOR UPDATE;
    IF v_status IS NULL THEN
        RAISE EXCEPTION 'Session % introuvable', NEW.session_id;
    ELSIF v_status = 'validated' THEN
        RAISE EXCEPTION 'Session % déjà validée (figée) : une correction doit être postée dans la session ouverte courante, jamais ici.', NEW.session_id;
    END IF;
    -- 'closed' reste inscriptible UNIQUEMENT pour les écritures de
    -- validation du caissier central (cash_validate_session) : c'est l'état
    -- transitoire "fermée par l'utilisateur, en attente de validation".
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_cash_movement_guard
    BEFORE INSERT OR UPDATE OR DELETE ON cash_movement
    FOR EACH ROW EXECUTE FUNCTION cash_movement_guard();

-- ============================================================
-- 5. SOLDE DE SESSION — snapshot maintenu en continu (pattern settlement_balance)
-- ============================================================

CREATE TABLE cash_session_balance (
    session_id        BIGINT NOT NULL REFERENCES cash_session(id),
    currency_code       VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    balance_minor         BIGINT NOT NULL DEFAULT 0,
    last_movement_id        BIGINT,
    movement_count             INT NOT NULL DEFAULT 0,
    updated_at                   TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (session_id, currency_code)
);

COMMENT ON TABLE cash_session_balance IS
'Snapshot O(sessions x devises), jamais recalculé par lecture du journal.
 Permet de vérifier une session ouverte depuis un mois sans rejouer l''historique.';

CREATE OR REPLACE FUNCTION cash_session_balance_refresh() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO cash_session_balance (session_id, currency_code, balance_minor, last_movement_id, movement_count, updated_at)
    VALUES (NEW.session_id, NEW.currency_code, NEW.amount_minor, NEW.id, 1, now())
    ON CONFLICT (session_id, currency_code) DO UPDATE
    SET balance_minor    = cash_session_balance.balance_minor + NEW.amount_minor,
        last_movement_id = NEW.id,
        movement_count   = cash_session_balance.movement_count + 1,
        updated_at       = now();
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_cash_movement_balance_refresh
    AFTER INSERT ON cash_movement
    FOR EACH ROW EXECUTE FUNCTION cash_session_balance_refresh();

-- ============================================================
-- 6. ALLOCATION — pont de traçabilité pour l'espèce individuellement trackée
-- ============================================================

CREATE TABLE cash_cash_allocation (
    id                          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_movement_id           BIGINT NOT NULL REFERENCES cash_movement(id),
    consumed_by_movement_id        BIGINT NOT NULL REFERENCES cash_movement(id),
    amount_minor                     BIGINT NOT NULL CHECK (amount_minor > 0),
    created_at                         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_allocation_distinct CHECK (source_movement_id <> consumed_by_movement_id)
);

COMMENT ON TABLE cash_cash_allocation IS
'Pont de traçabilité pour l''espèce individuellement trackée. Répond à "cette
 sortie provient de quel encaissement client ?" sans séparation physique des
 billets. "Restant disponible" d''un mouvement source = amount_minor -
 SUM(allocations où source_movement_id = ce mouvement). Ne concerne QUE les
 mouvements en instrument_tracking_mode=''individual'' ; un chèque/LCN/PC ne
 se scinde jamais (une seule ligne d''allocation, montant plein) — la même
 formule fonctionne uniformément pour les deux cas.';

CREATE INDEX idx_cash_allocation_source   ON cash_cash_allocation(source_movement_id);
CREATE INDEX idx_cash_allocation_consumer ON cash_cash_allocation(consumed_by_movement_id);

-- Consommation FIFO automatique : utilisée pour tout décaissement générique
-- (paiement fournisseur, mouvement libre débit, transfert sortant) où
-- l'application ne désigne pas une pièce précise. Respecte
-- strict_source_isolation : une source isolée n'est jamais mobilisable ici.
--
-- BEST-EFFORT : cette fonction ne garantit PAS la couverture totale du
-- montant. Elle trace la provenance sur tout ce qui EST individuellement
-- traçable et mobilisable (dans l'ordre FIFO) ; le reliquat non couvert est
-- implicitement financé par le pool libre/agrégé de la session (alimentation,
-- transferts reçus...), qui n'a par nature aucune provenance à tracer. Le
-- vrai garde-fou "fonds insuffisants" est porté par cash_post_outflow, qui
-- vérifie le solde de session APRÈS coup — pas par cette fonction, qui
-- répondrait sinon "insuffisant" même quand la session a largement de quoi
-- payer, simplement pas sous forme de pièce identifiable.
CREATE OR REPLACE FUNCTION cash_allocate_fifo(
    p_session_id BIGINT, p_currency_code VARCHAR(3),
    p_consumed_by_movement_id BIGINT, p_amount_minor BIGINT
) RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    r RECORD;
    v_remaining BIGINT := p_amount_minor;
    v_take      BIGINT;
BEGIN
    FOR r IN
        SELECT cm.id,
               cm.amount_minor - COALESCE(
                   (SELECT SUM(a.amount_minor) FROM cash_cash_allocation a WHERE a.source_movement_id = cm.id), 0
               ) AS available
        FROM cash_movement cm
        JOIN settlement_instrument ri            ON ri.id = cm.instrument_id
        JOIN cash_payment_method_routing cpmr   ON cpmr.payment_method_id = ri.payment_method_id
        WHERE cm.session_id = p_session_id
          AND cm.currency_code = p_currency_code
          AND cm.amount_minor > 0
          AND cpmr.instrument_tracking_mode = 'individual'
          AND cpmr.strict_source_isolation = false
        ORDER BY cm.effective_date, cm.id  -- FIFO
        FOR UPDATE OF cm
    LOOP
        EXIT WHEN v_remaining <= 0;
        CONTINUE WHEN r.available <= 0;
        v_take := LEAST(v_remaining, r.available);
        INSERT INTO cash_cash_allocation (source_movement_id, consumed_by_movement_id, amount_minor)
        VALUES (r.id, p_consumed_by_movement_id, v_take);
        v_remaining := v_remaining - v_take;
    END LOOP;
    -- v_remaining résiduel = financé par le pool libre, aucune erreur ici.
END;
$$;

-- ============================================================
-- 7. FONCTIONS DE POSTING — sessions
-- ============================================================

CREATE OR REPLACE FUNCTION cash_open_session(p_holder_account_id BIGINT, p_office_account_id BIGINT, p_opened_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE v_id BIGINT;
BEGIN
    INSERT INTO cash_session (holder_account_id, office_account_id, opened_by)
    VALUES (p_holder_account_id, p_office_account_id, p_opened_by)
    RETURNING id INTO v_id;
    RETURN v_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_receive_instrument(p_session_id BIGINT, p_instrument_id BIGINT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_type_id BIGINT; v_currency VARCHAR(3); v_amount BIGINT; v_movement_id BIGINT;
BEGIN
    SELECT currency_code, amount_minor INTO v_currency, v_amount
    FROM settlement_instrument WHERE id = p_instrument_id;
    IF v_currency IS NULL THEN
        RAISE EXCEPTION 'Instrument % introuvable', p_instrument_id;
    END IF;

    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'instrument_receipt';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, created_by)
    VALUES (p_session_id, v_type_id, v_currency, v_amount, p_instrument_id, p_by)
    RETURNING id INTO v_movement_id;
    RETURN v_movement_id;
END; $$;

COMMENT ON FUNCTION cash_receive_instrument IS
'Encaissement d''une pièce Règlements dans la session. À appeler uniquement
 si cash_payment_method_routing du mode de règlement de l''instrument a
 routing_type_code=''cash_session''.';

-- Décaissement générique sans instrument dédié (paiement fournisseur, frais,
-- mouvement libre débit...). p_amount_minor en magnitude positive.
CREATE OR REPLACE FUNCTION cash_post_outflow(
    p_session_id BIGINT, p_movement_type_code VARCHAR(40), p_currency_code VARCHAR(3),
    p_amount_minor BIGINT, p_memo TEXT, p_by BIGINT
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_type_id BIGINT; v_movement_id BIGINT; v_new_balance BIGINT;
BEGIN
    IF p_amount_minor <= 0 THEN
        RAISE EXCEPTION 'cash_post_outflow attend une magnitude positive, reçu %', p_amount_minor;
    END IF;
    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = p_movement_type_code;
    IF v_type_id IS NULL THEN
        RAISE EXCEPTION 'Type de mouvement caisse inconnu: %', p_movement_type_code;
    END IF;

    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, memo, created_by)
    VALUES (p_session_id, v_type_id, p_currency_code, -p_amount_minor, p_memo, p_by)
    RETURNING id INTO v_movement_id;

    -- Garde-fou réel "fonds insuffisants" : le solde de session (toutes
    -- provenances confondues) ne doit jamais devenir négatif. C'est ICI que
    -- ça se vérifie, pas dans cash_allocate_fifo (qui ne voit que la part
    -- individuellement traçable, pas le pool libre).
    SELECT balance_minor INTO v_new_balance
    FROM cash_session_balance WHERE session_id = p_session_id AND currency_code = p_currency_code;
    IF v_new_balance < 0 THEN
        RAISE EXCEPTION 'Solde caisse insuffisant (session %, devise %) : solde deviendrait %', p_session_id, p_currency_code, v_new_balance;
    END IF;

    -- Trace la provenance sur le pool d'espèces individuellement trackées
    -- mobilisable (best-effort, voir cash_allocate_fifo).
    PERFORM cash_allocate_fifo(p_session_id, p_currency_code, v_movement_id, p_amount_minor);

    RETURN v_movement_id;
END; $$;

COMMENT ON FUNCTION cash_post_outflow IS
'Primitive de sortie générique (pas de source désignée). Utilisée par le
 paiement fournisseur et les mouvements libres débit. Pour une sortie visant
 UNE pièce précise et connue (dépôt banque, transmission externe), utiliser
 cash_deposit_add_traceable_item / cash_transmission_add_item à la place,
 qui court-circuitent le FIFO au profit d''une allocation déterministe.';

CREATE OR REPLACE FUNCTION cash_pay_supplier_cash(p_session_id BIGINT, p_currency_code VARCHAR(3), p_amount_minor BIGINT, p_memo TEXT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
BEGIN
    RETURN cash_post_outflow(p_session_id, 'supplier_disbursement', p_currency_code, p_amount_minor, p_memo, p_by);
END; $$;

CREATE OR REPLACE FUNCTION cash_free_debit(p_session_id BIGINT, p_currency_code VARCHAR(3), p_amount_minor BIGINT, p_memo TEXT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
BEGIN
    RETURN cash_post_outflow(p_session_id, 'free_debit', p_currency_code, p_amount_minor, p_memo, p_by);
END; $$;

CREATE OR REPLACE FUNCTION cash_free_credit(p_session_id BIGINT, p_currency_code VARCHAR(3), p_amount_minor BIGINT, p_memo TEXT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE v_type_id BIGINT; v_movement_id BIGINT;
BEGIN
    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'free_credit';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, memo, created_by)
    VALUES (p_session_id, v_type_id, p_currency_code, p_amount_minor, p_memo, p_by)
    RETURNING id INTO v_movement_id;
    RETURN v_movement_id;
END; $$;

-- ============================================================
-- 8. TRANSFERT ENTRE SESSIONS — mécanisme unique (confirmé : T-CD/T-CC et
--    E-TF/D-TF du legacy sont le même besoin)
-- ============================================================

CREATE TABLE cash_transfer (
    id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id          UUID NOT NULL DEFAULT gen_random_uuid(),
    from_session_id    BIGINT NOT NULL REFERENCES cash_session(id),
    to_session_id      BIGINT NOT NULL REFERENCES cash_session(id),
    currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    amount_minor       BIGINT NOT NULL CHECK (amount_minor > 0),
    reason             TEXT,
    out_movement_id    BIGINT REFERENCES cash_movement(id),
    in_movement_id     BIGINT REFERENCES cash_movement(id),
    transferred_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    transferred_by     BIGINT REFERENCES party_account(id),
    CONSTRAINT chk_transfer_distinct_sessions CHECK (from_session_id <> to_session_id)
);

COMMENT ON TABLE cash_transfer IS
'Transfert entre deux sessions ouvertes quelconques (besoin de monnaie,
 appoint, redistribution). Toujours créé via cash_post_transfer() — deux
 jambes atomiques. Limite V1 documentée : la traçabilité individuelle de
 l''espèce ne franchit pas le saut (le côté receveur voit un crédit générique,
 non rattaché au client d''origine) — cas jugé hors scope par l''utilisateur.';

CREATE OR REPLACE FUNCTION cash_post_transfer(p_from_session_id BIGINT, p_to_session_id BIGINT, p_currency_code VARCHAR(3), p_amount_minor BIGINT, p_reason TEXT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_out_id BIGINT; v_in_id BIGINT; v_type_in BIGINT; v_transfer_id BIGINT;
BEGIN
    v_out_id := cash_post_outflow(p_from_session_id, 'transfer_out', p_currency_code, p_amount_minor, p_reason, p_by);

    SELECT id INTO v_type_in FROM cash_movement_type WHERE code = 'transfer_in';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, memo, created_by)
    VALUES (p_to_session_id, v_type_in, p_currency_code, p_amount_minor, p_reason, p_by)
    RETURNING id INTO v_in_id;

    INSERT INTO cash_transfer (from_session_id, to_session_id, currency_code, amount_minor, reason, out_movement_id, in_movement_id, transferred_by)
    VALUES (p_from_session_id, p_to_session_id, p_currency_code, p_amount_minor, p_reason, v_out_id, v_in_id, p_by)
    RETURNING id INTO v_transfer_id;

    RETURN v_transfer_id;
END; $$;

-- ============================================================
-- 9. CONVERSION DE DEVISE EN CAISSE — remplace le hack "achat devise" (3
--    opérations indépendantes) par un geste unique et traçable.
-- ============================================================

CREATE TABLE cash_conversion (
    id                    BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id             UUID NOT NULL DEFAULT gen_random_uuid(),
    session_id            BIGINT NOT NULL REFERENCES cash_session(id),
    from_currency_code    VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    from_amount_minor     BIGINT NOT NULL CHECK (from_amount_minor > 0),
    to_currency_code      VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    to_amount_minor       BIGINT NOT NULL CHECK (to_amount_minor > 0),
    reason                TEXT,
    out_movement_id       BIGINT REFERENCES cash_movement(id),
    in_movement_id        BIGINT REFERENCES cash_movement(id),
    converted_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    converted_by          BIGINT REFERENCES party_account(id),
    CONSTRAINT chk_conversion_distinct_currency CHECK (from_currency_code <> to_currency_code)
);

COMMENT ON TABLE cash_conversion IS
'Change de devise en un seul geste au sein d''une même session (ex: sortie
 DZD, entrée TND pour payer un fournisseur tunisien). Le taux est dérivable
 (to_amount/from_amount), stocké implicitement par les deux montants pour
 audit. Remplace le contournement legacy à 3 opérations indépendantes.';

CREATE OR REPLACE FUNCTION cash_post_conversion(p_session_id BIGINT, p_from_currency VARCHAR(3), p_from_amount BIGINT, p_to_currency VARCHAR(3), p_to_amount BIGINT, p_reason TEXT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_out_id BIGINT; v_in_id BIGINT; v_type_in BIGINT; v_conv_id BIGINT;
BEGIN
    v_out_id := cash_post_outflow(p_session_id, 'conversion_out', p_from_currency, p_from_amount, p_reason, p_by);

    SELECT id INTO v_type_in FROM cash_movement_type WHERE code = 'conversion_in';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, memo, created_by)
    VALUES (p_session_id, v_type_in, p_to_currency, p_to_amount, p_reason, p_by)
    RETURNING id INTO v_in_id;

    INSERT INTO cash_conversion (session_id, from_currency_code, from_amount_minor, to_currency_code, to_amount_minor, reason, out_movement_id, in_movement_id, converted_by)
    VALUES (p_session_id, p_from_currency, p_from_amount, p_to_currency, p_to_amount, p_reason, v_out_id, v_in_id, p_by)
    RETURNING id INTO v_conv_id;

    RETURN v_conv_id;
END; $$;

-- ============================================================
-- 10. CLÔTURE DE SESSION — l'écart est un mouvement, pas une colonne
-- ============================================================

CREATE TABLE cash_session_count (
    id                          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    session_id                  BIGINT NOT NULL REFERENCES cash_session(id),
    currency_code               VARCHAR(3) NOT NULL REFERENCES ref_currency(code),
    theoretical_amount_minor    BIGINT NOT NULL,
    counted_amount_minor        BIGINT NOT NULL,
    variance_movement_id        BIGINT REFERENCES cash_movement(id),  -- NULL si écart = 0
    counted_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
    counted_by                  BIGINT REFERENCES party_account(id),
    UNIQUE (session_id, currency_code)
);

COMMENT ON TABLE cash_session_count IS
'Un comptage par devise à la clôture. L''écart (counted - theoretical) est
 TOUJOURS matérialisé comme un cash_movement (type closing_variance) AVANT la
 fermeture de la session — jamais une correction silencieuse hors journal.';

CREATE OR REPLACE FUNCTION cash_count_session_currency(p_session_id BIGINT, p_currency_code VARCHAR(3), p_counted_amount_minor BIGINT, p_counted_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_theoretical BIGINT; v_variance BIGINT; v_type_id BIGINT; v_movement_id BIGINT; v_count_id BIGINT;
BEGIN
    SELECT COALESCE(balance_minor, 0) INTO v_theoretical
    FROM cash_session_balance WHERE session_id = p_session_id AND currency_code = p_currency_code;
    v_theoretical := COALESCE(v_theoretical, 0);
    v_variance := p_counted_amount_minor - v_theoretical;

    IF v_variance <> 0 THEN
        SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'closing_variance';
        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, memo, created_by)
        VALUES (p_session_id, v_type_id, p_currency_code, v_variance, 'Écart de clôture', p_counted_by)
        RETURNING id INTO v_movement_id;
    END IF;

    INSERT INTO cash_session_count (session_id, currency_code, theoretical_amount_minor, counted_amount_minor, variance_movement_id, counted_by)
    VALUES (p_session_id, p_currency_code, v_theoretical, p_counted_amount_minor, v_movement_id, p_counted_by)
    RETURNING id INTO v_count_id;

    RETURN v_count_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_close_session(p_session_id BIGINT, p_closed_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_draft_deposit_id BIGINT;
    v_draft_transmission_id BIGINT;
BEGIN
    -- §67 : un brouillon de bordereau bloque la clôture (décision utilisateur).
    SELECT id INTO v_draft_deposit_id
    FROM cash_deposit
    WHERE session_id = p_session_id AND status_code = 'draft'
    LIMIT 1;
    IF v_draft_deposit_id IS NOT NULL THEN
        RAISE EXCEPTION
            'Impossible de clôturer la session % : bordereau de dépôt % encore en brouillon — valider ou supprimer ce bordereau',
            p_session_id, v_draft_deposit_id;
    END IF;

    SELECT id INTO v_draft_transmission_id
    FROM cash_external_transmission
    WHERE session_id = p_session_id AND status_code = 'draft'
    LIMIT 1;
    IF v_draft_transmission_id IS NOT NULL THEN
        RAISE EXCEPTION
            'Impossible de clôturer la session % : bordereau de transmission % encore en brouillon — valider ou supprimer ce bordereau',
            p_session_id, v_draft_transmission_id;
    END IF;

    UPDATE cash_session SET status_code = 'closed', closed_at = now(), closed_by = p_closed_by
    WHERE id = p_session_id AND status_code = 'open';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Session % introuvable ou déjà fermée', p_session_id;
    END IF;
END; $$;

-- ============================================================
-- 11. VALIDATION PAR LE CAISSIER CENTRAL — tout ou rien (décision actée).
--     Un mouvement agrégé par devise pour le non-traçable, un mouvement par
--     pièce pour le traçable (chèque/LCN/PC/espèce individual non consommée).
-- ============================================================

CREATE OR REPLACE FUNCTION cash_validate_session(p_session_id BIGINT, p_validator_session_id BIGINT, p_validated_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
    v_out_type BIGINT; v_in_type BIGINT;
    r RECORD;
BEGIN
    SELECT status_code INTO v_status FROM cash_session WHERE id = p_session_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'closed' THEN
        RAISE EXCEPTION 'Seule une session fermée peut être validée (session %, statut %)', p_session_id, v_status;
    END IF;

    SELECT id INTO v_out_type FROM cash_movement_type WHERE code = 'session_validation_out';
    SELECT id INTO v_in_type  FROM cash_movement_type WHERE code = 'session_validation_in';

    -- (1) Non individuellement traçable : NULL ou tracking='aggregate',
    --     et jamais une jambe déjà consommatrice d'une allocation (sinon
    --     double comptage avec la réduction du "restant" en (2)).
    FOR r IN
        SELECT cm.currency_code, SUM(cm.amount_minor) AS total
        FROM cash_movement cm
        LEFT JOIN settlement_instrument ri          ON ri.id = cm.instrument_id
        LEFT JOIN cash_payment_method_routing cpmr ON cpmr.payment_method_id = ri.payment_method_id
        WHERE cm.session_id = p_session_id
          AND (cm.instrument_id IS NULL OR cpmr.instrument_tracking_mode = 'aggregate')
          AND NOT EXISTS (SELECT 1 FROM cash_cash_allocation a WHERE a.consumed_by_movement_id = cm.id)
        GROUP BY cm.currency_code
        HAVING SUM(cm.amount_minor) <> 0
    LOOP
        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, effective_date, memo, created_by)
        VALUES (p_session_id, v_out_type, r.currency_code, -r.total, CURRENT_DATE, 'Validation caissier central', p_validated_by);

        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, effective_date, memo, created_by)
        VALUES (p_validator_session_id, v_in_type, r.currency_code, r.total, CURRENT_DATE, 'Validation session ' || p_session_id, p_validated_by);
    END LOOP;

    -- (2) Individuellement traçable : restant = montant - somme des
    --     allocations déjà consommées (formule uniforme cheque/LCN/PC/espèce).
    FOR r IN
        SELECT cm.id AS movement_id, cm.currency_code, cm.instrument_id,
               cm.amount_minor - COALESCE(SUM(a.amount_minor), 0) AS remaining
        FROM cash_movement cm
        JOIN settlement_instrument ri          ON ri.id = cm.instrument_id
        JOIN cash_payment_method_routing cpmr ON cpmr.payment_method_id = ri.payment_method_id
        LEFT JOIN cash_cash_allocation a      ON a.source_movement_id = cm.id
        WHERE cm.session_id = p_session_id
          AND cpmr.instrument_tracking_mode = 'individual'
          AND cm.amount_minor > 0
        GROUP BY cm.id, cm.currency_code, cm.instrument_id, cm.amount_minor
        HAVING cm.amount_minor - COALESCE(SUM(a.amount_minor), 0) <> 0
    LOOP
        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, effective_date, memo, created_by)
        VALUES (p_session_id, v_out_type, r.currency_code, -r.remaining, r.instrument_id, CURRENT_DATE, 'Validation caissier central', p_validated_by);

        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, effective_date, memo, created_by)
        VALUES (p_validator_session_id, v_in_type, r.currency_code, r.remaining, r.instrument_id, CURRENT_DATE, 'Validation session ' || p_session_id, p_validated_by);
    END LOOP;

    UPDATE cash_session SET status_code = 'validated', validated_at = now(), validated_by = p_validated_by
    WHERE id = p_session_id;
END; $$;

COMMENT ON FUNCTION cash_validate_session IS
'Tout ou rien (décision actée) : valide la totalité de la session ou rien.
 Après exécution, cash_session_balance de la session validée doit être à
 zéro sur toutes les devises — invariant vérifiable, jamais recalculé à la main.';

-- ============================================================
-- 12. LOCALISATION DÉRIVÉE DES INSTRUMENTS
-- ============================================================

CREATE TABLE cash_instrument_location (
    instrument_id     BIGINT PRIMARY KEY REFERENCES settlement_instrument(id),
    location_type     VARCHAR(20) NOT NULL CHECK (location_type IN ('session','deposit','bank_account','transmission')),
    session_id        BIGINT REFERENCES cash_session(id),
    deposit_id        BIGINT,  -- FK ajoutée après création de cash_deposit
    bank_account_id   BIGINT,  -- FK ajoutée après création de cash_bank_account
    transmission_id   BIGINT,  -- FK ajoutée après création de cash_external_transmission
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_location_single CHECK (
        (location_type = 'session'      AND session_id      IS NOT NULL AND deposit_id IS NULL AND bank_account_id IS NULL AND transmission_id IS NULL) OR
        (location_type = 'deposit'      AND deposit_id      IS NOT NULL AND session_id IS NULL AND bank_account_id IS NULL AND transmission_id IS NULL) OR
        (location_type = 'bank_account' AND bank_account_id IS NOT NULL AND session_id IS NULL AND deposit_id IS NULL AND transmission_id IS NULL) OR
        (location_type = 'transmission' AND transmission_id IS NOT NULL AND session_id IS NULL AND deposit_id IS NULL AND bank_account_id IS NULL)
    )
);

COMMENT ON TABLE cash_instrument_location IS
'Localisation dérivée (maintenue par trigger), autoritaire pour les
 instruments intégralement traçables (chèque/LCN/PC, espèce non encore
 scindée). Pour l''espèce individuellement trackée partiellement consommée,
 l''état réel se dérive de cash_cash_allocation (une pièce peut être en
 partie encore en session, en partie déposée) — cette table donne alors la
 dernière localisation connue du reliquat, pas une vérité absolue à 100%.';

CREATE OR REPLACE FUNCTION cash_instrument_location_from_movement() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.instrument_id IS NOT NULL AND NEW.amount_minor > 0 THEN
        INSERT INTO cash_instrument_location (instrument_id, location_type, session_id, updated_at)
        VALUES (NEW.instrument_id, 'session', NEW.session_id, now())
        ON CONFLICT (instrument_id) DO UPDATE
        SET location_type = 'session', session_id = NEW.session_id,
            deposit_id = NULL, bank_account_id = NULL, transmission_id = NULL, updated_at = now();
    END IF;
    RETURN NEW;
END; $$;

CREATE TRIGGER trg_cash_movement_location
    AFTER INSERT ON cash_movement
    FOR EACH ROW EXECUTE FUNCTION cash_instrument_location_from_movement();

-- ============================================================
-- 13. COMPTES BANCAIRES — N-N symétrique avec les bureaux (décision actée,
--     corrige l'hypothèse 1 compte = 1 bureau)
-- ============================================================

CREATE TABLE cash_bank_account (
    id                          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                   UUID NOT NULL DEFAULT gen_random_uuid(),

    label                       VARCHAR(150) NOT NULL,
    bank_name                   VARCHAR(150),
    account_number              VARCHAR(100),
    iban                        VARCHAR(50),
    bic_swift                   VARCHAR(20),

    -- Une devise par compte (contrainte legacy UNIQUE(devise) rejetée :
    -- plusieurs comptes PEUVENT partager la même devise).
    currency_code               VARCHAR(3) NOT NULL REFERENCES ref_currency(code),

    is_reconcilable              BOOLEAN NOT NULL DEFAULT true,
    min_balance_allowed_minor      BIGINT,
    min_balance_desired_minor        BIGINT,
    accounting_account_code            VARCHAR(30),

    opening_balance_minor                 BIGINT NOT NULL DEFAULT 0,
    opening_date                             DATE,

    status_code                                VARCHAR(20) NOT NULL DEFAULT 'active'
                                                  CHECK (status_code IN ('active','closed')),

    created_at                                   TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                                     BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE cash_bank_account IS
'Compte bancaire, entité de premier rang. Une devise par compte, mais
 AUCUNE unicité de devise entre comptes (rejet explicite de la contrainte
 legacy UNIQUE(devise_id) — deux comptes peuvent être en TND).';

CREATE UNIQUE INDEX uq_cash_bank_account_public_id ON cash_bank_account(public_id);

ALTER TABLE cash_instrument_location
    ADD CONSTRAINT fk_cash_instrument_location_bank_account FOREIGN KEY (bank_account_id) REFERENCES cash_bank_account(id);

CREATE TABLE cash_bank_account_office (
    bank_account_id     BIGINT NOT NULL REFERENCES cash_bank_account(id),
    office_account_id   BIGINT NOT NULL REFERENCES party_account(id),  -- porte party_account_office
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (bank_account_id, office_account_id)
);

COMMENT ON TABLE cash_bank_account_office IS
'N-N symétrique, aucun bureau titulaire privilégié (décision actée 16/07).
 Un compte bancaire peut être partagé entre plusieurs bureaux.';

-- ============================================================
-- 14. JOURNAL BANCAIRE — append-only, miroir de cash_movement côté banque
-- ============================================================

CREATE TABLE cash_bank_transaction_type (
    id           BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code         VARCHAR(40) NOT NULL UNIQUE,
    label        VARCHAR(150) NOT NULL,
    normal_sign  VARCHAR(1) NOT NULL CHECK (normal_sign IN ('C','D')),
    is_system    BOOLEAN NOT NULL DEFAULT false,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO cash_bank_transaction_type (code, label, normal_sign, is_system) VALUES
    ('bank_deposit',            'Dépôt (bordereau de remise)',                'C', true),
    ('direct_settlement',  'Règlement atterrissant directement en banque','C', true),
    ('outgoing_transfer',      'Virement émis',                              'D', false),
    ('bank_fee',       'Frais bancaires',                          'D', false),
    ('overdraft_interest',                  'Agios',                                  'D', false),
    ('credit_interest',                  'Intérêts créditeurs',                  'C', false),
    ('returned_instrument',      'Pièce retournée impayée',               'D', true);

CREATE TABLE cash_bank_transaction (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                UUID NOT NULL DEFAULT gen_random_uuid(),

    bank_account_id           BIGINT NOT NULL REFERENCES cash_bank_account(id),
    transaction_type_id        BIGINT NOT NULL REFERENCES cash_bank_transaction_type(id),

    amount_minor                  BIGINT NOT NULL CHECK (amount_minor <> 0),  -- signé

    instrument_id                   BIGINT REFERENCES settlement_instrument(id),  -- règlement direct (V, VE...)
    deposit_id                        BIGINT,  -- FK ajoutée après création de cash_deposit

    value_date                          DATE NOT NULL DEFAULT CURRENT_DATE,
    memo                                   TEXT,
    reference                                VARCHAR(100),

    reversal_of_transaction_id                 BIGINT REFERENCES cash_bank_transaction(id),

    created_at                                   TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_by                                     BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE cash_bank_transaction IS
'Journal append-only du compte bancaire, NOTRE version (jamais fusionnée
 avec le relevé importé — voir cash_bank_statement). Peut naître d''un dépôt
 (deposit_id), d''un règlement atterrissant directement en banque sans caisse
 (instrument_id, ex: virement reçu), ou d''un fait purement bancaire sans
 contrepartie amont (frais, overdraft_interest — origine non obligatoire, à la différence
 de settlement_ledger_entry).';

CREATE INDEX idx_cash_bank_transaction_account ON cash_bank_transaction(bank_account_id, value_date, id);
CREATE UNIQUE INDEX uq_cash_bank_transaction_public_id ON cash_bank_transaction(public_id);

CREATE OR REPLACE FUNCTION cash_bank_transaction_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP IN ('UPDATE','DELETE') THEN
        RAISE EXCEPTION 'cash_bank_transaction est append-only : UPDATE/DELETE interdits (id=%)', OLD.id;
    END IF;
    RETURN NEW;
END; $$;

CREATE TRIGGER trg_cash_bank_transaction_guard
    BEFORE UPDATE OR DELETE ON cash_bank_transaction
    FOR EACH ROW EXECUTE FUNCTION cash_bank_transaction_guard();

CREATE TABLE cash_bank_balance (
    bank_account_id   BIGINT PRIMARY KEY REFERENCES cash_bank_account(id),
    balance_minor     BIGINT NOT NULL DEFAULT 0,
    last_transaction_id BIGINT,
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE OR REPLACE FUNCTION cash_bank_balance_refresh() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO cash_bank_balance (bank_account_id, balance_minor, last_transaction_id, updated_at)
    VALUES (NEW.bank_account_id, NEW.amount_minor, NEW.id, now())
    ON CONFLICT (bank_account_id) DO UPDATE
    SET balance_minor = cash_bank_balance.balance_minor + NEW.amount_minor,
        last_transaction_id = NEW.id, updated_at = now();
    RETURN NEW;
END; $$;

CREATE TRIGGER trg_cash_bank_transaction_balance_refresh
    AFTER INSERT ON cash_bank_transaction
    FOR EACH ROW EXECUTE FUNCTION cash_bank_balance_refresh();

CREATE OR REPLACE FUNCTION cash_receive_bank_direct(p_bank_account_id BIGINT, p_instrument_id BIGINT, p_value_date DATE, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_currency VARCHAR(3); v_account_currency VARCHAR(3); v_amount BIGINT; v_type_id BIGINT; v_id BIGINT;
BEGIN
    SELECT currency_code, amount_minor INTO v_currency, v_amount FROM settlement_instrument WHERE id = p_instrument_id;
    SELECT currency_code INTO v_account_currency FROM cash_bank_account WHERE id = p_bank_account_id;
    IF v_currency IS DISTINCT FROM v_account_currency THEN
        RAISE EXCEPTION 'Devise instrument (%) <> devise compte bancaire (%)', v_currency, v_account_currency;
    END IF;

    SELECT id INTO v_type_id FROM cash_bank_transaction_type WHERE code = 'direct_settlement';
    INSERT INTO cash_bank_transaction (bank_account_id, transaction_type_id, amount_minor, instrument_id, value_date, created_by)
    VALUES (p_bank_account_id, v_type_id, v_amount, p_instrument_id, COALESCE(p_value_date, CURRENT_DATE), p_by)
    RETURNING id INTO v_id;

    INSERT INTO cash_instrument_location (instrument_id, location_type, bank_account_id, updated_at)
    VALUES (p_instrument_id, 'bank_account', p_bank_account_id, now())
    ON CONFLICT (instrument_id) DO UPDATE
    SET location_type = 'bank_account', bank_account_id = p_bank_account_id,
        session_id = NULL, deposit_id = NULL, transmission_id = NULL, updated_at = now();

    RETURN v_id;
END; $$;

COMMENT ON FUNCTION cash_receive_bank_direct IS
'Pour les modes de règlement routing_type_code=''direct_bank'' (virement,
 versement espèce au guichet) : jamais de caisse, atterrit directement ici.';

-- ============================================================
-- 15. BORDEREAUX DE REMISE (dépôt en banque)
--     Cycle de vie brouillon/validation (§67, 24/07) : un brouillon n'a
--     AUCUN effet comptable ; les cash_movement naissent à la validation.
-- ============================================================

CREATE TABLE cash_deposit_type (
    code    VARCHAR(20) PRIMARY KEY,
    label   VARCHAR(60) NOT NULL
);

INSERT INTO cash_deposit_type (code, label) VALUES
    ('cheque',  'Remise de chèques'),
    ('lcn',     'Remise de lettres de change'),
    ('cash',    'Remise d''espèces');

CREATE TABLE cash_deposit (
    id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id         UUID NOT NULL DEFAULT gen_random_uuid(),
    session_id        BIGINT NOT NULL REFERENCES cash_session(id),
    bank_account_id   BIGINT NOT NULL REFERENCES cash_bank_account(id),
    deposit_type_code VARCHAR(20) NOT NULL REFERENCES cash_deposit_type(code),
    status_code       VARCHAR(20) NOT NULL DEFAULT 'draft'
                         CHECK (status_code IN ('draft','validated','cancelled')),
    reference         VARCHAR(100),
    deposited_at      TIMESTAMPTZ,  -- NULL tant que brouillon ; posé à la validation
    deposited_by      BIGINT REFERENCES party_account(id),
    confirmed_transaction_id BIGINT REFERENCES cash_bank_transaction(id),  -- posé par cash_confirm_deposit()
    CONSTRAINT chk_deposit_lifecycle CHECK (
        (status_code = 'draft'     AND deposited_at IS NULL)
        OR (status_code = 'validated' AND deposited_at IS NOT NULL)
        OR (status_code = 'cancelled' AND deposited_at IS NOT NULL)
    )
);

COMMENT ON TABLE cash_deposit IS
'Bordereau de remise. Cycle draft→validated→cancelled (§67). Un brouillon
 n''a aucun cash_movement ; la caisse affiche toujours le contenu physique
 du tiroir. Un seul brouillon par session (index unique partiel). Après
 confirmation bancaire (confirmed_transaction_id), plus d''annulation.';

CREATE UNIQUE INDEX uq_cash_deposit_one_draft_per_session
    ON cash_deposit(session_id) WHERE status_code = 'draft';
CREATE UNIQUE INDEX uq_cash_deposit_public_id ON cash_deposit(public_id);
CREATE INDEX idx_cash_deposit_session ON cash_deposit(session_id);

ALTER TABLE cash_bank_transaction
    ADD CONSTRAINT fk_cash_bank_transaction_deposit FOREIGN KEY (deposit_id) REFERENCES cash_deposit(id);
ALTER TABLE cash_instrument_location
    ADD CONSTRAINT fk_cash_instrument_location_deposit FOREIGN KEY (deposit_id) REFERENCES cash_deposit(id);

CREATE TABLE cash_deposit_item (
    id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    deposit_id    BIGINT NOT NULL REFERENCES cash_deposit(id) ON DELETE CASCADE,
    instrument_id BIGINT NOT NULL REFERENCES settlement_instrument(id),
    amount_minor  BIGINT NOT NULL CHECK (amount_minor > 0),
    movement_id   BIGINT UNIQUE REFERENCES cash_movement(id),  -- NULL en brouillon ; posé à la validation
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_deposit_item IS
'Ligne de bordereau. instrument_id + amount_minor obligatoires (un dépôt
 peut ne couvrir qu''une PARTIE d''une remise d''espèces). movement_id
 renseigné uniquement à la validation — cohérence brouillon/validé portée
 par cash_validate_deposit, pas par un CHECK inter-tables.';

CREATE INDEX idx_cash_deposit_item_deposit ON cash_deposit_item(deposit_id);

-- Crée un bordereau en brouillon (aucun mouvement).
CREATE OR REPLACE FUNCTION cash_create_deposit_draft(
    p_session_id BIGINT, p_bank_account_id BIGINT, p_deposit_type_code VARCHAR(20),
    p_reference VARCHAR(100), p_by BIGINT
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
    v_deposit_id BIGINT;
BEGIN
    SELECT status_code INTO v_status FROM cash_session WHERE id = p_session_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'open' THEN
        RAISE EXCEPTION 'Session % introuvable ou non ouverte (statut %)', p_session_id, v_status;
    END IF;

    INSERT INTO cash_deposit (session_id, bank_account_id, deposit_type_code, reference, deposited_by)
    VALUES (p_session_id, p_bank_account_id, p_deposit_type_code, p_reference, p_by)
    RETURNING id INTO v_deposit_id;

    RETURN v_deposit_id;
END; $$;

-- Ajoute une ligne à un brouillon — AUCUN cash_movement.
CREATE OR REPLACE FUNCTION cash_deposit_add_item(
    p_deposit_id BIGINT, p_instrument_id BIGINT, p_amount_minor BIGINT, p_by BIGINT
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
    v_item_id BIGINT;
BEGIN
    IF p_amount_minor <= 0 THEN
        RAISE EXCEPTION 'amount_minor doit être > 0, reçu %', p_amount_minor;
    END IF;

    SELECT status_code INTO v_status FROM cash_deposit WHERE id = p_deposit_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Bordereau de dépôt % n''est pas en brouillon (statut %)', p_deposit_id, v_status;
    END IF;

    INSERT INTO cash_deposit_item (deposit_id, instrument_id, amount_minor)
    VALUES (p_deposit_id, p_instrument_id, p_amount_minor)
    RETURNING id INTO v_item_id;

    RETURN v_item_id;
END; $$;

-- Supprime un brouillon (et ses lignes). Interdit si validé/annulé.
CREATE OR REPLACE FUNCTION cash_delete_deposit_draft(p_deposit_id BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    SELECT status_code INTO v_status FROM cash_deposit WHERE id = p_deposit_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Seuls les brouillons de dépôt peuvent être supprimés (id=%, statut %)', p_deposit_id, v_status;
    END IF;
    DELETE FROM cash_deposit WHERE id = p_deposit_id;
END; $$;

-- Valide le brouillon : contrôle des fonds, crée les sorties, pose movement_id + horodatage.
CREATE OR REPLACE FUNCTION cash_validate_deposit(p_deposit_id BIGINT, p_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_dep RECORD;
    v_session_status VARCHAR(20);
    v_account_currency VARCHAR(3);
    v_out_type BIGINT;
    r RECORD;
    v_src RECORD;
    v_available BIGINT;
    v_out_id BIGINT;
    v_new_balance BIGINT;
    v_remaining BIGINT;
    v_take BIGINT;
BEGIN
    SELECT * INTO v_dep FROM cash_deposit WHERE id = p_deposit_id FOR UPDATE;
    IF v_dep.id IS NULL THEN
        RAISE EXCEPTION 'Bordereau de dépôt % introuvable', p_deposit_id;
    END IF;
    IF v_dep.status_code IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Bordereau de dépôt % n''est pas en brouillon (statut %)', p_deposit_id, v_dep.status_code;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM cash_deposit_item WHERE deposit_id = p_deposit_id) THEN
        RAISE EXCEPTION 'Bordereau de dépôt % vide : impossible de valider', p_deposit_id;
    END IF;

    SELECT status_code INTO v_session_status FROM cash_session WHERE id = v_dep.session_id FOR UPDATE;
    IF v_session_status IS DISTINCT FROM 'open' THEN
        RAISE EXCEPTION 'Session % du bordereau n''est pas ouverte (statut %)', v_dep.session_id, v_session_status;
    END IF;

    SELECT currency_code INTO v_account_currency FROM cash_bank_account WHERE id = v_dep.bank_account_id;
    SELECT id INTO v_out_type FROM cash_movement_type WHERE code = 'bank_deposit_out';

    FOR r IN
        SELECT di.id AS item_id, di.instrument_id, di.amount_minor, si.currency_code
        FROM cash_deposit_item di
        JOIN settlement_instrument si ON si.id = di.instrument_id
        WHERE di.deposit_id = p_deposit_id
        ORDER BY di.id
    LOOP
        IF r.currency_code IS DISTINCT FROM v_account_currency THEN
            RAISE EXCEPTION 'Devise instrument % (%) <> devise compte bancaire (%)',
                r.instrument_id, r.currency_code, v_account_currency;
        END IF;

        -- Fonds disponibles = restant non alloué des mouvements d'entrée de cette pièce dans la session
        SELECT COALESCE(SUM(remaining), 0) INTO v_available
        FROM (
            SELECT cm.amount_minor - COALESCE((
                SELECT SUM(a.amount_minor) FROM cash_cash_allocation a WHERE a.source_movement_id = cm.id
            ), 0) AS remaining
            FROM cash_movement cm
            WHERE cm.session_id = v_dep.session_id
              AND cm.instrument_id = r.instrument_id
              AND cm.amount_minor > 0
        ) s
        WHERE remaining > 0;

        IF v_available < r.amount_minor THEN
            RAISE EXCEPTION
                'Fonds insuffisants pour valider le dépôt % : instrument % disponible %, demandé %',
                p_deposit_id, r.instrument_id, v_available, r.amount_minor;
        END IF;

        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, memo, created_by)
        VALUES (v_dep.session_id, v_out_type, r.currency_code, -r.amount_minor, r.instrument_id,
                'Validation dépôt ' || p_deposit_id, p_by)
        RETURNING id INTO v_out_id;

        SELECT balance_minor INTO v_new_balance
        FROM cash_session_balance WHERE session_id = v_dep.session_id AND currency_code = r.currency_code;
        IF v_new_balance < 0 THEN
            RAISE EXCEPTION 'Solde caisse insuffisant après sortie dépôt (session %, devise %, solde %)',
                v_dep.session_id, r.currency_code, v_new_balance;
        END IF;

        -- Allouer FIFO sur les sources de cet instrument
        v_remaining := r.amount_minor;
        FOR v_src IN
            SELECT cm.id AS source_id,
                   cm.amount_minor - COALESCE((
                       SELECT SUM(a.amount_minor) FROM cash_cash_allocation a WHERE a.source_movement_id = cm.id
                   ), 0) AS remaining
            FROM cash_movement cm
            WHERE cm.session_id = v_dep.session_id
              AND cm.instrument_id = r.instrument_id
              AND cm.amount_minor > 0
            ORDER BY cm.id
        LOOP
            EXIT WHEN v_remaining <= 0;
            IF v_src.remaining <= 0 THEN
                CONTINUE;
            END IF;
            v_take := LEAST(v_src.remaining, v_remaining);
            INSERT INTO cash_cash_allocation (source_movement_id, consumed_by_movement_id, amount_minor)
            VALUES (v_src.source_id, v_out_id, v_take);
            v_remaining := v_remaining - v_take;
        END LOOP;

        UPDATE cash_deposit_item SET movement_id = v_out_id WHERE id = r.item_id;

        INSERT INTO cash_instrument_location (instrument_id, location_type, deposit_id, updated_at)
        VALUES (r.instrument_id, 'deposit', p_deposit_id, now())
        ON CONFLICT (instrument_id) DO UPDATE
        SET location_type = 'deposit', deposit_id = p_deposit_id,
            session_id = NULL, bank_account_id = NULL, transmission_id = NULL, updated_at = now();
    END LOOP;

    UPDATE cash_deposit
    SET status_code = 'validated', deposited_at = now(), deposited_by = p_by
    WHERE id = p_deposit_id;
END; $$;

COMMENT ON FUNCTION cash_validate_deposit IS
'Valide un brouillon de dépôt : contrôle des fonds à cet instant (pas de
 réservation pendant le brouillon), crée les cash_movement de sortie,
 renseigne movement_id, pose deposited_at, statut validated.';

-- Annule un dépôt validé (pas confirmé) : contre-passation dans la session ouverte courante.
CREATE OR REPLACE FUNCTION cash_cancel_deposit(p_deposit_id BIGINT, p_target_session_id BIGINT, p_reason TEXT, p_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_dep RECORD;
    v_target_status VARCHAR(20);
    r RECORD;
BEGIN
    SELECT * INTO v_dep FROM cash_deposit WHERE id = p_deposit_id FOR UPDATE;
    IF v_dep.id IS NULL THEN
        RAISE EXCEPTION 'Bordereau de dépôt % introuvable', p_deposit_id;
    END IF;
    IF v_dep.status_code IS DISTINCT FROM 'validated' THEN
        RAISE EXCEPTION 'Seuls les dépôts validés peuvent être annulés (id=%, statut %)', p_deposit_id, v_dep.status_code;
    END IF;
    IF v_dep.confirmed_transaction_id IS NOT NULL THEN
        RAISE EXCEPTION 'Dépôt % déjà confirmé en banque (transaction %) : annulation interdite (point de non-retour)',
            p_deposit_id, v_dep.confirmed_transaction_id;
    END IF;

    SELECT status_code INTO v_target_status FROM cash_session WHERE id = p_target_session_id FOR UPDATE;
    IF v_target_status IS DISTINCT FROM 'open' THEN
        RAISE EXCEPTION 'Session cible % doit être ouverte pour recevoir la contre-passation (statut %)',
            p_target_session_id, v_target_status;
    END IF;

    FOR r IN
        SELECT di.movement_id, di.instrument_id
        FROM cash_deposit_item di
        WHERE di.deposit_id = p_deposit_id AND di.movement_id IS NOT NULL
        ORDER BY di.id
    LOOP
        -- Libérer les allocations dont ce mouvement de sortie était consommateur
        DELETE FROM cash_cash_allocation WHERE consumed_by_movement_id = r.movement_id;

        PERFORM cash_reverse_movement(r.movement_id, p_target_session_id,
            COALESCE(p_reason, 'Annulation dépôt ' || p_deposit_id), p_by);

        INSERT INTO cash_instrument_location (instrument_id, location_type, session_id, updated_at)
        VALUES (r.instrument_id, 'session', p_target_session_id, now())
        ON CONFLICT (instrument_id) DO UPDATE
        SET location_type = 'session', session_id = p_target_session_id,
            deposit_id = NULL, bank_account_id = NULL, transmission_id = NULL, updated_at = now();
    END LOOP;

    UPDATE cash_deposit SET status_code = 'cancelled' WHERE id = p_deposit_id;
END; $$;

-- Confirme le bordereau validé : crée LA transaction bancaire (somme des items).
CREATE OR REPLACE FUNCTION cash_confirm_deposit(p_deposit_id BIGINT, p_value_date DATE, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_dep RECORD;
    v_total BIGINT; v_type_id BIGINT; v_tx_id BIGINT; r RECORD;
BEGIN
    SELECT * INTO v_dep FROM cash_deposit WHERE id = p_deposit_id FOR UPDATE;
    IF v_dep.status_code IS DISTINCT FROM 'validated' THEN
        RAISE EXCEPTION 'Seuls les dépôts validés peuvent être confirmés (id=%, statut %)', p_deposit_id, v_dep.status_code;
    END IF;
    IF v_dep.confirmed_transaction_id IS NOT NULL THEN
        RAISE EXCEPTION 'Dépôt % déjà confirmé (transaction %)', p_deposit_id, v_dep.confirmed_transaction_id;
    END IF;

    SELECT SUM(-cm.amount_minor) INTO v_total
    FROM cash_deposit_item di JOIN cash_movement cm ON cm.id = di.movement_id
    WHERE di.deposit_id = p_deposit_id;

    SELECT id INTO v_type_id FROM cash_bank_transaction_type WHERE code = 'bank_deposit';
    INSERT INTO cash_bank_transaction (bank_account_id, transaction_type_id, amount_minor, deposit_id, value_date, created_by)
    VALUES (v_dep.bank_account_id, v_type_id, v_total, p_deposit_id, COALESCE(p_value_date, CURRENT_DATE), p_by)
    RETURNING id INTO v_tx_id;

    UPDATE cash_deposit SET confirmed_transaction_id = v_tx_id WHERE id = p_deposit_id;

    FOR r IN
        SELECT cm.instrument_id FROM cash_deposit_item di
        JOIN cash_movement cm ON cm.id = di.movement_id
        WHERE di.deposit_id = p_deposit_id AND cm.instrument_id IS NOT NULL
    LOOP
        INSERT INTO cash_instrument_location (instrument_id, location_type, bank_account_id, updated_at)
        VALUES (r.instrument_id, 'bank_account', v_dep.bank_account_id, now())
        ON CONFLICT (instrument_id) DO UPDATE
        SET location_type = 'bank_account', bank_account_id = v_dep.bank_account_id,
            session_id = NULL, deposit_id = NULL, transmission_id = NULL, updated_at = now();
    END LOOP;

    RETURN v_tx_id;
END; $$;

-- ============================================================
-- 16. TRANSMISSION EXTERNE (bon de commande / prise en charge)
--     Même cycle de vie brouillon/validation que cash_deposit (§67).
-- ============================================================

CREATE TABLE cash_external_transmission (
    id                        BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    public_id                 UUID NOT NULL DEFAULT gen_random_uuid(),
    session_id                BIGINT NOT NULL REFERENCES cash_session(id),
    transmitted_to_account_id BIGINT NOT NULL REFERENCES party_account(id),  -- l'amicale émettrice
    status_code               VARCHAR(20) NOT NULL DEFAULT 'draft'
                                 CHECK (status_code IN ('draft','validated','cancelled')),
    reference                 VARCHAR(100),
    transmitted_at            TIMESTAMPTZ,  -- NULL tant que brouillon
    transmitted_by            BIGINT REFERENCES party_account(id),
    CONSTRAINT chk_transmission_lifecycle CHECK (
        (status_code = 'draft'     AND transmitted_at IS NULL)
        OR (status_code = 'validated' AND transmitted_at IS NOT NULL)
        OR (status_code = 'cancelled' AND transmitted_at IS NOT NULL)
    )
);

COMMENT ON TABLE cash_external_transmission IS
'Bordereau de transmission. Même triptyque header/items et même cycle
 draft/validated/cancelled que cash_deposit (§67). Un seul brouillon par
 session. Le statut métier par ligne (transmitted/settled/disputed) vit sur
 les items après validation du bordereau.';

CREATE UNIQUE INDEX uq_cash_transmission_one_draft_per_session
    ON cash_external_transmission(session_id) WHERE status_code = 'draft';
CREATE UNIQUE INDEX uq_cash_external_transmission_public_id ON cash_external_transmission(public_id);
CREATE INDEX idx_cash_external_transmission_session ON cash_external_transmission(session_id);

ALTER TABLE cash_instrument_location
    ADD CONSTRAINT fk_cash_instrument_location_transmission FOREIGN KEY (transmission_id) REFERENCES cash_external_transmission(id);

CREATE TABLE cash_external_transmission_item (
    id                         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    transmission_id            BIGINT NOT NULL REFERENCES cash_external_transmission(id) ON DELETE CASCADE,
    instrument_id              BIGINT NOT NULL REFERENCES settlement_instrument(id),
    amount_minor               BIGINT NOT NULL CHECK (amount_minor > 0),
    movement_id                BIGINT UNIQUE REFERENCES cash_movement(id),  -- NULL en brouillon
    accompanying_invoice_id    BIGINT,  -- crochet futur Facturation
    status_code                VARCHAR(20) NOT NULL DEFAULT 'draft'
                                  CHECK (status_code IN ('draft','transmitted','settled','disputed')),
    settlement_instrument_id   BIGINT REFERENCES settlement_instrument(id),  -- pièce de remboursement
    created_at                 TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_external_transmission_item IS
'Ligne de transmission. status_code : draft (brouillon) → transmitted (à la
 validation du bordereau) → settled|disputed. Révision §65/§67 : DEFAULT
 draft, pas transmitted — une ligne non validée n''est PAS transmise.';

CREATE INDEX idx_cash_transmission_item_transmission ON cash_external_transmission_item(transmission_id);

CREATE OR REPLACE FUNCTION cash_create_transmission_draft(
    p_session_id BIGINT, p_transmitted_to_account_id BIGINT, p_reference VARCHAR(100), p_by BIGINT
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
    v_id BIGINT;
BEGIN
    SELECT status_code INTO v_status FROM cash_session WHERE id = p_session_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'open' THEN
        RAISE EXCEPTION 'Session % introuvable ou non ouverte (statut %)', p_session_id, v_status;
    END IF;

    INSERT INTO cash_external_transmission (session_id, transmitted_to_account_id, reference, transmitted_by)
    VALUES (p_session_id, p_transmitted_to_account_id, p_reference, p_by)
    RETURNING id INTO v_id;

    RETURN v_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_transmission_add_item(
    p_transmission_id BIGINT, p_instrument_id BIGINT, p_amount_minor BIGINT,
    p_accompanying_invoice_id BIGINT, p_by BIGINT
) RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
    v_item_id BIGINT;
BEGIN
    IF p_amount_minor <= 0 THEN
        RAISE EXCEPTION 'amount_minor doit être > 0, reçu %', p_amount_minor;
    END IF;

    SELECT status_code INTO v_status FROM cash_external_transmission WHERE id = p_transmission_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Bordereau de transmission % n''est pas en brouillon (statut %)', p_transmission_id, v_status;
    END IF;

    INSERT INTO cash_external_transmission_item (transmission_id, instrument_id, amount_minor, accompanying_invoice_id)
    VALUES (p_transmission_id, p_instrument_id, p_amount_minor, p_accompanying_invoice_id)
    RETURNING id INTO v_item_id;

    RETURN v_item_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_delete_transmission_draft(p_transmission_id BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_status VARCHAR(20);
BEGIN
    SELECT status_code INTO v_status FROM cash_external_transmission WHERE id = p_transmission_id FOR UPDATE;
    IF v_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Seuls les brouillons de transmission peuvent être supprimés (id=%, statut %)', p_transmission_id, v_status;
    END IF;
    DELETE FROM cash_external_transmission WHERE id = p_transmission_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_validate_transmission(p_transmission_id BIGINT, p_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_tr RECORD;
    v_session_status VARCHAR(20);
    v_out_type BIGINT;
    r RECORD;
    v_src RECORD;
    v_available BIGINT;
    v_out_id BIGINT;
    v_new_balance BIGINT;
    v_remaining BIGINT;
    v_take BIGINT;
BEGIN
    SELECT * INTO v_tr FROM cash_external_transmission WHERE id = p_transmission_id FOR UPDATE;
    IF v_tr.id IS NULL THEN
        RAISE EXCEPTION 'Bordereau de transmission % introuvable', p_transmission_id;
    END IF;
    IF v_tr.status_code IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Bordereau de transmission % n''est pas en brouillon (statut %)', p_transmission_id, v_tr.status_code;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM cash_external_transmission_item WHERE transmission_id = p_transmission_id) THEN
        RAISE EXCEPTION 'Bordereau de transmission % vide : impossible de valider', p_transmission_id;
    END IF;

    SELECT status_code INTO v_session_status FROM cash_session WHERE id = v_tr.session_id FOR UPDATE;
    IF v_session_status IS DISTINCT FROM 'open' THEN
        RAISE EXCEPTION 'Session % du bordereau n''est pas ouverte (statut %)', v_tr.session_id, v_session_status;
    END IF;

    SELECT id INTO v_out_type FROM cash_movement_type WHERE code = 'external_transmission_out';

    FOR r IN
        SELECT ti.id AS item_id, ti.instrument_id, ti.amount_minor, si.currency_code
        FROM cash_external_transmission_item ti
        JOIN settlement_instrument si ON si.id = ti.instrument_id
        WHERE ti.transmission_id = p_transmission_id
        ORDER BY ti.id
    LOOP
        SELECT COALESCE(SUM(remaining), 0) INTO v_available
        FROM (
            SELECT cm.amount_minor - COALESCE((
                SELECT SUM(a.amount_minor) FROM cash_cash_allocation a WHERE a.source_movement_id = cm.id
            ), 0) AS remaining
            FROM cash_movement cm
            WHERE cm.session_id = v_tr.session_id
              AND cm.instrument_id = r.instrument_id
              AND cm.amount_minor > 0
        ) s
        WHERE remaining > 0;

        IF v_available < r.amount_minor THEN
            RAISE EXCEPTION
                'Fonds insuffisants pour valider la transmission % : instrument % disponible %, demandé %',
                p_transmission_id, r.instrument_id, v_available, r.amount_minor;
        END IF;

        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, memo, created_by)
        VALUES (v_tr.session_id, v_out_type, r.currency_code, -r.amount_minor, r.instrument_id,
                'Validation transmission ' || p_transmission_id, p_by)
        RETURNING id INTO v_out_id;

        SELECT balance_minor INTO v_new_balance
        FROM cash_session_balance WHERE session_id = v_tr.session_id AND currency_code = r.currency_code;
        IF v_new_balance < 0 THEN
            RAISE EXCEPTION 'Solde caisse insuffisant après sortie transmission (session %, devise %, solde %)',
                v_tr.session_id, r.currency_code, v_new_balance;
        END IF;

        v_remaining := r.amount_minor;
        FOR v_src IN
            SELECT cm.id AS source_id,
                   cm.amount_minor - COALESCE((
                       SELECT SUM(a.amount_minor) FROM cash_cash_allocation a WHERE a.source_movement_id = cm.id
                   ), 0) AS remaining
            FROM cash_movement cm
            WHERE cm.session_id = v_tr.session_id
              AND cm.instrument_id = r.instrument_id
              AND cm.amount_minor > 0
            ORDER BY cm.id
        LOOP
            EXIT WHEN v_remaining <= 0;
            IF v_src.remaining <= 0 THEN
                CONTINUE;
            END IF;
            v_take := LEAST(v_src.remaining, v_remaining);
            INSERT INTO cash_cash_allocation (source_movement_id, consumed_by_movement_id, amount_minor)
            VALUES (v_src.source_id, v_out_id, v_take);
            v_remaining := v_remaining - v_take;
        END LOOP;

        UPDATE cash_external_transmission_item
        SET movement_id = v_out_id, status_code = 'transmitted'
        WHERE id = r.item_id;

        INSERT INTO cash_instrument_location (instrument_id, location_type, transmission_id, updated_at)
        VALUES (r.instrument_id, 'transmission', p_transmission_id, now())
        ON CONFLICT (instrument_id) DO UPDATE
        SET location_type = 'transmission', transmission_id = p_transmission_id,
            session_id = NULL, deposit_id = NULL, bank_account_id = NULL, updated_at = now();
    END LOOP;

    UPDATE cash_external_transmission
    SET status_code = 'validated', transmitted_at = now(), transmitted_by = p_by
    WHERE id = p_transmission_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_cancel_transmission(p_transmission_id BIGINT, p_target_session_id BIGINT, p_reason TEXT, p_by BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    v_tr RECORD;
    v_target_status VARCHAR(20);
    r RECORD;
BEGIN
    SELECT * INTO v_tr FROM cash_external_transmission WHERE id = p_transmission_id FOR UPDATE;
    IF v_tr.id IS NULL THEN
        RAISE EXCEPTION 'Bordereau de transmission % introuvable', p_transmission_id;
    END IF;
    IF v_tr.status_code IS DISTINCT FROM 'validated' THEN
        RAISE EXCEPTION 'Seules les transmissions validées peuvent être annulées (id=%, statut %)', p_transmission_id, v_tr.status_code;
    END IF;
    IF EXISTS (
        SELECT 1 FROM cash_external_transmission_item
        WHERE transmission_id = p_transmission_id AND status_code IN ('settled','disputed')
    ) THEN
        RAISE EXCEPTION 'Transmission % : au moins une ligne settled/disputed — annulation interdite', p_transmission_id;
    END IF;

    SELECT status_code INTO v_target_status FROM cash_session WHERE id = p_target_session_id FOR UPDATE;
    IF v_target_status IS DISTINCT FROM 'open' THEN
        RAISE EXCEPTION 'Session cible % doit être ouverte (statut %)', p_target_session_id, v_target_status;
    END IF;

    FOR r IN
        SELECT ti.movement_id, ti.instrument_id, ti.id AS item_id
        FROM cash_external_transmission_item ti
        WHERE ti.transmission_id = p_transmission_id AND ti.movement_id IS NOT NULL
        ORDER BY ti.id
    LOOP
        DELETE FROM cash_cash_allocation WHERE consumed_by_movement_id = r.movement_id;

        PERFORM cash_reverse_movement(r.movement_id, p_target_session_id,
            COALESCE(p_reason, 'Annulation transmission ' || p_transmission_id), p_by);

        INSERT INTO cash_instrument_location (instrument_id, location_type, session_id, updated_at)
        VALUES (r.instrument_id, 'session', p_target_session_id, now())
        ON CONFLICT (instrument_id) DO UPDATE
        SET location_type = 'session', session_id = p_target_session_id,
            deposit_id = NULL, bank_account_id = NULL, transmission_id = NULL, updated_at = now();
    END LOOP;

    UPDATE cash_external_transmission SET status_code = 'cancelled' WHERE id = p_transmission_id;
END; $$;

CREATE OR REPLACE FUNCTION cash_transmission_settle_item(p_item_id BIGINT, p_settlement_instrument_id BIGINT)
RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    UPDATE cash_external_transmission_item
    SET status_code = 'settled', settlement_instrument_id = p_settlement_instrument_id
    WHERE id = p_item_id AND status_code = 'transmitted';
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Item transmission % introuvable ou non transmitted', p_item_id;
    END IF;
END; $$;

-- ============================================================
-- 16bis. CORRECTIONS — annulation post-clôture et retour d'instrument.
--     Résout structurellement les deux points de douleur legacy explicités :
--     "annulation sur caisse déjà clôturée" et "chèque retourné introuvable".
-- ============================================================

-- Correction générique d'un mouvement passé (erreur de saisie, annulation
-- d'une pièce). Ne mute JAMAIS le mouvement original — poste une écriture
-- inverse, datée d'aujourd'hui, dans une session OUVERTE (peu importe
-- laquelle : le trigger de garde refuse toute autre cible). C'est la
-- réponse structurelle à "impossible de corriger une caisse d'il y a 3
-- semaines" : on ne rouvre jamais l'ancienne, on corrige dans la courante.
CREATE OR REPLACE FUNCTION cash_reverse_movement(p_original_movement_id BIGINT, p_target_session_id BIGINT, p_reason TEXT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE
    v_currency VARCHAR(3); v_amount BIGINT; v_instrument_id BIGINT;
    v_already_reversed BIGINT; v_type_id BIGINT; v_movement_id BIGINT; v_new_balance BIGINT;
BEGIN
    SELECT currency_code, amount_minor, instrument_id
    INTO v_currency, v_amount, v_instrument_id
    FROM cash_movement WHERE id = p_original_movement_id;

    IF v_currency IS NULL THEN
        RAISE EXCEPTION 'Mouvement % introuvable', p_original_movement_id;
    END IF;

    SELECT id INTO v_already_reversed FROM cash_movement WHERE reversal_of_movement_id = p_original_movement_id;
    IF v_already_reversed IS NOT NULL THEN
        RAISE EXCEPTION 'Mouvement % déjà contre-passé par le mouvement %', p_original_movement_id, v_already_reversed;
    END IF;

    -- Un encaissement déjà (même partiellement) consommé/déposé/transmis ne
    -- peut pas être annulé tel quel : la provenance de ce qui a déjà quitté
    -- la session serait invalidée. On bloque plutôt qu'on ne désynchronise.
    IF v_amount > 0 AND EXISTS (SELECT 1 FROM cash_cash_allocation WHERE source_movement_id = p_original_movement_id) THEN
        RAISE EXCEPTION 'Mouvement % déjà partiellement consommé/déposé/transmis (voir cash_cash_allocation) : annulation directe impossible, traiter chaque consommation séparément d''abord', p_original_movement_id;
    END IF;

    SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'generic_correction';
    INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, effective_date, memo, reversal_of_movement_id, created_by)
    VALUES (p_target_session_id, v_type_id, v_currency, -v_amount, v_instrument_id, CURRENT_DATE, COALESCE(p_reason, 'Contre-passation'), p_original_movement_id, p_by)
    RETURNING id INTO v_movement_id;

    SELECT balance_minor INTO v_new_balance FROM cash_session_balance WHERE session_id = p_target_session_id AND currency_code = v_currency;
    IF v_new_balance < 0 THEN
        RAISE EXCEPTION 'Contre-passation refusée : solde de la session cible deviendrait négatif (%)', v_new_balance;
    END IF;

    RETURN v_movement_id;
END; $$;

COMMENT ON FUNCTION cash_reverse_movement IS
'Contre-passation générique. La session d''origine du mouvement annulé n''est
 JAMAIS rouverte ni touchée — c''est la session cible (ouverte, choisie par
 l''appelant, typiquement la session courante de l''utilisateur) qui reçoit
 l''écriture inverse. reversal_of_movement_id documente le lien pour
 l''audit. Un mouvement déjà utilisé comme source d''allocation ne peut pas
 être renversé directement (limite V1 assumée).';

-- Retour d'instrument impayé (chèque/LCN rejeté). Route la contre-écriture
-- selon la localisation DÉRIVÉE actuelle de la pièce — jamais reconstruite
-- à la main. C'est un FAIT ÉCONOMIQUE NOUVEAU (pas une correction de saisie),
-- donc PAS de reversal_of_movement_id : la pièce a bien été reçue, c'est son
-- sort qui a changé après coup. Le re-débit du client lui-même est du
-- ressort de Règlements (settlement_instrument.status_code -> 'returned'),
-- appelé séparément par l'application dans la même transaction logique.
CREATE OR REPLACE FUNCTION cash_handle_instrument_return(p_instrument_id BIGINT, p_target_session_id BIGINT, p_reason TEXT, p_by BIGINT)
RETURNS TABLE(result_type VARCHAR, result_id BIGINT) LANGUAGE plpgsql AS $$
DECLARE
    v_loc RECORD;
    v_currency VARCHAR(3); v_amount BIGINT;
    v_type_id BIGINT; v_movement_id BIGINT; v_tx_id BIGINT;
BEGIN
    SELECT * INTO v_loc FROM cash_instrument_location WHERE instrument_id = p_instrument_id;
    IF v_loc IS NULL THEN
        RAISE EXCEPTION 'Aucune localisation connue pour l''instrument % (jamais entré dans Cash Management ?)', p_instrument_id;
    END IF;

    SELECT currency_code, amount_minor INTO v_currency, v_amount FROM settlement_instrument WHERE id = p_instrument_id;

    IF v_loc.location_type = 'session' THEN
        SELECT id INTO v_type_id FROM cash_movement_type WHERE code = 'returned_instrument_out';
        INSERT INTO cash_movement (session_id, movement_type_id, currency_code, amount_minor, instrument_id, memo, created_by)
        VALUES (COALESCE(NULLIF(v_loc.session_id, NULL), p_target_session_id), v_type_id, v_currency, -v_amount, p_instrument_id, COALESCE(p_reason, 'Pièce retournée impayée'), p_by)
        RETURNING id INTO v_movement_id;
        RETURN QUERY SELECT 'cash_movement'::VARCHAR, v_movement_id;

    ELSIF v_loc.location_type IN ('bank_account','deposit') THEN
        -- Si encore au stade 'deposit' (bordereau non confirmé), le compte
        -- bancaire concerné se retrouve via le bordereau.
        DECLARE v_bank_account_id BIGINT;
        BEGIN
            IF v_loc.location_type = 'bank_account' THEN
                v_bank_account_id := v_loc.bank_account_id;
            ELSE
                SELECT bank_account_id INTO v_bank_account_id FROM cash_deposit WHERE id = v_loc.deposit_id;
            END IF;

            SELECT id INTO v_type_id FROM cash_bank_transaction_type WHERE code = 'returned_instrument';
            INSERT INTO cash_bank_transaction (bank_account_id, transaction_type_id, amount_minor, instrument_id, memo, created_by)
            VALUES (v_bank_account_id, v_type_id, -v_amount, p_instrument_id, COALESCE(p_reason, 'Pièce retournée impayée'), p_by)
            RETURNING id INTO v_tx_id;
        END;
        RETURN QUERY SELECT 'cash_bank_transaction'::VARCHAR, v_tx_id;

    ELSIF v_loc.location_type = 'transmission' THEN
        UPDATE cash_external_transmission_item
        SET status_code = 'disputed'
        WHERE movement_id IN (SELECT id FROM cash_movement WHERE instrument_id = p_instrument_id AND amount_minor < 0);
        RETURN QUERY SELECT 'cash_external_transmission_item'::VARCHAR, v_loc.transmission_id;
    END IF;
END; $$;

COMMENT ON FUNCTION cash_handle_instrument_return IS
'Point d''entrée unique pour un retour d''impayé, quelle que soit l''étape où
 se trouve la pièce (encore en caisse, en banque, ou transmise en externe).
 Répond structurellement au problème legacy "difficile de tracer un chèque
 retourné et son impact". Le p_target_session_id n''est utilisé que si la
 localisation session connue est elle-même devenue indisponible (garde-fou).';



CREATE TABLE cash_bank_statement (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    bank_account_id  BIGINT NOT NULL REFERENCES cash_bank_account(id),
    period_start     DATE NOT NULL,
    period_end       DATE NOT NULL,
    imported_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    imported_by      BIGINT REFERENCES party_account(id),
    source_reference VARCHAR(150)
);

CREATE TABLE cash_bank_statement_line (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    statement_id   BIGINT NOT NULL REFERENCES cash_bank_statement(id),
    value_date     DATE NOT NULL,
    amount_minor   BIGINT NOT NULL CHECK (amount_minor <> 0),
    label          VARCHAR(255),
    bank_reference VARCHAR(150)
);

COMMENT ON TABLE cash_bank_statement_line IS
'La version DE LA BANQUE, jamais fusionnée avec cash_bank_transaction (notre
 version). Le rapprochement est un lien explicite entre les deux.';

CREATE TABLE cash_reconciliation_match (
    id                   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    statement_line_id    BIGINT NOT NULL REFERENCES cash_bank_statement_line(id),
    bank_transaction_id  BIGINT NOT NULL REFERENCES cash_bank_transaction(id),
    amount_minor         BIGINT NOT NULL CHECK (amount_minor <> 0),  -- partiel autorisé
    matched_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    matched_by           BIGINT REFERENCES party_account(id)
);

COMMENT ON TABLE cash_reconciliation_match IS
'N-N à montants partiels : 1 ligne de relevé peut couvrir 1 bordereau entier
 (dépôt groupé) ou 1 seul chèque, et inversement. Jamais bloquant : ce qui ne
 matche pas reste non rapproché sans empêcher le reste. Un écart assumé
 (frais bancaires, overdraft_interest) se qualifie via un cash_bank_transaction dédié,
 pas via ce lien.';

CREATE INDEX idx_cash_reconciliation_line ON cash_reconciliation_match(statement_line_id);
CREATE INDEX idx_cash_reconciliation_tx   ON cash_reconciliation_match(bank_transaction_id);

CREATE OR REPLACE FUNCTION cash_reconcile_match(p_statement_line_id BIGINT, p_bank_transaction_id BIGINT, p_amount_minor BIGINT, p_by BIGINT)
RETURNS BIGINT LANGUAGE plpgsql AS $$
DECLARE v_id BIGINT;
BEGIN
    INSERT INTO cash_reconciliation_match (statement_line_id, bank_transaction_id, amount_minor, matched_by)
    VALUES (p_statement_line_id, p_bank_transaction_id, p_amount_minor, p_by)
    RETURNING id INTO v_id;
    RETURN v_id;
END; $$;

-- ============================================================
-- 18. RATTACHEMENT CAISSIER CENTRAL — rattachement simple, interne à
--     Cash Management (choix licence par module, décision actée)
-- ============================================================

CREATE TABLE cash_validator_assignment (
    id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    validator_account_id     BIGINT NOT NULL REFERENCES party_account(id),
    scope_office_account_id  BIGINT NOT NULL REFERENCES party_account(id),
    valid_from               TIMESTAMPTZ NOT NULL DEFAULT now(),
    valid_to                 TIMESTAMPTZ,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cash_validator_assignment IS
'Rattachement simple caissier central <-> bureau, entièrement interne à
 cash_ (aucune dépendance à un futur module RBAC/permissions). Un client sans
 le module Caisse n''a jamais cette table ni cet écran d''admin.';

CREATE INDEX idx_cash_validator_scope ON cash_validator_assignment(scope_office_account_id) WHERE valid_to IS NULL;

-- ============================================================
-- FIN
-- ============================================================
