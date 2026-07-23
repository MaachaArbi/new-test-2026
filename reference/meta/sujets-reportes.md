# Sujets Reportés — À ne pas oublier

Document vivant. Chaque sujet volontairement écarté du périmètre en cours doit être noté ici avec assez de contexte pour reprendre la discussion sans tout re-expliquer.

**16/07 (session de cadrage)** : fusion de `sujets-reportes-additions.md` dans ce fichier (section 21 remplacée, sections 22-25 intégrées). Le fichier `sujets-reportes-additions.md` ne doit plus être utilisé séparément.

**19/07 (régénération post-session Pricing)** : correction d'une collision de numérotation — la session Pricing avait généré des points §47 à §51 sans relire ce fichier maître au préalable, entrant en collision avec les vrais §47 ("Stratégie de migration en parallèle") et §48 ("Trois points identifiés par le chat pilote Backend") déjà existants. Renumérotés en §49 à §53. Nouveau point §54 ajouté (récapitulatif de clôture du module Pricing).

**20/07 (réouverture ciblée Booking — généralisation booking_log)** : §19 clos (module transverse `log_` construit et testé), §48 point 2 clos (ADR-006/`log_audit` enfin construite). Nouveau module documenté dans `00-INDEX.md` : **Log** (`log_entity_type`/`log_activity`/`log_audit`), `modele-conceptuel-log.md`, `schema-log-v1.sql`, `diff-booking-log-generalization.diff`.

**20/07 (2e réouverture ciblée Booking — 5 ajouts additifs)** : §6/§33 clos (renommage `booking_accommodation_detail` + FK réelle `ref_accommodation`), §30 clos (`booking_payment.collected_by_account_id` + FK `pointvente_id`/`pointvente_paiement_id` sur `booking`), §35 clos (`processing_status_code` + `log_activity`, option hybride), §49 clos (définitions `api_in`/`api_out` corrigées, codes inchangés), service `guide` ajouté à `booking_service_type`. **Conséquence structurelle** : `schema-ref-static-v1.sql` et `schema-pointvente-v1.sql` doivent désormais s'exécuter **avant** `schema-booking-v1.sql` (ordre corrigé dans `00-INDEX.md`). Point "attribution de siège" (`seat_id`) explicitement reporté à une session dédiée à part.

---

## 1. Module Pricing / Contracting (remise, marge, conditions commerciales) — ✅ RÉSOLU le 19/07/2026 (partie Pricing), voir §54
**Origine** : sorti d'`ost_amicale` (30+ colonnes `remise_*`/`marge_*`/`marge_globale_*` par ligne de produit).
**Décision provisoire** : module indépendant (préfixe à définir, ex `pricing_`), référence `party_account.id`. Gère aussi la délégation de pricing des sous-comptes.
**À trancher** : structure du module, granularité, versionning/historisation des conditions.
**Mise à jour (16/07)** : positionné en ordre 5 des modules restants (voir `00-INDEX.md`), après le référentiel statique — dépendance bloquante confirmée. Doit aussi trancher au démarrage si "Rules Engine" (mentionné dans l'ancien `00-project_overview.md`) est absorbé ici ou reste un sujet distinct.
**✅ Résolu le 19/07/2026** : module Pricing figé — voir §54 pour le détail complet. Rules Engine confirmé de facto absorbé. Le "Contracting" (tarifs d'achat, micro-marges par arrangement/politique enfant/réduction chambre) reste distinct et non traité — voir §54, point sur la découverte des micro-marges de contrat.

## 2. Plafond & solde (multi-devise, multi-produit, multi-bureau)
**Origine** : `plafond`, `soldeTemporaire`, `dateExpirationSolde`, `compte_tiers` sur `ost_amicale`/`ost_client`.
**Décision provisoire** : un plafond par devise et par produit, module dédié, indépendant de `party_account`.
**Précision** : solde scopé par (client, bureau, devise). Comptes bancaires par bureau et par devise → futur module Cash Management (point 2bis).
**Précision (module Booking)** : Booking dépend de ce module via une interface (`SolvencyCheckerInterface`, stub retournant toujours `true`).
**Fusionne désormais avec le point 21 ci-dessous** — c'est le même futur module Règlements Client/Fournisseur. **Le plafond lui-même reste hors Règlements, voir section 25 (mise à jour) ci-dessous — appartient au futur module Pricing/Finance.**

## 2bis. Cash Management (comptes bancaires par bureau/devise) — ✅ RÉSOLU, voir point 21bis

**Origine** : "le bureau de Tunisie peut avoir plusieurs comptes bancaires, chacun dans une devise".
**Décision provisoire (obsolète)** : hors périmètre Party. Référence `party_account_office.account_id`.
**Précision (module Booking)** : `booking_settlement`/`booking_payment` constatent des faits datés et immuables mais ne calculent aucune échéance ni solde réel — Cash Management lit ces faits via Règlements, jamais directement.
**✅ Résolu le 17/07/2026** : Cash Management V1.0 figé. Cardinalité tranchée en **N-N symétrique** compte↔bureaux (aucun titulaire privilégié, corrige l'hypothèse "1 compte = 1 bureau" ci-dessus). Voir point 21bis et `modele-conceptuel-cash-management.md`.

## 3. Point de vente — ✅ RÉSOLU le 17/07/2026

**Origine** : rattache un utilisateur interne à un bureau physique.
**Décision finale** : table de référence légère `pointvente`, FK `office_account_id` vers `party_account(id)` (doit porter `party_account_office`, règle applicative). **Volontairement PAS un `party_account`** — question posée explicitement par l'utilisateur (réutiliser `party_account` avec un attribut ?), tranchée par la négative : `party_account` = acteur économique (achète/vend/doit), un point de vente n'est acteur de rien. Voir `modele-conceptuel-pointvente.md` pour le raisonnement complet.
**Cardinalité** : N points de vente par bureau, très variable selon le bureau, aucune limite.
**Rattachement client** : confirmé inutile — le point de vente ne concerne que la résa/le paiement, jamais le tiers.
**Fiche** : adresse/contact propres au point de vente (pas hérités du bureau). Pas de `code` court (aucun besoin réel confirmé — facile à ajouter plus tard). Désactivation via `is_active` simple, jamais de suppression physique.
**Précision (module Booking)** : `pointvente_id`/`pointVentePaiement_id` confirmés sur données réelles (hôtel ET maritime) — deux rôles distincts (vente / paiement, potentiellement des sites différents) pointant vers la **même** table `pointvente`. Booking n'a pas été modifié dans cette session — ajout des deux FK nullables à faire dans une session dédiée au chat pilote Booking. **✅ Fait le 20/07/2026** : `booking.pointvente_id`/`booking.pointvente_paiement_id` ajoutées (nullable, `REFERENCES pointvente(id)`), voir clôture point 30.
**Cash Management** : vérifié, aucun recouvrement — `cash_session.office_account_id` est purement informatif, scopé par `holder_account_id` (le caissier), indépendant du point de vente.
**Enrichissements évalués et écartés** (horaires d'ouverture, géolocalisation) : aucune valeur fonctionnelle réelle identifiée, refusés pour éviter la sur-ingénierie.
**Nouveau sujet ouvert par cette session** : voir point 29 ci-dessous (rendement agents/points de vente).

## 4. `party_account_group` — tags/segmentation commerciale — ✅ RÉSOLU le 19/07/2026
**Décision provisoire** : structure actée conceptuellement (`party_account_group` + `party_account_group_member`, many-to-many, historisé), implémentation reportée.
**À trancher** : `group_type` (une dimension ou plusieurs superposées ?), administration des groupes.
**✅ Résolu le 19/07/2026** : déclenché par un besoin réel du module Pricing (cibler des groupes d'affiliés partageant les mêmes règles de marge). Réouverture ponctuelle documentée de Party (`party-account-group-extension.diff`) : `party_account_group_type` (référentiel de dimensions, **plusieurs superposées** — un compte peut appartenir à des groupes de types différents simultanément, décision qui lève l'inconnue laissée ouverte ci-dessus), `party_account_group` (scopé par dimension), `party_account_group_member` (historisé, même pattern que `party_account_role` : `valid_from`/`valid_to`, index partiel sur l'appartenance active). Administration des groupes : reste hors périmètre de cette réouverture (structure de données seulement). Testé sur PostgreSQL réel, y compris la superposition de deux dimensions sur un même compte. Voir §54 pour le contexte complet côté Pricing.

## 5. RBAC fin / permissions granulaires — ✅ RÉSOLU le 20/07/2026
**Décision actée** : `party_function` + `party_account_function` implémentées (Party). Résolution des droits construite par le module Permissions/Franchises/Config (figé 20/07) : `core_permission` (opt-in inversé, ADR-017), `core_role`, `core_role_permission`, `core_account_role`, `core_permission_grant`. Voir `schema-permissions-config-v1.sql` et `modele-conceptuel-permissions-franchise-config.md`.

## 6. Lien Hôtel ↔ Fournisseur — ✅ RÉSOLU le 17/07/2026
**Décision provisoire (obsolète)** : ignoré pour le moment, à traiter avec le module Contracting/référentiel Hôtels. `booking_hotel_detail.hotel_code` pointera vers ce futur référentiel.
**✅ Résolu le 17/07/2026** : module **Référentiel Hébergement & Géographie** figé V1.0 — `ref_accommodation` existe désormais. Le branchement réel de la FK sur `booking_hotel_detail` (et son renommage en `booking_accommodation_detail`) reste une action distincte, reportée à une session dédiée au chat pilote Booking — voir point 33 ci-dessous. Voir `modele-conceptuel-ref-static.md` et `schema-ref-static-v1.sql`.
**✅ Renommage/FK effectués le 20/07/2026** : `booking_hotel_detail` renommée `booking_accommodation_detail`, `hotel_code` (texte libre) remplacé par `accommodation_id BIGINT REFERENCES ref_accommodation(id)` (nullable — résas antérieures à la réconciliation, ou import legacy jamais rapproché), `hotel_name_snapshot` renommé `accommodation_name_snapshot`. Testé en sandbox avec un `ref_accommodation` réel (chaîne complète pays→région→ville→catégorie→hébergement). Conséquence structurelle : `schema-ref-static-v1.sql` doit désormais s'exécuter **avant** `schema-booking-v1.sql` (ordre corrigé dans `00-INDEX.md`).

## 7. RGPD / règles de suppression définitive
**À trancher** : politique de purge (délai, périmètre, cascade sur `party_account_document`, `core_credential`, `booking_provider_snapshot`...).

## 8. Stratégie d'indexation détaillée / volumétrie réelle
**À trancher** : au fil de la montée en charge, revoir index (notamment `pg_trgm` sur `display_name`, **automatisation pg_partman pour `booking` — non encore mise en place, obligatoire avant production**).

## 9. Dédoublonnage à la migration — ✅ RÉSOLU (politique actée)
**Décision actée** : migration sélective assumée — certaines données legacy seront abandonnées plutôt que migrées telles quelles. Cohérent avec la stratégie de migration en parallèle (§47) : seules les données de référence/mapping propres sont importées, jamais un mapping automatique aveugle du legacy.

## 10. Génération `public_id` (UUIDv7) — ✅ RÉSOLU le 20/07/2026
**Décision actée** : rester en UUIDv4 (`gen_random_uuid()`), ne pas basculer en UUIDv7. Justification : `public_id` n'est jamais la clé primaire réelle dans ce projet (c'est `id BIGINT identity`, ADR-018) — le bénéfice principal d'UUIDv7 (performance d'écriture sur un index séquentiel à fort volume) ne s'applique pas ici. `public_id` sert uniquement d'identifiant externe exposé (API/URL) sans révéler de volumétrie — l'aléatoire pur (v4) n'est même pas un inconvénient pour cet usage. Basculer en v7 ajouterait de la complexité applicative (Symfony) pour un gain quasi nul dans cette architecture précise.

## 11. `legal_form_code` — référentiel forme juridique — ✅ RÉSOLU le 20/07/2026 (à construire)
**Décision actée** : besoin réel confirmé par l'utilisateur — référentiel **générique international** (pas uniquement les formes tunisiennes). Rattaché à `party_account_organization_identity` (Party, déjà existant) via une nouvelle colonne `legal_form_code` + table de référence `party_legal_form` (jamais d'ENUM, convention du projet). **Reste à construire** — petite réouverture ponctuelle de Party, non encore faite à la date de cette entrée.

## 12. Périmètre exact du module `core_` — ✅ RÉSOLU
Sessions/tokens JWT/MFA résolus par la réouverture Auth avancée (`diff-core-auth-avancee.sql`, module Permissions/Franchises/Config, figé 20/07). Permissions RBAC fines résolues par le même module (`core_permission`/`core_role`/etc., voir point 5). Périmètre `core_` désormais stable : authentification, sessions, MFA, tentatives de connexion, politique de sécurité par rôle.

## 13. Renommage crm_ → party_ (historique) — ✅ RÉSOLU
Vérifié le 20/07/2026 : aucun fichier `crm_*` obsolète ne traîne dans le Project.

## 14. Paramètres métier par compte (JSONB `autre_config` legacy) — ✅ TRIAGE COMPLET le 20/07/2026

**Principe acté** : le JSON `autre_config` legacy mélangeait au moins 7-8 domaines métier différents dans un seul fourre-tout — décision explicite de l'utilisateur de **ne pas reproduire ce pattern**, même sous une forme "améliorée" (pas de nouveau JSON générique). Chaque paramètre rejoint son module propriétaire naturel, avec un vrai typage.

Triage complet des 21 paramètres identifiés (capture legacy `ost_amicale`) :

| Paramètre | Destination | Statut |
|---|---|---|
| `BOOK_NOW_PAY_LATER` | `booking.option_expiry_at` | ✅ Construit |
| `ALL_RESERVATIONS_ON_REQUEST` | `booking.is_on_request` | ✅ Construit |
| `DISABLE_PAYMENT_WITHOUT_BALANCE` | Plafond/solvabilité (§2/§25bis, `SolvencyCheckerInterface`) | ⏳ Rattaché à un point déjà ouvert, session Finance dédiée |
| `HIDE_LIST_FACTURE` | RBAC — permission "voir liste factures" | ⏳ Permission à ajouter dans `core_permission` (additif simple, pas de nouvelle session) |
| `HIDE_PRINT_INVOICE` | RBAC — permission "imprimer facture" | ⏳ Permission à ajouter dans `core_permission` (additif simple) |
| `GOLD_PARTNER` | `party_account_group` (dimension `commercial`) | ⏳ Devient une appartenance de groupe plutôt qu'un booléen — à créer au moment de l'usage réel |
| `ADMINISTRATOR_AGENCY` | Périmètre de visibilité — `party_account_function` (pas RBAC, confirmé §modèle Permissions : "RBAC ne fait pas de filtrage de données") | ⏳ Rattaché à un mécanisme déjà prévu, lié à API OUT |
| `NUM_SUPPORT`, `SHOW_CONTACT_INFO_INSTEAD_OF_AGENCY`, `COMMENT_PAYEMENT` | Variables de contenu — module Documents (§41, figé) | ⏳ Variables dynamiques par affilié, à intégrer au moteur de templates existant |
| `MATRICULE_REQUIRED`, `EMAIL_REQUIRED`, `MATRICULE_LABEL` | **Hors périmètre BDD — migre vers l'admin du CMS** (décision utilisateur, 20/07) | ❌ Pas un sujet de ce Project |
| `HIDE_METHOD_PAYEMENT_IN_TARIF_DISPO`, `HIDE_ADDRESS_IN_TARIF_DISPO` | **Hors périmètre BDD — migre vers l'admin du CMS** (décision utilisateur, 20/07) | ❌ Pas un sujet de ce Project |
| `DISABLED_SEND_MAILS` | Module notifications, via une **règle d'exclusion** plutôt qu'un booléen isolé (orientation actée, plus extensible — permet de distinguer plus tard "pas de confirmation" de "pas de relance commerciale") | ⏳ Orientation de conception actée, pas construit — pas de trigger réel identifié à ce jour |
| `PRECALCULATED_RATES`, `PRECALCULATED_RATES_WITH_PROMO` | Config moteur de recherche/tarification — **transitoire, disparaîtra avec le futur module tarifs précalculés** | ⏳ Ne pas construire maintenant, rattaché à ce futur module explicitement |
| `PROVIDER_API_FRANCHISE`, `URL_API_FRANCHISE`, `LOGIN_API_FRANCHISE`, `PASSWORD_API_FRANCHISE` | Supprimés — remplacés par `party_account_franchise` + future table de connexion Provider Integration | ✅ Confirmé obsolète par l'utilisateur |

**Décision globale de l'utilisateur (20/07)** : les points marqués ⏳ restent volontairement **ouverts et non construits** — pas de précipitation à leur donner une structure de détail avant d'avoir une vision d'ensemble du projet et, idéalement, une première vue des interfaces réelles. Cohérent avec la discipline déjà appliquée sur tout le reste du projet (ne jamais construire avant qu'un besoin réel soit confirmé).

## 15. Services Booking non encore confrontés à des cas réels
**État (16/07)** : Hôtel et Maritime validés en profondeur sur données réelles (JSON brut, >30 réservations). Vol/Billetterie confronté seulement à des captures d'écran et un dump SQL initial (jamais d'export JSON brut comme Hôtel/Maritime). Transfert validé par déduction uniquement. Excursion/spa/visa/pool_access/bus/train : structure générique non éprouvée sur cas réel.
**Recommandation** : si un futur travail sur Booking reprend, prioriser un export JSON brut Vol/Billetterie (le format qui a été le plus révélateur), avant excursion/spa/visa qui semblent plus simples et à faible risque.

