# Décision — Soft-delete (`deleted_at`) vs disable (`is_disabled`) sur PartyAccount

Date : 2026-07-21  
Contexte : formalisation avant purge CLI des comptes de test ; pattern à
répliquer sur les futurs modules (Booking, etc.) qui exposent `deleted_at`.

## Décision

**Deux axes orthogonaux — ne pas fusionner.**

| Concept Domain | Colonne | Sens | Effet listes par défaut |
|---|---|---|---|
| `disable()` / `isDisabled()` | `is_disabled` | Désactivation **métier réversible** (compte encore « là », plus « actif » pour des opérations) | **Reste visible** tant que `deleted_at IS NULL` (filtre métier éventuel à part) |
| `delete()` / `isDeleted()` / `deletedAt()` | `deleted_at` | **Soft-delete** — retrait du périmètre lecture par défaut | **Masqué** (`WHERE deleted_at IS NULL`, déjà dans `ListPartyAccountsHandler`) |

État Domain pour le soft-delete : un seul champ `?DateTimeImmutable $deletedAt`
(comme `validTo` sur les assignations). `isDeleted()` est dérivé
(`deletedAt !== null`) — pas de bool séparé en plus du timestamp.

`delete()` est **idempotent** (second appel no-op, conserve le premier
`deletedAt`). Il **n'appelle pas** `disable()`.

## Pourquoi pas fusionner

- Un compte peut être **disabled** sans être retiré des listes admin / audit.
- Un compte peut être **soft-deleted** pour nettoyer une démo / retirer un
  doublon sans inventer un hard delete ni toucher aux enfants append-only
  (role / function / group_member restent, FK intactes).
- Fusionner forcerait `is_disabled` à chaque soft-delete (ou l'inverse) et
  brouillerait les filtres / sémantiques métier vs technique de retrait.

## Conséquences

- Lectures listées / API « actives » : filtre `deleted_at IS NULL`.
- Pas de `DELETE FROM party_account` ni cascade hard sur les assignations
  pour un soft-delete.
- Futurs modules avec `deleted_at` : même distinction
  (disable/enable métier ≠ soft-delete lecture).

## Référence

- `PartyAccount` docblock Domain
- Commande `app:party:purge-test-accounts` (soft-delete via Domain uniquement)
