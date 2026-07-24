# Backend — Vue d'ensemble du projet (ERP Tourisme)

**Statut** : Document de cadrage — aucun code Symfony n'existe encore.
**Rôle de ce document** : contexte pour Cursor / toute session de génération de code backend. À relire avant d'ouvrir une session de conception ou de code sur un module.

---

## 🎯 Contexte

Refonte complète d'un ERP tourisme legacy (Symfony 2.8, 10+ ans) vers une architecture moderne. Développement **solo, assisté par IA** (pas d'équipe à coordonner) — c'est une différence structurante par rapport à une première tentative de cadrage abandonnée il y a 6 mois, qui supposait une équipe de 5 personnes. Cette différence doit rester présente à l'esprit dans toute décision d'architecture : la question n'est jamais « est-ce compréhensible par un junior », mais « est-ce que ça reste cohérent et maintenable session après session, y compris quand c'est un agent IA qui écrit le code ».

**La conception de la base de données est pilotée dans une session dédiée** (`00-Main DB architect`, même Project). C'est la seule chose concrète et figée à ce jour. Ce document backend en dépend directement et ne doit jamais la contredire.

**État de la conception BDD au moment de la rédaction** : **estimée à moins de 60% du périmètre total.** Tout module non listé comme "figé" ci-dessous n'existe pas encore et ne doit pas être anticipé en code.

---

## 📈 Volumétrie & Charge (inchangé)

- **100+ clients** (agences de voyages + hôtels), **~20 utilisateurs/agence**, B2C via site web public, APIs externes exposées
- **Gros clients** : jusqu'à 12 000 000 requêtes API/jour (~139 req/sec, pics 500-1000 req/sec)
- **Architecture** : 1 serveur dédié = 1 client (isolation physique complète, ADR-004 backend = ADR-004 BDD, décision identique des deux côtés)

## 🔧 Stack Technique

- **Backend** : PHP 8.3+, Symfony 7.2, PostgreSQL 16
- **Frontend** : React 18 + Vite (back-office), Next.js (site B2C) — non revu dans cette session, hérité de la réflexion précédente sans contre-indication identifiée
- **Cache/Queue (Redis, RabbitMQ)** : **non tranché**. Présents dans l'ancienne réflexion par défaut, à revalider quand un besoin concret apparaîtra (ex. cache de session, jobs asynchrones réels) plutôt qu'à poser en préventif

## 🏗️ Critères de décision (ordre de priorité, inchangé)

1. **Performance** : P95 < 200ms, support 12M req/jour sur les plus gros clients
2. **Simplicité** : maintenable en solo + IA (reformulé — l'ancienne version disait "par une équipe junior")
3. **Qualité** : tests ciblés, pas de dette technique

---

## 📦 Modules — alignés sur la conception BDD réelle

**Périmètre backend = périmètre BDD.** Pas de découpage `Modules/` Symfony inventé indépendamment. Voir `02-backend-module-index.md` pour le détail et les dépendances.

Modules **figés côté BDD** (base disponible pour démarrer une conception backend, aucun n'a encore de code) :
Party, Core, Référentiel commun, Booking, Règlements Client/Fournisseur, Cash Management, Point de vente, Référentiel Hébergement & Géographie, Facturation/Avoirs, Product/Catalogue.

Modules **non commencés côté BDD** (ne pas concevoir le backend avant que la BDD existe) :
Pricing/Contracting (marges de vente), Utilisateurs avancés/permissions/Configuration avancée, Contracting hôtelier avancé + Provider Integration (volontairement repoussés en dernier, legacy sert d'API gateway temporaire sur ce périmètre).

**Retiré du périmètre** : Stock Management (décision utilisateur actée côté BDD le 16/07 — ne doit apparaître nulle part côté backend non plus).

---

## 🎓 Principes directeurs (reformulés pour le contexte solo + IA)

### 1. Performance > tout, sans "on optimisera plus tard"
Inchangé. Benchmarks obligatoires sur endpoints critiques, index dès la création, pas de report.

### 2. Prévisibilité structurelle plutôt que "simplicité pour juniors"
Le principe reste le même dans sa forme (une règle unique, non ambiguë, "la logique métier est TOUJOURS à tel endroit") mais la justification change : ce n'est plus pour un junior humain, c'est pour qu'un agent IA générant du code sur des dizaines de sessions produise quelque chose de cohérent sans réinventer la structure à chaque fois. C'est directement l'écho, côté code, du principe tranché côté BDD le 18/07 : *la base porte les invariants structurels, la vraie logique métier reste dans une couche applicative unique et jamais ailleurs.*

### 3. Pas de cérémonie sans besoin réel
Contrairement à la réflexion d'il y a 6 mois, on ne pose pas systématiquement tout l'outillage DDD tactique (Domain Events inter-modules, une interface par entité) en préventif. On commence minimal (voir `01-backend-architecture-decisions.md`, ADR-002bis) et on enrichit seulement quand un besoin réel apparaît.

### 4. Sécurité by design, cohérence avec Core dès le départ
L'authentification/autorisation doit s'appuyer sur `schema-core-identity-v1.sql` (module Core, déjà figé côté BDD), pas être réinventée indépendamment côté backend.

---

## ⚠️ Ce qui n'est PAS tranché (ne pas anticiper)

- Politique de suppression (soft/hard delete) par table — dépend de la BDD, pas encore confirmée
- Stratégie d'audit trail (table générique vs Domain Events applicatifs)
- Détail de l'authentification JWT / lien avec Core
- Niveau exact de DDD tactique (Domain Events systématiques ou non)
- Statut du module Permissions (application séparée ou module interne au monolithe)
- Stratégie de coexistence avec le vrai legacy Symfony 2.8 en production (hors du périmètre de cette relecture — sujet à rouvrir plus tard, pas urgent)

Voir `01-backend-architecture-decisions.md` pour le détail de chaque point en attente.

---

**Version** : 1.0 (première version rédigée depuis zéro dans ce Project)
**Dépend de** : `00-INDEX.md` (conception BDD)
