# Architecture Decisions — Backend (OS-TRAVEL / MyGo)

**Statut** : Rédigé depuis zéro dans ce Project, sur la base d'une réflexion antérieure abandonnée (numérotation ADR reprise pour lisibilité, mais chaque décision est ré-examinée, pas recopiée).
**Critère de décision** : Performance > Simplicité (solo + IA) > Qualité.
**Aucun code n'existe encore** — ces décisions sont des fondations à valider avant la première session de conception d'un module backend concret.

Chaque ADR porte un statut :
- ✅ **CONFIRMÉ** — l'argument tient indépendamment du contexte équipe→solo, rien à changer
- 🔁 **REFORMULÉ** — la décision tient, mais la justification a changé
- 🔴 **REMPLACÉ** — décision annulée par une décision BDD plus récente
- 🟡 **EN ATTENTE** — pas assez d'information pour trancher, dépend d'un module BDD pas encore conçu

---

## ADR-001 : Monolithe modulaire (pas microservices)

**Statut** : ✅ CONFIRMÉ

Transactions ACID locales, zéro latence inter-services, 1 seul déploiement. Aucun de ces arguments ne dépend de la taille de l'équipe. Cohérent avec ADR-004 (1 serveur = 1 client) côté BDD.

---

## ADR-002 : Architecture 3 couches (Domain / Application / Infrastructure)

**Statut** : 🔁 REFORMULÉ

**Ancienne justification** : prévisibilité pour développeurs juniors.
**Nouvelle justification** : prévisibilité pour un développement solo assisté par IA sur de nombreuses sessions. Une règle unique et non ambiguë ("la logique métier est TOUJOURS dans Domain/") évite qu'un agent IA réinvente une structure différente à chaque génération de code. C'est l'équivalent côté backend du principe tranché côté BDD le 18/07 : *la base porte les invariants structurels ; la vraie logique métier (calculs, orchestration, règles qui évoluent) reste dans une couche applicative unique, jamais en procédure stockée.*

**Règles inchangées** :
```
Domain      → zéro dépendance framework, zéro accès BDD/HTTP
Application → dépend de Domain uniquement
Infrastructure → dépend de Domain + Application, implémente les interfaces
```

---

## ADR-002bis : Niveau de DDD tactique — minimal par défaut

**Statut** : 🔁 REFORMULÉ (nouveau contenu, pas dans la réflexion d'origine sous cette forme)

L'ancienne réflexion posait systématiquement tout l'outillage DDD tactique (Aggregate Root avec Domain Events dispatchés/pull, une interface Repository par entité, Value Objects partout) dès le départ, sur tous les modules. Ce niveau de cérémonie se justifie surtout quand plusieurs développeurs doivent communiquer via des contrats stables — moins évident en solo.

**Décision** :
- **Value Objects** : gardés, notamment pour tout ce qui a un écho direct côté BDD (`Money` encapsulant `ref_currency.minor_unit`, formats validés par CHECK). Faible coût, forte valeur.
- **Une interface Repository par agrégat racine ayant un vrai cycle de vie propre** (`party_account`, `booking`, `reglement_ledger_entry`...) : gardée. Pas systématique sur des tables satellites simples.
- **Domain Events inter-modules** : **posés seulement quand un besoin réel de découplage apparaît**, pas en préventif sur chaque création d'entité. Tant que la communication reste séquentielle (un Handler appelle directement un Repository d'un autre module via une interface partagée), pas besoin de la mécanique événementielle complète.

*Point discuté en session, pas encore éprouvé sur du code réel — à réévaluer au premier module concret si ça s'avère insuffisant.*

---

## ADR-003 : CQRS léger (Doctrine ORM writes / DBAL reads)

**Statut** : ✅ CONFIRMÉ

Aucun rapport avec la taille d'équipe — choix de performance pur (95% de lectures sur 12M req/jour). Rien côté BDD ne le contredit ; les patterns comme le grand livre append-only de Règlements s'accommodent bien d'un read model en tableaux.

---

## ADR-004 : Un serveur = un client (isolation physique)

**Statut** : ✅ CONFIRMÉ — identique à ADR-004 côté BDD

Pas de `tenant_id` nulle part, jamais.

---

## ADR-005 : Politique de suppression (soft/hard delete)

**Statut** : 🟡 EN ATTENTE

