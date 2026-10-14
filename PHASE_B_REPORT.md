# Informe Fase B — Cierre funcional, integridad documental y hardening

**Fecha:** 2026-08-30  
**Proyecto:** `labor-certification-system`  
**Resultado:** completada para el alcance técnico definido; `SIGN-001` y la oficialización del PDF permanecen deliberadamente abiertas.

## 1. Resultado ejecutivo

Fase B cerró la visualización privada y el lifecycle de soportes, conectó generación/descarga administrativa, hizo efectiva la comprobación SHA-256, alineó contratos frontend/API, reforzó cardinalidades y borrado conservador, acotó consultas, normalizó errores modificados, amplió auditoría y tests, y actualizó dependencias de forma focalizada.

El documento continúa identificado como **“CERTIFICADO LABORAL TEMPORAL”**. No se añadió firma escaneada, electrónica o digital, PKI, X.509, clave privada, TSA, sello de tiempo, proveedor externo, logo/QR oficial ni apariencia jurídica. El SHA-256 es sólo un control de integridad técnica: **no es una firma digital**.

## 2. Indicadores antes/después

| Indicador | Antes de Fase B | Después de Fase B |
| --- | ---: | ---: |
| Laravel | 52 tests / 207 aserciones | **74 tests / 341 aserciones**, 0 fallos |
| React tests | Sin infraestructura | **5 archivos / 8 tests**, 0 fallos |
| React build | Verde | **Verde**, Vite 8.2.2, 2023 módulos |
| ESLint | Verde | **Verde**, 0 errores |
| Composer audit | 23 advisories / 7 paquetes | **0 advisories** |
| npm audit React | 9 vulnerabilidades | **0 vulnerabilidades** |
| npm audit Blade | Resolución no reproducible | **0 vulnerabilidades**, lockfile + `npm ci` |
| Docker | 4 servicios healthy | **4 servicios healthy** tras rebuild/recreate |
| Smoke HTTP | Línea base funcional | `/up` 200, `/auth/me` 401, código inválido 404, React 200 |

El build React conserva un warning no bloqueante por bundle principal de 540.59 kB (gzip 165.16 kB).

## 3. Archivos creados

- `PHASE_B_REPORT.md`.
- `backend/laravel-app/app/Http/Requests/IndexQueryRequest.php`.
- `backend/laravel-app/app/Services/VerificarIntegridadCertificadoService.php`.
- `backend/laravel-app/database/migrations/2026_08_30_000004_add_phase_b_integrity_constraints.php`.
- `backend/laravel-app/tests/Feature/FaseBApiContractTest.php`.
- `backend/laravel-app/tests/Feature/FaseBIntegridadDominioTest.php`.
- `backend/laravel-app/package-lock.json`.
- `frontend/react-app/src/test/setup.ts`.
- `frontend/react-app/src/components/layout/Header.test.tsx`.
- `frontend/react-app/src/features/dashboard/pages/FuncionarioDashboardPage.test.tsx`.
- `frontend/react-app/src/features/solicitudes/pages/SolicitudConfirmacionPage.test.tsx`.
- `frontend/react-app/src/features/validacion-publica/components/ValidationResultCard.test.tsx`.
- `frontend/react-app/src/features/certificados/services/certificados.service.test.ts`.

## 4. Archivos modificados

### Backend

- Controladores: `ActuacionAdministrativaController`, `AuditLogController`, `CargoController`, `CertificadoController`, `FuncionarioController`, `ManualFuncionController`, `PagoSoporteController`, `RangoSalarialController`, `SolicitudCertificacionController` y `ValidacionPublicaController`.
- Requests: `StoreFuncionarioRequest`, `UpdateCargoRequest`, `UpdateFuncionarioRequest` y `UpdateRangoSalarialRequest`.
- Recursos/modelos/policy: `ValidacionCertificadoResource`, `Certificado`, `Funcionario` y `PagoSoportePolicy`.
- Servicios: `PagoSoporteService` y `ResolverSalarioFuncionarioService`.
- Infraestructura API: `bootstrap/app.php` y `routes/api.php`.
- Tests ampliados: `CertificadoValidacionTest`, `ManualFuncionesTest`, `PagoSoporteTest` y `SnapshotCertificadoTest`.
- Dependencias/build: `composer.lock`, `package.json`, `docker/php/Dockerfile` y `DOCKER_SETUP.md`.

