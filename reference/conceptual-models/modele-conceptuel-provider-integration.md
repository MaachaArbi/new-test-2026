# Modèle Conceptuel — Provider Integration (`provider_`)

**Statut** : Figé (V1.0) — 21 juillet 2026, testé sur PostgreSQL réel (sandbox), chaîne complète (16 étapes, 291 tables)
**Périmètre** : API IN (fournisseurs de contenu — Hôtel/Vol/Voiture/Train/Ferries/tout service Booking) + Outils techniques purs (SMS/Emailing/Passerelle de paiement)
**Hors périmètre** (reportés, voir `sujets-reportes.md`) : API OUT, Channel Manager, gestion des licences/entitlements (transverse), Contracting hôtelier avancé
**Dépend de** : `content_provider` (`schema-ref-static-v1.sql`), `party_account` + `party_account_office` (`schema-party-account-v1.sql`), `log_entity_type` + `log_audit_trigger()` (`schema-log-v1.sql`)
**Livrables associés** : `schema-provider-integration-v1.sql`

## Pourquoi ce module, et le tournant conceptuel qui l'a façonné

Le cadrage initial partait d'une hypothèse implicite : un provider aurait une forme de connexion à peu près stable, avec des variantes marginales. Cinq captures legacy réelles (Algebratec, Leferry, BoosterBC, Brevo, clictopay) ont immédiatement invalidé cette hypothèse — chaque provider impose son propre contrat d'identification (login/mot de passe/endpoint, ou token/endpoint, ou agency_code+PIN, ou API-key/quota...), et ses propres paramètres techniques arbitraires (JuniperV2 en documente près de 30, chacun typé et commenté).

**Découverte structurante, apportée par l'utilisateur, pas anticipée au cadrage** : le client ne saisit jamais ce contrat lui-même. Il **installe** un provider depuis un catalogue/marketplace (modèle explicitement comparé à l'écosystème de plugins WordPress), signe un accord commercial en amont (hors périmètre de ce projet), puis ne renseigne dans le système que ses identifiants concrets. Cette découverte a fait basculer tout le module d'un modèle "structure de connexion typée en base" vers un **modèle plugin** : le contrat (quels champs, quels types, quels défauts, quelle aide) vit dans un manifeste détenu par OctaSoft (marketplace, produit séparé, non conçu ici) ; le système ne stocke que les **valeurs concrètes** saisies par le client, validées côté Domain contre ce manifeste.

## Décisions structurantes

### 1. Le contrat de connexion ne vit jamais en base client — "monde 1a"
Deux mondes étaient possibles : mirror-er la déclaration du contrat côté client (comme les entités `ref_static` à `oct_code`), ou la laisser entièrement dans le plugin. Tranché en session : le client ne fait jamais de requête SQL sur une clé de configuration précise (confirmé explicitement : "ils servent uniquement pour les utiliser lors des appels API"), donc aucune contrainte d'intégrité fine n'est nécessaire en base sur ces clés — le monde "plugin" (contrat externe) est retenu sans réserve.

**Conséquence directe** : `provider_connection.credentials` et `provider_connection.config` sont des `JSONB` non typés structurellement — ce n'est **pas** de l'EAV déguisé (le schéma existe et est fort, il vit simplement dans le manifeste du plugin, pas dans la base). Contraste explicite avec `config_application_setting` (colonnes explicites) : là-bas l'ensemble des clés est fini et connu du système, ici il est ouvert et détenu par un tiers (chaque plugin apporte les siennes) — colonnes explicites structurellement impossibles ici.

**Gestion de version — "monde 1a" retenu explicitement contre "1b"** : une connexion porte seulement `version_code` (la version du manifeste contre laquelle elle a été validée), jamais un snapshot complet du manifeste. Le manifeste d'une version donnée étant déterministe et détenu par le plugin, connaître la version suffit à retrouver le bon contrat. Le scénario qui aurait justifié un snapshot (connexion survivant à un plugin totalement retiré du catalogue, besoin de validation hors-ligne) a été explicitement écarté par l'utilisateur.

### 2. `content_provider` vs `technical_service_provider` — deux ancres, jamais confondues
Distinction actée dès le cadrage (17/07, confirmée 20/07), et son sens précis clarifié en session : **Provider** = la techhouse (quelle classe de code appeler, quels paramètres techniques) — c'est `content_provider`, déjà figé, entité fondatrice inchangée. **Fournisseur** = l'entité économique à qui l'agence doit de l'argent (ex: Tunisiabeds) — un `party_account` rôle `fournisseur`, pas un nouveau concept.

Ce qui semblait être un champ dupliqué sur l'écran legacy ("Propriétaire" + "Fournisseur") s'est révélé être **deux FK légitimes et distinctes** : une vers la techhouse technique, une vers le débiteur/créancier commercial réel.

