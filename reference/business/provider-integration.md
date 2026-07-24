# Métier — Provider Integration (connexions fournisseurs)

**Pas encore balayé.** Compilé depuis `modele-conceptuel-provider-integration.md`.

---

## Ce que le module décrit

Comment l'agence se connecte aux systèmes de ses fournisseurs pour interroger leurs
disponibilités et leurs tarifs — typiquement des agrégateurs comme HotelBeds.

## Comment le métier fonctionne réellement

### Le modèle est celui d'une boutique d'extensions

**Le client ne saisit jamais un contrat technique.** Il **installe** un fournisseur depuis un
catalogue, exactement comme on installe une extension WordPress. Il a signé un accord
commercial en amont, hors du système, puis ne renseigne que ses identifiants.

Ce que le fournisseur exige — quels champs, quels formats, quelle aide à la saisie — vit dans
un **manifeste détenu par OctaSoft**, produit séparé. Le système ne stocke que les valeurs
concrètes saisies par le client, validées contre ce manifeste.

C'est ce qui explique un choix de conception à première vue surprenant : les identifiants et
la configuration sont stockés sans structure figée en base. Ce n'est pas un fourre-tout — la
structure existe, elle vit simplement ailleurs, chez le fournisseur du manifeste. Chaque
extension apporte ses propres champs ; les figer en colonnes serait impossible.

### Ce qui est construit et ce qui ne l'est pas

Seule l'**API entrante** est conçue : l'agence interroge ses fournisseurs. L'API sortante —
d'autres systèmes interrogeant l'agence — et la connexion aux gestionnaires de canaux sont
explicitement reportées, l'implémentation de l'existant représentant déjà plusieurs mois.

### Les journaux d'appels

Chaque interrogation d'un fournisseur est tracée. C'est la table au volume le plus
imprévisible du système. Un journal rattaché à une réservation ne doit **jamais** être
supprimé ; seuls les appels n'ayant pas abouti sont purgés après un mois — décision explicitement
marquée comme révisable.

---

## À compléter

- **⚠️ La devise imposée par la source** *(point ouvert, délocalisé depuis Party le 24/07)* —
  un produit acheté à un fournisseur qui facture en dollars ne devrait être revendable qu'en
  dollars, et le client doit en être averti dans son espace. **Rien ne porte cette contrainte
  aujourd'hui** : ni la connexion fournisseur ni le catalogue n'ont de devise. Besoin nouveau,
  pas une reprise du legacy.
- **Le parcours d'installation** : que voit le client, quelles étapes ?
- **Que se passe-t-il quand un fournisseur répond mal** — indisponible, lent, réponse
  incohérente ? Bascule-t-on sur une autre source ?
- **La gestion des licences** (§58) : la base contiendra les modules actifs et leurs dates
  d'expiration, mais la couche commerciale est une application séparée.
