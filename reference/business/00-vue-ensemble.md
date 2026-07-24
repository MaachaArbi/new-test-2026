# Connaissance métier — Vue d'ensemble

**Objet de ce dossier** : expliquer **comment le métier fonctionne**, en langage métier.
Il complète les autres documents de `reference/`, il ne les remplace pas :

| Document | Répond à |
|---|---|
| `meta/00-INDEX.md` | Où sont les choses, dans quel ordre les exécuter |
| `conceptual-models/` | Comment c'est modélisé, et pourquoi |
| `meta/sujets-reportes.md` | Ce qui reste à décider |
| **`business/` (ici)** | **Comment le métier fonctionne réellement** |

**À lire en premier** par toute session (backend, frontend, conception) qui découvre le projet.
Sans ce dossier, on lit des tables sans comprendre ce qu'elles décrivent.

---

## Le métier en une page

L'entreprise est une **agence de voyages tunisienne** qui vend des prestations touristiques —
hébergement, vol, maritime, location de voiture, transfert, spa, visa, bus, guide, packages —
à trois types de clients :

- des **particuliers** qui achètent au comptoir ou en ligne ;
- des **agences partenaires** (B2B) qui revendent à leurs propres clients ;
- des **entreprises et amicales** qui achètent pour leurs employés ou adhérents.

Elle achète ces prestations à des **fournisseurs** : hôtels, compagnies aériennes et maritimes,
loueurs, réceptifs, ou via des **API d'agrégateurs** (type HotelBeds).

Sa marge vient de l'écart entre le prix d'achat et le prix de vente, ou d'une **commission**
versée par le fournisseur.

## Les quatre gestes fondamentaux

**1. Réserver** — un agent (ou le client lui-même dans son espace) enregistre une prestation.
La réservation fige un prix d'achat et un prix de vente, chacun avec sa devise et son taux
de change du jour.

**2. Devoir et payer** — la réservation validée crée une dette : le client doit à l'agence,
l'agence doit au fournisseur. Ces dettes vivent dans un **grand livre** append-only. Un
paiement ne modifie jamais la dette : il ajoute une écriture qui la compense.

**3. Encaisser physiquement** — quand le paiement est en espèces ou en chèque, il transite par
une **caisse**. L'argent y reste jusqu'à sa remise en banque ou sa transmission à un tiers.

**4. Facturer** — un document légal est émis, avec sa numérotation sans trous, sa TVA et son
timbre fiscal.

## Ce qui structure tout le reste

**Un serveur par client.** Chaque agence cliente a sa propre base. Aucune donnée n'est
partagée, aucun identifiant de locataire n'existe nulle part.

**Rien ne se supprime en comptabilité.** Une écriture fausse se corrige par une écriture
inverse, jamais par une modification. Le passé ne bouge pas.

**Le legacy sert de preuve fonctionnelle, jamais de gabarit.** On reprend les besoins réels
qu'il révèle, jamais ses structures.

**Le paramétrage vit en base, le comportement dans le code.** Un taux de TVA, un mode de
règlement ou un type de document sont des données modifiables sans déploiement.

---

## Comment ce dossier se construit

Il se remplit **au fil du balayage module par module** (démarré le 24/07/2026). Chaque module
balayé donne lieu à une confrontation entre le schéma et les écrans réels du legacy ; ce qui
en ressort est écrit ici, pendant que c'est frais.

Les sections marquées **« À compléter »** signalent des zones où la connaissance métier manque.
Elles servent de liste de questions pour le balayage à venir — inutile de tout relire, il
suffit de répondre là où c'est signalé.

**État du balayage** : Party ✅ · les autres modules restent à balayer.
