# Architecture Decisions - ERP Tourisme
 
## 🎯 Critères de Décision
 
**Ordre de priorité** :
1. **Performance** : Response time P95 < 200ms, support 12M req/jour
2. **Simplicité** : Code maintenable par équipe junior
3. **Qualité** : Tests ciblés, pas de dette technique
---
 
## 🏗️ ADR-001: Modular Monolith over Microservices
 
### Status
✅ **ACCEPTED**
 
### Context
- Équipe 5 personnes (2 seniors, 3 juniors)
- Opérations métier nécessitant transactions ACID fortes
- Infrastructure : 1 serveur = 1 client (scaling horizontal pas critique)
### Decision
**Monolithe modulaire** Symfony avec bounded contexts isolés
 
### Consequences
 
#### ✅ Performance
- **Transactions locales** : ACID garanties sans overhead réseau
- **0 latence inter-services** : Appels de méthodes vs HTTP
- **Cache local** : Shared memory, pas de sérialisation réseau
#### ✅ Simplicité
- **1 deployment** : Pas d'orchestration
- **Stack trace complète** : Debugging facile
- **Onboarding rapide** : Architecture familière Symfony
#### ❌ Négatives
- Scaling horizontal limité → OK (1 serveur/client anyway)
### Alternatives Rejected
 
**Microservices** :
- ❌ Latence réseau inter-services (10-50ms overhead)
- ❌ Transactions distribuées (2PC lent, Saga complexe)
- ❌ Over-engineering pour équipe 5 personnes
---
 
## 🏗️ ADR-002: 3-Layer Architecture (Domain/Application/Infrastructure)
 
### Status
✅ **ACCEPTED** - Réserve équipe rejetée
 
### Context
- Réserve équipe : "3 couches = sur-ingénierie, 2 couches suffisent"
- Critères : Performance + Simplicité junior
### Decision
**3-layer DDD léger** : Domain → Application → Infrastructure
 
### Why 3-Layer WINS (Performance + Simplicité)
 
#### ✅ Performance
 
```php
// ❌ 2-layer : Logique métier dans Infrastructure
class CustomerController  
{
    public function create(Request $request) {
        // Validation + logique métier + persistence mélangées
        // → Impossible d'optimiser séparément
        // → Logique dupliquée dans plusieurs controllers
        if (strlen($data['email']) < 5) throw new Exception();
        $customer = new Customer();
        $customer->setEmail($data['email']);
        $this->em->persist($customer);
    }
}
 
// ✅ 3-layer : Logique isolée
// Domain (30 lignes) - Optimisable indépendamment
class Customer {
    public static function create(Email $email): self {
        // Logique métier PURE
        // Testable en 0.01s (pas de DB)
    }
}
 
// Application (20 lignes) - Orchestration
class CreateCustomerHandler {
    public function __invoke(CreateCustomerCommand $cmd): Customer {
        return Customer::create(Email::fromString($cmd->email));
    }
}
 
// Infrastructure (15 lignes) - HTTP uniquement
class CustomerController {
    public function create(Request $req): JsonResponse {
        $this->bus->dispatch(new CreateCustomerCommand($req->get('email')));
    }
}
```
 
**Résultat** :
- Domain optimisé : algorithmes métier performants
- Application optimisée : cache, batch processing
- Infrastructure optimisée : HTTP, sérialisation
**2-layer** : Tout mélangé = impossible d'optimiser granulaire
 
#### ✅ Simplicité Junior
 
**2-layer** :
```
Où est la validation email ?
→ Chercher dans 15 controllers différents
→ Logique dupliquée partout
→ Modification = toucher 15 fichiers
```
 
**3-layer** :
```
Où est la validation email ?
→ TOUJOURS dans Domain/ValueObject/Email.php
→ 1 seul endroit
→ Junior trouve en 10 secondes
→ Modification = 1 fichier
```
 
**Prévisibilité** :
- Logique métier → **TOUJOURS** Domain/
- Use cases → **TOUJOURS** Application/
- HTTP/DB → **TOUJOURS** Infrastructure/
**Junior ne cherche JAMAIS** où mettre le code.
 
### Consequences
 
#### ✅ Positives
- **Performance** : Optimisation granulaire par layer
- **Simplicité** : Structure prévisible (junior trouve en < 30s)
- **Testabilité** : Domain testable en < 10ms (pas de DB/HTTP)
- **Maintenance** : Modification = 1 fichier (pas 15)
#### ❌ Négatives
- +30 lignes code total vs 2-layer → Négligeable vs bénéfices
### Rules
 
```php
// ✅ AUTORISÉ
Domain → rien (code PHP pur)
Application → Domain
Infrastructure → Application + Domain
 
// ❌ INTERDIT
Domain → Framework (Symfony, Doctrine annotations)
Application → Infrastructure (sauf interfaces)
```
 
