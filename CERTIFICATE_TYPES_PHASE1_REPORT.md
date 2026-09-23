# Certificate Types — Phase 1 Report

Date: 2026-09-23

Branch: `feat/certificate-types`

Starting HEAD: `2573b3b52f0380d760f22ad979a3470c79010714`

Functional baseline and merge-base: `79720315bb36f8a00ed9d42718173d642e75bd1a`

## 1. Preflight CT

Git preflight matched the required state: the branch was `feat/certificate-types`, HEAD was `2573b3b52f0380d760f22ad979a3470c79010714`, the merge-base was `79720315bb36f8a00ed9d42718173d642e75bd1a`, and the worktree was clean.

Only the three reserved CT databases were checked. `scl_certificate_types_dev`, `scl_certificate_types_test`, and `scl_certificate_types_e2e` initially contained zero tables. The baseline migrations were then applied only to those databases as required for the preflight.

`docker-compose.certificate-types.yml` was the only feature Compose stack started. Its mounts resolved to `C:\Users\mondr\source\labor-certification-recovery`; application storage, Composer dependencies, frontend dependencies, build output, test storage, and E2E storage use named `scl_ct_*` volumes. No mount points to environment A. The CT endpoints are `http://localhost:3200` and `http://localhost:8280`.

The unchanged baseline reproduced in CT before implementation:

- backend: 142 tests, 761 assertions;
- frontend: 26/26;
- Playwright: 3/3;
- ESLint: pass;
- TypeScript: pass;
- production build: pass.

No source changes were made until those results were obtained. Environment A (`localhost:3000` / `localhost:8080`) and recovery (`localhost:3100` / `localhost:8180`) were not used for development or testing.

## 2. Salary-domain inventory

The inventory was made with a case-insensitive global search and then classified by actual use instead of deleting by text match.

| Match | Layer | Previous active use | Phase action |
|---|---|---|---|
| `solicitudes_certificacion.requiere_salario` | DB / model / API | Monthly modality, request payload and resource | Phase 1: removed; legacy payload is explicitly rejected |
| `funcionario_cargo.salario_override` | DB / model | Exceptional salary source for certificate issuance | Phase 1: removed |
| `rangos_salariales` | DB | Salary source used only by certificate issuance and its CRUD | Phase 1: table removed by the incremental migration |
| `RangoSalarial` and factory | model / tests | Active salary catalogue | Phase 1: removed |
| salary controller, requests and resource | backend / API | `/rangos-salariales` CRUD | Phase 1: removed; no dead route or HTTP 410 compatibility facade remains |
| `ResolverSalarioFuncionarioService` | backend | Salary resolution for issuance and snapshot | Phase 1: removed |
| `SALARIO_NO_RESOLUBLE` and salary-source failures | backend | Blocked issuance when salary could not be resolved | Phase 1: removed with the resolver |
| `rangos_salariales.ver/crear/editar` | permissions | Admin and secretary salary administration | Phase 1: removed |
| salary fields in new snapshots/PDF input | snapshot / PDF | Printed or exposed the resolved salary | Phase 1: removed from schema v3; minimal PDF adaptation only |
| salary choices in employee request/dashboard | frontend | Selected CON/SIN salary | Phase 1 minimum contract adaptation: replaced by `sencillo` / `funciones` |
| salary administration page, navigation and E2E visual flows | frontend / E2E | Administrative salary UI | Phase 2 debt, intentionally not redesigned or removed in this phase; its backend endpoint no longer exists |
| migrations before 2026-09-23 and `*-camilo_ortega` files | historical | Schema history and recovery evidence | Preserved as historical evidence; not active runtime code |
| snapshot schema v2 in historical certificates | historical data | Immutable legacy salary-era representation | Preserved without rewriting |

No other institutional purpose for `rangos_salariales` was found outside this certificate flow.

## 3. Schema before and after

The baseline schema was inspected directly in `scl_certificate_types_dev`.

Before:

