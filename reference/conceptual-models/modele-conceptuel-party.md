# Modèle Conceptuel — Module Party (tiers unifié)

**Statut** : Figé (V1.5) — balayage confrontation legacy 24 juillet 2026 (V1.2 initiale 14/07 ; V1.4 groupes/franchise 19–20/07)
**Anciennement nommé** : module `crm_` (renommé en `party_` le 14/07/2026 — voir décision ci-dessous)
**Remplace** : `ost_amicale`, `ost_client`, `ost_com_fournisseur`, identité de `ost_user`
**Convention de nommage** : préfixe par module (`party_`, `core_`, `ref_`).

## Pourquoi "Party" et pas "CRM"

Ce module modélise **le tiers** (qui est ce compte, quel rôle il porte, qui peut agir pour lui) — une brique fondationnelle consommée par Booking, Invoicing, Contracting **et** le futur vrai module CRM (leads, opportunités, pipeline commercial, activités — fonctionnalités qui n'existaient pas dans l'ancien logiciel). Le nommer `crm_` aurait entré en collision avec ce futur module, et avec le vocabulaire "compte comptable" du futur module Finance. **"Party"** est le nom reconnu en modélisation d'entreprise pour ce pattern exact (entité unifiée personne/organisation avec rôles).

## Principe directeur

