-- =============================================================================
-- Suppression des tranches DEFAULT (§8) — panne bruyante assumée
-- =============================================================================
-- NE SUPPRIME QUE si la tranche est VIDE. Sinon RAISE et arrêt.
-- =============================================================================

DO $$
DECLARE
    v_count bigint;
    v_name text;
BEGIN
    FOREACH v_name IN ARRAY ARRAY[
        'booking_default',
        'core_session_default',
        'core_auth_attempt_default',
        'provider_call_log_default'
    ]
    LOOP
        IF to_regclass('public.' || v_name) IS NULL THEN
            RAISE NOTICE '% : absente — rien à faire', v_name;
            CONTINUE;
        END IF;

        EXECUTE format('SELECT count(*) FROM %I', v_name) INTO v_count;
        IF v_count > 0 THEN
            RAISE EXCEPTION
                '% contient % ligne(s) — NE PAS supprimer. Réattribuer d''abord les lignes hors fourre-tout (§8).',
                v_name, v_count;
        END IF;

        EXECUTE format('DROP TABLE %I', v_name);
        RAISE NOTICE '% : vide — supprimée', v_name;
    END LOOP;
END $$;
