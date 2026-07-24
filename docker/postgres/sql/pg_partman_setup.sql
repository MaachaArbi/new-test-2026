-- =============================================================================
-- pg_partman — enregistrement des 4 tables partitionnées (§8)
-- =============================================================================
-- ÉTAPE OBLIGATOIRE DU DÉPLOIEMENT, à exécuter APRÈS la chaîne des schémas
-- et AVANT la première utilisation applicative.
-- Voir docs/decisions/2026-07-24-pg-partman-deploiement.md
--
-- Idempotent : safe à rejouer. p_default_table = false (panne bruyante).
-- =============================================================================

CREATE SCHEMA IF NOT EXISTS partman;
CREATE EXTENSION IF NOT EXISTS pg_partman WITH SCHEMA partman;

-- Renommer les anciennes tranches bootstrap (_yYYYYmMM) vers la convention
-- pg_partman (_pYYYYMMDD) si elles existent encore (runtime historique).
DO $$
DECLARE
    r record;
    v_new text;
    v_m text[];
BEGIN
    FOR r IN
        SELECT c.relname AS old_name
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public'
          AND c.relkind IN ('r', 'p')
          AND c.relname ~ '^(booking|core_session|core_auth_attempt|provider_call_log)_y[0-9]{4}m[0-9]{2}$'
    LOOP
        v_m := regexp_match(r.old_name, '^(.*)_y([0-9]{4})m([0-9]{2})$');
        v_new := v_m[1] || '_p' || v_m[2] || v_m[3] || '01';
        IF to_regclass('public.' || v_new) IS NULL THEN
            EXECUTE format('ALTER TABLE %I RENAME TO %I', r.old_name, v_new);
            RAISE NOTICE 'renamed % -> %', r.old_name, v_new;
        END IF;
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- Helper : enregistrer une table si elle existe et n'est pas déjà gérée
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    v_exists boolean;
    v_managed boolean;
BEGIN
    -- booking (DATE, aucune rétention)
    SELECT EXISTS (
        SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relname = 'booking'
    ) INTO v_exists;
    IF v_exists THEN
        SELECT EXISTS (
            SELECT 1 FROM partman.part_config WHERE parent_table = 'public.booking'
        ) INTO v_managed;
        IF NOT v_managed THEN
            PERFORM partman.create_parent(
                p_parent_table := 'public.booking',
                p_control := 'booking_date',
                p_interval := '1 month',
                p_type := 'range',
                p_premake := 3,
                p_default_table := false,
                p_automatic_maintenance := 'on',
                p_jobmon := false
            );
        END IF;
        UPDATE partman.part_config
        SET premake = 3,
            infinite_time_partitions = true,
            retention = NULL,
            retention_keep_table = true,
            retention_keep_index = true
        WHERE parent_table = 'public.booking';
    END IF;

    -- core_session (TIMESTAMPTZ, rétention 3 mois)
    SELECT EXISTS (
        SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relname = 'core_session'
    ) INTO v_exists;
    IF v_exists THEN
        SELECT EXISTS (
            SELECT 1 FROM partman.part_config WHERE parent_table = 'public.core_session'
        ) INTO v_managed;
        IF NOT v_managed THEN
            PERFORM partman.create_parent(
                p_parent_table := 'public.core_session',
                p_control := 'created_at',
                p_interval := '1 month',
                p_type := 'range',
                p_premake := 3,
                p_default_table := false,
                p_automatic_maintenance := 'on',
                p_jobmon := false
            );
        END IF;
        UPDATE partman.part_config
        SET premake = 3,
            infinite_time_partitions = false,
            retention = '3 months',
            retention_keep_table = false,
            retention_keep_index = false
        WHERE parent_table = 'public.core_session';
    END IF;

    -- core_auth_attempt (TIMESTAMPTZ, rétention 3 mois)
    SELECT EXISTS (
        SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relname = 'core_auth_attempt'
    ) INTO v_exists;
    IF v_exists THEN
        SELECT EXISTS (
            SELECT 1 FROM partman.part_config WHERE parent_table = 'public.core_auth_attempt'
        ) INTO v_managed;
        IF NOT v_managed THEN
            PERFORM partman.create_parent(
                p_parent_table := 'public.core_auth_attempt',
                p_control := 'created_at',
                p_interval := '1 month',
                p_type := 'range',
                p_premake := 3,
                p_default_table := false,
                p_automatic_maintenance := 'on',
                p_jobmon := false
            );
        END IF;
        UPDATE partman.part_config
        SET premake = 3,
            infinite_time_partitions = false,
            retention = '3 months',
            retention_keep_table = false,
            retention_keep_index = false
        WHERE parent_table = 'public.core_auth_attempt';
    END IF;

    -- provider_call_log (TIMESTAMPTZ, AUCUNE purge pg_partman — applicatif)
    SELECT EXISTS (
        SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relname = 'provider_call_log'
    ) INTO v_exists;
    IF v_exists THEN
        SELECT EXISTS (
            SELECT 1 FROM partman.part_config WHERE parent_table = 'public.provider_call_log'
        ) INTO v_managed;
        IF NOT v_managed THEN
            PERFORM partman.create_parent(
                p_parent_table := 'public.provider_call_log',
                p_control := 'created_at',
                p_interval := '1 month',
                p_type := 'range',
                p_premake := 3,
                p_default_table := false,
                p_automatic_maintenance := 'on',
                p_jobmon := false
            );
        END IF;
        UPDATE partman.part_config
        SET premake = 3,
            infinite_time_partitions = true,
            retention = NULL,
            retention_keep_table = true,
            retention_keep_index = true
        WHERE parent_table = 'public.provider_call_log';
    END IF;
END $$;

-- Première passe de maintenance (crée les tranches d'avance manquantes)
SELECT partman.run_maintenance(p_jobmon := false);