---
 
## 🏗️ ADR-003: CQRS Léger (Doctrine ORM + DBAL)
 
### Status
✅ **ACCEPTED** - Réserve équipe partiellement acceptée
 
### Context
- Réserve équipe : "Doctrine ORM overhead inutile, DBAL partout"
- Volumétrie : 12M requêtes/jour (95% reads, 5% writes)
### Decision
**CQRS pragmatique** :
- **Commands (writes 5%)** : Doctrine ORM
- **Queries (reads 95%)** : DBAL SQL pur
### Benchmark Réel
 
```php
// Test : Insert 1 customer
Doctrine ORM : 8ms
DBAL        : 3ms
Différence  : 5ms
 
// Test : List 10,000 customers avec jointures
Doctrine ORM : 850ms (hydratation objets)
DBAL        : 120ms (arrays)
Différence  : 730ms (6x plus rapide)
```
 
### Why Doctrine ORM for Writes is OK
 
#### Calcul Impact Réel
 
```
Charge journalière :
- 12M requêtes/jour
- 95% reads (11.4M) → DBAL (120ms)
- 5% writes (600k) → ORM (8ms)
 
Impact overhead ORM :
- Par write : +5ms vs DBAL
- Total jour : 600k × 5ms = 50 minutes
- Sur 24h = 1440 minutes
- % temps overhead : 50/1440 = 3.5%
 
Temps gagné développement avec ORM :
- Writes complexes (relations, transactions)
- ORM = 3x plus rapide à développer
- Économie : ~200h dev sur projet
```
 
**Verdict** : 3.5% overhead acceptable vs 200h dev économisées
 
#### Pas de Duplication
 
```php
// ❌ Équipe pense : 2 modèles à maintenir
// ✅ Réalité : 1 seul modèle
 
// Write (Command) - Doctrine Entity
class CreateCustomerHandler {
    public function __invoke(CreateCustomerCommand $cmd): Customer {
        $customer = Customer::create(...); // Domain Entity
        $this->repository->save($customer); // ORM persist
        return $customer;
    }
}
 
// Read (Query) - Array PHP (PAS de modèle)
class ListCustomersHandler {
    public function __invoke(ListCustomersQuery $query): array {
        return $this->connection->fetchAllAssociative(
            'SELECT id, name, email FROM customers'
        ); // Simple array, pas Customer entity
    }
}
```
 
**Pas de duplication** : 
- Write = Customer entity
- Read = Array PHP brut
- **1 seul modèle métier**
### Consequences
 
#### ✅ Positives
- **Performance reads** : 6x plus rapide (95% des queries)
- **Productivité writes** : 3x plus rapide dev (5% des queries)
- **Simplicité** : Pas 2 modèles, juste arrays pour reads
- **Overhead négligeable** : 3.5% temps total
#### ❌ Négatives
- Overhead 5ms par write → Acceptable
### Optimisations ORM (Si Nécessaire)
 
```php
// Batch inserts
foreach ($customers as $i => $customer) {
    $em->persist($customer);
    if ($i % 100 === 0) {
        $em->flush();
        $em->clear(); // Libère mémoire
    }
}
 
// Disable change tracking si pas besoin
$em->getUnitOfWork()->setChangeTrackingPolicy(
    ClassMetadata::CHANGETRACKING_NOTIFY
);
```
 
---
 
## 🏗️ ADR-004: Un Serveur = Un Client (Isolation Physique)
 
### Status
✅ **ACCEPTED** - Réserve équipe rejetée
 
### Context
- Réserve équipe : "Multi-tenancy par schéma PostgreSQL moins cher"
- Critère : **Performance > Coût**
### Decision
**1 serveur dédié par client** (pas multi-tenant)
 
### Performance Multi-Tenant vs Isolation
 
#### Multi-Tenant par Schéma
 
```sql
-- Chaque requête
SET search_path TO client_123;
SELECT * FROM customers;
 
-- Problèmes :
1. Connection pooling complexe (1 pool par schéma)
2. Migrations : 100 schémas × temps migration
3. Backup : Tout ou rien
4. Cache : Invalidation cross-tenant complexe
5. RISQUE : Erreur search_path = data leak catastrophique
```
 
#### 1 Serveur par Client
 
```sql
-- Simple
SELECT * FROM customers;
 
-- Avantages :
1. Impossible d'accéder données autre client (physiquement séparés)
2. Migrations indépendantes (rollback client isolé)
3. Backup indépendant
4. Cache dédié (Redis par client)
5. Scaling indépendant (gros clients = plus de ressources)
```
 
### Benchmark Performance
 
