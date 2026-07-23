## Reprise à froid

Journal — Endpoint POST création PartyAccount.
Date : 2026-07-21
- `POST /api/v1/party-accounts` (JWT requis)
- DTO `CreatePartyAccountRequest` + validation Symfony → 422

## Origine

```
# TASK — Endpoint d'écriture Party : création d'un compte (POST)

## Lecture obligatoire
1. GetPartyAccountController.php + ExceptionListener.php (déjà validés —
   même format de réponse, même gestion d'erreurs à respecter)
2. src/Modules/Party/Domain/Entity/PartyAccount.php (factories createPerson/
   createOrganization, contrainte parentAccountId réservé à organization)

## Portée
UN SEUL endpoint : POST /api/v1/party-accounts — création d'un compte
(person ou organization). Pas de PUT/PATCH/DELETE dans ce prompt.

## 1. DTO de requête (validé AVANT d'atteindre le Domain)
src/Modules/Party/Infrastructure/Http/Dto/CreatePartyAccountRequest.php
Champs : nature (string, doit être 'person' ou 'organization' —
#[Assert\Choice]), displayName (string, #[Assert\NotBlank],
#[Assert\Length(max: 255)]), email (string|null, #[Assert\Email] si fourni),
parentAccountId (int|null — PAS de contrainte ici, la vraie règle "réservé à
organization" reste appliquée par le Domain, ne pas la dupliquer dans le DTO).

Utiliser Symfony Validator (déjà disponible). Si la validation échoue AVANT
même d'atteindre le Handler : retourner 422 avec un format d'erreur cohérent
avec l'existant — mais attention, ce ne sont PAS des DomainException (erreurs
de validation d'input, pas de règle métier) donc PAS géré par
ExceptionListener tel quel. Décision : gérer la validation directement dans
le Controller (appel explicite au Validator, construction manuelle de la
réponse 422 si erreurs), PAS en laissant échouer une exception Symfony
générique qui finirait en 500 halluciné par notre listener existant.
Format 422 : {"error": {"code": "validation_failed", "message": "...",
"violations": [{"field": "...", "message": "..."}]}}.

## 2. Application (réutiliser l'existant, ne pas dupliquer)
Un compte create passe déjà par PartyAccount::createPerson/createOrganization
directement — pas de nouveau Command/Handler nécessaire si la logique est
triviale (juste convertir le DTO en appel factory + save). Si un Handler
dédié CreatePartyAccountHandler te semble justifié pour rester cohérent avec
le pattern déjà établi ailleurs (Assign*, Set*), le créer — sinon, expliquer
dans le journal pourquoi ce n'était pas nécessaire ici.

## 3. Controller
src/Modules/Party/Infrastructure/Http/Controller/CreatePartyAccountController.php
- POST /api/v1/party-accounts
- Désérialise + valide le body en CreatePartyAccountRequest
- Construit le PartyAccount via la bonne factory selon `nature`
- 201 Created, corps = PartyAccountResponse (public_id, jamais id interne —
  même garde-fou que sur GET)
- Header Location: /api/v1/party-accounts/{publicId}

## Tests d'intégration (WebTestCase, PostgreSQL réel)
- Création person valide → 201, corps correct, Location présent, compte
  vérifiable ensuite via GET
- Création organization valide → idem
- nature invalide (ni person ni organization) → 422 avec violations
- displayName vide → 422
- email mal formé → 422
- organization avec parentAccountId incohérent (créer un cas qui viole la
  vraie règle Domain, si applicable ici — sinon documenter que ce cas
  n'existe que côté person et est déjà couvert par les tests Domain existants)
- Requête sans token JWT → 401 (l'endpoint est protégé comme le reste de /api/v1)

## Documentation
- docs/journal/2026-07-2X-create-party-account-endpoint.md
- docs/STATUS.md : "Endpoint création de compte opérationnel. Reste : liste
  (GET paginé), update/delete si besoin plus tard."
- docs/backlog/todo.md mis à jour

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés et les résultats.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — Endpoint POST création PartyAccount

Date : 2026-07-21

## Livré

- `POST /api/v1/party-accounts` (JWT requis)
- DTO `CreatePartyAccountRequest` + validation Symfony → 422
  `{error: {code: validation_failed, message, violations[]}}`
- Application `CreatePartyAccountCommand` / `CreatePartyAccountHandler`
  (cohérent avec CreatePartyAccountGroup / Assign* — factories Domain + save)
- Réponse 201 = `PartyAccountResponse` (pas d'`id` interne) + `Location`

## Pourquoi un Handler

Même si la logique est courte (match nature → factory → save), un Handler
Application isole le Controller de Domain/Repository et reste aligné sur le
pattern déjà établi (pas d'appel factory Direct depuis le Controller).

## Règle parentAccountId

Non dupliquée dans le DTO. `person` + `parentAccountId` →
`InvalidPartyAccountStateException` (400 traduit). `organization` + parent
autorisé. Couvert aussi en unit Domain.

## Validation email unifiée (corrigé)

`#[Assert\Email]` remplacé par `#[Assert\Regex(pattern: Email::FORMAT_PATTERN)]`
sur `CreatePartyAccountRequest`. `Email::FORMAT_PATTERN` est une constante
**publique** partagée avec `Email::fromString()` — une seule règle, plus de
divergence 422 vs 400.

Cas autrefois divergents (`a@b.c`, `user@123.45.67.89`) → **422**
`validation_failed` (couverts par test HTTP dédié).

## Suite

Liste GET paginée ; update/delete si besoin.