- `solicitudes_certificacion.tipo_certificado` was a varchar without a canonical type check;
- `solicitudes_certificacion.requiere_salario` was boolean;
- monthly uniqueness was `(funcionario_id, periodo_mes, requiere_salario)`;
- `funcionario_cargo.salario_override` was nullable decimal;
- `rangos_salariales` contained code, grade, year, salary, currency, observations, state and audit users;
- certificates already stored immutable, versioned snapshots;
- the Manual used explicit assignment and normative-relation tables.

After:

- `tipo_certificado` is the only modality source of truth;
- the DB check accepts only `sencillo` and `funciones`;
- `requiere_salario` and `salario_override` no longer exist;
- `rangos_salariales` no longer exists;
- monthly uniqueness is `(funcionario_id, periodo_mes, tipo_certificado)`;
- Manual, assignment, lineage, certificate and snapshot foreign keys remain intact.

## 4. Incremental migration

New migration: `2026_09_23_000001_replace_salary_modality_with_certificate_types.php`.

It was designed from the inspected schema. It does not copy or apply the failed historical migration `2026_09_21_000001_consolidate_certificate_type.php` or the change set `tipo-certificado-incompleto`.

Before changing data, the migration rejects unsupported type values and collisions that would violate the canonical monthly key. It then drops the old modality constraint, normalizes known values, installs the type check and new UNIQUE, removes certificate-only salary columns/table, and removes the three salary permissions. It does not infer certificate type from `requiere_salario`.

## 5. Data normalization

The only mapping is:

- `laboral` → `sencillo`;
- `funciones` → `funciones`.

A controlled CT clone with one synthetic legacy `laboral` row validated the mapping: after the migration it contained one `sencillo` and two unchanged `funciones` rows. Setting `requiere_salario=true` on that synthetic row did not change the mapping.

The migration refuses an automatic rollback because dropped salary catalogue data and the discarded boolean dimension cannot be reconstructed without invention; rollback requires restoring the pre-migration backup.

## 6. Constraints and concurrency

PostgreSQL contains:

- `solicitudes_tipo_certificado_check` for `sencillo | funciones`;
- `solicitudes_funcionario_periodo_tipo_unique` for employee + calendar month + type;
- the existing first-day-of-month check for `periodo_mes`.

Issuance also takes a transaction-scoped PostgreSQL advisory lock keyed by employee, Bogotá calendar month and certificate type. A real two-request HTTP race against CT API returned exactly `201,409`. The UNIQUE remains the final database defence.

## 7. Backend removed

The active salary model, controller, store/update requests, resource, factory, resolver service, routes and salary-specific feature test were removed. Seeders no longer create salary catalogue data. Active backend search results now contain salary terms only in negative assertions and the deliberate validation rule that rejects `requiere_salario`.

## 8. Permissions removed

Removed:

- `rangos_salariales.ver`;
- `rangos_salariales.crear`;
- `rangos_salariales.editar`.

The resulting matrix has 36 current permissions. Admin has all 36, secretary retains 16, and funcionario retains its single self-service permission with no administrative access.

## 9. API contracts

`POST /api/v1/solicitudes` accepts only:

```json
{"tipo_certificado":"sencillo"}
```

or:

```json
{"tipo_certificado":"funciones"}
```

`requiere_salario`, the old `tipo` alias, employee IDs and manual-ficha IDs are not accepted. A request that sends `requiere_salario` receives HTTP 422 so stale clients are detected.

## 10. Availability

`GET /mi-certificacion/disponibilidad` preserves the existing envelope and now returns `periodo`, `sencillo`, and `funciones`. Each type includes `puede_solicitar` and `proxima_fecha_disponible`. CON/SIN salary keys are absent.

## 11. Issuance service

`ExpedirCertificacionService` now coordinates authenticated identity, active state, one valid and unambiguous employment assignment, canonical type, type-specific validation, monthly quota, filing, optional payment, snapshot and generation.

`sencillo` validates the employment assignment but never calls the function resolver. `funciones` resolves the assigned normative profile and fails safely for missing, incompatible, incomplete, unpublished or ambiguous Manual sources. No salary resolver or salary source participates in issuance.

## 12. Snapshot

New certificates use snapshot schema version 3.

Both types contain employee, employment assignment, position, dependency, dates, employment nature, certificate type and generation metadata. Only `funciones` includes `manual_funciones`, with Manual/version/profile/source, functional area, purpose and functions. New snapshots contain no salary, range, `requiere_salario` or salary source.