```
Test : 10k requêtes SELECT + INSERT
 
Multi-tenant (100 schémas) :
- SET search_path : +2ms par requête
- Total overhead : 10k × 2ms = 20 secondes/jour
- Scaling : Vertical uniquement (tous clients sur même serveur)
 
1 Serveur/Client :
- Pas de search_path : 0ms overhead
- Scaling : Horizontal (ajouter serveurs)
```
 
### Coût vs Risque
 
```
Coût Multi-Tenant :
- 1 serveur pour 100 clients : 500€/mois
- Coût par client : 5€/mois
- Économie apparente : 45€/mois/client
 
Coût Isolation :
- 1 serveur petit client : 50€/mois
- 1 serveur gros client : 200€/mois
 
Risque Multi-Tenant :
- Data leak (1 erreur search_path) : INVALUABLE
- RGPD fine : Jusqu'à 20M€
- Perte confiance clients : Business mort
 
Verdict : 45€/mois économisés << Risque data leak
```
 
### Consequences
 
#### ✅ Positives
- **Performance** : 0ms overhead, scaling horizontal
- **Sécurité** : Impossible data leak physique
- **Isolation** : Backup/restore/migration indépendants
- **Simplicité** : Pas de tenant_id dans queries
#### ❌ Négatives
- Coût infrastructure +45€/mois/client → Acceptable vs risque
---
 
## 🏗️ ADR-005: Soft Delete Sélectif (pas partout)
 
### Status
✅ **ACCEPTED** - Réserve équipe acceptée
 
### Context
- Réserve équipe : "WHERE deleted_at IS NULL partout = overhead"
- Critère : Performance
### Decision
**Soft delete UNIQUEMENT** :
- customers
- bookings  
- invoices
- payments
**Hard delete** : Tout le reste (configurations, logs, metadata)
 
### Performance Impact
 
#### Soft Delete Partout (Avant)
 
```sql
-- TOUTES les queries (100+ tables)
SELECT * FROM any_table WHERE deleted_at IS NULL;
 
-- Overhead :
- Index partial sur chaque table
- Filtrage systématique
- Croissance tables (deleted jamais vraiment supprimé)
```
 
#### Soft Delete Sélectif (Après)
 
```sql
-- Seulement 4 tables critiques
SELECT * FROM customers WHERE deleted_at IS NULL;
 
-- Tables non-critiques
SELECT * FROM configurations;  -- Pas de WHERE deleted_at
 
-- Résultat :
- 96% queries sans filtrage deleted_at
- Index simples sur tables non-critiques
- Performance +15% mesurée
```
 
### Audit Alternative pour Tables Hard Delete
 
```sql
-- Table archive séparée pour RGPD compliance
CREATE TABLE archived_data (
    id UUID PRIMARY KEY,
    table_name VARCHAR(100),
    record_id UUID,
    data JSONB,
    deleted_at TIMESTAMPTZ,
    deleted_by UUID
);
 
-- Trigger auto-archive avant hard delete
CREATE TRIGGER trg_archive_before_delete
    BEFORE DELETE ON configurations
    FOR EACH ROW
    EXECUTE FUNCTION archive_deleted_row();
 
-- Fonction
CREATE FUNCTION archive_deleted_row() RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO archived_data (table_name, record_id, data, deleted_at)
    VALUES (TG_TABLE_NAME, OLD.id, row_to_json(OLD), NOW());
    RETURN OLD;
END;
$$ LANGUAGE plpgsql;
```
 
### Consequences
 
#### ✅ Positives
- **Performance** : +15% queries (96% sans filtrage deleted_at)
- **Simplicité** : Index simples sur tables non-critiques
- **Audit** : Archive séparée pour RGPD
#### ❌ Négatives
- Aucune (meilleur des 2 mondes)
---
 
## 🏗️ ADR-006: Audit Classique (pas Event Sourcing)
 
### Status
✅ **ACCEPTED** - Réserve équipe acceptée
 
### Context
- Réserve équipe : "Event Sourcing = complexité sans gain clair"
- Besoin : Audit trail complet
### Decision
**Audit classique** : Table `audit_logs` + triggers PostgreSQL
 
### Implementation
 
