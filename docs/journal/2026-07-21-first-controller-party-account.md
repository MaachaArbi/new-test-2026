## Reprise à froid

Journal — 2026-07-21 — Premier Controller PartyAccount + ExceptionListener.
Premier endpoint HTTP : `GET /api/v1/party-accounts/{publicId}`. Objectif : prouver la chaîne routing → DTO réponse → JSON → DomainException traduites avant d'empiler écriture / listes.
Premier endpoint HTTP : `GET /api/v1/party-accounts/{publicId}`. Objectif :
prouver la chaîne routing → DTO réponse → JSON → DomainException traduites

## Origine

```
# TASK — Premier Controller Party : lecture d'un compte + listener d'erreurs global

## Lecture obligatoire
1. Toute la discussion précédente sur les DTO (résumé : DTO requête validé
   AVANT le Domain, DTO réponse expose UNIQUEMENT public_id, jamais id interne)
2. src/Modules/Party/Domain/Entity/PartyAccount.php + son Repository
3. src/Shared/Domain/Exception/DomainException.php (errorCode + context) et
   translations/errors.*.yaml déjà en place

## Portée (volontairement minimale)
UN SEUL endpoint : GET /api/v1/party-accounts/{publicId} — retourne les infos
de base d'un compte. Rien en écriture dans ce prompt. Objectif : prouver la
chaîne complète (routing → DTO → JSON → erreurs traduites) avant d'empiler
plus d'endpoints.

## 1. DTO de réponse
src/Modules/Party/Infrastructure/Http/Dto/PartyAccountResponse.php
Champs : publicId (string), nature (string), displayName (string), email
(string|null). JAMAIS le champ id interne — vérifier explicitement dans le
test qu'aucune clé "id" n'apparaît dans le JSON produit.
Méthode statique fromDomain(PartyAccount $account): self.

## 2. Query (lecture, ADR-003 — DBAL, pas de réhydratation Domain complète
   si évitable ; pour ce premier cas simple, findById() Domain existant reste
   acceptable, mais NOTER dans le journal que les futurs endpoints de LISTE
   devront passer par DBAL direct, pas par le Repository Domain)

## 3. Controller
src/Modules/Party/Infrastructure/Http/Controller/GetPartyAccountController.php
- Route par public_id, PAS par id interne (jamais dans l'URL)
- Résout le PartyAccount via son public_id (ajouter findByPublicId() à
  PartyAccountRepositoryInterface si absent — méthode de lecture simple)
- 404 si introuvable (lever une exception Domain dédiée si elle n'existe pas
  déjà : PartyAccountNotFoundException existe déjà pour le cas by-id interne,
  vérifier si elle convient ou s'il faut un cas by-public-id distinct)
- Controller minimal, délègue tout, pas de logique dedans

## 4. Listener d'erreurs global (le vrai sujet de ce prompt)
src/Shared/Infrastructure/Http/ExceptionListener.php (kernel.exception)
- Si l'exception est une DomainException : construit une réponse JSON
  {error: {code: errorCode(), message: <traduit via Translator, domain
  'errors', locale résolue depuis Accept-Language ou 'en' par défaut>,
  context: context()}}, statut HTTP approprié (404 pour *NotFoundException,
  409 pour *AlreadyActive/AlreadyUsed, 400 pour le reste — mapper explicitement,
  pas une règle magique par nom de classe)
- Si l'exception n'est PAS une DomainException : ne jamais exposer le message
  brut/stack trace en réponse (risque de fuite d'info), réponse générique 500,
  mais le logger complet (DomainExceptionProcessor déjà en place ne s'applique
  qu'aux DomainException — vérifier qu'une exception technique quelconque est
  quand même bien logguée avec le request_id, même sans error_code/context)
- Utiliser le request_id déjà posé par CorrelationIdHolder dans la réponse
  d'erreur (header X-Request-Id déjà présent normalement, vérifier)

## Tests
Integration (client HTTP Symfony, WebTestCase ou équivalent) :
- GET sur un public_id existant → 200, JSON correct, ZÉRO champ "id"
- GET sur un public_id inexistant → 404, JSON structuré avec error.code
  traduit selon Accept-Language (tester au moins fr et en)
- Vérifier que le header X-Request-Id est présent dans la réponse, y compris
  en cas d'erreur

## Documentation
- docs/journal/2026-07-2X-first-controller-party-account.md
- docs/STATUS.md : "Premier Controller opérationnel (lecture PartyAccount par
  public_id). Listener d'erreurs global branché — DomainException traduites
  en JSON. Reste : endpoints d'écriture, autres modules."
- docs/backlog/todo.md : retirer "listener HTTP" de la liste transverse (fait)

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — 2026-07-21 — Premier Controller PartyAccount + ExceptionListener

## Contexte

Premier endpoint HTTP : `GET /api/v1/party-accounts/{publicId}`. Objectif :
prouver la chaîne routing → DTO réponse → JSON → DomainException traduites
avant d'empiler écriture / listes.

## Faits

1. **DTO** `PartyAccountResponse` — `publicId`, `nature`, `displayName`,
   `email` ; jamais `id` interne (`fromDomain` + `toArray`).
2. **Controller** minimal — `findByPublicId` + 404 via
   `PartyAccountNotFoundException::forPublicId` (même `errorCode`
   `party_account.not_found`).
3. **Repository** — `findByPublicId(PublicId)` ajouté (lecture Domain OK pour
   ce cas 1-entité).
4. **ExceptionListener** (Shared) — DomainException → JSON
   `{error:{code,message,context}}` traduit domain `errors` ;
   statut 404/409/400 mappé explicitement sur `errorCode()` (pas de magie
   sur le nom de classe) ; non-Domain → 500 générique + log complet
   (`exception` dans le context Monolog → `request_id` via processor).
5. **Accept-Language** → locale `en|fr|ar`, défaut `en`.
6. **X-Request-Id** — déjà posé par `RequestIdSubscriber` ; aussi forcé sur
   les réponses d'erreur du listener.

## ADR-003 (note)

`findByPublicId` via Repository Domain acceptable pour ce GET simple.
Les futurs endpoints de **liste** devront passer par DBAL direct, pas par
réhydratation Domain complète.

## Qualité

phpstan / deptrac / phpcpd / phpunit OK.
Rattrapage clôture : test 500 technique (HTTP + unit logger) — voir
`2026-07-21-first-controller-party-account-cloture.md`.