### Frontend React

- Generación y detalle de solicitudes: `SolicitudDetailPage.tsx`, `solicitudes.service.ts` y tipos asociados.
- Descarga: `DownloadCertificateButton.tsx`, `CertificadoDetailPage.tsx`, `certificados.service.ts` y tipos.
- Validación pública: componente, página, servicio y DTO del feature.
- Reportes: `ReportsSummary.tsx`, `ReportesPage.tsx` y `reporte.types.ts`.
- CRUD: formularios/servicios de Cargo, Rango Salarial y Funcionario.
- Seguridad/desarrollo: `LoginForm.tsx` y utilidades de errores.
- Testing/build: `package.json`, `package-lock.json`, `vite.config.ts` y `tsconfig.app.json`.

### Documentación

- `AUDIT_REPORT.md` fue reestructurado conservando hechos históricos bajo **ESTADO ORIGINAL**, separando **CORREGIDO EN FASE A** y **ESTADO ACTUAL**.
- `DOCKER_SETUP.md` documenta lockfile Blade y `npm ci`.

## 5. Migración y cambios BD

Se aplicó únicamente la migración incremental `2026_08_30_000004_add_phase_b_integrity_constraints` en batch 3.

Preflight sobre `scl_db`:

- duplicados `funcionarios.user_id`: **0**;
- duplicados `pagos_soportes.solicitud_certificacion_id`: **0**.

Constraints/índices añadidos:

- índice único `funcionarios_user_id_unique`; PostgreSQL conserva el comportamiento natural de permitir varios `NULL` si el diseño los usa;
- índice único `pagos_soportes_solicitud_unique`;
- check `manual_funcion_orden_positivo_check` (`orden > 0`);
- check `certificados_snapshot_completo_check` (schema y datos ambos presentes o ambos ausentes para compatibilidad histórica).

La generación nueva exige adicionalmente ambos campos de snapshot desde el modelo. No se modificó ninguna migración histórica ni se depuraron duplicados automáticamente.

## 6. Endpoints y contratos

- Nuevo `GET /api/v1/pagos/{pago}/archivo`: Sanctum + Policy, storage privado, MIME real, `Content-Disposition` seguro, `nosniff`, auditoría y respuestas 401/403/404 sin path físico.
- React consume `POST /api/v1/solicitudes/{id}/generar-certificado`; no puede forzar `certificado_generado` por cambio genérico de estado.
- React consume el endpoint protegido existente de descarga de certificado como blob autenticado.
- Descarga y validación pública recalculan SHA-256 antes de declarar/servir el documento.
- Validación pública distingue `valido`, `no_encontrado`, `anulado` e `integridad_comprometida`; anulado muestra **CERTIFICADO ANULADO**.
- Reportes conservan `snake_case`; un contract test fija el contrato actual sin ampliar funcionalidad.
- Los listados modificados validan página, máximo `per_page=100`, estados, fechas, búsqueda, orden y dirección mediante allowlists.
- Las APIs modificadas usan envelope coherente para 401/403/404/409/422/429 y un fallback 500 sin stack.

## 7. Lifecycle de soportes

Secuencia implementada: almacenar nuevo → transacción/lock y actualizar BD → commit → intentar eliminar anterior.

- Si falla BD: se elimina el archivo nuevo y se conserva fila/archivo anterior.
- Si falla la eliminación antigua: la fila ya confirmada sigue apuntando al nuevo archivo válido; se registra log y auditoría `eliminar_soporte_anterior_fallido`.
- Tests cubren happy path, fallo BD y fallo de eliminación posterior.

No se movieron soportes a `/public` ni se ejecutaron operaciones fuera de la carpeta del recurso.

## 8. Integridad del certificado y token público

