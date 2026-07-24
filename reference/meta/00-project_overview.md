# Project Overview - ERP Tourisme

**Version** : 5.0 (mis à jour 22/07/2026 — remplace la v4.0 du 19/07/2026, obsolète : 3 modules figés depuis (Log, Permissions/Franchises/Config, Provider Integration) + resync documentaire du 22/07, voir `00-INDEX.md` « Note de resync » et `sujets-reportes.md` §60)
**Maintainer** : Tech Lead / Pilote conception (ce Project)

> ⚠️ **Rappel méthode** (inchangé depuis v3.0) : conception BDD-first — chaque module métier est conçu, testé sur PostgreSQL réel et confronté à des données legacy réelles avant tout développement applicatif (voir `00-INDEX.md`, section Méthode).

---

## 🎯 Vision

Refonte complète d'un ERP tourisme legacy (**OS-TRAVEL**, Symfony 2.8, 10+ ans) vers une architecture moderne, **ultra-performante** et maintenable pour les 10 prochaines années. Nom de la nouvelle application encore à trancher (discussion en cours, hors périmètre de ce document).

**Critères de Succès par Priorité** :
1. **Performance** : Response time P95 < 200ms, support 12M requêtes/jour
2. **Simplicité** : Code lisible et maintenable par équipe junior
3. **Qualité** : Tests ciblés, pas de dette technique

---

## 📊 Contexte Métier

### Type d'Application
**ERP Tourisme B2B et B2C** gérant l'ensemble de la chaîne de valeur — multi-services (hôtel, vol, maritime, location voiture, transfert, spa, visa, bus, guide, packages...). "MyGo" est le nom d'un client, pas de l'application elle-même.

### Principe directeur de conception

Deux traitements différents selon le module :
- **Party, Booking, Règlements** : le legacy a servi de matière première solide à challenger sur données réelles.
- **Tous les autres modules** : conception **repensée depuis le besoin métier réel**, le legacy servant uniquement de liste de fonctionnalités à confronter — jamais un gabarit structurel à reproduire. Exception maintenue pour Booking, Contracting et les intégrations API/providers.

**Règle transverse acquise en session Pricing (19/07)** : avant de construire une table pour un concept qui *semble* générique (regroupement, catégorie, dimension transverse...), vérifier explicitement s'il n'est pas déjà anticipé dans un module de couche plus basse (Party, `ref_static`) — chercher dans `sujets-reportes.md` et les `modele-conceptuel-*.md` avant de construire, pas après.