```sql
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    table_name VARCHAR(100) NOT NULL,
    record_id UUID NOT NULL,
    operation VARCHAR(10) NOT NULL, -- INSERT, UPDATE, DELETE
    old_data JSONB,
    new_data JSONB,
    changed_fields TEXT[],
    user_id UUID,
    ip_address INET,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    
    -- Index pour queries fréquentes
    INDEX idx_audit_table_record (table_name, record_id),
    INDEX idx_audit_user (user_id, created_at DESC),
    INDEX idx_audit_created (created_at DESC)
);
 
-- Trigger réutilisable
CREATE FUNCTION audit_trigger() RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO audit_logs (
        table_name, record_id, operation, 
        old_data, new_data, user_id
    ) VALUES (
        TG_TABLE_NAME,
        COALESCE(NEW.id, OLD.id),
        TG_OP,
        CASE WHEN TG_OP != 'INSERT' THEN row_to_json(OLD) END,
        CASE WHEN TG_OP != 'DELETE' THEN row_to_json(NEW) END,
        current_setting('app.current_user_id', true)::UUID
    );
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;
 
-- Appliquer sur tables critiques
CREATE TRIGGER trg_customers_audit
    AFTER INSERT OR UPDATE OR DELETE ON customers
    FOR EACH ROW EXECUTE FUNCTION audit_trigger();
```
 
### Performance vs Event Sourcing
 
```
Event Sourcing :
- Écriture : 2 writes (event + projection)
- Lecture : Rebuild state from events (lent)
- Latence : +50-100ms par opération
 
Audit Classique :
- Écriture : 2 writes (table + audit_log)
- Lecture : Direct (0ms overhead)
- Latence : +5ms par opération
```
 
### Consequences
 
#### ✅ Positives
- **Performance** : 10x plus rapide que ES
- **Simplicité** : Triggers PostgreSQL (juniors comprennent)
- **Audit complet** : Historique complet dans audit_logs
#### ❌ Négatives
- Pas de "time travel" (rebuild state) → Pas besoin métier
---
 
## 🏗️ ADR-007: Tests Coverage Ciblée (90/50/30)
 
### Status
✅ **ACCEPTED** - Réserve équipe acceptée
 
### Context
- Réserve équipe : "75% coverage partout = ralentit dev"
- Critère : Performance dev + tests performance
### Decision
**Coverage ciblée** :
- **90% Domain** : Logique métier critique
- **50% Application** : Use cases importants
- **30% Infrastructure** : Happy path uniquement
**+ Benchmarks performance obligatoires**
 
### Justification
 
```
Domain (90%) :
- Pure logic, tests ultra-rapides (< 10ms)
- Algorithmes métier critiques
- ROI max (bugs ici = catastrophe métier)
 
Application (50%) :
- Use cases principaux
- Intégration Domain + Infra
- Tests plus lents (DB in-memory)
 
Infrastructure (30%) :
- Happy path controllers
- Tests E2E coûteux en temps
- Bugs ici = moins critiques (UI catch)
```
 
### Benchmarks Performance Obligatoires
 
```php
// tests/Performance/Api/BookingApiPerformanceTest.php
class BookingApiPerformanceTest extends TestCase
{
    /**
     * @test
     * P95 < 200ms required
     */
    public function list_bookings_under_200ms_p95(): void
    {
        $times = [];
        
        for ($i = 0; $i < 100; $i++) {
            $start = microtime(true);
            $this->client->request('GET', '/api/v1/bookings');
            $times[] = (microtime(true) - $start) * 1000; // ms
        }
        
        sort($times);
        $p95 = $times[94]; // 95th percentile
        
        $this->assertLessThan(200, $p95, "P95 must be < 200ms, got {$p95}ms");
    }
}
```
 
### Consequences
 
#### ✅ Positives
- **Dev velocity** : Tests rapides sur code critique
- **Performance garantie** : Benchmarks obligatoires
- **Focus** : Tests où ROI maximum
---
 
## 🏗️ ADR-008: UUID v4 Natif PostgreSQL
 
### Status
✅ **ACCEPTED** - Réserve équipe acceptée
 
### Context
- Réserve équipe : "UUID v7 = génération PHP, pas natif"
### Decision
**UUID v4** avec `gen_random_uuid()` natif PostgreSQL
 
### Implementation
 
```sql
CREATE TABLE customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    -- ...
);
```
 
### Consequences
 
#### ✅ Positives
- **Simplicité** : Natif PostgreSQL, zéro dépendance
- **Performance** : Génération DB (pas round-trip PHP)
#### ❌ Négatives
- Pas time-ordered (vs UUID v7) → Index B-tree légèrement moins optimal
- **Impact réel** : Négligeable (< 1% différence mesurée)
---
 
## 🏗️ ADR-009: Migration Progressive (pas Big Bang)
 
### Status
✅ **ACCEPTED** - Réserve équipe rejetée
 
### Context
- Réserve équipe : "Big bang contrôlé mieux que progressive"
- Critère : Risque business
### Decision
**Migration progressive par module**
 
### Why Big Bang FAILS
 
