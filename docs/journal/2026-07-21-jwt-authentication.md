## Reprise à froid

Journal — Authentification JWT (login).
Date : 2026-07-21
- Bundle `lexik/jwt-authentication-bundle` + clés RSA hors Git (`config/jwt/*.pem`)
- `SecurityUser` + `CoreCredentialUserProvider` + enrichissement payload

## Origine

```
# TASK — Authentification JWT (login) sur CoreCredential

## Lecture obligatoire
1. src/Modules/Core/Domain/ (CoreCredential, PasswordHasherInterface — déjà
   fait et validé)
2. config/packages/security.yaml (déjà existant, minimal : password_hashers +
   firewall main avec security:false)

## Choix technique imposé
lexik/jwt-authentication-bundle (standard Symfony pour JWT, mature, pas de
raison de réinventer). Installer, générer les clés RSA (public/private) via
la commande dédiée du bundle, stockées hors du repo Git (ajouter au
.gitignore si pas déjà fait).

## 1. Configuration sécurité
Étendre security.yaml : firewall main devient un vrai firewall JWT sur
/api/v1/* (stateless, json_login sur /api/v1/auth/login), le reste
(éventuel futur back-office HTML) hors périmètre. Provider Symfony custom
qui résout un compte via CoreCredentialRepositoryInterface::
findByProviderIdentity() — ATTENTION : pour le login local, l'identifiant
n'est pas provider_user_id mais l'email du party_account associé. Réfléchis
au bon point d'entrée : probablement un provider qui fait
PartyAccountRepository->findByEmail() (à créer si absent) puis résout le
CoreCredential local associé à ce compte, vérifie via
PasswordHasherInterface::verify().

## 2. Endpoint
POST /api/v1/auth/login — body {email, password} → 200 {token: "..."} en cas
de succès (géré nativement par le firewall JWT une fois configuré, pas de
Controller custom nécessaire pour le login lui-même a priori — vérifier avec
la doc du bundle plutôt que de réinventer).
401 générique en cas d'échec (ne JAMAIS préciser si c'est l'email ou le mot
de passe qui est faux — éviter l'énumération de comptes, réflexe sécurité
standard).

## 3. Compte de test
Étendre BootstrapAgencyAccountCommand (ou créer une commande séparée
app:core:bootstrap-admin-credential) pour créer un CoreCredential local
associé au compte agence, avec un mot de passe fourni en argument de commande
(jamais en dur dans le code). Idempotent comme le reste du bootstrap.

## 4. Tests d'intégration
- Login avec bon email/mot de passe → 200 + token JWT valide (décodable,
  contient au minimum l'identifiant du compte)
- Login avec mauvais mot de passe → 401 générique
- Login avec email inexistant → 401 générique IDENTIQUE (même message, même
  structure — vérifier qu'on ne peut pas distinguer les deux cas depuis
  l'extérieur)
- Un endpoint protégé (réutiliser GetPartyAccountController ou un endpoint
  factice) sans token → 401 ; avec token valide → 200

## Documentation
- docs/decisions/2026-07-2X-jwt-lexik.md : choix du bundle, structure du
  provider, décision anti-énumération
- docs/journal/2026-07-2X-jwt-authentication.md
- docs/STATUS.md : "Authentification JWT opérationnelle (login). CORS et
  endpoints d'écriture restent à faire."
- docs/backlog/todo.md mis à jour

Si le mapping provider Symfony ↔ CoreCredential Domain pose une difficulté
d'intégration inattendue (Symfony Security attend généralement un objet
UserInterface — à réconcilier avec l'agrégat Domain pur qu'on a construit,
sans dépendance framework), ARRÊTE-TOI et documente le problème précisément
plutôt que de faire un compromis architectural non discuté (par exemple,
NE PAS faire hériter CoreCredential lui-même de Symfony UserInterface — ça
casserait ADR-002 ; la bonne solution est probablement un petit adapter
Infrastructure séparé qui implémente UserInterface en enveloppant le Domain).

Relance phpstan/deptrac/phpcpd/phpunit. Colle le contenu intégral de tous les
fichiers créés/modifiés et les résultats.
Le point d'attention que j'ai mis en garde explicite dans le prompt (UserInterface de Symfony vs pureté du Domain) est le vrai risque de cette vague — je m'attends à devoir vérifier ça de près au retour.
```

## Décisions prises

Décisions attribuées : non déterminable avec certitude

---

# Journal — Authentification JWT (login)

Date : 2026-07-21

## Livré

- Bundle `lexik/jwt-authentication-bundle` + clés RSA hors Git (`config/jwt/*.pem`)
- `SecurityUser` + `CoreCredentialUserProvider` + enrichissement payload
  (`public_id` uniquement — pas d'`account_id` interne, ADR-018)
- `POST /api/v1/auth/login` → `{token}` ; `/api/v1/*` protégé
- `PartyAccountRepository::findByEmail()`
- Commande idempotente `app:core:bootstrap-admin-credential <password>`
- Tests intégration login + endpoint protégé ; GetPartyAccount adapté au JWT

## Architecture

Domain `CoreCredential` inchangé (pas de `UserInterface`). Adapter Infrastructure
uniquement — cf. `docs/decisions/2026-07-21-jwt-lexik.md`.

## Suite

CORS, endpoints d'écriture Party.