`technical_service_provider` (SMS/Email/Passerelle de paiement) est une entité **séparée**, volontairement minimale (`id`, `public_id`, `code`, `name`, `category_code`) : jamais de `oct_code`, jamais de synchronisation OctaSoft Static Data — confirmé qu'aucun prestataire technique pur n'a de `party_account` fournisseur derrière (paiement par abonnement, hors Règlements fournisseur classique).

`technical_service_category` est une table de référence extensible (confirmé en session : l'ensemble email/SMS/passerelle de paiement peut s'élargir — chat/WhatsApp, push notification...) — sert à la fois le choix du bon formulaire d'admin et le filtrage de la future marketplace.

### 3. `provider_connection` — table unique, CHECK croisés (pattern Pricing reconduit)
Une seule table pour les deux catégories, avec deux FK d'ancre nullables et des `CHECK` verrouillant la cohérence — même garde-fou structurel que `pricing_rule_nature`/FK composite (déjà validé 2 fois en sandbox, `sujets-reportes.md` §53), adapté ici à deux FK nullables mutuellement exclusives plutôt qu'à un discriminant dénormalisé :

- Exactement une ancre renseignée (`content_provider_id` XOR `technical_service_provider_id`)
- `fournisseur_account_id` obligatoire si ancre contenu, interdit si ancre technique
- `deactivated_reason_code` interdit si `is_active = true`

Testés en violation active en sandbox (5 scénarios de rejet, tous confirmés).

### 4. Aucune unicité structurelle — désambiguïsation entièrement côté Application
Point clarifié en profondeur en session : ni `(content_provider, fournisseur, bureau)`, ni aucune autre combinaison de FK n'est naturellement unique. Deux connexions clictopay strictement identiques en ancrage peuvent coexister (deux comptes distincts chez le même fournisseur). **Décision assumée, pas un oubli** : le `label` libre est le seul élément distinctif, et "quelle connexion active utiliser en cas de doublon" reste entièrement une règle Application, jamais résolue par une contrainte SQL. Testé en sandbox : deux connexions jumelles insérées sans erreur.