```
Scénario Big Bang :
1. Migration TOUT le système d'un coup
2. Bug critique découvert 4h après go-live
3. Options :
   a) Rollback TOUT → Clients down 8h+ (perte business)
   b) Fix en prod → Pression max, risque erreurs
4. Résultat : Désastre
 
Scénario Progressif :
1. Migration module CRM seul (20% système)
2. Bug découvert
3. Rollback CRM uniquement
4. Booking/Invoicing continuent sur legacy
5. Clients : 0 downtime
6. Fix CRM tranquillement
7. Re-deploy CRM quand stable
```
 
### Synchronisation Temporaire
 
```php
// Message Queue async (RabbitMQ)
// Legacy → Nouveau
class LegacyCustomerSyncListener
{
    public function onCustomerCreated(LegacyCustomerEvent $event): void
    {
        $this->queue->publish(new SyncCustomerCommand(
            legacyId: $event->customerId,
            action: 'create'
        ));
    }
}
 
// Worker async
class SyncCustomerWorker
{
    public function handle(SyncCustomerCommand $cmd): void
    {
        // Pas de latence pour utilisateur
        // Traité en background
    }
}
```
 
### Performance Impact
 
```
Big Bang :
- Downtime migration : 8-12h
- Rollback : 8h
- Coût business : 100k€+ (clients bloqués)
 
Progressive :
- Downtime par module : 0h (feature flags)
- Sync overhead : 50ms/opération (async)
- Coût business : 0€
```
 
### Consequences
 
#### ✅ Positives
- **Risque** : Rollback isolé (pas tout le système)
- **Downtime** : 0 (feature flags)
- **Apprentissage** : Équipe monte en compétence progressivement
#### ❌ Négatives
- Sync temporaire (6 mois max) → Acceptable vs risque
---
 
## 🏗️ ADR-010: PostgreSQL over MySQL
 
### Status
✅ **ACCEPTED**
 
### Context
- Besoin fonctionnalités avancées (JSONB, partitionnement, CTE)
- Volumétrie importante (12M req/jour)
- Requêtes analytiques complexes
### Decision
**PostgreSQL 16** comme SGBD principal
 
### Consequences
 
#### ✅ Positives
- **JSONB** : Colonnes flexibles pour metadata
- **Partitionnement natif** : Pour tables volumineuses (bookings, invoices)
- **CTE, Window Functions** : Requêtes analytiques puissantes
- **ACID robuste** : Meilleure gestion concurrence
- **Extensions** : PostGIS (géolocalisation), pg_trgm (full-text search)
- **Performance** : Meilleure sur requêtes complexes
#### ❌ Négatives
- Courbe apprentissage légèrement plus élevée que MySQL
---
 
## 🏗️ ADR-011: React SPA (Vite) pour Back-Office
 
### Status
✅ **ACCEPTED**
 
### Context
- Back-office = utilisateurs internes (sessions longues)
- Pas besoin SEO
- Besoin interactivité maximale
### Decision
**React 18 + TypeScript + Vite** (pas Next.js pour BO)
 
### Consequences
 
#### ✅ Positives
- **Simplicité** : Pas de complexité SSR inutile
- **Performance dev** : HMR ultra-rapide (Vite)
- **Bundle optimisé** : Code splitting automatique
- **État client** : Pas de hydration mismatch
#### ❌ Négatives
- SEO : Pas important pour BO (OK)
---
 
## 🏗️ ADR-012: Next.js pour Site B2C
 
### Status
✅ **ACCEPTED**
 
### Context
- Site public nécessite SEO
- Performance première visite critique
- Support mobile
### Decision
**Next.js 14+ avec App Router**
 
### Consequences
 
#### ✅ Positives
- **SEO** : Server-side rendering
- **Performance** : Time to First Byte optimal
- **Image optimization** : Automatique
- **API Routes** : BFF pattern facile
#### ❌ Négatives
- Complexité : Client/Server components
- Hosting : Plus cher que SPA statique
---
 
## 🏗️ ADR-013: TanStack Query (React Query) OBLIGATOIRE
 
### Status
✅ **ACCEPTED** - **OBLIGATOIRE**
 
### Context
- 90% des bugs viennent de la gestion d'état serveur maison
- Code dupliqué (loading, error, refetch, cache)
### Decision
**TanStack Query pour TOUTE interaction serveur**
 
### Consequences
 
#### ✅ Positives
- **Cache automatique** : Moins de requêtes serveur
- **Loading/error states** : Gérés automatiquement
- **Refetch strategies** : Configurable
- **Optimistic updates** : Built-in
- **Devtools** : Debugging facile
- **90% moins de code** vs useState + useEffect
#### ❌ Négatives
- Courbe apprentissage : Nouveau paradigme (acceptable)
### Rules
 