Historical schema-v2 snapshots were not rewritten.

## 13. Minimal PDF adaptation

The existing PDF template was changed only enough to consume snapshot v3 and stop depending on salary fields. It prints the canonical certificate type and keeps the baseline generation path operational. There was no institutional redesign, new letterhead, final multipage work, signature or official QR work.

## 14. Tests

Final Phase 1 results:

- backend: 139 tests, 739 assertions, all passing;
- frontend: 26/26, all passing;
- ESLint: pass;
- TypeScript: pass;
- production build: pass;
- PHP syntax scan: pass;
- salary route listing: no matching routes;
- real same-type concurrency smoke test: HTTP `201,409`.

Coverage includes both certificate types, no function resolution for `sencillo`, assigned/incomplete/missing Manual behaviour for `funciones`, independent monthly quotas, repeat 409s, database collision defence, legacy payload 422, absence of active salary resolution/routes/permissions, v3 snapshot contents and Manual preservation.

E2E remained reserved for Phase 2/3 after the successful baseline preflight and `scl_certificate_types_e2e` was not advanced to the Phase 1 schema.

## 15. Fresh install

A new temporary database `scl_certificate_types_fresh_phase1` started empty and ran every migration through `2026_09_23_000001_replace_salary_modality_with_certificate_types`. The final schema had the canonical check and UNIQUE and had no `requiere_salario`, `salario_override`, or `rangos_salariales`.

CT dev was also rebuilt from zero with the normal seeders after implementation; it produced 36 permissions and remained isolated from all ordinary/recovery databases.

## 16. Real upgrade

`scl_certificate_types_upgrade_phase1` was created from a read-only dump stream of `scl_recovery_db`; the dump was restored directly through a pipe and no dump file was written to the repository. Its latest migration was the baseline `2026_09_05_000001_add_manual_versioning_workflow`. Running Artisan applied only the new 2026-09-23 migration.

After upgrade:

- existing types were valid and unchanged where already `funciones`;
- the new constraints were present;
- all three assignments and all three normative relationships remained;
- all three historical certificates remained;
- no unauthorized row loss was detected;
- permissions were 36 with zero salary permissions.

The fresh, upgrade and mapping verification databases were dropped after their evidence was recorded. The three reserved CT databases were retained.

## 17. Manual integrity

The upgraded baseline clone still contained Manual version ID 10, `Decreto 1000-24/015 de 2023`, state `publicado`, with exactly:

- 344 profiles;
- 3,115 functions;
- 2,326 knowledge entries.

No Manual content was modified by the migration.

## 18. Historical data preserved

Certificates 31, 32 and 33 retained snapshot schema version 2 and their exact original PDF paths and hashes:

- 31: `6413e2278dc2a65c6aefa4691a8040ae625c5020223cf30679ef6877ae80e404`;
- 32: `3768d7e1de1a8cd5b57ba3ba7ec7e444bf08d0db7b5f7552cc81ea0559e0ea6a`;
- 33: `037dc0d4381d8b4750c91933a8d3a9cecba67c513916b1fc0f0c57b47b877412`.

Their absent binaries were not regenerated, deleted or modified. Any salary data inside their immutable legacy snapshots remains historical evidence and is not emitted by the new flow.

## 19. Phase 2 and Phase 3 debt

Phase 2 must remove the now-inert frontend salary administration page, route/navigation/quick action, update the visual reporting surfaces, and rewrite the Playwright flows/screenshots around the two canonical types. Phase 3 owns the definitive institutional PDF, multipage layout, letterhead, signing and official QR.

The current task deliberately did not perform either redesign.

## 20. Git and scope result

The baseline merge-base remains `79720315bb36f8a00ed9d42718173d642e75bd1a`. The baseline branch/tag, environment A, recovery services/databases, ordinary databases, AuthProvider and `/auth/me` session-cache fix were not modified. No PDF binary, private storage, dump, dependency directory, environment file, token or secret is part of the change set.

The Phase 1 commit message is:

`feat(certificates): replace salary modality with certificate types`
