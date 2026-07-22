# Modèle conceptuel — Permissions / Franchises / Documents-Emails / Auth avancée / Configuration avancée

Session du 20/07/2026. Avant-dernier module (voir `00-INDEX.md`).

## Périmètre et non-périmètre

**Dans ce module** : RBAC dynamique (ADR-017), délégation d'administration (franchise/B2B), qualification franchise, moteur de documents/emails, sessions/MFA/tentatives d'authentification, configuration applicative globale.

**Explicitement hors périmètre, routé ailleurs** :
- Calcul de commission franchise → Pricing (déjà existant, `rule_nature='commission'`)
- Modalités de paiement actif/inactif (§43) → réouverture Pricing V1.1 (`rule_nature='payment_modality'`), traitée par le pilote DB Architect
- Journal générique (`booking_log` → `log_activity`/`log_audit`) → réouverture Booking, nouveau module `log_`, traitée par le pilote DB Architect
- Calcul de prime agent (§29) → futur module Performance/Objectifs, non prioritaire
- Rate limiting IP, chiffrement du secret TOTP (vault/secret manager), parsing user-agent/géolocalisation IP → décisions d'infrastructure, remontées à "00-Main DEV Backend"

## RBAC (ADR-017)

Deux tables décomposent ce que le legacy confondait dans un seul "rôle Symfony" : `core_permission` (catalogue des actions gatées, opt-in inversé — une ligne = action fermée par défaut) et `core_role` (groupe de permissions nommé, extensible, distinct de `party_role`). `core_permission.is_delegable` porte le plafond fixe et universel de délégation (jamais configurable par franchise). Attribution : `core_role_permission` (composition d'un rôle), `core_account_role` (attribution historisée compte↔rôle, cumulable), `core_permission_grant` (octroi direct — rôle XOR compte, l'exception jamais la norme).

`core_permission_category` (auto-référencée) est purement organisationnelle — zéro impact sur `denyUnlessGranted()`.

**Discipline de mise en production actée** : une permission critique doit être livrée avec sa fonctionnalité dans la même release, jamais ajoutée après coup (sinon retour au problème "fonctionnalité qui disparaît après une mise à jour").

## Franchises

Décision centrale : une franchise est un `party_account` à part entière (`party_role='franchise'`, nouveau rôle distinct malgré la ressemblance avec `fournisseur`/B2B — le sens de la dette est inversé, justifie un rôle séparé). Extension `party_account_franchise` (1-1, même pattern que `party_account_office`). `pointvente.office_account_id` peut désormais référencer soit un bureau interne (`party_account_office`) soit une franchise (`party_account_franchise`) — règle applicative élargie, **aucun changement de colonne**. Le grand livre reste toujours porté par le `party_account` franchise, jamais par `pointvente` (principe non modifié).

La délégation d'administration (auto-gestion utilisateurs/marges/infos) est un mécanisme générique partagé avec les agences B2B distributrices (`parent_account_id`), pas un mécanisme franchise-spécifique — c'est le RBAC + `is_delegable` qui la porte, aucune table dédiée.

## Documents / Emails

Moteur unique pour email et document (convergence confirmée : les deux rendent vers HTML). Format de stockage neutre (`{{code_composant}}`), jamais de syntaxe de moteur de templating (Twig proscrit en base — risque SSTI + portabilité stack). `document_component_type` : catalogue fermé de composants pré-calculés côté Domain (logique métier jamais écrite par le client). `document_template` : plusieurs versions possibles, **une seule active à la fois par `document_type`** (contrainte base, testé). Suppression = physique (pas de `deleted_at`, aucun document généré n'en dépend — décision actée de ne pas versionner les documents produits).

`document_trigger_rule` porte un `context_type_code` (`booking` construit avec ses dimensions ; `invoicing` seedé sans dimension, déclenchement brut uniquement, en attendant un 2ᵉ besoin réel confirmé ; `none` pour les emails système). `document_recipient_rule` : rôle dynamique et adresse statique combinables (pas de XOR, contrairement à RBAC).

## Auth avancée

Correction au passage : `core_credential.provider` devient une vraie FK (`core_credential_provider`), incohérence détectée en session. Sessions (`core_session`, partitionnée) avec refresh token **rotatif** — hash courant + hash précédent, jamais en clair. Réutilisation détectée → révocation de **toutes** les sessions du compte + notification (pas seulement la session compromise). Limite de sessions concurrentes et seuil de verrouillage : `party_role_security_policy`, gouvernance interne non délégable.

MFA : TOTP uniquement en V1 (WebAuthn évalué, écarté pour l'instant — audience peu technophile). Secret chiffré (`secret_encrypted`) — **premier besoin de chiffrement applicatif du projet**, vault/secret manager à trancher côté Backend. Codes de récupération à usage unique, hachés.

`core_auth_attempt` (partitionnée, `account_id` nullable) séparée de `log_activity` — isole le bruit d'un brute force, rétention courte indépendante.

**Limite connue et assumée** (trouvée par test sandbox) : `core_session.current_refresh_token_hash` n'a pas d'unicité garantie en base (la colonne de partition `created_at` devrait figurer dans tout index unique, ce qui la rendrait inopérante en pratique). Protection réelle = entropie du token généré côté Application, pas une contrainte SQL. Décision : accepté tel quel.

## Configuration applicative

`config_application_setting` : table **singleton** (colonnes explicites typées, pas de clé/valeur générique — anti-EAV, cohérent avec le principe déjà appliqué partout). Justifié par ADR-004 (1 serveur = 1 client). Premier usage : `mfa_issuer_name`. Futurs paramètres = `ALTER TABLE` additif, jamais une ligne générique.

## Décisions clés et justification

1. **`core_permission` ≠ `party_role`** — deux notions de rôle distinctes coexistent délibérément (fonctionnel vs structurel), ne jamais les confondre dans une session future.
2. **RBAC ne fait pas de filtrage de données** — confirmé explicitement par l'utilisateur ; le périmètre de visibilité franchise (voir ses seuls dossiers) est un mécanisme séparé, porté par `party_account_function` (déjà existant), pas par RBAC.
3. **`is_delegable` est un plafond fixe et universel**, jamais configurable par franchise — vérifié par test sandbox (aucune contrainte SQL ne l'enforce, c'est une règle Application cross-table, ADR-002).
4. **Un composant de document encapsule de la vraie logique métier** — catalogue fermé, extensible uniquement par nouvelle version applicative, jamais par le client (distinct d'une simple variable).
5. **`document_trigger_rule` anticipe un 2ᵉ contexte (`invoicing`) sans le construire** — cohérent avec le principe déjà appliqué sur ce projet ("un mécanisme se généralise dès qu'un 2ᵉ cas d'usage réel apparaît", appliqué ici par anticipation documentée plutôt que par réouverture future).
6. **Sécurité maximale par défaut sur Auth** (rotation refresh token, révocation totale du compte, notification systématique) — choix explicite et répété de l'utilisateur ("toujours le plus sécurisé").