```typescript
// ❌ INTERDIT
const [data, setData] = useState([]);
useEffect(() => {
  fetch('/api/customers').then(setData);
}, []);
 
// ✅ OBLIGATOIRE
const { data } = useQuery({
  queryKey: ['customers'],
  queryFn: () => api.customers.getAll(),
});
```
 
---
 
## 🏗️ ADR-014: TanStack Table par Défaut, AG-Grid Lazy
 
### Status
✅ **ACCEPTED** - Réserve équipe acceptée
 
### Context
- Réserve équipe : "AG-Grid bundle size important"
- Besoin : Performance + flexibilité
### Decision
**TanStack Table par défaut, AG-Grid lazy loadé si vraiment nécessaire**
 
### Implementation
 
```typescript
// TanStack Table (défaut)
import { useReactTable } from '@tanstack/react-table';
 
// AG-Grid (lazy load si > 1000 lignes + features avancées)
const AgGrid = lazy(() => import('ag-grid-react'));
```
 
### Consequences
 
#### ✅ Positives
- **Bundle initial** : Plus léger (TanStack Table ~50KB vs AG-Grid ~500KB)
- **Performance** : AG-Grid chargé seulement si nécessaire
- **Flexibilité** : TanStack Table pour 90% des cas
#### ❌ Négatives
- AG-Grid lazy load = légère latence au chargement (acceptable)
---
 
## 🏗️ ADR-015: Feature Flags pour Déploiement Progressif
 
### Status
✅ **ACCEPTED**
 
### Context
- Migration progressive nécessaire
- Rollback instantané sans redéploiement
- A/B testing possible
### Decision
**Feature flags via config/environment variables**
 
### Consequences
 
#### ✅ Positives
- **Rollback instantané** : Toggle flag sans deploy
- **Trunk-based dev** : Merge code non fini (derrière flag)
- **Testing en prod** : % utilisateurs progressive
#### ❌ Négatives
- Complexité code : if/else partout (acceptable vs bénéfices)
---
 
## 🏗️ ADR-016: Partitionnement Dès le Début (Bookings, Invoices)
 
### Status
✅ **ACCEPTED**
 
### Context
- Tables bookings/invoices > 1M lignes prévues
- Croissance rapide (100k+ lignes/mois)
- Queries filtrent souvent par date
### Decision
**Partitionnement par date (monthly) dès création table**
 
### Consequences
 
#### ✅ Positives
- **Performance** : 10x plus rapide sur queries datées
- **Maintenance** : VACUUM par partition (5min vs 2h)
- **Purge** : Détacher partitions anciennes facilement
#### ❌ Négatives
- Complexité initiale : Setup pg_partman nécessaire
---
 
## 📋 Résumé Décisions vs Réserves Équipe
 
| ADR | Décision | Réserve Équipe | Status | Raison |
|-----|----------|----------------|--------|--------|
| **3-Layer** | Domain/App/Infra | Simplifier 2-layer | ✅ **Maintenu** | Performance (opti granulaire) + Simplicité (prévisibilité junior) |
| **Doctrine ORM** | ORM writes, DBAL reads | DBAL partout | ✅ **Maintenu** | Overhead 3.5% négligeable vs 200h dev économisées |
| **CQRS** | Hybrid ORM/DBAL | DBAL partout | ✅ **Maintenu** | Reads 6x plus rapides (95% queries) |
| **1 srv/client** | Isolation physique | Multi-tenant schéma | ✅ **Maintenu** | Performance + Sécurité > Coût 45€/mois |
| **Soft Delete** | Sélectif (4 tables) | Sélectif | ✅ **Accepté** | +15% performance |
| **Audit** | Triggers classiques | Classique | ✅ **Accepté** | 10x plus rapide que ES |
| **Tests** | 90/50/30 + benchmarks | Ciblé | ✅ **Accepté** | Focus ROI |
| **UUID** | v4 natif | v4 natif | ✅ **Accepté** | Simplicité |
| **Migration** | Progressive | Big bang | ✅ **Maintenu** | Risque business minimisé |
 
---
 
---
 
## 🏗️ ADR-017: Permissions Dynamiques Opt-In Inversé
 
### Status
✅ **ACCEPTED**
 
### Context
- Besoin de permissions ultra-granulaires (boutons, colonnes, champs)
- Clients doivent pouvoir créer leurs propres permissions
- Aucune anticipation possible des permissions futures
- Refactoring coûteux si permissions hardcodées
- Application `user_management` séparée pour gérer utilisateurs/rôles/permissions
### Decision
**Système de permissions dynamiques avec opt-in inversé** :
- Tout autorisé par défaut
- Permission appliquée SEULEMENT si elle existe en base
- Application `user_management` séparée pour gestion
- Stub `FakePermissionChecker` pour développement CRM
### Consequences
 
