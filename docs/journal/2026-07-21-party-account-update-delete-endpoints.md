## Reprise à froid

Journal — Endpoints PATCH + DELETE PartyAccount.
Date : 2026-07-21
- `PATCH /api/v1/party-accounts/{publicId}` — mise à jour partielle
(`displayName`, `isDisabled` → `disable()` / `enable()`). Body vide /

## Origine

```
# TASK — Compléter le CRUD Party : PATCH (update/disable/enable) + DELETE (soft)

## Lecture obligatoire
1. CreatePartyAccountController.php + GetPartyAccountController.php (pattern
   déjà en place — réutiliser strictement le même style de réponse/erreur)
2. PartyAccount.php : updateDisplayName(), disable(), enable(), delete()
   (toutes déjà présentes côté Domain, aucune à créer)

## Portée
Deux endpoints, un seul prompt car les deux sont mécaniques une fois le
pattern PATCH établi.

## 1. PATCH /api/v1/party-accounts/{publicId}
DTO de requête (tous les champs optionnels, PATCH = mise à jour partielle) :
- displayName (string|null) → si fourni, updateDisplayName()
- isDisabled (bool|null) → si true, disable() ; si false, enable()
Body vide ou tous champs null → 400 explicite ("no changes provided"), pas
un no-op silencieux qui renverrait 200 sans rien faire.
Compte introuvable → 404 (réutiliser PartyAccountNotFoundException::
forPublicId, déjà existante).
Succès → 200, corps = PartyAccountResponse à jour (même format que GET).

## 2. DELETE /api/v1/party-accounts/{publicId}
Appelle PartyAccount::delete() (soft-delete, idempotent — déjà vérifié
Domain). Compte introuvable → 404. Déjà supprimé → 200 quand même
(cohérent avec l'idempotence Domain, pas une erreur).
Succès → 204 No Content (pas de corps, convention REST standard pour DELETE).

Après un DELETE réussi, un GET sur le même publicId doit retourner 404
(vérifier que findByPublicId() exclut bien deleted_at IS NOT NULL — si le
Repository actuel ne filtre pas dessus, AJUSTER findByPublicId() pour
exclure les comptes soft-deleted par défaut, cohérent avec
ListPartyAccountsHandler qui le fait déjà).

## Tests d'intégration (PostgreSQL réel)
- PATCH displayName seul → 200, valeur mise à jour, vérifiable via GET
- PATCH isDisabled=true puis isDisabled=false → 200, état bien reflété
- PATCH body vide → 400
- PATCH sur publicId inexistant → 404
- DELETE sur compte existant → 204, GET suivant → 404
- DELETE deux fois de suite → 200 les deux fois (idempotence), pas d'erreur
- DELETE sur publicId inexistant → 404
- Les deux endpoints sans JWT → 401
- Vérifier qu'aucun champ "id" interne n'apparaît nulle part

## Documentation
- docs/journal/2026-07-2X-party-account-update-delete-endpoints.md
- docs/STATUS.md : "CRUD Party HTTP complet (create, read, list, update,
  delete). Prêt pour le vrai front."
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés/modifiés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — Endpoints PATCH + DELETE PartyAccount

Date : 2026-07-21

## Livré

- `PATCH /api/v1/party-accounts/{publicId}` — mise à jour partielle
  (`displayName`, `isDisabled` → `disable()` / `enable()`). Body vide /
  aucun champ applicable → `party_account.no_changes_provided` (400).
- `DELETE /api/v1/party-accounts/{publicId}` — soft-delete Domain
  (`PartyAccount::delete()`). Premier appel → **204** ; déjà soft-deleted →
  **200** (idempotent) ; inexistant → **404**.
- `PartyAccount::enable()` ajouté (manquant alors que PATCH le requiert).
- `findByPublicId()` / `findByEmail()` excluent `deleted_at IS NOT NULL`.
  `findByPublicIdIncludingDeleted()` pour DELETE idempotent.
- `PartyAccountResponse` expose aussi `isDisabled` (jamais `id` interne).

## Architecture

Même style que Create/Get : Controller HTTP → Handler Application → Domain /
Repository. Erreurs Domain via ExceptionListener + catalogue `errors`.

## Suite

CRUD Party HTTP complet. Prêt pour le vrai front.

Clôture : `2026-07-21-party-account-update-delete-endpoints-cloture.md`.
