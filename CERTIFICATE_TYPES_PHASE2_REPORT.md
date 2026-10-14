# Certificate Types — Phase 2 Report

Date: 2026-09-23

Branch: `feat/certificate-types`

Starting Phase 1 commit: `b1423bf672f15a5170991caaf123027057a16ec3`

Functional baseline and merge-base: `79720315bb36f8a00ed9d42718173d642e75bd1a`

## 1. Precheck and publication

The worktree started clean on `feat/certificate-types` at the required Phase 1 commit. The branch was published to `origin` with a normal, non-forced push before Phase 2 source changes. The remote ref was verified at `b1423bf672f15a5170991caaf123027057a16ec3`.

Only the CT stack and its reserved databases were used. Environment A (`3000/8080`), recovery (`3100/8180`), ordinary databases and recovery databases were not used.

## 2. E2E CT migration

`scl_certificate_types_e2e` was advanced with normal Laravel migrations through `2026_09_23_000001_replace_salary_modality_with_certificate_types`. The schema verification confirmed:

- `requiere_salario` is absent;
- `rangos_salariales` is absent;
- `tipo_certificado` accepts only `sencillo | funciones`;
- the monthly UNIQUE uses employee, month and certificate type.

`migrate:fresh` was used later only on the expressly isolated `scl_certificate_types_e2e` database to make the real Playwright fixtures deterministic.

## 3. Frontend inventory and removal

The case-insensitive inventory covered salary words, the removed boolean, the salary range module, CON/SIN salary copy and legacy `laboral` type usage.

Removed from active frontend code:

- the complete `features/rangos-salariales` module (page, form, table, schema, services and types);
- its lazy import and router entry;
- its sidebar entry and icon import;
- its API endpoint constant;
- the unused quick-actions component containing salary administration;
- the active salary-domain error message;
- the active README route reference.

The final active-source search has zero functional salary references. Remaining frontend matches are negative tests that prove salary is absent. Files ending in `-camilo_ortega` outside the deleted salary module remain historical recovery evidence and are excluded from the TypeScript build by `tsconfig.app.json`.

## 4. Routes and navigation

The salary route has no import, lazy chunk, router entry, redirect or navigation link. Final administrative navigation is:

- Dashboard;
- Funcionarios;
- Cargos;
- Manual de Funciones;
- Certificaciones;
- Configuración;
- Auditoría;
- Reportes.

The existing automatic issuance flow remains; no legacy manual-approval page was restored.

## 5. Canonical TypeScript contract

The only active type is:

```ts
type TipoCertificado = 'sencillo' | 'funciones'
```

Human labels and descriptions are centralized in `features/solicitudes/utils/certificateType.ts`. Components no longer duplicate internal values as user-facing copy and no parallel `incluyeFunciones` boolean was introduced.

The request payload contains `tipo_certificado` and optional existing observations only. It never sends `requiere_salario`, `funcionario_id`, `manual_ficha_id` or a `tipo` alias.

## 6. Employee self-service

The dashboard and request flow expose only:

- **Certificado laboral sencillo** — “Constancia de vinculación laboral y datos del empleo.”
- **Certificado laboral con funciones** — “Incluye la información laboral y las funciones correspondientes al empleo según el Manual aplicable.”

Each option consumes its own `puede_solicitar` and `proxima_fecha_disponible` values. One blocked type does not disable the other. Selection uses native radio semantics, clear descriptions, visible focus and textual disabled/availability explanations. The employee never chooses a Manual profile or individual function.

The real confirmation shows filing number, human certificate label, date, status and the immediate PDF action when applicable.

## 7. Normative errors

The frontend maps missing, incomplete, incompatible, ambiguous and non-current Manual-source errors. Manual/profile explanations are scoped to the functions certificate; the simple path substitutes a general employment-validation message if an incompatible backend ever returns a Manual error.

No salary error or invented salary guidance remains.

## 8. Administrative certificates and dashboard

The dashboard distinguishes simple and functions certificates and contains no salary card, quick action or indicator.

Certificate administration now includes:

- filters `Todos`, `Sencillo`, `Con funciones`;
- a type column with human labels;
- the type on certificate detail;
- the explicit label `Registro histórico de modelo anterior` for records without a canonical request or with a pre-v3 snapshot.

The frontend does not render salary fields from new certificates and does not rewrite legacy snapshots.

## 9. Public validation

The minimal public backend contract now includes `tipo_certificado` only for snapshot schema v3 or newer. The public frontend renders the human label and keeps the existing minimization boundary: no full snapshot, salary data or function list is exposed.