L'ancienne réflexion listait des tables génériques (`customers`, `bookings`...) qui ne correspondent plus au schéma réel (`party_account`, `booking`...). Aucune politique de suppression par table n'a été confirmée côté conception BDD à ce jour.
**Bloquant pour** : le pattern de Repository générique (`delete()` doit savoir s'il fait un UPDATE deleted_at ou un DELETE réel).
→ Voir prompt de retour vers le chat DB.

---

## ADR-006 : Audit trail

**Statut** : 🟡 EN ATTENTE

L'ancienne réflexion proposait une table `audit_logs` générique + triggers PostgreSQL. Rien de tel n'existe dans la conception BDD actuelle. Il faut trancher si l'audit est porté par la BDD (triggers génériques) ou par la couche applicative (Domain Events + log métier Symfony) — **avant** de concevoir le premier module backend, pour éviter un doublon ou un trou.
→ Voir prompt de retour vers le chat DB.

---

## ADR-007 : Coverage de tests ciblée (90/50/30)

**Statut** : ✅ CONFIRMÉ (reformulé sans référence à une équipe junior)

- 90% Domain (logique pure, tests rapides, ROI maximal)
- 50% Application (use cases)
- 30% Infrastructure (happy path)
- Benchmarks performance obligatoires sur endpoints critiques

---

## ADR-008 : UUID v4 natif comme clé primaire

**Statut** : 🔴 REMPLACÉ par ADR-018 (BDD)

**Décision adoptée** : `id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY` (clé technique interne) + `public_id UUID NOT NULL DEFAULT gen_random_uuid()` (identifiant exposé en API/URL). Uniforme sur **toutes** les tables, sans exception.

Conséquences pour le backend, à respecter dès la première ligne de code :
- Les Repository interfaces manipulent la clé technique (BIGINT / int PHP) en interne
- Les Controllers exposent uniquement `public_id` dans les routes et réponses API — jamais la clé technique
- Le mapping Doctrine (XML, pas d'annotations, cf. ADR-002) déclare deux colonnes distinctes, pas une seule PK string

---

## ADR-009 : Stratégie de migration / coexistence avec le legacy

**Statut** : 🟡 EN ATTENTE — hors périmètre de cette session

Le vrai legacy Symfony 2.8 est toujours en production. La question de la coexistence (progressive vs big bang, synchronisation) reste réelle à terme, mais aucun code backend n'existe encore pour la poser concrètement. Pour le périmètre Contracting hôtelier avancé / Provider Integration, la conception BDD a déjà tranché : le legacy sert d'**API gateway temporaire**. Pour le reste des modules, rien n'est décidé — à rouvrir quand le premier module backend approchera du déploiement, pas maintenant.

---

## ADR-010 : PostgreSQL 16

**Statut** : ✅ CONFIRMÉ

---

## ADR-011 à ADR-014 : Choix frontend (React/Vite, Next.js, TanStack Query, TanStack Table)

**Statut** : Non réexaminés dans cette session (hors périmètre backend) — conservés tels quels, sans contre-indication identifiée côté BDD.

---

## ADR-015 : Feature flags

**Statut** : ✅ CONFIRMÉ comme outil disponible, usage à définir au cas par cas (pas de mécanique de rollout progressif à construire en préventif tant qu'aucun module n'est en production)

---

## ADR-016 : Partitionnement dès le début (tables à fort volume)

**Statut** : ✅ CONFIRMÉ, aligné sur la BDD

`booking_` est partitionné par date dès la conception. **Attention** : `reglement_ledger_entry` est explicitement **non partitionné en V1** côté BDD (à réévaluer au-delà de 10M lignes) — ne pas supposer un partitionnement uniforme sur toutes les tables volumineuses.

---

## ADR-017 : Permissions dynamiques, opt-in inversé

**Statut** : 🟡 EN ATTENTE

Le principe métier (tout autorisé par défaut, une permission ne s'applique que si elle existe en base, granularité bouton/colonne/champ) reste une bonne intention, indépendante du contexte équipe. **Mais** l'implémentation envisagée à l'origine (application `user_management` **séparée**, appelée en HTTP) est en tension directe avec ADR-001 (rejet des microservices pour une petite équipe/solo, sur-ingénierie). Le module BDD correspondant (« Utilisateurs avancés / permissions / Configuration avancée ») n'est pas encore conçu. **Ne rien coder avant que ce module existe côté BDD.** Probable révision : module interne au monolithe plutôt qu'application séparée — à confirmer quand le sujet s'ouvrira réellement.

---

## ADR-019 (nouveau) : Périmètre des modules backend = périmètre BDD figé

**Statut** : ✅ CONFIRMÉ

Le découpage `src/Modules/` Symfony suit strictement les modules BDD, ni plus ni moins :
- Pas de "CRM" générique → module **Party** (+ **Core** pour l'authentification, séparé)
- Pas de "Cash Management" fourre-tout → **Règlements** (grand livre client/fournisseur) et **Cash Management** (caisses/banques) sont deux modules distincts
- **Stock Management retiré** — ne doit apparaître nulle part
- **Pricing** (marges de vente) et **Contracting hôtelier avancé** (tarifs d'achat) sont deux modules distincts, découplés

Détail complet : `02-backend-module-index.md`.

---

## ADR-020 (nouveau) : Nommage aligné sur la BDD

**Statut** : ✅ CONFIRMÉ

- Nom de table Doctrine = nom de table BDD réel, préfixe module inclus (`party_account`, pas `accounts`)
- Namespace module Symfony aligné sur le préfixe BDD (`Modules/Party/`, `Modules/Booking/`, `Modules/Reglements/`...)
- Pas de traduction/renommage générique anglais côté ORM qui masquerait le préfixe métier
- Exception connue et assumée côté BDD : `reglement_` et `pointvente_` restent en français (dette documentée, renommage volontairement différé — le backend en hérite tel quel, ne pas "corriger" unilatéralement côté code)

---

**Version** : 1.0
**Dépend de** : `01-architecture_decisions.md` (BDD), ADR-018 en particulier