**Principe transverse acté le 18/07** : la base de données porte les invariants structurels (contraintes d'intégrité, plafonds, unicité — protection contre tout écrivain, y compris futurs scripts/services). La logique métier réelle (calculs, orchestration, règles qui évoluent) reste en couche Domain PHP/Symfony (ADR-002), jamais en procédure stockée.

---

## 🧩 Modules du périmètre (état au 22/07/2026)

### ✅ Figés (14 modules)

| Module | Version |
|---|---|
| Party (tiers unifié) | V1.4 — réouverte 19/07 (`party_account_group`) et 20/07 (`party_role='franchise'`) |
| Core (identité/auth) | V1.2 — réouverte 20/07 (Auth avancée : sessions, MFA, `core_auth_attempt`) |
| Référentiel commun (langues, devises) | V1.2 — extension hébergement/`oct_code` bakée le 22/07 |
| Log (journal d'activité + audit trail technique, transverse) | V1.0 (20/07) — ADR-006 enfin construit ; généralise l'ex-`booking_log` |
| Booking (réservations multi-services) | V1.3 — extensions data-driven (`booking_service_type_extension`, retour Backend §61) le 22/07 ; `booking_log` retiré (généralisé en Log) |
| Règlements Client/Fournisseur (grand livre) | V1.0, validé sur 2064 pièces + 1675 règlements réels |
| Cash Management (caisses, banques) | V1.0 |
| Point de vente | V1.0 |
| Référentiel Hébergement & Géographie | V1.0 — réouverte 19/07 (`ref_country_group`) |
| Facturation / Avoirs | V1.0 |
| Product / Catalogue (8 sous-modules : Hôtel, Véhicule, Spa, Visa, Guide, Transfert, Aérien, Bus, Package) | V1.0 |
| Pricing — marges de vente | V1.1 — 3ᵉ nature `payment_modality` bakée le 22/07 ; NE contient PAS le contracting hôtelier |
| Permissions / Franchises / Documents-Emails / Configuration avancée | V1.0 (20/07) — RBAC opt-in inversé (ADR-017), franchises |
| Provider Integration (API IN + outils techniques) | V1.0 (21/07) — modèle « plugin » ; API OUT et Channel Manager reportés |

**293 tables au total** dans le schéma actuel (vérifié par exécution réelle de la chaîne complète le 22/07/2026, `ON_ERROR_STOP=1`, 16/16 sans erreur). Le compte de 292 annoncé au 21/07 était erroné (il incluait `booking_log`, obsolète, resté par erreur) — corrigé à la resync du 22/07. Voir `00-INDEX.md` « Note de resync ».

### ⏳ Modules restants, ordre validé

Un seul module reste à concevoir (Permissions/Franchises/Config figé le 20/07, Provider Integration API IN figé le 21/07 — voir table ci-dessus).

1. **Contracting hôtelier avancé** (tarifs d'achat, micro-marges par arrangement/politique enfant/réduction chambre) — volontairement en dernier (partie la plus complexe et risquée : argent + engagements client réels), le legacy servant d'API gateway temporaire pendant plusieurs mois. Dépend de Pricing (✅), Product/Catalogue (✅), Provider Integration (✅). **Provider Integration API OUT + Channel Manager** sont eux reportés explicitement (voir `sujets-reportes.md` §57).

Détail des dépendances : voir `00-INDEX.md`.

### 🕰️ Backlog post-V1 (besoin réel confirmé, non prioritaire)

- **Loyalty Programs** (fidélité) + **Marketing/Coupons de réduction** (regroupés — un coupon touchera probablement le moteur de règles Pricing déjà figé)
- **"Vrai" CRM** (leads, pipeline, opportunités — distinct de Party)
- **Rules Engine** — ✅ confirmé absorbé de facto par Pricing
- **Channel Manager** — probablement fusionné techniquement avec Provider Integration
- **Module Comptabilité générale** — NON recommandé en version complète (domaine réglementé, risque disproportionné). Alternative retenue : module léger "Interface Comptable" en couche de projection/export depuis Règlements/Cash Management/Facturation, jamais un nouveau système transactionnel de référence légale.
- **Logs API IN/OUT** — stockage séparé recommandé, hors de cette base transactionnelle PostgreSQL (volume/pattern incompatibles avec une base client-isolée)

### ❌ Hors périmètre

- **Stock Management** (stocks, dépôts, inventaire) — retiré du périmètre (décision utilisateur, 16/07). Ne pas confondre avec Product/Catalogue (fiche technique commerciale), bien dans le périmètre.

---

## 🔄 Stratégie de migration (actée 19/07)

Sur une période de 2-3 mois, les deux systèmes tournent en parallèle :
- **Legacy reste seul maître de la vérité financière** — règlements/factures saisis manuellement en double par l'équipe, jamais automatisés entre les deux systèmes (élimine le risque de double comptabilisation silencieuse).
- **Import automatique** (cron/à la demande) limité aux données de référence/mapping : produits (Product/Catalogue), clients (Party), users (futur module Utilisateurs).
- **Conséquence directe** : le module Utilisateurs avancés est un vrai prérequis concret pour cette stratégie, pas seulement le prochain module dans l'ordre.
- Legacy continue de servir d'**API Gateway temporaire** pour la partie la plus risquée (contracting hôtelier, connectivité providers) pendant que le reste bascule.

---

## 📈 Volumétrie & Charge

### Utilisateurs
- **100+ clients** (agences de voyages + hôtels)
- **~20 utilisateurs par agence**
- **B2C** via site web public
- **APIs externes** exposées aux clients

### Données
- **Milliers de lignes** par client, jusqu'à des bases avec ~1 million de réservations envisagées
- **Architecture** : 1 serveur dédié = 1 client (isolation physique complète)
- **Pas de multi-tenancy** dans la DB (chaque client = instance séparée)

### Charge Applicative
- **Clients moyens** : 500 000 requêtes API/jour
- **Gros clients** : 12 000 000 requêtes API/jour (~139 req/sec, pics à 500-1000 req/sec)
- **Pattern** : Forte lecture sur APIs providers (cache critique)

---

## 🏗️ Architecture Cible

### Core Business - Monolithe Modulaire
**Stack** : Symfony 7.4 + PostgreSQL 16

**Architecture** : **3-Layer DDD Léger**
- **Domain** : Logique métier PURE (zéro dépendance framework)
- **Application** : Use Cases, orchestration (Commands/Queries)
- **Infrastructure** : Implémentations techniques (Doctrine, Controllers, DBAL)

Détail complet : voir `01-architecture_decisions.md` (18 ADR). **Un chat pilote dédié ("00-Main DEV Backend") a démarré le 19/07** pour revalider l'architecture backend (documentation vieille de 6 mois : `02-coding_standards.md` à `16-permissions-system.md`, `100-anti_patterns_doc.md`...) à la lumière de toute la conception BDD menée depuis — pas encore uploadée dans ce Project au moment de cette régénération.

### Services Découplés (Microservices)
- **Provider Aggregator** : Go (appels parallèles multi-providers, haute perf) — rejoint le futur module Provider Integration
- **Payment Gateway** : Symfony/Go (sécurité, isolation)
- **Notifications** : Node.js/NestJS (push temps réel)
- **Static Data Service** : rôle désormais tenu par **OctaSoft Static Data**, produit séparé indépendant de ce Project (source de vérité mutualisée pour tous les clients — pays/villes/hébergement/aérien..., voir `modele-conceptuel-ref-static.md` pour le modèle miroir côté client)

### Frontend
- **Back-Office** : React 18 + Vite (SPA)
- **Site B2C** : Next.js 14+ (SSR pour SEO)
- **Mobile** : React Native (Expo) — Phase 2

### Analytics & Search
- **ClickHouse** : Reporting BI, agrégations
- **TypeSense** : Recherche textuelle, autocomplete

### Infrastructure
- **Deployment** : 1 serveur dédié par client (isolation totale)
- **Database** : PostgreSQL 16, 1 DB par instance
- **Cache** : Redis (sessions, cache applicatif)
- **Queue** : RabbitMQ (jobs asynchrones, notifications)
- **Storage** : S3-compatible pour fichiers
- **Logs API IN/OUT** : store dédié séparé (à concevoir), jamais dans la base transactionnelle

---

## 🎯 Objectifs Projet (Must-Have) — à revalider avec le chat Backend

### Base de Données
1. Normalisation correcte : 3NF minimum, pas de duplication
2. Contraintes d'intégrité fortes : FK, CHECK, UNIQUE, triggers
3. Performance : index stratégiques, partitionnement dès le début (`booking` et 3 autres — pg_partman intégré au déploiement, §8)
4. Audit trail : **✅ construit le 20/07 (module Log transverse — `log_audit` + trigger générique `log_audit_trigger()`, ADR-006), voir `sujets-reportes.md` §48 point 2 (résolu)**
5. Disparition / suppression : **✅ ADR-005 révisé le 24/07 — quatre régimes** (logique `deleted_at`, contre-passation, désactivation, suppression réelle). Voir `01-architecture_decisions.md` ADR-005 ; clôture `sujets-reportes.md` §48.
6. Isolation stricte : 1 DB = 1 client (pas de RLS)
7. Évolutivité : migrations versionnées, rollback possible

### Backend
1. Architecture 3-layer : Domain → Application → Infrastructure
2. CQRS pragmatique : Commands (Doctrine ORM) / Queries (DBAL SQL pur)
3. Testing ciblé : 90% Domain / 50% Application / 30% Infrastructure + benchmarks obligatoires
4. API REST : OpenAPI spec, versioning URL
5. Performance : Response time P95 < 200ms
6. Auth/session (JWT/MFA) : **✅ résolu** (Auth avancée / `diff-core-auth-avancee.sql`, voir `sujets-reportes.md` §48 point 3)

### Frontend
1. Component-based, réutilisabilité maximale
2. State management : TanStack Query (server) + Zustand (client)
3. Performance : lazy loading, code splitting
4. Accessibility : WCAG 2.1 AA
5. Responsive : Mobile-first
6. **Principe UX acté (18/07)** : centraliser et minimiser la navigation par rapport au legacy (chaque service avait son propre module/interfaces séparés) — personnalisation de documents et configuration email pensées comme modules transverses de Configuration avancée, pas dupliquées par service.

### Gestion Devises (Critique)
- BIGINT minor units (`ref_currency.minor_unit`), sauf `pricing_*` qui utilise `NUMERIC(12,4)` pour taux/montants de marge (nature différente d'un montant transactionnel définitif)
- Historisation taux de change avec validité temporelle
- Conversion au moment du calcul, pas stockée

---

## 👥 Équipe

- **5 développeurs** : 2 seniors, 3 juniors
- **Intégrateurs front** séparés (si nécessaire)
- **1 DevOps** (infra, CI/CD)

---

## 🔗 Intégrations Externes (rejoint le futur module Provider Integration)

### API Providers
- **Hôtels** : Juniper, Hotelbeds, Amadeus, Sabre — plus généralement OctaSoft Static Data comme couche de mapping/rapprochement mutualisée
- **Aérien** : Amadeus, Sabre
- **Transferts** : Providers locaux
- **Maritime** : APIs compagnies

### Payments
- **Stripe** (cartes bancaires)
- **Providers locaux** (selon pays)

### Notifications
- **Email** : Brevo (ex-Sendinblue)
- **SMS** : Twilio
- **Push** : Firebase Cloud Messaging

---

## 🎓 Principes Directeurs

1. **Performance > Everything** — benchmarks obligatoires, index dès la création, monitoring temps réel
2. **Simplicité pour Juniors** — code prévisible, structure répétable, noms explicites
3. **Testabilité** — Domain layer 100% testable
4. **Pragmatisme** — pas de sur-ingénierie, ORM acceptable pour writes
5. **Sécurité by Design** — validation backend systématique, prepared statements, 1 serveur = 1 client, rate limiting
6. **Invariants critiques en base, métier en Symfony** (acté 18/07) — voir principe directeur en tête de document.

---

## 📐 Bounded Contexts (mis à jour 19/07)

```
┌──────────────────────────────────────────────────────────────────────┐
│                           ERP TOURISME (nom à définir)                │
├──────────────────────────────────────────────────────────────────────┤
│  ✅ Figés (14 modules, 293 tables)                                     │
│  ┌────────┐┌────────┐┌────────────┐┌─────────┐┌─────────────┐        │
│  │ Party  ││  Core  ││Référentiel ││ Booking ││ Règlements  │        │
│  │        ││        ││  commun    ││         ││             │        │
│  └────────┘└────────┘└────────────┘└─────────┘└─────────────┘        │
│  ┌──────┐┌───────────┐┌────────────┐┌────────────┐┌─────────┐        │
│  │ Cash ││  Point de ││ Référentiel││Facturation ││ Product ││        │
│  │ Mgmt ││   vente   ││ Hébergement││  / Avoirs  ││/Catalogue│       │
│  └──────┘└───────────┘└────────────┘└────────────┘└─────────┘        │
│  ┌─────────────────────────┐                                          │
│  │  Pricing (marges vente) │                                          │
│  └─────────────────────────┘                                          │
│                                                                        │
│  ⏳ Restants                                                           │
│  ┌───────────────────────────────┐┌──────────────────────────────┐   │
│  │Utilisateurs avancés / Config  ││Contracting hôtelier +         │   │
│  │avancée                        ││Provider Integration (dernier) │   │
│  └───────────────────────────────┘└──────────────────────────────┘   │
│                                                                        │
│  🕰️ Backlog post-V1 : Loyalty+Coupons, "vrai" CRM, Interface         │
│     Comptable, Channel Manager                                       │
│                                                                        │
│  🌐 Externe (hors Project) : OctaSoft Static Data (référentiel        │
│     mutualisé multi-clients, chat/Project dédié séparé)              │
└──────────────────────────────────────────────────────────────────────┘

Communication inter-modules : lecture directe documentée (ex: Règlements lit
Booking sans jamais y écrire) ou interfaces explicites (SolvencyCheckerInterface).
Concept générique découvert en cours de route → relocalisé vers la couche
de base (Party, ref_static), jamais dupliqué au niveau du module consommateur.
```

---

## 🚀 Success Criteria

### Technical
- [ ] Benchmarks performance validés (12M req/jour supportés)
- [ ] Database queries < 50ms (P95)
- [ ] Zero downtime deployments
- [ ] Rollback < 5 minutes
- [x] `pg_partman` mis en place sur les 4 tables partitionnées (§8, 24/07) — étape de déploiement obligatoire

### Business
- [ ] Migration 100% sans perte de données (stratégie parallèle 2-3 mois, voir ci-dessus)
- [ ] Aucune régression fonctionnelle
- [ ] Performance >= système legacy
- [ ] Formation équipe complétée
- [ ] Documentation à jour

### Quality
- [ ] 0 bug critique en production
- [ ] SLA 99.9% uptime
- [ ] Security audit passed
- [ ] RGPD compliance (politique de purge à définir, voir `sujets-reportes.md` §7)
- [ ] Accessibility WCAG AA

---

## 📚 Documentation Structure (conception BDD — Project actuel)

```
Project ERP Tourisme/
├── 00-INDEX.md                       # À lire en premier, état par module + ordre
├── 00-project_overview.md            # Ce fichier
├── 01-architecture_decisions.md      # 17 ADR + ADR-018 (amendé v2.1 le 21/07)
├── sujets-reportes.md                # Backlog vivant, sujets ouverts (60 points au 22/07)
├── modele-conceptuel-*.md            # 1 par module figé (12 fichiers)
├── schema-*.sql                      # 1 par module figé (14 fichiers)
├── *.diff / diff-*.sql               # Réouvertures ponctuelles documentées (11 fichiers, tous bakés dans leurs bases)
├── pricing-test-data.sql             # Jeu de données de test réel (Pricing)
├── ost_*.sql                         # Exports legacy bruts, matière première
└── 00-EXPERT-REVIEW.md               # Archive Sprint 1-3 Symfony, obsolète pour la conception
```

---

## 📎 Annexe historique — implémentation Symfony antérieure (v2.1, 20/01/2026)

> **Décision actée (16/07/2026)** : cette implémentation (Sprints CRM, Symfony 2.8 → 7.4) est abandonnée. Le développement applicatif reprendra **à zéro** une fois la phase de conception BDD terminée, sur la base solide issue de ce Project — pas en poursuivant l'implémentation Sprint 1-4 ci-dessous. Aucune donnée ni code de cette annexe n'influence la conception des modules restants.

État rapporté au 20/01/2026 (archive, non actualisé depuis) :
- Sprint 1-3 (fondations, Accounts & Contacts, Multi-valeurs) : terminés
- Sprint 4 (Leads & Opportunities) : en cours
- 118 tests, 366 assertions, coverage 90% Domain / 50% Application
- 20+ endpoints REST, 99 fichiers PHP

Timeline prévue à l'époque — **obsolète**, ne correspond plus à l'ordre des modules validé dans ce Project.
