# Front jetable — validation API OsTravel

**Code jetable.** À supprimer une fois le vrai front construit. Aucune qualité durable, pas de design, pas de TypeScript.

## Lancer

```bash
cd front-jetable
npm install
npm run dev
```

Ouvre l’URL affichée (souvent `http://localhost:5173`). L’API attendue est `http://127.0.0.1:8080/api/v1` (constante `API_BASE` dans `src/api.js`).

## Token en localStorage

Le JWT est stocké dans `localStorage` pour survivre à un refresh de page. **Ce n’est pas une pratique à reproduire** dans le vrai front (session httpOnly / stratégie auth propre).

## Stack volontairement minimale

React 18 + Vite (JS), `fetch` natif, état local, CSS basique. Pas de router, pas de state manager, pas de kit UI.