Un tiers n'a pas de type fixe : il porte un ou plusieurs **rôles** qui évoluent dans le temps. On découple :
- **L'identité** (qui est ce tiers) → `party_account`
- **Le rôle** (ce qu'il fait pour la plateforme, à un instant T) → `party_account_role`
- **La fonction/l'accès** (quelle personne agit avec quelle casquette pour quelle organisation) → `party_account_function`
- **La segmentation commerciale** (base du pricing) → tags/groupes, indépendants du rôle, reportés
- **L'authentification** (comment on le vérifie) → `core_credential`, module séparé

## Entités — Module `party_`

| Table | Rôle |
|---|---|
| `party_account` | Identité pivot, fine, jointe partout. `nature` (person/organization) + `parent_account_id` pour sous-comptes B2B + `logo_url` (cache) + devises d'affichage/facturation (défauts, pas contraintes) |
| `party_account_address` | Adresses multi-valeurs, typées (legal/billing/delivery/domiciliation), historisées |
| `party_role` / `party_role_translation` | Référentiel de rôles (table, pas ENUM) + libellés traduits (en/fr/ar) |
| `party_account_role` | Association historisée account↔rôle, cumulable |
| `party_account_person_identity` | Extension 1-1, nature=person — volontairement minimale (`first_name`/`last_name`) |
| `party_account_organization_identity` | Extension 1-1, nature=organization (matricule fiscal, RC, comptes comptables d'export…) |
| `party_function` / `party_function_translation` | Référentiel des fonctions métier + libellés traduits, inclut la fonction générique `member` |
| `party_account_function` | Attribution historisée d'une fonction (ou accès basique via `member`) à une personne, contextualisée par organisation, cumulable |
| `party_account_attribute` | JSONB, une ligne/compte — soupape anti-dette technique (champs rares) |
| `party_account_document` | Documents ET pièces d'identité versionnés dans le temps |
| `party_account_office` | Extension 1-1 : marque qu'un compte est un bureau opérationnel (entité légale par pays), devise par défaut |
| `party_account_office_relation` | Lien approuvé et historisé tiers↔bureau (client/fournisseur), obligatoire avant transaction |
| `party_tax_exemption_type` (+ trad.) / `party_account_tax_exemption` | Exonérations TVA / timbre, indépendantes, historisées |
| `party_assignment_type` (+ trad.) / `party_account_manager_assignment` | Responsables commercial / recouvrement (distinct de `party_account_function`) |
| `party_account_credit_limit` | Plafond / rallonge par devise (autorisation de découvert) |
| `party_account_commercial_policy` | `force_on_request` / `block_when_insufficient_balance` (colonnes typées) |

## Entités — Module `core_`

| Table | Rôle |
|---|---|
| `core_credential` | Authentification multi-provider (local/google/facebook/api_key), transverse à tous les rôles, découplée de Party |

## Entités — Module `ref_` (référentiel commun, partagé entre modules)

| Table | Rôle |
|---|---|
| `ref_language` | Langues supportées (en/fr/ar), EN = pivot. Extensible sans migration lourde |
| `ref_currency` | Devises supportées (ISO 4217), avec `minor_unit` (nb décimales, ex: TND=3) |

## Décisions clés et justification

1. **Pas de table unique avec colonne `type`** — c'est le piège qui a produit `ost_amicale` (100+ colonnes, 90% NULL selon le rôle).
2. **Email = clé pivot d'unicité** (unique, cf. auth/notifications) — téléphone volontairement non-unique (retour d'expérience legacy).
3. **`parent_account_id` (self-FK)** — modélise le pattern B2B distributeur : une agence "master" ouvre et gère des sous-agences ; la relation commerciale/juridique reste portée par l'agence maître. Plafond et pricing des sous-comptes délégués au module Pricing/Finance, pas géré ici.
4. **Documents d'identité versionnés, pas figés** — CIN/passeport/permis vivent dans `party_account_document`, qui porte `document_number`/`issue_date`/`expiry_date`. Un renouvellement de passeport = nouvelle ligne, l'historique est conservé nativement.
5. **Champs rares → JSONB soupape, pas colonnes dédiées** — `party_account_attribute` accueille des attributs sporadiques qui ne justifient pas une colonne toujours-NULL sur une table jointe en masse. Ce n'est **pas** un EAV générique ni un `autre_config` (§14). Un attribut devenu stable doit être promu en colonne typée. (Les exemples legacy `birth_date`/`marriage_date` ont été **écartés** du produit le 24/07 — la soupape reste utile comme mécanisme.)
6. **Authentification sortie du module Party** — `core_credential` vit dans un module séparé (`core_`) : préoccupation transverse et sécuritaire, pas une donnée de relation commerciale.
7. **Comptes `channel`/`system`** — remplacent l'ancien "compte passager générique" pour le web ; un compte par canal, traçabilité propre.
8. **BIGINT identity + `public_id` UUID** — clé technique séquentielle pour la performance (évite la fragmentation d'index causée par un UUID v4 aléatoire) ; `public_id` exposable en API/URL.
9. **Multilingue ciblé, pas systématique** — seul le contenu (libellés `party_role`/`party_function`) est traduit, jamais les données métier (`display_name`, adresses...). Trois langues (`ref_language`) : EN pivot, FR, AR.
10. **Logo = document + cache** — le logo est un `party_account_document` (`document_type='logo'`, historisé), avec `logo_url` dénormalisé sur `party_account` pour les lectures fréquentes.
11. **`created_by`/`updated_by` systématique sur les tables mutables du tiers critique** — les tables historisées en append-only (`party_account_role`, `party_account_function`) n'ont que `created_by` : on ne fait jamais d'UPDATE sur leur contenu, on clôture une ligne (`valid_to`) et on en crée une nouvelle.
12. **Adresses multi-valeurs dès le départ, pas différées** — l'ancien `ost_client` avait déjà 3 adresses distinctes (`adresse`, `delivery_adresse`, `adressedomiciliation`) : besoin prouvé, pas spéculatif. Migrer une colonne plate vers une table plus tard implique une vraie migration de données ; le faire maintenant, avant volume, est quasi gratuit.
13. **Pas de NULL magique pour le contexte interne** — `party_account_function.organization_account_id` est toujours renseigné. Le contexte "interne" (staff back-office) pointe vers un `party_account` réel représentant l'agence exploitant la plateforme elle-même (à créer au bootstrap). Prépare aussi le futur module Facturation (identité légale de l'émetteur).
14. **Fusion accès/fonction (`party_account_member` supprimée)** — l'ancienne notion d'"a le droit d'agir pour cette organisation, sans fonction précise" devient la fonction générique `member` dans `party_account_function`. Une seule table au lieu de deux quasi identiques, moins d'écriture dupliquée.
15. **Multi-bureau (tenant multi-pays)** — un "bureau" (Tunisie, Algérie, France...) est un `party_account` comme n'importe quel tiers, pas une notion à part : il peut être client/fournisseur d'un autre bureau du même groupe. `party_account_office` marque juste "ce compte est un de mes bureaux" (devise par défaut, code). Le rattachement tiers↔bureau (`party_account_office_relation`) est **obligatoire** avant toute transaction, avec workflow d'approbation — la visibilité comptable entre bureaux (grand livre partagé ou non) est hors périmètre Party.
16. **Décisions sur un tiers vivent dans Party** (balayage 24/07) — exonérations fiscales, plafond/découvert, affectations de responsables, politique commerciale. Règle : « Party porte ce qu'on décide SUR un tiers ; Règlements porte ce qu'on constate AVEC lui. »
17. **Plafond = autorisation de découvert par devise** — une seule table pour permanent et rallonge (`valid_to`). Formule Domain : `disponible = solde grand livre (devise) + plafond + rallonges valides`. Pas de ventilation par service. Le paiement libère de la capacité.
18. **Affectations distinctes de `party_account_function`** — responsable commercial/recouvrement d'un client ≠ fonction exercée dans une organisation.

## Hors périmètre (volontairement, cf. `sujets-reportes.md`)

Pricing/remises/marges, point de vente, permissions RBAC fines, lien hôtel↔fournisseur, et le "vrai" module CRM (leads/opportunités/pipeline/activités). Plafond, exonérations, affectations et politique commerciale : **intégrés en V1.5** (balayage 24/07).