Legacy records without a canonical type are identified as `Registro histórico de modelo anterior`.

## 10. Reports and audit

Reports continue to render only metrics delivered by the existing backend summary. No unsupported breakdown or synthetic indicator was invented, and no salary metric remains.

Audit structure was not changed. Visible metadata is now allow-listed instead of dumping every primitive payload. `tipo_certificado` is translated through the central label helper, while internal or unnecessary payload fields remain hidden.

## 11. Configuration and employee administration

`requiere_pago_certificado` remains a single independent configuration. No price per type, fee schedule or salary setting was added.

Employee creation still requires an explicit compatible Manual profile. Certificate type and employment profile remain separate concepts.

## 12. Session-cache regression

The baseline `/auth/me` fix remains intact. Tests verify:

- logout clears identity-dependent TanStack Query data, including certificate availability;
- a 401 clears storage and query cache;
- a 403 preserves the session and cache;
- employee → logout → admin login revalidates `/auth/me` and reaches the correct dashboard.

## 13. Frontend tests

Vitest result: **30/30** across 13 files.

The suite covers both dashboard choices, absence of salary copy, independent availability, both canonical payloads, human confirmation labels, public validation labels and minimization, first-access administration and logout/401/403 cache behaviour. Navigation, reports and the absence of salary UI are also exercised by real Playwright routes.

Quality gates:

- ESLint: pass;
- TypeScript: pass;
- production build: pass.

## 14. Backend regression and contract tests

Backend result: **139 tests / 742 assertions**, all passing.

The three additional assertions verify that public validation returns the canonical simple type while omitting salary, full snapshots and function lists. The rest of Phase 1 remains green, including canonical payload validation, monthly quotas, PostgreSQL concurrency defence, snapshots and Manual rules.

## 15. Real Playwright E2E

All requests use the actual CT API and PostgreSQL database; there are no HTTP mocks.

The final suite contains seven scenarios:

1. admin login, `/auth/me`, 36 permissions, dashboard and active modules, with no salary module;
2. admin creates an employee, first-login password change, selects simple, real HTTP 201, real confirmation and `%PDF-` download;
3. a complete assigned profile produces a functions certificate whose snapshot contains `E2E-001`, a known function and a real `%PDF-`;
4. a repeated same-type request returns 409 while the opposite type succeeds;
5. employee logout followed by admin login proves identity/cache isolation;
6. an incomplete assigned profile permits simple but rejects functions with `MANUAL_FICHA_INCOMPLETA`;
7. critical employee and admin screens remain navigable without page overflow at 360 px and 768 px.

Final Playwright result: **7/7**.

## 16. Visual evidence

Seventeen real CT screenshots are stored in `frontend/react-app/evidence/certificate-types-phase2/`:

1. login;
2. employee dashboard;
3. simple request;
4. functions request;
5. real confirmation;
6. admin dashboard;
7. employees;
8. employee creation;
9. employee detail;
10. positions;
11. Manual;
12. certificates;
13. certificate detail;
14. configuration;
15. audit;
16. reports;
17. public validation controlled state.

They were generated by Playwright against real CT services and visually inspected after generation. They live outside `src` and are not included in the production bundle.

## 17. Final search and route inventory

The active frontend contains zero functional matches for salary, salary ranges or `requiere_salario`. Accepted matches are only negative test assertions. Historical recovery copies, migrations, legacy immutable snapshots and historical reports are not active runtime code and were not rewritten.

The production build emits no salary-range lazy chunk. Route inventory contains no frontend or backend salary route.

## 18. Manual and permissions

The full backend regression includes the reconciliation assertions for exactly:

- 344 profiles;
- 3,115 functions;
- 2,326 knowledge entries.

The E2E `/auth/me` contract confirms the admin has all **36** current permissions. No Manual content or permission matrix was modified by Phase 2.

## 19. Phase 3 scope still pending

The current PDF remains technical and functional. Phase 2 deliberately does not implement:

- definitive institutional certificate design;
- final letterhead or multipage composition;
- electronic/digital signature;
- official QR;
- legal/institutional officialization.

## 20. Git result

The requested commit message is:

`feat(frontend): align certificate UX with canonical types`

The baseline merge-base remains `79720315bb36f8a00ed9d42718173d642e75bd1a`. No environment file, dump, private storage, generated PDF, token, secret, dependency directory or production bundle is part of the change set.