### 5. Le grain "bureau" — `party_account_office`, jamais `sales_point` ni franchise
`office_account_id` référence `party_account_office(account_id)`, **nullable** (connexion globale, partagée par toute l'installation — cas par défaut pour un client à bureau unique). Confirmé explicitement : jamais au niveau `sales_point` (simple lieu physique, pas d'identité économique propre) ni au niveau franchise (`party_account_franchise` — la franchise n'a pas son propre accès provider, distinct du siège).

### 6. `is_active` en BOOLEAN — exception assumée à la règle anti-ENUM du projet
Contrairement au principe transverse "tables de référence, pas d'ENUM" (tenu partout ailleurs, y compris pour de petits ensembles fixes), `is_active` reste un simple `BOOLEAN`. Justification explicite de l'utilisateur : ensemble fermé à dessein ("juste Actif/Inactif, c'est tout, pour savoir si je dois l'interroger ou pas") — aucun besoin d'état intermédiaire (pas de brouillon/testé/suspendu). Documenté comme revirement assumé, même logique que le précédent `ref_property_category`/`ref_accommodation_rental_mode`.

**Complément retenu, combo à deux mécanismes complémentaires** (pas concurrents) :
- `deactivated_reason_code` (FK `provider_connection_deactivation_reason`, table ouverte) + `note` (TEXT libre) pour la **lecture directe immédiate** sur la ligne — l'utilisateur a explicitement priorisé ce besoin ("après quelques mois je vais oublier, le client devra aller dans les logs sinon").
- `log_audit_trigger()` posé sur `provider_connection` (déjà figé, module `log_`, juste appliqué à une table de plus, `entity_type='provider_connection'` ajouté à `log_entity_type`) pour l'**historique complet gratuit** des bascules successives — testé en sandbox, capture avant/après + acteur confirmée.

Origine de ce point : une idée de l'utilisateur en cours de session — désactivation automatique d'un provider trop lent — a révélé le besoin, plutôt qu'un renoncement à une machine à états complète.

### 7. Chiffrement applicatif des credentials — jamais en clair sur disque
`credentials` (JSONB) contient des secrets réels (mots de passe, tokens, clés API vues en toutes lettres sur les écrans legacy). Décision explicite : **chiffrement côté Application avant écriture** (option retenue contre `pgcrypto` en base et contre l'absence de chiffrement), cohérent avec ADR-002 — la base ne porte aucune logique cryptographique, la colonne reste un JSONB standard sans spécificité de schéma pour cette raison. Le déchiffrement à la lecture est également côté Domain.

### 8. Logs d'appel — durci par rapport à `sujets-reportes.md` §44, pas juste confirmé
§44 recommandait déjà de ne pas stocker les payloads haute fréquence dans cette base. La session a précisé et durci ce point avec des besoins réels non anticipés (litige, statistiques de taux d'erreur/timing, historique par réservation, téléchargement request/response) :

- **Le payload complet** (fichiers request/response téléchargeables) vit entièrement **hors de cette base**, sur une future API gateway/microservice dédié (infra, non conçu dans ce Project) — dépendance à sa disponibilité pour l'affichage détaillé **explicitement acceptée**.
- **`provider_call_log`** est un pointeur de corrélation minimal, **jamais un payload** : `correlation_id` (clé de récupération côté gateway), `provider_connection_id`, `entity_type`/`entity_id` (FK applicative, réutilise `log_entity_type` déjà figé, nullable — tous les appels ne sont pas liés à une réservation : recherches stériles, Brevo, clictopay), `service_name`, `status_code`, `error_code`, `message`, `timing_ms`, `purge_at`, `created_at`.
- **Partitionnement mensuel**, même pattern que `booking`/`core_session`/`core_auth_attempt` — volume attendu jusqu'à ~10M lignes/jour (recherches) pour ~1000 réservations/jour chez le plus gros client. Testé en sandbox : routage automatique correct sur 3 partitions explicites + fallback `DEFAULT`.
- **`status_code`** (Succès/Alerte/Échec) et **`error_code`** (vocabulaire normalisé **par l'agence elle-même**, jamais le code brut hétérogène du tiers — confirmé explicitement) sont deux tables de référence ouvertes, cohérentes avec le principe anti-ENUM (contraste assumé avec `is_active`, fermé).
- **`purge_at` porté PAR LIGNE, pas par type** : contrairement à `log_entity_type.retention_days` (rétention uniforme par entité), chaque appel a sa propre politique de rétention selon le service/endpoint (ex: recherche stérile → J+1, email d'inscription → J+2, email de confirmation → date d'arrivée), calculée par l'Application à l'écriture et **modifiable après coup** (ex: réservation reportée → purge repoussée). Aucune contrainte d'immutabilité en base — testé en sandbox (`UPDATE` réussi sur une ligne existante).
- **Distinction fonctionnelle confirmée pour la purge** : conservé si rattaché à une entité durable (`entity_id IS NOT NULL`, ex: réservation aboutie), purgeable rapidement si recherche stérile jamais convertie — mais la mécanique retenue au final est plus fine encore (par ligne, pas par simple présence de lien), d'où `purge_at` explicite plutôt qu'une règle calculée.
- Le job de purge lui-même reste hors DB (cohérent ADR-002 et le principe déjà posé pour `log_`), mais devra à la fois supprimer les lignes expirées **et** `DROP` positivement les partitions mensuelles anciennes devenues vides (point technique soulevé en session — une partition vide n'est pas gratuite indéfiniment).

### 9. `booking_provider_snapshot` et `content_provider` restent inchangés
Aucune réouverture de Booking ni de `ref_static` dans cette session. `booking_provider_snapshot` garde son rôle actuel (payload brut technique rattaché à une réservation) — non fusionné avec `provider_call_log`, qui couvre un périmètre plus large (tous les appels, liés ou non à une réservation).

### 10. Train/Ferries et tout futur service Booking — déjà couverts, sans rien à ajouter
Confirmé en session : l'ancre `content_provider` est générique par nature, indépendante du service vendu. Le trou Maritime (`sujets-reportes.md` §51, absence de fiche Product/Catalogue) reste un problème du module Product/Catalogue, jamais de Provider Integration.

## Entités

| Table | Rôle |
|---|---|
| `technical_service_category` | Référentiel ouvert des catégories d'outils techniques (email/sms/passerelle de paiement...) |
| `technical_service_provider` | Prestataire technique pur (Brevo, clictopay...) — entité fondatrice minimale, jamais de `oct_code` |
| `provider_connection_deactivation_reason` | Référentiel ouvert des raisons de désactivation (manuel vs automatique, par cause) |
| `provider_connection` | Table unique de connexion — ancrage contenu OU technique, credentials/config JSONB, `is_active` + raison, audité |
| `provider_call_status` | Référentiel ouvert (succès/alerte/échec) |
| `provider_call_error_code` | Référentiel ouvert des erreurs normalisées **par l'agence** |
| `provider_call_log` | Pointeur de corrélation minimal, partitionné mensuel, jamais de payload |

## Hors périmètre (reporté, voir `sujets-reportes.md`)

- **API OUT** — nature exacte du "client" côté Party non tranchée (`party_account` générique vs rôle `franchise` vs rôle B2B/affilié à créer). Confirmé : toujours donné à un seul client, uniquement des API de services Booking.
- **Channel Manager** — distinct d'API OUT, resté en backlog post-V1.
- **Gestion des licences/entitlements** — transverse à tout le projet (modules, options, jusqu'au bouton près), pas propre à ce module. Signalement pur, aucune structure anticipée (pas de `license_reference` sur `provider_connection`).
- **Contracting hôtelier avancé** — autre moitié du dernier module, session dédiée à venir.
