# Décision — Fondation observabilité (sans backend externe)

**Date :** 2026-07-21  
**Statut :** acceptée

## Contexte

OsTravel aura besoin d’observabilité (erreurs, corrélation de requêtes, logs exploitables).
Sentry, GlitchTip ou un autre backend d’erreurs sont des candidats, mais le choix dépend
d’une décision d’infrastructure **transverse à plusieurs produits**, hors du périmètre
d’OsTravel seul. Brancher un SDK maintenant créerait un couplage prématuré.

## Décision

1. **Différer** l’intégration Sentry / GlitchTip (ou équivalent).
2. Poser dès maintenant une fondation locale :
   - `DomainException` (Shared) avec `errorCode()` + `context()` — prêt pour logs et future traduction ;
   - Monolog en **JSON structuré** vers `var/log/` ;
   - processor d’ID de corrélation (`X-Request-Id` / génération UUID) ;
   - processor qui enrichit les logs d’une `DomainException` avec `error_code` + `domain_context`.

## Conséquences

Quand la décision transverse sera tranchée, **aucun retouche** du code Domain ni des
processors existants ne sera nécessaire : on ajoutera uniquement un **nouveau handler
Monolog** (et éventuellement un bundle SDK) pointant vers le backend choisi.

La vérification HTTP bout-en-bout (header entrant → logs → header réponse) sera
complétée au premier Controller réel ; les processors et le subscriber sont déjà testés
unitairement / via KernelTestCase.
