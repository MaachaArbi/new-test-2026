# Backend — Index des modules

**Rôle** : équivalent backend de `00-INDEX.md` (BDD). À relire avant d'ouvrir une session de conception ou de code sur un module.
**Règle absolue** : aucune conception backend détaillée sur un module dont la BDD n'est pas figée.

---

## État par module

| Module BDD | Statut BDD | Statut conception backend | Dépend de (BDD) |
|---|---|---|---|
| Party (tiers unifié) | ✅ Figé V1.2 | ⏳ Non commencé | — |
| Core (identité/auth) | ✅ Figé V1.1 | ⏳ Non commencé | Party |
| Référentiel commun (langues, devises) | ✅ Figé | ⏳ Non commencé | — |
| Booking (réservations multi-services) | ✅ Figé V1.1 | ⏳ Non commencé | Party, Référentiel commun |
| Règlements Client/Fournisseur | ✅ Figé V1.0 | ⏳ Non commencé | Party, Booking |
| Cash Management (caisses, banques) | ✅ Figé V1.0 | ⏳ Non commencé | Party, Règlements |
| Point de vente | ✅ Figé V1.0 | ⏳ Non commencé | Party |
| Référentiel Hébergement & Géographie | ✅ Figé V1.0 | ⏳ Non commencé | Référentiel commun |
| Facturation / Avoirs | ✅ Figé V1.0 | ⏳ Non commencé | Party, Booking, Règlements, PDV, Ref-Hébergement |
| Product / Catalogue | ✅ Figé V1.0 | ⏳ Non commencé | Référentiel commun, Ref-Hébergement |
| Pricing / Contracting (marges de vente) | ❌ Non commencé | ⏳ Non commencé | Party, Product/Catalogue |
| Utilisateurs avancés / permissions / Config avancée | ❌ Non commencé | ⏳ Non commencé | Tous modules métier |
| Contracting hôtelier avancé + Provider Integration | ❌ Non commencé, repoussé | ⏳ Non commencé | Référentiel statique, Pricing, Product/Catalogue |

**Aucun module n'a de code backend à ce jour.** La colonne "Statut conception backend" ne passera à "en cours" que lorsqu'une session dédiée sera ouverte sur un module précis — pas avant.

---

## Candidat naturel pour la première session backend

**Party + Core**, dans cet ordre : c'est la fondation identitaire dont dépendent presque tous les autres modules figés (Booking, Règlements, Cash Management, Point de vente, Facturation). Concevoir le backend dessus en premier évite de refaire un travail de fondation plus tard.

**Mais** : conformément à la décision de l'utilisateur, la priorité reste de terminer la conception BDD (encore <60% du périmètre). Cette section sert de repère pour *quand* le moment viendra, pas une invitation à commencer maintenant.

---

## Sujets en attente de décision backend (voir `01-backend-architecture-decisions.md` pour le détail)

- ADR-005 : politique de suppression par table (soft/hard delete)
- ADR-006 : stratégie d'audit trail
- ADR-009 : coexistence avec le legacy réel (hors périmètre immédiat)
- ADR-002bis : niveau de Domain Events (à confirmer sur le premier module réel)
- ADR-017 : statut du futur module Permissions (interne au monolithe vs application séparée)
- Authentification JWT : dépendance exacte à `schema-core-identity-v1.sql`, pas encore détaillée côté backend

---

**Version** : 1.0
**Dépend de** : `00-INDEX.md` (BDD)