## 16. Conducteur additionnel (location de voiture)
**Décision provisoire** : structure déjà supportée nativement (`booking_traveler` 1-N, `is_pax_leader`, `charge_type_code='supplement'`). Non confirmée sur cas réel à 2+ conducteurs.

## 17. Réconciliation achat/commission maritime — ✅ RÉSOLU
**Mise à jour (16/07)** : la règle "booking_charge additif / booking_settlement redécoupe la marge, jamais additif" a été confirmée indépendamment sur hôtel, vol ET maritime (via `commission`/`commission_b2b` en taux, champ `rate` de `booking_settlement`). Considérée stable, close.

## 18. Historique des transitions de statut (`booking.status_code`) — ✅ RÉSOLU
**Décision actée** : le besoin est désormais porté par `log_activity` (`metadata.status_code_snapshot`, ex-`booking_log.status_code_snapshot`, généralisé le 20/07 — voir §19). Pas de table `booking_status_history` dédiée.

## 19. `booking_log` — périmètre volontairement local à Booking
**Décision actée** : si Party, Règlements ou un autre module expriment un jour le même besoin, extraire un module `notification_`/`event_log_` transverse à ce moment-là (2 cas d'usage réels justifient une généralisation, pas un seul).
**Mise à jour (16/07)** : la règle des "2 cas d'usage réels" est désormais atteinte — le futur module Provider Integration (module 6, voir `00-INDEX.md`) exprime le même besoin de logs centralisés (API IN/OUT, outils techniques). Un module transverse de logs peut être conçu dès que les cas d'usage seront cadrés, indépendamment de l'ordre des autres modules.
**Mise à jour (19/07)** : un 3ᵉ signal est apparu — `pricing_rule_log` (module Pricing, ✅ figé) reproduit le même pattern (`event_type`/`field_changes JSONB`/acteur). Voir §54.

**Décisions actées avec le chat pilote (20/07) — NON ENCORE IMPLÉMENTÉES, à construire dans une session de réouverture Booking dédiée** :
1. `entity_type` : PAS un `VARCHAR` libre — table de référence `log_entity_type` (code, libellé), jamais une chaîne libre (principe anti-ENUM du projet).
2. `status_code_snapshot` (propre à `booking_log` actuel) : NE reste PAS une colonne dédiée sur la table généralisée — déplacé dans `metadata` (JSONB). Booking y stockera `{"status_code_snapshot": "confirmed"}`, comportement préservé à l'identique côté applicatif.
3. Index composite `(entity_type, entity_id, created_at DESC)`.
4. Distinction à documenter clairement dans le futur modèle conceptuel : **`log_activity`** = journal métier lisible/visible client (qui a fait quoi), peuplé explicitement par le code applicatif Symfony ; **`log_audit`** = traçabilité technique/sécurité au niveau ligne (avant/après), peuplée par un trigger générique réutilisable posé sur les tables critiques (ADR-006, décision papier depuis 6 mois, jamais construite — voir §48). Même forme structurelle, deux finalités distinctes, à ne jamais confondre.
5. Emplacement : nouveau préfixe transverse `log_` (ni `core_`, ni `party_`, ni `booking_` par convention historique) — nouvelle entrée dédiée dans `00-INDEX.md`.
6. Décision utilisateur (19/07) : construire `log_audit` **en même temps** que `log_activity`, dans la même réouverture — les deux sont cousines structurellement mais alimentées différemment (voir point 4).
7. **Précédent déjà construit et vérifié** (module Permissions/Franchises/Config, ✅ figé, `diff-core-auth-avancee.sql`) : `core_auth_attempt` est une table de log partitionnée, volontairement **séparée** de la future `log_activity` — isole le bruit d'un brute force, rétention courte indépendante. Ne pas fusionner `core_auth_attempt` dans `log_activity`/`log_audit` lors de cette réouverture — elle reste distincte par conception.
8. **Point de coordination ouvert, à trancher dans la session de réouverture** : le futur `schema-log-v1.sql` doit-il porter un mécanisme de rétention configurable générique par `entity_type` ? `core_auth_attempt` a une rétention courte demandée par l'utilisateur — vérifier si `log_activity` a le même besoin avant de dupliquer un mécanisme ailleurs.

**✅ Résolu le 20/07/2026** : module transverse `log_` construit et testé sur PostgreSQL réel (sandbox), les 8 points ci-dessus tous implémentés. `log_entity_type` (référentiel + rétention configurable par entité et par nature de log), `log_activity` (remplace `booking_log` à l'identique, `status_code_snapshot` déplacé dans `metadata`), `log_audit` (ADR-006, enfin construite — voir clôture §48 point 2), `log_audit_trigger()` (fonction générique, un seul paramètre `entity_type_code` à la pose). `booking_log` supprimée (`diff-booking-log-generalization.diff`). Voir `modele-conceptuel-log.md` et `schema-log-v1.sql`.

**Découverte en testant, non anticipée dans le cadrage** : sur une table partitionnée (`booking`), `TG_TABLE_NAME` dans le trigger générique retourne le nom de la **partition physique** (ex: `booking_y2026m07`), pas le nom logique `booking` — `log_audit.table_name` se fragmente donc par partition pour toute entité partitionnée. Documenté dans `schema-log-v1.sql`/`modele-conceptuel-log.md` ; toute requête de reporting doit normaliser ce préfixe côté Application.

**`pricing_rule_log` non fusionné** — reste un journal local à Pricing, décision explicite pour éviter de rouvrir ce module sans bénéfice clair. Point ouvert restant : le §19 avait identifié `pricing_rule_log` comme 3ᵉ signal justifiant l'extraction — le signal a servi à déclencher la généralisation, sans obliger la fusion des données elles-mêmes.

**Point de rétention (point 8) tranché** : mécanisme générique posé (`log_entity_type.activity_retention_days`/`audit_retention_days`), mais **le job de purge périodique lui-même n'est pas construit** (pas de CRON en base, cohérent ADR-002) — seulement la configuration. À construire côté Application quand le volume le justifiera.

## 20. `booking_charge` — granularité tranchée après plusieurs itérations
**Décision actée** : `booking_charge` = décomposition additive agrégée uniquement (pas de détail jour/chambre/personne). La marge/commission entre bénéficiaires vit exclusivement dans `booking_settlement`, jamais additionnée au total. Cette règle a résisté à hôtel, vol, maritime, location voiture — considérée stable.

---

## 21. ★ MODULE RÈGLEMENTS CLIENT/FOURNISSEUR — ✅ FIGÉ V1.0 (16/07/2026)

**Statut** : Conception terminée. Voir `modele-conceptuel-reglements.md` et `schema-reglements-v1.sql`.

**Ce qui a été construit** :
- Grand livre append-only (`reglement_ledger_entry`), immuable par trigger, clé `(party_account_id, party_role, currency_id)`
- Snapshot de solde (`reglement_balance`), O(comptes), réconcilié avec SUM à froid
- Instrument de paiement (`reglement_instrument`) avec cycle de vie propre
- Lettrage N-N optionnel (`reglement_matching`) — ne touche pas le solde
- Transfert de solde atomique (`reglement_transfer` + `reglement_post_transfer()`)
- Fonction de posting comme seul chemin d'écriture

**Décisions irréversibles actées** :
- L'autorisation de débit (AD) disparaît comme instrument en production
- `montant`/`montant_alloue` legacy ne migrent pas (dérivés du lettrage)
- Toute correction = contre-passation + repost (jamais un delta)
- `effective_date` ≠ `created_at` — le passé est stable

**Points de couplage avec les autres modules** :
- `booking_payer_split` → source de la projection de l'obligation (lu, jamais modifié)
- `invoice_id`/`credit_note_id` → **✅ branchés le 18/07/2026** (FK réelles ajoutées par `ALTER TABLE` additif dans `schema-invoicing-v1.sql`, Règlements non rouvert). Utilisés uniquement pour les lignes libres (écriture nouvelle) et pour la contre-passation d'un avoir (toujours une écriture nouvelle, ancré ou libre) — jamais pour une ligne facture ancrée simple, qui ne touche jamais le grand livre. Voir `modele-conceptuel-facturation.md`.
- `is_cash_like` → crochet Cash Management, ✅ résolu (voir point 21bis)
- `SolvencyCheckerInterface` → lira `reglement_balance` (implémentation prévue dans le futur module Pricing/Finance — **toujours non implémentée**, voir §25bis et §54)

---

## 21bis. ★ MODULE CASH MANAGEMENT — ✅ FIGÉ V1.0 (17/07/2026)

**Périmètre** : sessions de caisse, comptes bancaires, bordereaux de remise, transmission externe (bon de commande/prise en charge), rapprochement bancaire. Documents : `modele-conceptuel-cash-management.md`, `schema-cash-management-v1.sql`.

**Décisions actées avec l'utilisateur** :
- La caisse EST la session (pas d'entité caisse persistante), fond de caisse non persistant entre sessions du même utilisateur — confirmé conforme au principe legacy "enveloppe".
- Compte bancaire ↔ bureaux : N-N symétrique, aucun titulaire privilégié.
- Rapprochement à deux niveaux (pièce individuelle ET bordereau), coexistants.
- Mode de règlement 100% configurable via `cash_payment_method_routing` (table compagnon 1-1 de `reglement_payment_method`) — plus aucun code en dur par code de mode de règlement.
- Fongibilité de l'espèce configurable (`instrument_tracking_mode` individual/aggregate) — résout la perte de traçabilité espèces sans changement opérationnel (idée "caisse de dépense" de l'utilisateur, jamais implémentée en legacy, remplacée par ce mécanisme).
- Isolation stricte des sources activable par mode de règlement (`strict_source_isolation`) — option rare (1 déploiement/~100), activée pour ce client sur l'espèce.
- Validation caissier central tout ou rien, transmission externe PC regroupée dès la V1.
- Rattachement caissier central↔bureau simple, interne à `cash_` (pas de dépendance RBAC/module 7 — cohérent avec le modèle de vente par licence/module).

**Correctif appliqué en cours de route** : `schema-reglements-v1.sql` référençait `ref_currency(id)`, colonne inexistante (`ref_currency` a pour PK `code`). Corrigé en `currency_code VARCHAR(3) REFERENCES ref_currency(code)`, aligné sur Party/Booking. Documenté comme correctif (pas une réouverture de décision) — voir addendum dans `01-architecture_decisions.md` et `reglements-currency_code-fix.diff`.

**Points restés ouverts** :
- Routing par défaut à confirmer avec l'utilisateur pour 4 modes de règlement (`V`, `VE`, `RC`, `RI`) — posés par déduction raisonnable dans le seed, ajustables par simple `UPDATE` sans migration.
- Rapprochement bancaire testé uniquement sur données synthétiques (`ost_com_operations_bancaires` jamais fournie) — à reconfronter dès qu'un export réel de relevé sera disponible.
- Traçabilité multi-saut : un transfert entre deux sessions fait perdre la traçabilité individuelle côté receveur — jugé hors scope par l'utilisateur (cas rarissime), à revisiter seulement si un besoin réel émerge.
- `cash_reverse_movement` refuse un mouvement déjà consommé par une allocation — pas de correction en cascade automatique en V1.



## 22. Transfert de solde inter-livres — construit en V1, à documenter sur cas réels

**Origine** : besoin découvert via le bricolage legacy (ristourne + fausse réservation diverse pour reporter une dette employé vers son amicale).
**Ce qui est construit** : `reglement_transfer` + `reglement_post_transfer()`. Partiel autorisé, sans contrainte de parenté, annulable en bloc.
**Ce qui manque** : jamais testé sur un vrai cas de report de dette (le bricolage legacy ne laisse pas de données propres à analyser). Valider sur le premier cas réel en production.
**Règle** : toujours passer par `reglement_post_transfer()`, jamais d'INSERT direct.

---

## 23. Fonctions de posting complémentaires (à écrire avec l'équipe dev)

`reglement_post_transfer()` est écrite et testée. Les suivantes sont documentées comme pattern mais non implémentées en V1 (leur signature dépend de l'API Symfony) :
- `reglement_post_obligation(booking_id, payer_split_id, effective_date)` — projection de l'obligation à la validation
- `reglement_post_credit(instrument_id, booking_id, amount, effective_date)` — crédit issu d'une pièce
- `reglement_post_reversal(entry_id, effective_date, reason)` — contre-passation

**À faire** : définir les signatures avec l'équipe dev avant tout développement Symfony du module.

---

## 24. Concurrence sur reglement_balance

**Situation** : le trigger AFTER INSERT convient pour une charge modérée (un seul INSERT à la fois sur un même compte). En cas de traitement batch (ex: import de paiements en masse), plusieurs transactions concurrentes sur le même compte peuvent provoquer une contention sur la ligne de solde.
**Solution recommandée** : maintien applicatif transactionnel (`SELECT FOR UPDATE` sur la ligne de balance avant UPDATE, dans la même transaction que l'INSERT ledger). À implémenter dès que le batch est envisagé.

---

## 25. Remboursement client — mécanique connue, cas réel non éprouvé

**Mécanique** : écriture `remboursement_client` (+X dans le livre client, ramène l'avance à 0) + pièce sortante (espèces ou nouveau chèque). **Mise à jour 17/07** : la caisse est désormais débitée via `cash_post_outflow`/`cash_pay_supplier_cash` (ou équivalent générique) — le crochet Cash Management existe et est testé pour un décaissement générique, mais pas spécifiquement sur un scénario de remboursement client.
**État** : non testé sur volume réel. Le premier cas de remboursement en production devra confirmer que le solde créditeur (avance) se résorbe correctement et que la pièce sortante n'a pas besoin d'une structure dédiée.

## 25bis. Plafond (mise à jour du point 2)

Les points 2 (plafond/solde) et 2bis (cash management) avaient été reportés et fusionnés dans la section 21. Le **plafond** (montant maximum qu'un compte peut engager) n'est PAS dans Règlements — il appartient au futur module Pricing/Finance qui implémentera `SolvencyCheckerInterface`. Ce point reste ouvert, à traiter au démarrage du module Pricing (module 5).
**Mise à jour (19/07)** : Pricing est désormais figé (voir §54) et ce point a été **explicitement écarté** de son périmètre en session — nature différente d'une règle de marge conditionnelle (contrainte de solvabilité par compte/devise). Reste ouvert, candidat à une session Finance dédiée.

---

## 26. Ordre des modules restants — validé avec l'utilisateur (16/07, mis à jour 17/07)

Voir `00-INDEX.md` pour le tableau complet et le détail des dépendances. **Cash Management figé le 17/07, Point de vente figé le 17/07, Référentiel Hébergement & Géographie figé le 17/07, Facturation/Avoirs figé le 18/07** (retirés de cette liste). Résumé de l'ordre restant :
1. Product / Catalogue (nouveau, voir point 36)
2. Pricing / Contracting (marges centralisées)
3. Utilisateurs avancés / permissions / franchises + Configuration avancée
4. Contracting hôtelier avancé + Provider Integration (repoussé en dernier, voir point 37)

Backlog post-V1, non prioritaire : Loyalty Programs, "vrai" CRM, Rules Engine (probablement absorbé par Pricing), Channel Manager (probablement fusionné avec Provider Integration).

**Stock Management retiré du périmètre du projet** (décision utilisateur, 16/07).

**Mise à jour (19/07)** : Product/Catalogue (1) et Pricing (2) désormais figés — voir `00-INDEX.md` pour l'ordre à jour (Utilisateurs avancés/Configuration avancée, puis Contracting hôtelier avancé + Provider Integration).

## 27. Modules "orphelins" de l'ancien `00-project_overview.md` — arbitrage (16/07)

L'ancien `00-project_overview.md` (document de 6 mois, contexte d'une implémentation Symfony antérieure) mentionnait 6 modules absents de `00-INDEX.md` à l'époque. Arbitrage utilisateur :
- **Stock Management** → supprimé du périmètre.
- **Rules Engine** → probablement absorbé par Pricing/Contracting, à confirmer explicitement au démarrage de ce module (voir point 1 ci-dessus). **✅ Confirmé le 19/07** — voir §54.
- **Loyalty Programs** → besoin réel confirmé, mais explicitement post-V1.
- **Provider Integration** → module réel, intégré à l'ordre validé (module 6).
- **Channel Manager** → besoin réel confirmé, probablement fusionné techniquement avec Provider Integration, à confirmer au démarrage de ce module.
- **Advanced Configuration** → module réel, intégré à l'ordre validé (rejoint le module 7, fin de parcours).

## 28. Principe directeur pour les modules restants — nouvelle conception, pas de reproduction legacy (16/07)

Décision explicite de l'utilisateur, à rappeler dans chaque prompt de démarrage des modules restants : contrairement à Party/Booking/Règlements où le legacy a servi de matière première solide, les modules restants (Cash Management, Point de vente, Référentiel, Facturation, Pricing, Provider Integration hors Booking/Contracting/API, Utilisateurs/Configuration) doivent partir d'une conception **repensée depuis le besoin métier**, le legacy n'étant qu'une liste de fonctionnalités à confronter, jamais un gabarit structurel à reproduire. Exception explicite maintenue pour tout ce qui touche Booking, Contracting et les intégrations API/providers, identifiés par l'utilisateur comme la partie la plus solide du système legacy.

## 29. Rapport de rendement agents/points de vente — primes, jamais résolu (né en session Point de vente, 17/07)

**Origine** : en creusant l'utilité réelle du reporting par point de vente (session Point de vente, 17/07), l'utilisateur a exposé un besoin plus large et jamais résolu côté legacy : un rapport de performance agents/points de vente servant à calculer des primes de rendement, pour inciter les agents à traiter les réservations (répondre au client, valider, encaisser).

**Le problème de fond (non technique, politique métier)** : le traitement d'une réservation se décompose en plusieurs opérations, chacune pouvant impliquer un ou plusieurs agents différents :
1. Contact client (vérification du sérieux/engagement)
2. Contact fournisseur (ex. hôtel, pour confirmer une résa sur demande)
3. Encaissement (potentiellement partiel, réparti entre plusieurs agents)
4. Annulation/remboursement

**Question ouverte posée par l'utilisateur à son équipe, jamais tranchée** : qui touche la prime au final — un agent désigné par résa, ou une répartition par tâche entre tous les agents impliqués ? Sujet **en instance** côté métier, pas seulement côté conception.

**Pourquoi ce n'est pas dans le module Point de vente** : `pointvente` reste une table de référence légère, aucune colonne supplémentaire n'y est nécessaire pour ce futur rapport (un simple `GROUP BY pointvente.id` suffira une fois la politique tranchée). Le sujet touche les **agents = utilisateurs**, donc relève strictement du **module 5 (ex-module 7, Utilisateurs avancés/permissions/franchises)**.

**Matière première déjà disponible côté Booking** (bonne nouvelle, pas besoin de tout reconstruire) : `booking_log` et `booking_approval` tracent déjà `actor_account_id` par événement (contact client, confirmation fournisseur...). Voir point 30 ci-dessous pour le trou identifié sur l'encaissement.

**À trancher au démarrage du module Utilisateurs avancés (module 5)** : politique de répartition de prime, avec l'équipe/l'utilisateur — ce n'est pas une décision que la conception BDD peut prendre à la place du métier.

## 30. `booking_payment` — aucune attribution d'agent/caissier encaisseur — ✅ RÉSOLU le 20/07/2026

**Constat** : `booking_payment` (schema-booking-v1.sql) capture le payeur via `payer_split_id`, mais **aucune colonne ne capture quel agent/caissier a physiquement encaissé** le paiement. Si un jour l'encaissement partiel par agent doit être primé (voir point 29), cette table manquera d'une colonne (`collected_by_account_id` ou équivalent).
**Action (obsolète)** : ne rien modifier maintenant — signalement pour une session future dédiée au chat pilote Booking, à traiter en même temps que l'ajout des FK `pointvente_id`/`pointvente_paiement_id` sur `booking` (voir point 3 ci-dessus).
**✅ Résolu le 20/07/2026** : `booking_payment.collected_by_account_id BIGINT REFERENCES party_account(id)`, nullable (legacy ne l'a pas systématiquement). FK `pointvente_id`/`pointvente_paiement_id` ajoutées sur `booking` dans la même réouverture (voir clôture point 3). Testé en sandbox.

---

## 31. ★ MODULE RÉFÉRENTIEL HÉBERGEMENT & GÉOGRAPHIE — ✅ FIGÉ V1.0 (17/07/2026)

**Périmètre** : miroir local (PULL uniquement) d'OctaSoft Static Data (produit séparé, hors périmètre de conception) — géographie (pays/région/ville), hébergement et son vocabulaire (catégorie, rating, board type, amenities, tags, chaîne hôtelière, type d'implantation, catégorie de chambre, options, suppléments/réductions), fournisseur de contenu. Documents : `modele-conceptuel-ref-static.md`, `schema-ref-static-v1.sql`, extension additive de `schema-ref-common.sql` (`ref-common-hebergement-extension.diff`).

**Décisions actées avec l'utilisateur** :
- Trois familles d'entités selon la synchronisation : miroir fermé (`oct_code NOT NULL`), miroir + ajout local (`oct_code NULLABLE`), purement local (hors périmètre de cette session).
- Hiérarchie géographique stricte Pays → Région → Ville, aucun doublon de FK en aval (transitivité uniquement).
- `content_provider` : entité fondatrice pour le futur module Provider Integration (module 3), à étendre par table compagnon — jamais recréer ni dupliquer.
- `ref_property_category` (TYPE d'hébergement) : revirement assumé — colonne simple prévue au cadrage initial, devenue référentiel à part entière en cours de session. **Clarifié avec l'utilisateur le 18/07, dans ce chat pilote** : le cadrage initial ("aucune différence, à part chambre vs unité") portait sur les *attributs métier* de la typologie (effectivement identiques entre Hôtel/Villa/Appartement), pas sur son besoin de *synchronisation/évolutivité*. L'utilisateur a confirmé explicitement (18/07) que cette typologie a vocation à être enrichie via OctaSoft Static Data dans le futur (ex: nouveaux types comme "riad", "chalet"...) — ce qui justifie pleinement le référentiel à part plutôt qu'une colonne fermée. Revirement validé, pas de contradiction : les deux échanges répondaient à des questions différentes.
- `ref_property_category` (TYPE) et `ref_property_rating` (CLASSEMENT) : deux référentiels indépendants, aucun lien structurel.
- `rental_mode` (room/whole_unit) : porté par `ref_property_category`, pas par l'hébergement — référentiel interne MyGo, jamais fourni par OctaSoft.
- Aucun mapping vocabulaire-fournisseur côté client (ex: board_type ↔ Webbeds) — résolu en amont par OctaSoft Static Data, données reçues déjà taguées avec le bon `oct_code`.
- `ref_supplement.from_mmdd`/`to_mmdd` : plage récurrente annuelle en format compact `SMALLINT` (mois×100+jour), choisi pour la performance de lecture.
- `ref_language`/`ref_currency` étendus de façon strictement additive (pas de doublon d'entité) — réouverture minimale documentée d'un module figé, aucune table existante référençant ces colonnes n'a été modifiée.

**Testé sur PostgreSQL 16 réel** : hiérarchie géographique complète (pays→région→ville, jointure transitive), rejet d'import sur parent non résolu, coexistence de plusieurs ajouts locaux (`oct_code NULL`) vs doublon rejeté sur `oct_code` renseigné, rental_mode transitif via catégorie, plage `from_mmdd`/`to_mmdd` à cheval sur le nouvel an, contraintes `CHECK` de cohérence.

**Points restés ouverts** : voir points 32 à 35 ci-dessous.

## 32. Contenu riche hébergement — reporté (né en session Référentiel Hébergement & Géographie, 17/07)

**Origine** : en construisant `ref_accommodation`, l'utilisateur a listé ce qui reste à ajouter mais volontairement reporté : description (multilingue), photos, tags, amenities, capacité descriptive (nombre de chambres, pertinent pour le mode `whole_unit`).

**Ce qui existe déjà et attend d'être relié** : `ref_amenity`, `ref_tag`, `ref_option`, `ref_hotel_chain`, `ref_supplement`, `ref_accommodation_location_type` — tout le vocabulaire est construit et figé, mais **aucune liaison vers `ref_accommodation` n'existe encore** (pas de `ref_accommodation_amenity`, `ref_accommodation_tag`, etc.).

**Ce qui reste à faire** : tables de jonction N-N (hébergement ↔ amenity/tag/option/supplément), `chain_id` sur `ref_accommodation` (FK vers `ref_hotel_chain`, absente du lot initial), `ref_accommodation_translation` (description multilingue), photos (URL vers fichier, hébergement Minio/S3 non tranché), capacité descriptive (nombre de chambres pour le mode `whole_unit`, évoqué mais jamais formalisé en colonne).

**Principe pour la reprise** : toutes des extensions additives 1-N/1-1 sur `ref_accommodation.id`, jamais de réouverture de la table de base.

## 33. Entités "pas encore dans OctaSoft" — aéroports, compagnies aériennes/maritimes (identifié en session Référentiel Hébergement & Géographie, 17/07)

**Constat de l'utilisateur** : certains référentiels attendus (aéroports, compagnies aériennes) ne sont pas encore intégrés dans OctaSoft Static Data lui-même (produit séparé) — ils le seront un jour, mais pas encore.

**Question ouverte, non tranchée dans cette session** : faut-il les modéliser dès maintenant avec un `oct_code NULLABLE` en anticipant leur future synchronisation (risque : structure figée avant que le vrai format OctaSoft soit connu), ou attendre qu'OctaSoft les couvre réellement et les créer sans `oct_code` en attendant (risque : migration mineure le jour où la synchronisation devient possible) ?

**Lien avec Booking** : `booking_transport_segment`/détails vol (voir `sujets-reportes.md` #15, Vol/Billetterie non confronté à des données réelles) bénéficieraient de ce référentiel une fois construit.

**Renommage acté pour Booking (pas fait dans cette session)** : `booking_hotel_detail` doit être renommé `booking_accommodation_detail` (ou équivalent), tout en anglais, cohérent avec la terminologie "accommodation" — confirmé par l'utilisateur (17/07, cadrage initial). Le branchement de la vraie FK vers `ref_accommodation.id` doit se faire au même moment. À signaler au chat pilote Booking pour une session dédiée.
**✅ Fait le 20/07/2026** — voir clôture complète point 6 ci-dessus.

## 38. ★ MODULE FACTURATION / AVOIRS — ✅ FIGÉ V1.0 (18/07/2026)

**Correctif appliqué en cours de route (18/07)** : la V1 initiale documentait le plafond `SUM(montants facturés sur ce split/settlement) ≤ montant du split/settlement` comme "règle absolue" mais ne l'appliquait nulle part en base — bug confirmé en sandbox par l'utilisateur (un split de 1000 DT acceptait 1700 DT de lignes facture sans erreur). Corrigé par 3 triggers `BEFORE INSERT OR UPDATE` : `invoicing_check_invoice_line_split_cap` (vente), `invoicing_check_supplier_invoice_line_settlement_cap` (achat), `invoicing_check_credit_note_line_remaining_cap` (reliquat de la ligne facture côté avoir). Retesté sur le scénario exact du bug (split 1000 DT, tentative de 1700 DT) — désormais rejeté. Cas limite UPDATE (une ligne modifiée ne se plafonne pas elle-même) également vérifié.

**Statut** : Conception terminée, testée sur PostgreSQL réel avec test de concurrence réel (50 validations simultanées sur la numérotation, aucun gap/doublon). Voir `modele-conceptuel-facturation.md` et `schema-invoicing-v1.sql`.

**Ce qui a été construit** :
- `invoicing_invoice`/`invoicing_invoice_line` (client), `invoicing_credit_note`/`invoicing_credit_note_line` (avoir client), `invoicing_supplier_invoice`/`_line`, `invoicing_supplier_credit_note`/`_line` — tables séparées par rôle (pas de colonne de rôle partagée, mécaniques trop différentes)
- Deux origines de ligne coexistantes : **ancrée** (`booking_payer_split_id` côté vente, `booking_settlement_id` côté achat — jamais `booking_id` directement, gère nativement le multi-payeur/multi-bénéficiaire) et **libre** (désignation manuelle, sans réservation, rend le système utilisable hors tourisme)
- Numérotation légale : séquence globale annuelle sans gap, garantie par `invoicing_next_number()` (verrou transactionnel), **uniquement côté client** (facture et avoir émis) — côté fournisseur, `supplier_reference` est le numéro du document reçu, jamais généré
- Timbre fiscal : collecté dans `booking_charge` (type `fiscal_stamp`, déjà existant côté Booking), porté **par couple (facture, réservation)** dans la ligne facture, avec réassignation automatique de la ligne porteuse si elle est supprimée (trigger `AFTER DELETE`)
- TVA à deux formules par ligne (sur le total / sur la commission), taux historisés par pays (`invoicing_tax_rate`), système volontairement non limité à la Tunisie
- FODEC : taux par ligne, fournisseur uniquement (`invoicing_tax_rate` avec `tax_type_code='fodec'`)
- Avoir **ancré = exclusivement automatique** (déclenché par annulation Booking, jamais de saisie manuelle possible — garde-fou en base via trigger `invoicing_check_credit_note_line_origin`), avoir **libre = manuel obligatoire** (seul moyen de corriger une facture sans réservation)
- Asymétrie fournisseur assumée : aucun avoir fournisseur généré automatiquement (l'agence ne peut pas anticiper le document du fournisseur) — remplacé par une vue de détection d'incohérence (`invoicing_supplier_reconciliation`)
- Crochets Règlements (`invoice_id`/`credit_note_id`) enfin branchés par `ALTER TABLE` additif — Règlements non rouvert

**Décisions irréversibles actées** :
- Un seul grand livre (Règlements) — Facturation ne recalcule jamais un solde, documente une obligation déjà projetée (ligne ancrée) ou pose une écriture nouvelle uniquement en l'absence d'obligation préexistante (ligne libre)
- `booking_payer_split`/`booking_settlement` restent figés après facturation — un avoir corrige un document fiscal, ne rouvre jamais de capacité de facturation
- Une facture = une seule devise

**Points de couplage avec les autres modules** :
- `booking_payer_split`/`booking_settlement`/`booking_charge` (type `fiscal_stamp`) → lus, jamais modifiés
- `reglement_ledger_entry.invoice_id`/`credit_note_id` → branchés (voir point 21 ci-dessus)
- `pointvente_id` → FK nullable en en-tête, reporting uniquement
- `cash_external_transmission_item.accompanying_invoice_id` → confirmé sans recouvrement, reste une pièce jointe documentaire, non modifié

**Points restés ouverts** (voir aussi les notes d'implémentation dans `schema-invoicing-v1.sql`) :
- **Lien facture client ↔ facture fournisseur (cas GNV)** : système où le client est lui-même considéré comme fournisseur (billetterie maritime GNV). Explicitement écarté du périmètre V1 sur demande explicite de l'utilisateur — trop spécifique à un seul client, complexité jugée disproportionnée. À reprendre uniquement si un besoin réel resurgit.
- **Répartition automatique d'un avoir sur plusieurs factures distinctes** (quand la réservation annulée a été facturée sur plusieurs factures) : jamais rencontré en pratique. Choix retenu : défalquage manuel par l'utilisateur, aucune règle système. À reconfronter au premier cas réel.
- **Réémission après avoir sur split figé** : le modèle ne permet pas nativement de refacturer un montant déjà facturé puis annulé par avoir (la capacité du split est définitivement consommée). À traiter en exception documentée si un besoin réel émerge, pas de mécanisme prévu en V1.
- **Verrou applicatif, pas structurel, sur la modification d'une facture validée** : rien n'empêche en base de modifier `invoicing_invoice_line` après passage de la facture en `status_code='validated'` (contrairement à l'append-only garanti par trigger sur `reglement_ledger_entry`). À la charge de la couche applicative Symfony, ou à durcir en base si un incident réel le justifie.
- `invoicing_post_credit_from_cancellation()` : signature à définir avec l'équipe dev (dépend de l'API applicative Booking/Symfony), même statut que les fonctions de posting Règlements manquantes (`reglement_post_obligation`, etc.)
- Calcul exact TVA (formule précise de passage TTC→HT selon le mode de vente) : responsabilité applicative, le schéma stocke le résultat sans l'imposer.

---

## 39. Incohérence de nommage FR/EN entre modules — reporté (18/07/2026)

**Origine** : en corrigeant `schema-invoicing-v1.sql` → `schema-invoicing-v1.sql` (renommage complet des identifiants en anglais, demandé explicitement par l'utilisateur), le constat suivant est apparu : certains modules déjà figés portent des préfixes **français** (`reglement_` — Règlements Client/Fournisseur, `pointvente_` — Point de vente), alors que tous les autres sont déjà en anglais (`party_`, `core_`, `ref_`, `booking_`, `cash_`, et désormais `invoicing_`, `pricing_`).

**Décision de cette session** : ne pas toucher aux modules déjà figés maintenant — `reglement_` et `pointvente_` sont potentiellement déjà consommés par du code applicatif (Symfony), et un renommage de préfixe à ce stade est une migration, pas une simple correction de style. Reporté.

**Renommages pressentis pour une future passe dédiée** (à confirmer en session, pas de décision définitive ici) :
- `reglement_*` → `settlement_*` ou `payment_*` (à trancher — "settlement" est plus proche du sens du module, "payment" plus étroit puisque le module couvre aussi les obligations, pas seulement les règlements reçus)
- `pointvente_` → `sales_point_` ou `outlet_`

**À faire avant toute exécution** : lister précisément l'impact (tables, colonnes de FK dans d'autres modules qui référencent ces préfixes — au moins `booking.pointvente_id`/`pointVentePaiement_id` à venir, `cash_` qui étend `reglement_payment_method`, `invoicing_` qui vient de brancher des FK sur `reglement_ledger_entry`), et confirmer si c'est fait en une seule migration SQL (`ALTER TABLE ... RENAME`) plutôt qu'une réécriture des fichiers `schema-*.sql` historiques (qui, eux, font foi de l'historique des décisions et ne doivent probablement pas être réécrits rétroactivement).

---

## 34. Organisation transverse des référentiels du projet — TVA, État (reporté, 17/07)

**Origine** : en construisant ce module, l'utilisateur a exprimé l'intention de réorganiser à terme **tous les référentiels de tous les modules** en un seul endroit, catégorisés par thème, plutôt que chacun dans son module métier d'origine (approche actuelle).

**Décision de cette session** : ne pas trancher cette question de fond dans le cadrage d'un module métier particulier — c'est un sujet transverse à tout le projet, qui mérite sa propre session dédiée. Deux référentiels concrets sont en attente de cet arbitrage :
- **TVA** (taux, historique dans le temps, rattachement par produit/pays/type de prestation, usage réel dans le calcul) — sujet fiscal nécessitant son propre cadrage métier, probablement dans le contexte Règlements ou Facturation.
- **État** (statut de traitement de réservation, sélectionné par l'équipe avec un commentaire libre pendant le traitement — voir point 35 ci-dessous).

**Note** : physiquement, toutes les tables vivent déjà dans la même base PostgreSQL — la question n'est pas technique mais organisationnelle/documentaire (regroupement dans un même `schema-*.sql` et/ou une vue d'ensemble transverse dans `00-INDEX.md`).

## 35. `État` — statut de traitement de réservation — ✅ RÉSOLU le 20/07/2026

**Origine** : l'utilisateur a un référentiel d'états (ex: "En attente du client", "Client contacté sérieux", "Attente confirmation disponibilité"...) que l'équipe sélectionne pendant le traitement d'une réservation, accompagné d'un commentaire libre, pour que le reste de l'équipe suive l'avancement.

**Pourquoi ce n'est pas dans le module Référentiel Hébergement & Géographie** : aucun rapport avec l'hébergement ou la géographie — c'est un concept de suivi de traitement de réservation, donc conceptuellement rattaché à **Booking** (ou un futur module de suivi/CRM autour de la réservation), pas à ce module.

**Question ouverte posée par l'utilisateur lui-même (obsolète, tranchée)** : garder ce système simple (statut + commentaire libre) ou le remplacer par quelque chose "de plus professionnel" (probablement un vrai journal d'activité horodaté, plus proche d'un pattern audit-log qu'un simple champ `status`) ?

**✅ Résolu le 20/07/2026 — option (c), hybride** : `booking.processing_status_code` (référentiel `booking_processing_status`, table pas ENUM) dénormalisé pour filtrage/affichage rapide, **orthogonal** à `booking.status_code` (même principe que `is_on_request`) — **et** chaque changement loggé dans `log_activity` (module Log, ✅ figé, `entity_type='booking'`, `event_type='processing_status_change'`, commentaire libre en `description`) pour l'historique complet. Même pattern déjà en place pour `status_code` (dénormalisé + historique via `log_activity`). Testé en sandbox. Liste de statuts volontairement minimale (3 exemples de cette entrée), extensible sans migration.

## 36. Module Product / Catalogue — nouveau module ajouté au périmètre (18/07/2026)

**Origine** : en discutant de l'ordre des modules restants, l'utilisateur a identifié un besoin initialement confondu avec le module "Stock Management" (retiré du périmètre le 16/07) — en réalité un module distinct, jamais formalisé avant cette session : la fiche technique commerciale de ce qui est vendu (hébergement, packages/voyages, véhicules), par opposition à la donnée descriptive déjà couverte par le Référentiel Hébergement & Géographie.

**Distinction actée avec l'utilisateur** :
- `ref_accommodation` (Référentiel statique, ✅ figé) = donnée **descriptive**, miroir OctaSoft Static Data (nom, localisation, catégorie, classement) — répond à « qu'est-ce que c'est ».
- **Product/Catalogue** (nouveau) = fiche technique **commerciale** — répond à « qu'est-ce que je vends », incluant la composition de packages/voyages et le catalogue véhicules (marques/modèles) pour la location de voiture et le transfert.

**Confirmé par l'utilisateur (18/07)** : module à part entière, distinct de Pricing/Contracting — pas une fusion.

**Dépendances** : Référentiel statique (✅ figé) pour la partie hébergement. Le référentiel véhicules (marques/modèles) n'est pas encore couvert par OctaSoft Static Data — à construire dans ce module ou en préalable, à trancher explicitement en session dédiée.

**Positionnement dans l'ordre** : module 2 (juste après Facturation), avant Pricing — logique : il faut savoir ce qu'on vend avant de savoir comment le tarifer. Voir `00-INDEX.md` pour le tableau complet.

## 37. Stratégie de migration — legacy en API Gateway temporaire (18/07/2026)

**Décision de l'utilisateur** : la partie la plus complexe et la plus risquée du système (Contracting hôtelier — tarifs d'achat — et Provider Integration — API multi-fournisseurs, merge, création réservation, vérification prix) ne sera pas migrée en premier. Le legacy continuera de servir d'API gateway pendant plusieurs mois pendant que le reste bascule. Raison explicite : risque direct sur de l'argent et des engagements client réels (ex: erreur de mapping hôtel = client envoyé dans le mauvais établissement/pays).

**Conséquence sur l'ordre de conception BDD** (voir `00-INDEX.md`) : Contracting hôtelier avancé + Provider Integration repoussés en dernier (module 5), Utilisateurs/Configuration avancée remonté avant (module 4) faute d'enjeu de risque de cette nature.

**Précision importante actée le 18/07, vérifiée sur écrans legacy réels (marge hôtel + marge billetterie)** : les marges de vente (Pricing) sont structurellement découplées du prix d'achat — le moteur de marge ne référence jamais le prix d'achat lui-même, seulement des règles conditionnelles (dates réservation/séjour, affilié, source achat/vente, critères spécifiques par service : hôtel/groupe hôtel vs pays départ/arrivée/compagnie/classe pour le vol) appliquées par-dessus un prix d'achat quel qu'il soit. **Pricing n'est donc pas repoussé avec le contracting hôtelier** — conserve sa place normale dans l'ordre (module 3).

**Points de vigilance identifiés pour la future session Pricing, à partir des écrans legacy montrés** :
- Beaucoup de critères de filtrage différents selon le service (hôtel : Hôtels/Groupe Hôtels ; vol : Pays départ/arrivée/Companies/Classes) — risque de tentation EAV à surveiller explicitement en session, ADR déjà tranché (EAV rejeté par principe) donc probablement une table de critères par service plutôt qu'une table générique, à confirmer avec de vrais exemples.
- Plusieurs granularités de marge observées (par chambre sur l'écran hôtel, "par dossier" sur l'écran vol, via un champ `Type marge`) — pas une granularité unique, à creuser en session dédiée.
**Mise à jour (19/07)** : ces deux points de vigilance ont été traités en session Pricing — voir §54. La granularité s'est avérée plus riche que prévu (éclatement par type de passager pour le vol), pas fusionnée avec la question de structure de table par service.

---

## 40. ★ MODULE PRODUCT / CATALOGUE — ✅ FIGÉ (19/07/2026) — 8 sous-modules complets

**Statut** : Module complet. Hôtel, Véhicule, Spa, Visa (figés 18/07) + Transfert, Aérien, Bus, Package (figés 19/07). Guide traité (aucune table nécessaire). Voir `modele-conceptuel-product-catalogue.md` et `schema-product-catalogue-v1.sql` pour le détail complet.

**Ce qui a été construit le 18/07** :
- **Hôtel** : `product_accommodation_room`/`_board` (+ traductions, photos, amenities), extension additive de `ref_static` pour les liaisons hôtel↔vocabulaire (ferme partiellement le point #112) et la description courte de l'hôtel.
- **Véhicule** : référentiels marque/carrosserie/énergie/boîte/équipements/suppléments, `product_vehicle_model` (le produit vendu, pas de couche "catégorie de location" — invalidée par capture Hertz réelle), `product_pickup_location` (comptoirs fixes, **usage restreint à la location** — voir correction 19/07 ci-dessous), `product_vehicle_unit` (réouverture scopée de Stock Management, 16/07).
- **Spa** : `product_spa_center` (découplé de l'hôtel mais lien redevenu structurant pour le cross-sell), `product_spa_treatment` avec composition structurée des packs et durée variable par centre, `product_spa_center_type` (Thalasso/Spa Thermal/Spa, ajouté suite benchmark).
- **Visa** : conçu sans aucun export legacy, à partir d'une capture concurrente + expertise sectorielle. `passport_country_id` nullable ajouté en fin de session suite à une question de relecture de l'utilisateur.

**Ce qui a été construit le 19/07** :
- **Guide** : aucune table. Confirmé par l'utilisateur : pas de catalogue à choisir, juste une prestation facturée. Seule action : ajouter `guide` à `booking_service_type` (✅ fait le 20/07/2026, réouverture Booking).
- **Transfert** : `product_transfer_vehicle_category` (+trad/photo) — vente par catégorie de véhicule (capacité pax/bagages), pas par modèle nommé. Équipements réutilisent `product_vehicle_equipment`. **Deux corrections actées en session** : privé/partagé retiré (relève de Contracting) ; `product_pickup_location` invalidé pour ce sous-module (comptoirs fixes ≠ adresse libre du client transfert) — corrige une hypothèse erronée notée précédemment dans ce même document.
- **Aérien** : `ref_airline_company` + `ref_cabin_class` ajoutés à `ref_static` (extension additive, `ref-static-airline-cabin-extension.diff`) — compagnie = descriptif/ajout local permis, classe cabine = référentiel fermé OctaSoft. `product_airline_aircraft_type` (flotte, contenu commercial) dans Catalogue.
- **Bus** : `product_bus_model` unique, utilisé aussi bien pour le trajet de ligne que le ramassage groupé (même objet vendu, contexte de résa différent).
- **Plan de sièges** (composant partagé Aérien/Bus) : `product_seat_map` (template, `CHECK` d'exclusivité aircraft_type/bus_model) + `product_seat` (siège matérialisé, éditable, `is_available` pour exclure un emplacement type conducteur — reconstruit depuis une capture du générateur legacy). Génération grille→sièges en Domain PHP, jamais en fonction stockée.
- **Package** : **réouverture documentée** de la décision figée le 18/07 ("packages = regroupement `booking_folder`, pas de fiche produit avec prix propre"), invalidée par une capture legacy réelle et le besoin explicite de l'utilisateur. `product_package` (sans prix stocké) + `product_package_country` (multi-pays, remplace le couple Pays/Destination-zone du legacy, zone abandonnée) + `product_package_tag` (réutilise `ref_tag`) + `product_package_component` (composition typée via `booking_service_type`, plusieurs lignes du même type possibles avec libellé propre — absorbe au passage les besoins Circuits/Activités identifiés en cours de session) + `product_package_visa` (réutilise `product_visa`, pas de référentiel de noms en texte libre).

**Décisions transverses actées pour tout le module** :
- Test décisionnel Catalogue vs `ref_static` : contenu commercial propre (description/photo) → Catalogue ; lien booléen descriptif → `ref_static` extension additive.
- Pas d'actif/inactif dans aucune fiche Catalogue (rôle du futur Contracting).
- Pas de contenu web/CMS/SEO — migre vers un futur CMS, hors périmètre MyGo.
- Prudence sur les "catégories commerciales" au-dessus d'un produit : à valider contre une vraie donnée de production avant de construire (invalidé une fois sur véhicule, confirmé une fois sur transfert — catégorie de véhicule, cette fois-ci, bien réelle).
- Zéro fonction PL/pgSQL de logique métier sur l'ensemble des 8 sous-modules (ADR-002 reconduit sans exception, y compris pour la génération du plan de sièges).

**Points restés ouverts, reportés** :
- **Trou Booking — attribution de siège** : `product_seat_map`/`product_seat` ne portent qu'un template. L'attribution d'un siège précis à un passager pour une résa précise nécessite `booking_flight_detail`/`booking_bus_detail` (`seat_id`) — à faire en session Booking dédiée. **Explicitement reporté le 20/07/2026** (réouverture ciblée Booking) : trop de questions de conception ouvertes (unicité, lien `product_seat_map`, conflits) pour une session de nettoyage — nécessite sa propre session dédiée.
- **"Guide" toujours absent de `booking_service_type`** (Booking, figé) — ✅ fait le 20/07/2026, voir ci-dessus.
- **`spa`/`accès piscine`/`train`** (présents dans `booking_service_type`, statut de couverture Catalogue jamais explicitement confirmé) — non retraité le 19/07, toujours en attente.
- **Livraison à domicile** (location de voiture) — confirmé hors périmètre Product/Catalogue, relève de Booking/calcul.
- **`ref_static` #112 partiellement fermé seulement** : `ref_hotel_chain` et `ref_supplement`, liaisons vers `ref_accommodation` non construites, toujours ouvertes.
- **`product_vehicle_unit`** : état courant mutable, pas d'historique en V1.
- **Anomalies legacy non vérifiées en base de production réelle** (chambre/pension hôtel, plaque véhicule).
- **`product_spa_treatment_component`** : pas de protection anti-cycle profond.
- **Maritime** : mentionné le 18/07 comme "à prévoir au cas où", jamais construit — aucun sous-module dédié à ce jour, **toujours ouvert, confirmé bloquant pour Pricing** (voir §51).

**Action pour la prochaine session** : le module Product/Catalogue est désormais complet. Prochain module dans l'ordre validé (`00-INDEX.md`) : Pricing/Contracting. Les deux trous Booking ci-dessus (ligne `guide` + extension seat_id) restent en dette documentée, à traiter dès qu'une session Booking dédiée est planifiée.

## 41. Personnalisation des documents (voucher, billet, contrat de voyage...) — cadrage (19/07/2026)

Besoin transverse identifié : possibilité pour l'utilisateur de personnaliser ses documents de voyage/financiers. Orientation actée : rattaché au futur module **Configuration avancée** (module 2 restant, "Utilisateurs avancés + Configuration avancée") — moteur de templates/variables de fusion, pas une nouvelle notion métier séparée. Ne concerne que la mise en page/personnalisation, pas la génération des faits eux-mêmes (portée par Facturation/Booking/Règlements selon le document).

## 42. Configuration des emails sortants — cadrage (19/07/2026)

Distinction actée entre deux sous-sujets à ne pas mélanger :
- **Règle métier** (quand/à qui/quel objet envoyer) → Configuration avancée (module 2), même famille que le point 41.
- **Tuyau technique** (connecteur SMTP/API d'envoi) → futur module Provider Integration (module 3, reporté), catégorie "outils techniques".
Les deux peuvent être conçus indépendamment, la configuration n'attend pas le tuyau technique.

## 43. Activation/désactivation des modalités de paiement par service/période — ✅ RÉSOLU le 19/07/2026 (réouverture ciblée)

Ambiguïté identifiée (19/07/2026), non résolue : simple bascule de configuration sur les moyens déjà posés dans Cash Management (`cash_payment_method_routing`), ou vraie règle conditionnelle par service/période (plus proche du moteur de marge Pricing observé sur les écrans legacy) ? Hypothèse retenue à confirmer : plutôt **Pricing/Contracting**, même nature de moteur de règles conditionnelles.
**Mise à jour (19/07, fin de session Pricing)** : Pricing est désormais figé (voir §54) et ce point n'a **pas été traité** dans cette session malgré l'intention initiale — reste ouvert tel quel. L'hypothèse "même moteur que Pricing" reste valide en principe mais n'a jamais été vérifiée concrètement.
**✅ Résolu le 19/07/2026 (réouverture ciblée, même jour)** : l'hypothèse "même moteur que Pricing" **confirmée** — mais recadrée : le besoin réel exprimé n'était pas une simple bascule actif/inactif par service/période, c'est une vraie **modalité de paiement hôtelière nommée**, combinant répartition acompte/solde (agence/fournisseur) et facturation au nom du client ou de l'agence. Traité comme 3ᵉ nature de règle (`payment_modality`) dans `pricing_rule`, aux côtés de `margin`/`commission`, réutilisant entièrement le moteur de ciblage existant (aucune nouvelle table de ciblage). Voir §55 pour le détail complet.

## 44. Logs API IN/OUT — stockage séparé recommandé (19/07/2026)

Recommandation explicite : NE PAS stocker les logs API IN/OUT (futur API Gateway centralisé) dans cette base PostgreSQL transactionnelle. Volume et pattern d'écriture (très haute fréquence, append-only, consultation par période/erreur) incompatibles avec une base client-isolée (ADR-004) dimensionnée pour du transactionnel métier. Rejoint la règle déjà actée en Booking (point 19) : système de log générique à extraire dès que 2 modules en expriment le besoin — c'est désormais le cas. Solution dédiée (store de logs séparé, possiblement mutualisé à l'échelle OctaSoft) à concevoir en dehors de ce Project de conception BDD transactionnelle.
**Mise à jour (19/07)** : `pricing_rule_log` (Pricing, ✅ figé) reproduit le même pattern générique — 3ᵉ occurrence réelle du besoin, voir §54.

## 45. Module Comptabilité générale — NON recommandé en version complète (19/07/2026)

Besoin exprimé : ajouter un vrai module de comptabilité (plan comptable, comptes tiers/comptables/TVA par taux, export comptable) — actuellement géré par export externe.

**Recommandation actée** : NE PAS construire un vrai système de comptabilité générale (plan comptable normalisé, écritures équilibrées débit/crédit, bilan, clôture d'exercice, déclarations fiscales) — domaine réglementé à enjeu légal réel, maintenance perpétuelle (législation fiscale évolutive), terrain déjà occupé par des logiciels matures/experts-comptables. Risque jugé disproportionné par rapport à la valeur, hors du métier différenciant (tourisme).

**Alternative recommandée** : module léger **"Interface Comptable"** = couche de projection/export générant des écritures comptables normalisées à partir des faits déjà capturés dans Règlements/Cash Management/Facturation — jamais un nouveau système transactionnel de référence légale. Même logique que Règlements projetant Booking sans jamais le modifier.

**Statut** : backlog stratégique, non encore priorisé dans l'ordre des modules restants.

## 46. Marketing / Fidélité (points, cartes de fidélité, coupons de réduction) — backlog post-V1 (19/07/2026)

Regroupé avec **Loyalty Programs** déjà listé en backlog post-V1 (voir plus haut, confirmé besoin réel mais explicitement post-V1). Coupons de réduction ajoutés à ce regroupement. Ces sujets consomment ce qui existe déjà (client Party, réservation Booking, montant Règlements) sans conditionner aucun module restant — aucune urgence de conception. Lien à vérifier le moment venu : un coupon de réduction touchera probablement le futur module Pricing/Contracting (remise conditionnelle de plus dans le même moteur de règles) plutôt que d'être un mécanisme séparé.

## 47. Stratégie de migration en parallèle (legacy + nouveau système, 2-3 mois) — contexte stratégique (19/07/2026)

Stratégie actée par l'utilisateur : sur une période de 2-3 mois, les deux systèmes tournent en parallèle. Legacy reste **seul maître de la vérité financière** (règlements/factures saisis manuellement en double par l'équipe, jamais automatisés entre les deux systèmes — élimine le risque de double comptabilisation silencieuse). Import automatique (cron/à la demande) limité aux données de référence/mapping : **produits (Product/Catalogue), clients (Party), users (futur module Utilisateurs)** — jamais les flux financiers (règlements, factures) qui restent en double saisie manuelle pendant la période.

**Conséquence directe** : le futur module Utilisateurs avancés devient un vrai prérequis concret pour cette stratégie de migration (le seul des trois — produits/clients/users — pas encore figé), pas juste un module suivant dans l'ordre.

**Points techniques à anticiper pour la moulinette d'import elle-même** (à cadrer en détail le moment venu, pas maintenant) :
- Identifiant de réconciliation stable legacy↔nouveau, même pattern que `oct_code` (jamais l'id interne comme pivot)
- Idempotence / gestion de resynchronisation (une résa legacy modifiée/annulée après import initial)
- Rapprochement périodique recommandé (ex: hebdomadaire) entre les deux systèmes sur échantillon, pour éviter une découverte d'écarts accumulés en fin de période plutôt qu'un contrôle continu

**Effet de bord positif** : ce travail forcera l'implémentation réelle des fonctions de posting Règlements (`reglement_post_obligation` etc.), actuellement documentées comme pattern non implémenté (point 23).

## 48. Trois points identifiés par le chat pilote Backend (19/07/2026) — à traiter, priorité libre

Analyse du chat "00-Main DEV Backend" (cadrage architecture Symfony), vérifiée et confirmée dans ce chat :

1. **Soft delete incohérent** : `01-architecture_decisions.md` (ADR-005, vieux de 6 mois) dit "soft delete uniquement sur customers/bookings/invoices/payments, hard delete ailleurs". Réalité vérifiée dans les schémas construits : `deleted_at` présent sur `party_account`/`party_account_address`/`party_account_document`, `booking_folder`, `core_credential`, `pointvente` — mais **absent** sur `invoicing_invoice` et `reglement_ledger_entry` (que l'ADR nomme pourtant explicitement "invoices"/"payments"). ADR-005 obsolète, à recadrer par rapport à ce qui a été réellement conçu (le caractère append-only de Règlements rend d'ailleurs le soft delete conceptuellement inadapté à cette table — contre-passation plutôt que suppression).

2. **Audit trail décidé sur papier, jamais construit — ✅ RÉSOLU le 20/07/2026** : `01-architecture_decisions.md` (ADR-006, vieux de 6 mois) décidait une table `audit_logs` générique + trigger réutilisable. Construite sous le nom `log_audit` (module transverse `log_`, voir clôture complète §19) dans la réouverture Booking du 20/07, en même temps que `log_activity`. Trigger générique `log_audit_trigger()` testé en sandbox (capture avant/après confirmée sur `INSERT`/`UPDATE`, y compris sur `booking` malgré son partitionnement). `pricing_rule_log` (Pricing, ✅ figé) reste un cas local séparé, non fusionné.

3. **Auth/session (JWT/MFA) — ✅ RÉSOLU (module Permissions/Franchises/Config, 20/07)** : `core_session` (partitionnée, refresh token rotatif), `core_mfa_totp`, `core_mfa_recovery_code`, `core_auth_attempt` (partitionnée, `account_id` nullable) construits et vérifiés en sandbox. Voir `diff-core-auth-avancee.sql` et `modele-conceptuel-permissions-franchise-config.md`. Point 12 ("périmètre `core_`") peut être considéré clos sur ce volet.

**Statut** : point 1 (soft delete) reste ouvert. Point 2 (audit trail/`log_audit`) résolu le 20/07. Point 3 (auth/session) résolu.

## 49. Incohérence `booking_channel.api_in`/`api_out` — ✅ RÉSOLU le 20/07/2026

**Constat (session Pricing, 19/07)** : `booking_channel` (figé, `schema-booking-v1.sql`) définit `api_in` = "un partenaire B2B réserve notre inventaire via API" et `api_out` = "nous réservons chez un fournisseur via son API". Cette définition est **inversée** par rapport à la terminologie employée par l'utilisateur partout ailleurs dans le projet et déjà actée pour le futur module Provider Integration (`00-INDEX.md`, module 3) : **API IN** = ce qu'on consomme (on interroge un tiers, ex. Booking.com/Expedia), **API OUT** = ce qu'on fournit (on devient fournisseur, on nous interroge).

**Décision (obsolète, appliquée)** : corriger `booking_channel` par réouverture ponctuelle documentée (diff additif, même pattern que `ref-static-*-extension.diff`) pour aligner les libellés/définitions sur la terminologie correcte. Aucune donnée réelle écrite avec ces codes à ce jour (pas de mise en prod) — aucun risque de migration, correction pure.

**Impact** : le module Pricing (`pricing_rule_sale_channel`) réutilise `booking_channel.code` tel quel dans le schéma v1 — à corriger conjointement quand le diff sera appliqué, aucune réouverture de Pricing nécessaire (FK sur les codes, pas sur leur définition).

**✅ Appliqué le 20/07/2026** : les codes `api_in`/`api_out` sont **inchangés** (aucune migration nécessaire), seules leurs définitions/commentaires sont corrigés dans `schema-booking-v1.sql` : `api_in` = nous consommons l'API d'un tiers, `api_out` = nous exposons notre API à un partenaire. **Pricing non retouché** (FK sur les codes, qui n'ont pas changé) — mais si `pricing_rule_sale_channel` contient déjà des données réelles utilisant l'ancienne sémantique, une vérification manuelle reste nécessaire côté Pricing (hors périmètre de cette session Booking).

---

## 50. Source vente Pricing — dimensions device/nationalité/IP identifiées mais non retenues en V1 — ✅ RÉSOLU (décision de ne pas construire)

**Origine** : en cadrant les critères "source vente" de Pricing, l'utilisateur a listé plusieurs dimensions possibles pour de futures règles : canal (API fournie à un partenaire / espace client login-motdepasse / backoffice / application mobile), devise (lapsus initial pour "device"), nationalité du client, IP.

**Clarifié en session** : "device" (mobile app) n'est pas un axe indépendant — il rejoint la dimension **canal**, déjà couverte par `pricing_rule_sale_channel` (réutilise `booking_channel`).

**Décision** : nationalité et IP ne sont **pas construites en V1** — absentes du legacy, aucun besoin réel confirmé aujourd'hui (cohérent avec la discipline "pas de valeur ajoutée artificielle" déjà appliquée sur tout le reste du projet). Non tranché : où vivrait la nationalité si le besoin émergeait un jour (`party_account` ne la porte pas aujourd'hui — à vérifier côté Party si le sujet resurgit) ; l'IP rejoindrait plutôt une résolution géographique que sa propre dimension.

**Statut** : hors périmètre V1, à reconfronter uniquement si un besoin réel émerge.

---

## 51. Maritime — aucune entité Product/Catalogue, trou découvert en session Pricing

**Constat** : en construisant les critères de règle par service, aucune fiche Catalogue n'existe pour le service `maritime` — absent des 8 sous-modules figés le 19/07 (Hôtel/Véhicule/Spa/Visa/Transfert/Aérien/Bus/Package).

**Conséquence pour Pricing** : une règle de marge/commission sur `service_type_code = 'maritime'` ne peut utiliser que les critères communs (`pricing_rule` — dates, affilié, source achat/vente), aucun critère fin propre au service (pas de compagnie maritime, pas de type de traversée...), contrairement à tous les autres services.

**Statut** : signalement pur, aucune action prise dans cette session. À combler soit en réouvrant Product/Catalogue pour ajouter un sous-module Maritime, soit en ajoutant directement une table `pricing_rule_maritime_criteria` improvisée le jour où le besoin se précise (cohérent avec le traitement déjà réservé à Transfert/Spa/Visa/Bus dans ce même schéma — Location voiture, elle, a depuis été confirmée et n'est plus improvisée, voir §54).

---

## 52. Micro-marges de contrat (par arrangement/politique enfant/réduction chambre) — découverte de périmètre, relève de Contracting

**Origine** : captures legacy montrées en session ("Tarif Arrangements", "Politique Enfants", "Réductions Chambres", module Contracting legacy) — achat et marge saisis côte à côte, à une granularité très fine (par arrangement, par palier d'âge enfant, par réduction chambre).

**Clarifié en session** : ce n'est pas un oubli de périmètre de Pricing — c'est une **deuxième famille de marge**, de nature différente de la marge conditionnelle transverse conçue dans ce module :
- **Marge conditionnelle transverse (Pricing, ce module)** : critères larges (dates, affilié, pays...), appliquée par-dessus un prix d'achat quel qu'il soit, jamais couplée à l'achat en base.
- **Micro-marge de contrat (futur Contracting, module 2)** : saisie au moment même du contrat, sur des sous-lignes qui n'existent que dans le contexte d'un contrat déjà chargé (arrangement de CET hôtel, chambre de CET hôtel) — achat et marge dans le même geste de saisie, structurellement rattachée au contrat.

**Précision métier importante** : ces micro-marges de contrat ne s'appliquent **qu'aux clients B2C**, jamais aux affiliés B2B.

**Décision** : rien à construire dans Pricing pour ce sujet — reste entièrement dans le périmètre du futur module Contracting. Noté ici pour que la session Contracting ne parte pas de zéro sur cette distinction.

**Lien avec un sujet évoqué hors périmètre** : l'utilisateur envisage un futur projet de grille tarifaire type booking.com (achat/marge/vente éditables par cellule). Analyse actée en session : **pas de conception à part** — une cellule éditée dans cette future UI équivaut à une `pricing_rule` créée/ajustée avec des critères resserrés au maximum (une chambre, un arrangement, un jour — granularité déjà vérifiée dans le schéma v1 de Pricing). La colonne achat de cette grille lira Contracting, jamais Pricing. Aucune action requise dans Pricing au-delà de la garantie de granularité déjà en place.

---

## 53. Bug confirmé — contamination margin/commission croisée sur pricing_rule (trouvé en vérification pilote, 19/07) — ✅ CORRIGÉ

**Constat, testé en sandbox réel** : rien n'empêchait structurellement une ligne `pricing_margin_detail` d'exister sur une règle dont `rule_nature_code='commission'` (ni l'inverse). Testé concrètement : `INSERT INTO pricing_margin_detail` sur une règle de nature `commission` accepté sans rejet.

**✅ Corrigé le 19/07/2026** : `rule_nature_code` dénormalisé sur `pricing_margin_detail`/`pricing_commission_detail`, verrouillé par `CHECK` à la valeur attendue, et vérifié par une FK composite `(rule_id, rule_nature_code)` vers `pricing_rule(id, rule_nature_code)` — pattern purement déclaratif, aucun trigger métier (conforme ADR-002). Retesté sur le scénario exact du bug (les deux sens) : désormais rejeté par violation de contrainte FK, sans casser les cas légitimes existants. Même famille de bug que le plafond de facturation trouvé sur le module Facturation (§38) — règle documentée comme structurante, jamais réellement appliquée en base avant vérification pilote.

---

## 54. ★ MODULE PRICING — MARGES DE VENTE — ✅ FIGÉ V1.0 (19/07/2026)

**Périmètre** : moteur de règles de marge/commission **conditionnelles**, appliqué par-dessus un prix d'achat quel qu'il soit (peu importe sa source — legacy aujourd'hui, futur Contracting plus tard). Ne stocke, ne saisit, ni ne calcule aucun prix d'achat, à aucune granularité — vérifié explicitement jusqu'au niveau le plus fin (une chambre, un jour). Documents : `schema-pricing-v1.sql`, `party-account-group-extension.diff`, `ref-static-country-group-extension.diff`.

**Ce qui a été construit** :
- **Noyau générique** (`pricing_rule`) : nature marge/commission (deux tables de détail séparées, jamais fusionnées — la marge fixe le prix de vente, la commission redistribue une marge déjà fixée vers un bénéficiaire tiers sans y toucher), service concerné, dates réservation (universelles, nullable = pas de contrainte). Tie-break de résolution confirmé explicitement par l'utilisateur : la règle la **plus récemment créée** (`created_at`, jamais `updated_at`) qui matche l'emporte, **y compris une règle générale sur une règle plus spécifique** — testé et confirmé comme comportement voulu (une règle générale créée après une règle ciblée l'écrase silencieusement, même pour le compte qu'elle ciblait précisément).
- **Ciblage affilié** (commun à tous les services) : compte précis et/ou groupe, combinés en OR. Le groupe référence **`party_account_group`** (Party, réouverture ponctuelle — voir clôture du point 4 ci-dessus), pas une table propre à Pricing — concept de portée générale (potentiellement réutile pour du reporting/statistiques), découvert en cours de session après vérification explicite dans `modele-conceptuel-party.md`/`sujets-reportes.md` plutôt que construit à neuf. Dimensions de groupe superposables (`party_account_group_type`), un compte peut appartenir à plusieurs groupes de types différents simultanément.
- **Source achat** : soit un `content_provider` réel (référentiel fermé, ref_static), soit un flag `is_local_direct` (contrat direct, futur Contracting) — jamais les deux (CHECK d'exclusivité, testé). **Source vente** : réutilise `booking_channel` — voir §49 pour l'incohérence de définition découverte et non corrigée à ce jour.
- **Critères par service** :
  - **Hôtel** — CONFIRMÉ sur écran legacy réel : checkin/séjour/durée séjour, hôtel précis et/ou chaîne (OR), chambre précise, arrangement précis, jours de semaine. Granularité vérifiée jusqu'à une seule chambre + un seul jour (condition posée explicitement pour une future grille tarifaire type booking.com, voir §52).
  - **Vol/Billetterie** — CONFIRMÉ sur un 2ᵉ écran legacy réel (session étendue) : pays départ/arrivée précis et/ou groupe de pays (OR), compagnie, classe cabine, date de départ (distincte de la date de réservation), intervalle de prix billet (devise obligatoire). Marge/commission **toujours éclatée par type de passager** (adulte/enfant/bébé) — modélisée en 3 colonnes nommées (`value`/`value_child`/`value_infant`) sur une seule ligne, pas en éclatement de lignes (ensemble fixe et fermé, cohérent avec le rejet de principe de l'EAV). Une seule nature (%/montant) pour les 3 colonnes, confirmé par l'utilisateur.
  - **Location voiture** — CONFIRMÉ après vérification explicite du modèle Product/Catalogue réel (pas de couche "catégorie de location" au-dessus de `product_vehicle_model`, décision actée le 18/07) : durée de location, intervalle de prix + devise, modèle précis et/ou carrosserie (`product_vehicle_body_type`, le concept le plus proche d'une "catégorie" réellement disponible), combinés en OR.
  - **Transfert/Spa/Visa/Bus** — IMPROVISÉS (aucun écran legacy vu), structure minimale par analogie, à reconfronter explicitement à la conception du futur module Contracting.
  - **Maritime** — NON COUVERT, aucune entité Product/Catalogue n'existe pour ce service (voir §51).
- **Audit trail** (`pricing_rule_log`) : append-only, snapshot des champs modifiés (avant/après) à chaque création/modification/activation/désactivation/suppression, avec auteur — reproduit fidèlement la capture "Historique" du legacy. `rule_id` en FK applicative (pas de contrainte réelle) : une règle peut être physiquement supprimée (confirmé, pas de soft delete ici), le log doit survivre à sa suppression.

**Corrections/découvertes structurelles actées en session** :
- **`party_account_group`** (clôture du point 4, ci-dessus) et **`ref_country_group`** (nouveau, ref_static) : deux concepts d'abord esquissés par erreur dans `pricing_`, puis relocalisés dès qu'identifiés comme des concepts de portée générale (géographie pure / regroupement de comptes) sans dépendance légitime vers Pricing — principe explicitement demandé par l'utilisateur en session ("plus de décisions seul, je pose la question").
- **Bug FK croisée margin/commission** : voir §53, corrigé et retesté.
- **Format de diff corrigé** : `ref-static-country-group-extension.diff` avait été livré une première fois en SQL brut au lieu du vrai format diff unifié (`--- a/fichier`/`+++ b/fichier`/`@@`) — corrigé pour respecter le précédent `ref-static-accommodation-links.diff`.
- **Renumérotation de ce fichier** : les points générés en session (initialement §47-§51) entraient en collision avec les vrais §47/§48 déjà existants (jamais relus avant génération) — renumérotés en §49-§53 lors de cette régénération.

**Décisions irréversibles actées** :
- Aucune table Pricing ne référence un prix d'achat, à aucune granularité, quelle que soit la source (legacy, futur Contracting) — vérifié explicitement, y compris pour le cas limite de la future grille tarifaire cellule par cellule.
- EAV rejeté même pour un ensemble fixe et fermé de faible cardinalité (adulte/enfant/bébé) — 3 colonnes nommées préférées à un éclatement en lignes.
- Un concept qui *semble* générique doit être vérifié contre les modules de couche plus basse (Party, ref_static) avant construction — pas après (leçon actée en session, ajoutée à la méthode de conception, voir `00-INDEX.md`).

**Points de couplage avec les autres modules** :
- `party_account_group` (Party) → ciblage affilié
- `ref_country_group` (ref_static) → ciblage géographique vol
- `content_provider` (ref_static) → source achat
- `booking_channel` (Booking) → source vente (incohérence de définition non corrigée, §49)
- `booking_service_type` (Booking) → discriminant de service sur `pricing_rule`
- `ref_airline_company`/`ref_cabin_class`/`ref_country` (ref_static), `product_accommodation_room`/`ref_board_type`/`ref_accommodation`/`ref_hotel_chain`/`product_vehicle_model`/`product_vehicle_body_type` (Product/Catalogue, ref_static) → critères par service
- `SolvencyCheckerInterface` (stub Booking) → **explicitement écarté** du périmètre de cette session (voir §25bis), reste un sujet Finance dédié

**Points restés ouverts** :
- **Point 43** (modalités de paiement actif/inactif par service/période) : non traité malgré l'intention initiale, reste ouvert.
- **Point 25bis** (plafond, `SolvencyCheckerInterface`) : explicitement écarté du scope de cette session.
- **Éclatement par passager de la commission** (vs marge, confirmée) : construit par symétrie/prudence, jamais confirmé par l'utilisateur pour la commission spécifiquement.
- **Transfert/Spa/Visa/Bus** : structures improvisées, à reconfronter à Contracting.
- **Maritime** : non couvert (§51).
- **Incohérence `booking_channel.api_in`/`api_out`** : identifiée mais non corrigée (§49).
- **`pricing_rule_log`** : 3ᵉ occurrence réelle du besoin de log générique transverse (avec Booking et le futur Provider Integration) — candidat à extraction, non fait (voir §44).

## 55. Pricing V1.1 — réouverture ciblée : modalité de paiement hôtelière (19/07/2026) — clôture le point 43

**Origine** : besoin métier reformulé par l'utilisateur — une "modalité de paiement" hôtelière est une combinaison nommée déterminant (1) la répartition acompte/solde entre agence et fournisseur (qui encaisse quoi), (2) au nom de qui la facture hôtel est établie (client ou agence). Confirmé en session : le ciblage (hôtel/groupe hôtel, chambre, affilié/groupe affilié, dates réservation, dates arrivée) est **entièrement** couvert par le moteur de ciblage existant de `pricing_rule` — **aucune nouvelle table de ciblage créée**.

**Ce qui a été construit** (`diff-pricing-payment-modality.sql`) :
- `pricing_rule_nature` étendu (INSERT additif) : `payment_modality`, 3ᵉ nature aux côtés de `margin`/`commission`.
- `pricing_payment_party_role` : petit référentiel dédié (`agency`/`supplier`/`client`) — distinct de `party_role` (Party), qui porte des rôles réels de tiers externes, pas cette bascule interne à la modalité de paiement.
- `pricing_payment_modality_detail` : même pattern FK composite `(rule_id, rule_nature_code)` que `pricing_margin_detail`/`pricing_commission_detail` (garde-fou anti-mélange, voir §53) — `deposit_percentage` (un seul pourcentage, le solde = 100 − acompte, pas de redondance), `deposit_collector_code`/`balance_collector_code` (agence ou fournisseur, jamais client — CHECK dédié), `invoiced_to_code` (client ou agence, jamais fournisseur — CHECK dédié, une facture au nom du fournisseur serait une facture d'achat, hors périmètre Pricing), `label`.

**Testé en sandbox réel par le chat pilote (20/07)** : chaîne complète (15 modules/diffs, y compris Permissions/Franchises/Config et le module `log_`) sans erreur. Garde-fou anti-mélange retesté (3ᵉ fois) : tentative de `pricing_payment_modality_detail` sur une règle `margin` — rejetée. Cas légitime accepté. Les deux CHECK de domaine retestés individuellement (collecteur = `client` rejeté ; facturé au nom du `supplier` rejeté).

**Hors périmètre explicite (confirmé en session)** : l'impact texte sur les documents générés (voucher) reste dans le module Documents (déjà figé, Permissions/Franchises/Configuration avancée) — pas de FK ajoutée depuis Pricing. Si un besoin réel émerge, la FK potentielle serait `document_trigger_rule` → `pricing_payment_modality_detail(rule_id)`, à ajouter côté Documents par réouverture ponctuelle de ce module-là.

**Livrable annexe** : `modele-conceptuel-pricing.md` créé — n'existait pas jusqu'ici malgré le module figé depuis le 19/07 (session initiale). Documente l'ensemble du module (pas seulement cet ajout), consolidé à partir du schéma et de l'historique complet de `sujets-reportes.md` §1/§4/§49-§54.

**Statut** : Pricing passe en **V1.1**. Point 43 clôturé.

**Note de coordination (ajoutée par le chat pilote, 20/07)** : cette réouverture a été produite par une session relancée à partir d'une copie de `sujets-reportes.md`/`00-INDEX.md` antérieure à la clôture des modules Permissions/Franchises/Config et `log_` (20/07). Le contenu SQL et le modèle conceptuel livrés étaient corrects et indépendants de cette bascule ; seuls les deux fichiers maîtres livrés avec cette session étaient obsolètes et n'ont PAS été utilisés pour remplacer les fichiers du Project — fusion faite manuellement par le chat pilote à partir de l'état réel. Voir aussi §19 (même type d'incident, déjà rencontré et corrigé une fois).

## 56. ★ MODULE PROVIDER INTEGRATION — API IN + OUTILS TECHNIQUES — ✅ FIGÉ V1.0 (21/07/2026)

**Origine** : dernier module du projet, dans sa partie API IN (fournisseurs de contenu) + outils techniques (SMS/emailing/paiement). Cadrage initial basé sur cinq captures legacy réelles (Algebratec, Leferry, BoosterBC, Brevo, clictopay), qui ont immédiatement invalidé l'hypothèse de départ d'une structure de connexion à peu près uniforme.

**Découverte structurante (apportée par l'utilisateur en session, jamais évoquée avant)** : le client n'ajoute jamais un provider manuellement — il l'**installe** depuis un catalogue/marketplace (modèle explicitement comparé aux plugins WordPress), signe un accord commercial en amont (hors MyGo, future application de gestion de licence non conçue), puis ne renseigne que ses identifiants concrets. Bascule complète du modèle envisagé : le **contrat** de connexion (quels champs, quels types, quels défauts) vit dans un manifeste détenu par OctaSoft, **jamais recopié en base client** — MyGo ne stocke que les valeurs concrètes, validées côté Domain contre ce manifeste ("monde 1a" : `version_code` suffit, pas de snapshot du contrat lui-même).

**Ce qui a été construit** (`schema-provider-integration-v1.sql`) :
- `technical_service_category` / `technical_service_provider` : entité séparée de `content_provider`, jamais de `oct_code`, confirmé qu'aucun prestataire technique pur n'a de `party_account` fournisseur derrière.
- `provider_connection` : table unique pour les deux catégories (contenu + technique), deux ancres FK nullables mutuellement exclusives, CHECK croisés (pattern Pricing reconduit, §53) — fournisseur (`party_account`) obligatoire si ancre contenu, interdit si ancre technique. **Aucune unicité structurelle** sur les combinaisons de FK (confirmé explicitement : deux connexions strictement jumelles, ex. deux comptes clictopay, peuvent coexister) — désambiguïsation 100% Application. `credentials`/`config` en JSONB (chiffrement applicatif des credentials, jamais en clair sur disque). `is_active` en BOOLEAN (exception assumée à la règle anti-ENUM, ensemble fermé confirmé) + `deactivated_reason_code`/`note` pour lecture directe, combinés à `log_audit_trigger()` posé dessus pour l'historique complet gratuit.
- `provider_call_log` : pointeur de corrélation minimal, **jamais de payload** (le store complet — fichiers request/response téléchargeables — vit sur une future API gateway externe, hors périmètre DB, durcit §44). Partitionné mensuellement (volume jusqu'à ~10M lignes/jour). `status_code`/`error_code` en tables de référence ouvertes (`error_code` normalisé **par l'agence elle-même**, jamais le code brut du tiers). `purge_at` porté PAR LIGNE, politique de rétention propre à chaque service/endpoint, recalculable après coup.

**Testé en sandbox réel par le chat pilote (21/07), en repartant des vrais fichiers du Project** : chaîne complète (16 étapes, 292 tables [chiffre corrigé à **291** le 22/07 — le 292 incluait par erreur `booking_log`, obsolète ; voir §60]) sans erreur. Tests de violation active sur les 3 CHECK croisés de `provider_connection` (5 scénarios de rejet confirmés individuellement : deux ancres, aucune ancre, fournisseur interdit sur ancre technique, fournisseur manquant sur ancre contenu, incohérence désactivation). Coexistence de connexions jumelles confirmée (2 lignes identiques en ancrage insérées sans erreur). `log_audit_trigger()` vérifié (capture avant/après + acteur, `entity_type='provider_connection'` bien enregistré dans `log_entity_type`). Partitionnement de `provider_call_log` vérifié (insertion routée automatiquement). `purge_at` modifiable après écriture, vérifié par `UPDATE` réel.

**Hors périmètre, explicitement reporté** : voir §57 (API OUT + Channel Manager) et §58 (licences/entitlements).

**`content_provider` et `booking_provider_snapshot` restent inchangés** — aucune réouverture de `ref_static` ni de Booking dans cette session.

**Livrables** : `modele-conceptuel-provider-integration.md`, `schema-provider-integration-v1.sql`.

## 57. Provider Integration — API OUT + Channel Manager — reportés (21/07/2026)

**Décision** : hors périmètre de la clôture du 21/07, reportés à une session dédiée future. L'utilisateur a explicitement choisi de ne pas les traiter maintenant, jugeant que l'implémentation de ce qui est déjà conçu (API IN + outils techniques) prendra déjà plusieurs mois.

**Ce qui est déjà tranché et à réutiliser tel quel à la reprise** : API OUT est toujours donné à un seul "client" à la fois, et ne couvre que les API de services Booking (pas de channel manager externe type Google Hotel Center dans ce cadre — distinct, voir Channel Manager ci-dessous).

**Ce qui reste à trancher au démarrage de cette future session** : la nature exacte du "client" côté Party — trois hypothèses non arbitrées :
1. N'importe quel `party_account` (FK ouverte, sans restriction de rôle) ;
2. Spécifiquement le rôle `franchise` (`party_role='franchise'`, déjà construit le 20/07) — seul cas explicitement nommé dans le legacy (§14, `PROVIDER_API_FRANCHISE`) ;
3. Un rôle B2B/affilié distinct de la franchise, potentiellement à créer dans Party.

**Channel Manager** : confirmé distinct d'API OUT ("client unique") lors de cette session — reste en backlog post-V1.

**Point d'ancrage legacy** : §14 (`PROVIDER_API_FRANCHISE`, `URL_API_FRANCHISE`, `LOGIN_API_FRANCHISE`, `PASSWORD_API_FRANCHISE`) reste confirmé obsolète, à remplacer par cette future structure — `provider_connection` (✅ figé) couvrira probablement le même besoin technique de connexion, mais du point de vue du client qui NOUS interroge plutôt que l'inverse.

## 58. Gestion des licences/entitlements — transverse à tout le projet, hors périmètre (21/07/2026)

**Origine** : en décrivant le parcours marketplace (session Provider Integration), l'utilisateur a mentionné que la base contiendra "les licences des modules actifs avec leurs dates d'expiration" — un sujet initialement pressenti comme propre à Provider Integration (lien entre une `provider_connection` et son entitlement payé), mais reformulé par l'utilisateur lui-même comme bien plus large : potentiellement tout module, toute option, "même un bouton dans une page si il le faut".

**Décision** : signalement pur, aucune structure construite ni anticipée dans ce module — pas de colonne `license_reference` sur `provider_connection`, cohérent avec la discipline du projet (pas de structure avant besoin réel cadré). Le sujet touche potentiellement RBAC/Permissions (déjà figé) autant que chaque module fonctionnel — nécessite une session de cadrage dédiée, transverse, quand le besoin sera prêt à être conçu.

**Lien avec Provider Integration** : le parcours marketplace décrit (catalogue → commande → paiement → activation → configuration des credentials dans MyGo) reste valide comme contexte produit, mais la couche licence elle-même (commande, plan tarifaire, paiement) est confirmée comme une **application séparée**, non conçue dans ce Project.

## 59. 3e incident de désynchronisation documentaire — session Provider Integration (21/07/2026)

**Constat** : la session Provider Integration a régénéré `00-INDEX.md`/`sujets-reportes.md` à partir d'une copie antérieure à la 2e réouverture Booking du 20/07 (§49 y apparaissait encore "non appliquée" alors que résolu). Contenu technique livré (`schema-provider-integration-v1.sql`, `modele-conceptuel-provider-integration.md`) entièrement correct et indépendant de cet écart — vérifié et validé sans réserve par le chat pilote. Seuls les deux fichiers maîtres ont été fusionnés manuellement plutôt qu'utilisés tels quels.

**Occurrences précédentes du même type d'incident** : session Pricing (§55, réouverture `payment_modality`) et session Booking elle-même (écart d'accès aux fichiers en aval, résolu comme photo figée d'environnement, voir `00-INDEX.md`). Trois incidents en 3 jours — la discipline de fin de session ("repartir des fichiers réellement présents, pas d'une copie locale") reste correcte en principe mais insuffisamment respectée en pratique par les sessions de module.

**Action structurelle recommandée pour la suite** : à la clôture de toute session future (Contracting hôtelier, dernier module), le chat pilote doit systématiquement vérifier la cohérence des fichiers maîtres livrés contre l'état réel du Project **avant** de les considérer comme prêts à l'upload — pas seulement les points annoncés par la session, comme déjà pratiqué depuis le premier incident, mais désormais une routine imposée à chaque clôture, sans exception.

## 60. 4e incident de désynchronisation — resync amendement ADR-018 v2.1 + 3 diffs non bakés — ✅ RÉSOLU le 22/07/2026

**Symptôme** : après le ré-upload des fichiers censés porter l'amendement ADR-018 v2.1 (`GENERATED BY DEFAULT` sur les 4 tables partitionnées), le chat pilote a rejoué la chaîne depuis les fichiers **réellement présents** dans le Project (`ON_ERROR_STOP=1`) et obtenu **290 tables** — et non 292 —, avec trois écarts par rapport à ce que déclarait `00-INDEX.md`.

**Cause racine** (confirmée par l'utilisateur) : suppression-sans-remplacement lors du dernier upload de l'amendement ADR-018 (pas un défaut de génération), **plus** trois `diff` jamais réellement bakés dans leurs schémas de base alors que l'INDEX les décrivait comme appliqués :
1. `diff-booking-log-generalization.diff` non appliqué → `booking_log` coexistait avec `log_activity`/`log_audit` (vrai doublon fonctionnel, +1 table). **C'est ce doublon qui gonflait faussement le compte à 292.**
2. `diff-pricing-payment-modality.sql` — en réalité un **pseudo-diff** (en-tête de hunk `@@ (additif pur…)` non standard, `patch` inopérant, `psql` naïf le saute) jamais intégré → `pricing_payment_party_role`, `pricing_payment_modality_detail`, nature `payment_modality` absentes du schéma pricing.
3. `ref-common-hebergement-extension.diff` non appliqué → `schema-ref-common.sql` restait en V1.1 (pas de `native_name`/`alpha3`/`oct_code` sur `ref_language`/`ref_currency`).

**Ce qui allait bien** : la chaîne s'exécutait sans erreur (16/16), et l'amendement ADR-018 v2.1 lui-même était correctement appliqué — les 4 tables partitionnées (`booking`, `core_session`, `core_auth_attempt`, `provider_call_log`) bien en `GENERATED BY DEFAULT`, vérifié via `information_schema`.

**Correctif appliqué le 22/07 (sur feu vert utilisateur, édition directe des 3 schémas figés)** : les 3 diffs bakés dans leurs bases. Chaîne rejouée en sandbox PostgreSQL 16 réel : **16/16, 0 erreur, 291 tables**, `booking_log` disparu, `payment_modality` présente, ref-common en V1.2, 4 tables partitionnées toujours `BY DEFAULT`. Fichiers maîtres corrigés en conséquence : `00-INDEX.md` (compte 292→291, statut baké des 3 diffs, « Note de resync »), `00-project_overview.md` (v5.0 : 14 modules, 291 tables, table des modules à jour), et cette entrée. Les 3 fichiers `.diff`/`.sql` d'origine restent comme trace historique (statut « baké », comme les 6 autres diffs du projet).

**Le compte de 292 (annoncé au 21/07, §56) était donc erroné** — il incluait `booking_log`. Compte correct : **291**.

**Garde-fou renforcé (au-delà du §59)** : un diff n'est réputé « appliqué/baké » que si le contenu cible est **vérifié présent dans le schéma de base** (grep de la table/colonne), jamais sur la seule foi d'une mention dans l'INDEX. Et tout fichier nommé `diff-*.sql` doit être soit un vrai script exécutable, soit baké dans sa base — jamais laissé comme pseudo-diff flottant non exécutable.

## 61. Éligibilité des extensions Booking rendue data-driven — ✅ RÉSOLU (Volet A) le 22/07/2026 ; Volet B (nature des charges) reporté délibérément

**Origine** : 1er point remonté par le chat DEV Backend Symfony en implémentant `BookingTransportSegment`. Le backend codait en dur la liste des `service_type` autorisés par extension (`private const ALLOWED_SERVICE_TYPES = [...]`), exactement le piège évité ailleurs (`party_role`/`party_function` : jamais de liste PHP figée, toujours un référentiel).

**Cadre d'analyse retenu (réutilisable sur tout module)** : quatre sortes de tables « _type », une seule est le piège. (1) carte d'éligibilité/capacité → doit être portée par le référentiel ; (2) attribut de comportement manquant → l'attribut doit vivre sur le référentiel ; (3) discriminateur où le code EST le comportement (`pricing_rule_nature`, `pricing_value_type`) → laisser, non « data-drivable » ; (4) label descriptif (`product_*_type`, `ref_room_category`) → laisser. Test : *le backend serait-il forcé de coder en dur une liste/branche qu'il pourrait lire depuis la table ?*

**Audit des modules figés** : deux vrais pièges trouvés — `booking_service_type` (éligibilité, ci-dessous) et `booking_charge_type` (nature de charge absente, Volet B). Faux positifs confirmés bien conçus : `invoicing_tax_type` (taux dans `invoicing_tax_rate`), `reglement_entry_type`/`reglement_payment_method`/`cash_movement_type`/`booking_status` (portent déjà `normal_sign`/`is_cash_like`/`is_final`). Candidats jaunes laissés hors scope tant qu'aucune branche backend n'est confirmée : `booking_channel`, `cash_routing_type`, `cash_deposit_type`, `ref_charge_unit`/`ref_charge_frequency`.

**Volet A — RÉSOLU le 22/07** : ajout de `booking_service_extension` (`code`, `label` : accommodation, transport_segment, car_rental) et `booking_service_type_extension` (`service_type_code`, `extension_code`, N-N). Table de liaison choisie plutôt que colonnes booléennes (relation N-N réelle, ajout d'éligibilité = un INSERT sans ALTER, cohérent avec les jonctions du projet). Seed : accommodation→hotel ; transport_segment→flight/train/maritime/transfer ; car_rental→car_rental. `bus` volontairement non mappé (suit la liste backend actuelle) — devient une décision de données. Testé en sandbox : chaîne 16/16, 0 erreur, **291 → 293 tables**, test de violation FK OK, preuve data-driven vérifiée (INSERT ('bus','transport_segment') rend `bus` éligible sans code PHP). Tâche d'implémentation backend dispatchée (transformation de `AssertBookingServiceType`, suppression du const, 3 Handlers, test data-driven).

**Volet B — reporté délibérément (22/07), pas un blocage** : `booking_charge_type` (23 valeurs pilotant taxe/marge/remise/prix de base/commission/assurance…) doit recevoir une colonne `category_code` → référentiel `booking_charge_category`. Granularité des catégories et 6 valeurs ambiguës (`accommodation` maritime, `meal`, `transfer_fee`, `vehicle_transport`, `supplement`, `refund`) à trancher sur les **branches réelles** du code backend, pas dans l'abstrait. Le chat Backend a explicitement refusé de répondre maintenant : `booking_charge` n'a encore aucun code écrit dessus, donc répondre aujourd'hui reviendrait à deviner des catégories à l'avance plutôt qu'à partir de vrais cas — exactement ce que ce garde-fou cherche à éviter. **Condition de reprise : quand `booking_charge` sera implémenté côté backend et que les vraies distinctions se présenteront dans le code** (le Backend a dit qu'il referait remonter le point explicitement à ce moment). Pas d'échéance fixée, pas d'action pilote à prévoir d'ici là. Les attributs de comportement (`sign`, `affects_taxable_base`, etc.) iront sur la catégorie (une place), différés jusqu'à confirmation. Baker en même temps → 293 → 294 tables.

## 62. reglement_post_transfer() : signature p_currency incohérente avec le schéma — ✅ RÉSOLU le 22/07/2026 (2 points de conception tranchés en même temps)

**Origine** : 2e point remonté par le chat DEV Backend Symfony, en préparant l'implémentation du grand livre Règlements.

**Problème 1 — corrigé** : `reglement_post_transfer(p_currency BIGINT, ...)` restait sur l'ancienne convention `currency_id` alors que le diff `reglements-currency_code-fix.diff` (17/07) avait déjà migré toutes les colonnes en `VARCHAR(3) REFERENCES ref_currency(code)`, y compris le corps de la fonction (les `INSERT ... currency_code ...`). Seule la déclaration du paramètre avait été oubliée par le diff. Corrigé en `p_currency VARCHAR(3)`. Testé en sandbox par appel réel de la fonction (2 comptes, code devise `'TND'`) : `reglement_transfer` + les 2 jambes de `reglement_ledger_entry` (signe opposé, même `transfer_id`) + `reglement_balance` (mis à jour par le trigger) tous corrects.

**Problème 2 — tranché, pas d'action de code** : la note du schéma sur la concurrence de `reglement_balance` mélangeait correction et performance. Clarification apportée : le trigger (`INSERT ... ON CONFLICT ... DO UPDATE`) est une opération **atomique** (verrou de ligne PostgreSQL implicite) — aucune race condition de type lost-update, contrairement à un `SELECT` + `UPDATE` séparés côté application sans `FOR UPDATE`. Le seul risque réel est une **contention** (attente de verrou) si plusieurs transactions postent simultanément sur le même triplet `(party_account_id, party_role, currency_code)` — des comptes différents ne se bloquent jamais entre eux. Volume réel confirmé par l'utilisateur : **100-200 règlements/jour max**, très en dessous de tout seuil de contention réaliste. Décision (méthode du projet : pas d'optimisation sans preuve réelle) : garder le trigger en V1, ne pas anticiper le passage à `SELECT FOR UPDATE` applicatif. Seuil de bascule documenté dans le commentaire du schéma pour référence future (changement radical de volume, ou confirmation par monitoring de lock wait).

**Problème 3 — clarifié, pas un problème réel** : l'intention du Backend (posting `obligation`/`instrument_credit` fait par INSERT Domain-contrôlé en attendant les fonctions SQL dédiées) était déjà l'intention documentée dans la note 3 du schéma (« non incluses en V1... à définir avec l'équipe dev »). Deux corrections de forme apportées : (a) nommage unifié — le commentaire de `reglement_ledger_entry` citait `reglement_post_instrument_credit`, la note 3 citait `reglement_post_credit` ; `reglement_post_credit` retenu (cohérent avec `reglement_post_transfer`/`reglement_post_reversal`) ; (b) la règle « jamais d'INSERT direct depuis l'application » sur `reglement_ledger_entry` est reformulée pour clarifier qu'elle interdit le contournement des invariants (signe, cohérence des jambes), pas l'absence de fonction SQL — un INSERT Domain-contrôlé respectant ces invariants est acceptable tant que la fonction dédiée n'existe pas.

**Fichiers modifiés** : `schema-reglements-v1.sql` uniquement (correction de bug + 2 clarifications de commentaires, aucune table ajoutée/retirée — total reste 293).

## 63. Doublon d'encaissement d'instrument en caisse (même session) — ✅ RÉSOLU le 23/07/2026

**Origine** : remontée du chat DEV Backend Symfony, en cadrant `cash_receive_instrument()`.

**Constat** : `cash_movement.instrument_id` n'avait aucune contrainte d'unicité — rien n'empêchait qu'un même `reglement_instrument.id` soit encaissé deux fois dans la même `cash_session`. Confirmé par l'utilisateur : un doublon dans la même session n'est jamais légitime. À distinguer d'un même instrument réapparaissant dans une session **différente** (ex. migration chèque agent → caissier central → banque via `cash_validate_session`), qui est un mouvement de vie légitime, pas un doublon.

**Résolu** : index unique partiel ajouté sur `cash_movement` :
```sql
CREATE UNIQUE INDEX uq_cash_movement_instrument_per_session ON cash_movement(session_id, instrument_id) WHERE instrument_id IS NOT NULL;
```
Scopé à `session_id` — bloque uniquement le doublon même-session, laisse structurellement possible la réapparition inter-sessions. Même pattern que `uq_cash_session_one_open_per_holder` (invariant en base, pas en Application).

Testé en sandbox : chaîne 16/16, 0 erreur, 293 tables (aucune table ajoutée, juste un index). Comportement vérifié par test réel : (A) ré-encaissement du même instrument dans la même session → rejeté (`duplicate key value violates unique constraint`) ; (B) même instrument dans une session différente (après fermeture de la première) → accepté.

**Point ouvert, non traité ici (volontairement différé)** : la distinction entre un transfert inter-sessions légitime post-`cash_validate_session` et un vrai doublon cross-session reste à trancher quand cette fonction sera construite côté backend (pas encore le cas). Aucune contrainte cross-session n'existe par construction — à reprendre lors de la vague `cash_validate_session`.

**Incident annexe détecté pendant cette réouverture** : `schema-ref-common.sql` et `schema-core-identity-v1.sql` ont de nouveau disparu de l'accès du chat pilote (46 fichiers au lieu de 48), très probablement suite à la suppression d'un doublon `schema-core-identity-v1.sql` plus tôt dans la journée qui a semble-t-il supprimé les deux exemplaires (même symptôme déjà observé sur `schema-ref-common.sql`). Travail complété via copies de sauvegarde en cache, vérifiées identiques aux versions validées. **À vérifier par l'utilisateur** : ces deux fichiers sont-ils toujours présents dans le Project ? Si absents, re-uploader (copies disponibles, déjà fournies).
