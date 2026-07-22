# Journal — 2026-07-21 — Fondation observabilité

## Faits

- `App\Shared\Domain\Exception\DomainException` : base abstraite (`context()`, `errorCode()`).
- Party : `InvalidEmailException` / `InvalidPartyAccountStateException` héritent de cette base ; factories enrichies.
- Monolog Bundle installé ; logs JSON dans `var/log/` (tous environnements).
- `CorrelationIdHolder` + `RequestIdProcessor` + `RequestIdSubscriber` (`X-Request-Id`).
- `DomainExceptionProcessor` : `error_code` + `domain_context` dans `extra` Monolog.
- Deptrac : couche `Monolog` autorisée depuis `Infrastructure`.
- Décision : différer Sentry/GlitchTip (hors périmètre OsTravel seul).

## Note

Vérification HTTP complète (premier Controller) reportée ; tests unitaires processors +
intégration Kernel (écriture JSON) en place.
