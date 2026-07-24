# Décision — Migrations SQL atomiques (BEGIN/COMMIT)

**Date :** 2026-07-24  
**Statut :** adopté  
**Tag :** (architecte DB, sur constat de vérification du commit e20b21d)

## Contexte

La migration `Version20260724140000` (§64, codes référentiels FR→EN) a
désactivé `trg_settlement_ledger_no_mutation` pour réécrire
`settlement_ledger_entry.party_role`, puis l’a réactivé. Elle s’exécute
via `execSqlFile` (PDO multi-instructions) avec `isTransactional() = false`.

Rien n’imposait ce mode non transactionnel (pas de `CONCURRENTLY`,
`VACUUM`, ni autre ordre incompatible). Un échec entre le `DISABLE` et
le `ENABLE` aurait laissé le trigger append-only **définitivement
désactivé** — perte silencieuse de l’invariant du grand livre, sans
signal d’alarme.

La migration a réussi et le trigger est confirmé `ACTIF` (tgenabled =
`'O'`) ; la règle ci-dessous vise les migrations **suivantes**.

## Décision

1. **Toute migration** exécutant du SQL multi-instructions via
   `execSqlFile` **DOIT** encadrer son SQL par `BEGIN; … COMMIT;`, sauf
   ordre techniquement incompatible (`CONCURRENTLY`, `VACUUM`, …). Dans
   ce cas, justifier explicitement l’exception dans le journal de la
   tâche.

2. **Toute migration qui DÉSACTIVE un trigger** doit impérativement être
   atomique. Un trigger porteur d’un invariant (append-only, garde-fou
   financier) resté désactivé après un échec partiel est une perte
   silencieuse d’intégrité — rien ne la signale.

3. **Alternative préférable** quand c’est possible : éviter le
   `DISABLE` persistant au niveau table. Préférer, dans la transaction :
   - modifier temporairement la condition du trigger, ou
   - `SET LOCAL session_replication_role = replica`
     (effets limités à la transaction ; pas d’état table laissé ouvert).

## Hors périmètre

Ne **pas** réécrire rétroactivement `Version20260724140000` (migration
déjà appliquée = fait daté). La règle s’applique aux migrations à venir.

## Conséquence

Revue / checklist migration : présence de `BEGIN`/`COMMIT` (ou
justification d’exception) ; si `DISABLE TRIGGER`, atomique obligatoire
+ vérification runtime `tgenabled = 'O'` après application.