`VerificarIntegridadCertificadoService` comprueba existencia, calcula SHA-256 con `hash_file` y compara mediante `hash_equals`. Una discrepancia registra `integridad_certificado_comprometida` y bloquea descarga/validez; nunca regenera el hash silenciosamente.

La regresión genera un PDF, verifica OK, altera físicamente el archivo y confirma que descarga/validación detectan el cambio. Archivo ausente y certificado anulado también tienen respuestas controladas.

Los tokens nuevos usan `random_bytes(32)`: 256 bits de entropía, representados como 64 caracteres hexadecimales y persistidos sólo como hash. `expires_at` y `used_at` siguen `null`:

- `expires_at` significará una futura caducidad definida institucionalmente;
- `used_at` significará una futura política explícita de primer uso/consumo o trazabilidad.

No se activó expiración arbitraria porque no existe regla institucional de validez pública.

## 9. Frontend

- Generación administrativa: mutation dedicada, loading/error, respuesta con certificado, invalidación de solicitud/listados/certificados y enlace al detalle.
- Descarga: Axios `blob`, Bearer, filename desde `Content-Disposition`, Object URL, click programático y `URL.revokeObjectURL`; maneja 401/403/404/409 sin exponer rutas.
- Validación pública: usa el DTO real Laravel y mensajes funcionales exigidos.
- Reportes: DTO y componentes en `snake_case`.
- Cargo/Rango/Funcionario: mutations reales, validación, errores 422 por campo, loading, invalidación, navegación/reset y éxito.
- Helpers demo: sólo `import.meta.env.DEV`; la búsqueda de cédulas demo en `dist` confirmó `DEMO_CREDENTIALS_ABSENT`.

## 10. Hard delete y cardinalidades

`FuncionarioController@destroy` cuenta, como mínimo, solicitudes, certificados, actuaciones, historial de cargos y pagos. Si hay cualquier relación devuelve 409 funcional y audita `eliminar_funcionario_bloqueado`. No activa cascadas.

Para un funcionario sin historial el hard delete permanece temporalmente permitido y auditado. Esto es una compatibilidad conservadora, no la política final: soft delete, retención y FKs deberán definirse antes de producción.

El dominio confirmó y la BD impone 1 usuario↔1 funcionario y 1 pago lógico↔1 solicitud. Los archivos pueden reemplazarse sobre la misma fila de pago.

## 11. Manual de Funciones, salario y snapshot

- Manual: fechas coherentes, orden positivo y no duplicado por versión/cargo, publicación sólo con funciones e inmutabilidad de versión publicada.
- Se auditan creación/edición/publicación. No se introdujo una regla jurídica adicional.
- CON salario: la generación se detiene con error de dominio 409 ante asignación vigente ausente/ambigua o rango aplicable ausente/ambiguo; no escoge valores arbitrarios.
- SIN salario: no invoca resolución salarial.
- Se conserva la fecha de generación como fecha temporal de resolución salarial, según la decisión vigente.
- Todo certificado nuevo requiere snapshot versión/datos; el modelo bloquea su modificación posterior.

## 12. Auditoría

Las acciones críticas revisadas registran usuario, acción, modelo/recurso, ID y timestamp. El modelo existente también registra IP, user agent y metadata con valores anteriores/nuevos cuando están disponibles.

Cobertura añadida/reforzada: cambio de estado, aprobar/rechazar, generación exitosa/fallida, descarga, anulación, visualización de soporte, fallo de lifecycle, integridad comprometida, edición/publicación de Manual, cargo, rango, funcionario y eliminación bloqueada/permitida sin historial.

No se implementó SIEM ni retención inmutable; `AUDIT-001` queda parcialmente abierto.

## 13. Dependencias PHP

Actualización focalizada, sin update masivo indiscriminado:

- `laravel/framework` 13.11.2 → 13.29.0;
- `guzzlehttp/guzzle` 7.10.3 → 8.1.0;
- `guzzlehttp/psr7` 2.10.1 → 3.1.0;
- `league/commonmark` 2.8.2 → 2.10.0;
- componentes Symfony afectados 8.0.x → 8.1.5;
- transitivas compatibles necesarias quedaron fijadas en `composer.lock`.