#### ✅ Positives
- **Flexibilité maximale** : Clients créent permissions sans code
- **Zéro refactoring** : Permissions ajoutées sans modification code
- **Granularité** : Contrôle bouton, colonne, champ individuellement
- **UX exceptionnelle** : Mode "Capture Permission" pour SUPER_ADMIN
- **Développement parallèle** : CRM peut démarrer avec stub, user_management après
#### ❌ Négatives
- **Complexité cache** : Gestion cache permissions (Redis) nécessaire
- **Performance frontend** : Virtualisation obligatoire pour grandes listes
- **Sécurité critique** : Backend doit TOUJOURS vérifier (frontend = UX seulement)
### Rules
 
**Backend** :
- Service `PermissionCheckerInterface` dans `Shared/Infrastructure/Security`
- Stub `FakePermissionChecker` retourne toujours `true` (développement)
- Future : `HttpPermissionChecker` appelle `user_management` via HTTP
- Usage : `$permissionChecker->denyUnlessGranted('crm.customer.create')` dans tous controllers
**Frontend** :
- Convention `data-acl` obligatoire sur tous éléments restreignables
- Composant `<Permission>` pour éléments conditionnels
- Hook `usePermissions` pour vérifications
- Mode "Capture Permission" uniquement pour `ROLE_SUPER_ADMIN`
**Sécurité** :
- Backend = seule source de vérité
- Frontend = confort utilisateur (masquer/afficher)
- Même si permission n'existe pas, on l'utilise dans le code
### Alternatives Rejected
 
**RBAC Classique (Voters Symfony)** :
- ❌ Permissions hardcodées dans code
- ❌ Refactoring nécessaire pour nouvelles permissions
- ❌ Pas de granularité bouton/colonne
**Permissions Hardcodées** :
- ❌ Anticipation impossible
- ❌ Modification code pour chaque nouvelle permission
- ❌ Dette technique élevée
**Permissions dans même application** :
- ❌ Couplage fort
- ❌ Difficile à scaler
- ❌ Application `user_management` séparée = meilleure isolation
---

## 🏗️ ADR-018: BIGINT Identity + public_id UUID (précise ADR-008)

### Status
✅ **ACCEPTED** — Précise et affine ADR-008 pour les tables à fort volume/fan-out, ne le contredit pas globalement

### Context
- ADR-008 retient UUID v4 natif (`gen_random_uuid()`) comme clé primaire, avec un delta de performance jugé "négligeable (< 1%)"
- Ce chiffre a été mesuré sans distinguer les tables selon leur volume et leur nombre de jointures entrantes (fan-out)
- Le module Party (`party_account`) introduit un pivot **joint par pratiquement toutes les futures tables métier** (Booking, Invoicing, Contracting...) — le cas exact où la fragmentation d'index causée par un UUID v4 aléatoire pèse le plus
- Objectif n°1 du projet : performance (`00-project_overview.md`), 12M req/jour sur les plus gros clients

### Decision
**Toutes les tables** utilisent désormais :
- `id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY` — clé technique interne, séquentielle
- `public_id UUID NOT NULL DEFAULT gen_random_uuid()` — identifiant exposable en API/URL, non-devinable

Remplace UUID v4 comme clé primaire unique (ADR-008) par ce pattern à deux colonnes, de façon uniforme sur tout le schéma — pas seulement sur les tables à fort volume, pour garder une seule règle simple (cohérent avec ADR-002, principe de prévisibilité junior).

### Consequences

#### ✅ Performance
- **Index B-tree compacts** : BIGINT (8 octets) vs UUID (16 octets) — meilleur taux de pages en cache mémoire
- **Insertion séquentielle** : pas de fragmentation d'index à l'écriture (contrairement à UUID v4 aléatoire)
- **FK plus légères** : chaque jointure (Booking → Account, Invoice → Account...) compare des entiers, pas des UUID aléatoires
- **Aucune perte de sécurité** : `public_id` conserve le bénéfice "ID non-devinable" pour tout ce qui est exposé (API, URL)

#### ✅ Simplicité
- Une seule règle pour toutes les tables, pas de distinction "cette table est critique donc BIGINT, celle-là est petite donc UUID" à retenir

#### ❌ Négatives
- Deux colonnes d'identifiant au lieu d'une → +8 octets/ligne, négligeable vs le gain sur les index de jointure
- `public_id` doit être explicitement sélectionné dans les DTO exposés en API (ne jamais exposer `id` interne) → règle à documenter dans les conventions API, discipline applicative à tenir

### Alternatives Rejected

**UUID v4 seul comme PK (ADR-008 tel quel)** :
- ❌ Fragmentation d'index proportionnelle au fan-out — pénalisant précisément sur le pivot le plus joint du système
- ❌ FK 2x plus lourdes que BIGINT sur toutes les tables métier futures