Resultados: `composer validate --strict --no-check-publish` correcto, `composer check-platform-reqs` correcto, `composer audit --locked` sin advisories y Laravel completo verde.

## 14. Dependencias npm y Blade

Actualización focalizada: Axios 1.20.0, React Router/DOM 7.18.3, Vite 8.2.2 y PostCSS 8.5.26, más transitivas compatibles fijadas. No se usó `npm audit fix --force`.

React: `npm ci`, `npm audit`, tests, build y lint correctos; 0 vulnerabilidades. Blade: nuevo `package-lock.json`, Vite 8.2.2, 0 vulnerabilidades. El build Docker ejecutó efectivamente `npm ci` y compiló assets Blade.

## 15. Tests backend añadidos/ampliados

- Acceso privado a soporte: admin, secretario, 403, 401, 404, MIME/Disposition/nosniff, auditoría y ausencia de path.
- Lifecycle: reemplazo exitoso, rollback BD y fallo de eliminación antigua.
- Integridad: token 256 bits, tamper, archivo ausente, anulación y respuesta pública.
- Hard delete/cardinalidades/salario/snapshot.
- Paginación, filtros, allowlist de orden, reporte snake_case y no stack.
- Manual de Funciones: fechas, orden, publicación e inmutabilidad.

Resultado final: **74 tests, 341 aserciones, 0 fallos** sobre `scl_db_test`.

## 16. Tests frontend y E2E

Vitest + React Testing Library quedó configurado de forma mínima. Los 8 tests cubren:

1. dashboard muestra CON/SIN salario;
2. sólo se bloquea la modalidad consumida;
3. confirmación muestra radicado real;
4. logout llama API;
5. validación válida;
6. validación inválida;
7. validación anulada;
8. descarga usa endpoint autenticado y revoca Object URL.

Playwright se documenta como pendiente. Incorporarlo ahora requería servidor E2E, usuarios/fixtures y estrategia de aislamiento adicionales, desproporcionados frente al smoke local y tests de alto valor ya añadidos. No se usaron datos de producción.

## 17. Hallazgos corregidos y parciales

Corregidos/cerrados técnicamente: `FILE-001`, `FILE-002`, `CERT-002`, `CERT-004`, `CERT-007`, `FRONT-001`, `FRONT-002`, `FRONT-003`, `AUTH-005`, `BACK-002`, cardinalidades de `DB-003`, actualización `DEP-001/DEP-002` y riesgo aplicativo inmediato `DB-001`.

Parciales: `BACK-003` (APIs modificadas), `AUDIT-001` (acciones críticas, sin SIEM/retención), `CERT-005` (fail-safe pero fecha institucional pendiente), `CERT-006` (entropía sí, política de expiración/uso pendiente), testing E2E y hard delete final/soft delete.

## 18. Pendientes y riesgos

- `SIGN-001` y `CERT-001`: firma, acto, plantilla y validez oficial; prohibido considerar cerrado.
- Política jurídica/institucional de fecha salarial, firmante, vigencia, expiración/uso del token y datos públicos.
- Storage inmutable/versionado, antivirus, reconciliación y retención.
- Bearer en `localStorage`, expiración Sanctum y CSP/CORS/infraestructura productiva.
- Soft delete/FKs/retención de expediente; el hard delete sin historial es temporal.
- Scope organizacional de admin/secretario.
- Paginación visual, E2E, code splitting y bundle >500 kB.
- `PdfBasicoService` y la plantilla actual no son aptos para certificación oficial.

## 19. Confirmación de no destrucción de datos

No se ejecutaron `migrate:fresh`, `db:wipe`, `docker compose down -v`, `TRUNCATE`, eliminación masiva, borrado de volúmenes ni equivalentes. Se usó `scl_db_test` para pruebas y una sola migración incremental sobre `scl_db`, precedida por comprobación de duplicados. Docker se reconstruyó/recreó sin eliminar volúmenes. No hubo pérdida de datos.