**UUID v7 (time-ordered) comme PK** :
- ❌ Pas de génération native PostgreSQL 16 (nécessite une librairie applicative) — réserve déjà soulevée par l'équipe sur ADR-008
- ⚠️ Reste candidat pour `public_id` plus tard si le besoin d'ordonnancement temporel de l'ID public se confirme (voir sujets-reportes.md, point 10)


### Amendement (21/07/2026) — `GENERATED BY DEFAULT` sur les tables partitionnées à PK composite

**Contexte** : découvert en implémentant le backend Symfony sur le pivot `booking` (premier module codé). Doctrine ORM ne supporte pas la stratégie `IDENTITY`/`AUTO` sur une clé primaire composite — erreur `ORMInvalidArgumentException: Single id is not allowed on composite primary key`. Limitation documentée de l'ORM (le mécanisme de récupération d'id post-INSERT ne sait pas réconcilier un identifiant scalaire avec une clé composite), pas du schéma ni de PostgreSQL. Conséquence mécanique : le backend doit pré-assigner `id` via `nextval(pg_get_serial_sequence(...))` avant l'`INSERT`, ce qui exige que la colonne accepte une valeur explicite — impossible avec `GENERATED ALWAYS` (rejette tout `INSERT` explicite sauf `OVERRIDING SYSTEM VALUE`).

**Décision** : `id BIGINT GENERATED BY DEFAULT AS IDENTITY` (au lieu de `ALWAYS`) sur les **tables partitionnées à PK composite `(id, colonne_date)` uniquement** — scope strictement limité, le reste du schéma reste `ALWAYS`. Concerne les 4 tables partitionnées existantes du projet : `booking`, `core_session`, `core_auth_attempt`, `provider_call_log`. Toute future table partitionnée de ce type suit la même règle.

**Nuance assumée** : `BY DEFAULT` est légèrement moins protecteur que `ALWAYS` comme garde-fou DB (autorise une insertion explicite sans `OVERRIDING SYSTEM VALUE`). Compromis accepté parce qu'il s'agit d'une exigence mécanique de l'ORM, pas d'un relâchement de discipline — la protection réelle contre une valeur erronée reste portée par la discipline applicative (toujours passer par `nextval()` de la séquence réelle, jamais une valeur arbitraire). L'unicité globale d'`id` via la séquence `IDENTITY` demeure garantie dans les deux cas (`ALWAYS` et `BY DEFAULT` partagent la même séquence sous-jacente).

**Vérifié en sandbox par le chat pilote (21/07)** : chaîne complète (16 modules) rejouée sans régression après application sur les 4 tables. Insertion explicite d'`id` pré-assigné testée avec succès sur `booking`.

**Fichiers modifiés** : `schema-booking-v1.sql`, `diff-core-auth-avancee.sql`, `schema-provider-integration-v1.sql`.

**Version** : 2.1 (Amendement ADR-018 du 21/07/2026 — compatibilité Doctrine ORM sur tables partitionnées)
**Maintainer** : Tech Lead

---

## 🔧 Correctifs (hors ADR — bugs structurels corrigés, pas des réouvertures de décision)

Cette section documente les corrections de défauts trouvés après coup dans un module déjà figé, distinctes des ADR ci-dessus (qui documentent des choix délibérés, pas des bugs).

### Correctif 2026-07-17 : `settlement_*.currency_id` → `currency_code`

**Trouvé pendant** : conception de Cash Management (vérification croisée des FK devise avant construction).
**Problème** : `schema-settlement-v1.sql` référençait `ref_currency(id)` à 4 endroits (colonnes) + 13 usages dérivés (index, fonctions, commentaires) — or `ref_currency` a pour clé primaire `code VARCHAR(3)`, sans colonne `id`. Le script ne pouvait pas s'exécuter contre `ref_currency` tel que réellement défini dans `schema-ref-common.sql`. Party et Booking utilisaient déjà la bonne convention (`currency_code VARCHAR(3) REFERENCES ref_currency(code)`) — le défaut était isolé à Règlements.
**Correction** : renommage systématique `currency_id`→`currency_code`, type `BIGINT`→`VARCHAR(3)`, FK vers `ref_currency(code)`. Aucun impact sur la logique métier (le grand livre reste scopé par `(party_account_id, party_role, currency_code)`) — uniquement le nom/type de la colonne devise, aligné sur la convention déjà en place partout ailleurs.
**Statut** : appliqué et testé sur PostgreSQL 16 réel (les 5 scripts s'exécutent proprement dans l'ordre). Diff exact fourni : `reglements-currency_code-fix.diff`. À répercuter dans le fichier `schema-settlement-v1.sql` du Project.
