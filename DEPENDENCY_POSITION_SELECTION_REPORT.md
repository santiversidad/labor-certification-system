# Selección de dependencia y cargo: entrega

5 de octubre de 2026. Repositorio canónico: `C:/Users/mondr/source/labor-certification-recovery`.

1. **Rama:** `feat/dependency-scoped-position-selection`. El precheck encontró el árbol limpio en `feat/institutional-brand-assets`, HEAD `4975944`; se revisaron los últimos 15 commits antes de crear la rama solicitada. No se usó reset, clean ni checkout para descartar trabajo.
2. **Commit:** entrega local con mensaje `feat(employees): scope positions by organizational unit`. El hash exacto se informa en la respuesta final y se obtiene con `git log -1 --format=%H` en esta rama. No se realizó push.
3. **Modelo anterior:** cargos genéricos; perfiles en `manual_cargo_versiones`; hechos laborales en `funcionario_cargo`; intervalos normativos en `funcionario_cargo_manual_fichas`. Dependencia textual en cargos/funcionarios y dependencia/área fuente en fichas. El formulario pedía una ficha técnica y consultaba cargos generales.
4. **Catálogo preexistente:** no había catálogo normalizado ni relación estructurada de unidades. Se reutilizan los ámbitos preservados del Manual mediante una proyección plana, sin jerarquía inferida.
5. **Tablas:** ninguna nueva. Migración aditiva de clave nullable/indexada en `funcionario_cargo`. Aplicada incrementalmente en `scl_certificate_types_dev`, `scl_certificate_types_test` y `scl_certificate_types_e2e`; sin backfill ni cambio de vinculaciones existentes.
6. **Origen:** 73 ámbitos concretos derivados de 335 fichas de la versión 10. Ocho perfiles con aplicabilidad explícita `Todas las dependencias` se ofrecen dentro de esas unidades; no generan una unidad ficticia. MF-0167 sigue sin mapear porque no tiene ámbito fiable. Se preservan originales y trazabilidad; solo se normalizan espacios, mayúsculas y guiones para las claves.
7. **Relación:** unidad + cargo + fecha + estado/intervalo del Manual → exactamente una ficha → relación normativa de la vinculación. Los perfiles transversales conservan la unidad elegida en el hecho laboral. El certificado con funciones utiliza la relación normativa exacta, sin buscar una ficha al azar.
8. **API:** tres GET administrativos bajo `/api/v1/manual-funciones/dependencias`: catálogo, `/cargos` y `/resolver`. Fecha requerida; búsqueda por nombre para unidades y denominación/código/grado para cargos; paginación de cargos y Resources con envelope actual. Create/update de funcionarios reciben IDs de unidad/cargo. La consulta legacy de fichas sigue disponible, pero el formulario no la usa.
9. **Validación:** permisos administrativos y middleware 2.9 existentes; cargo activo, unidad concreta aplicable, versión publicada y fecha válida. Rechazo de cero/múltiples candidatas y de IDs de ficha incompatibles, incluso cuando el cliente intenta resolver la ambigüedad con un ID. El texto de dependencia no se acepta como selección ni se persiste desde el cliente; la ficha/unidad se excluyen del mass assignment de `Funcionario`.
10. **Cambio de dependencia:** limpia inmediatamente cargo, búsqueda y confirmación del perfil. Respuestas tardías del perfil anterior no restauran la selección. El cargo inicia deshabilitado y solo consulta opciones de la unidad elegida, sin cargar funciones completas.
11. **Edición:** precarga unidad/cargo/perfil registrado. Un cambio exclusivamente personal omite selección laboral del payload y conserva la relación normativa. Refocus de ventana no reescribe el formulario con una consulta de detalle nueva. La ficha se muestra solo para confirmación; no existe tercer selector técnico en este flujo.
12. **Históricos:** sin backfill ni inferencia por denominación/texto libre. La unidad legacy se lee de la ficha exacta cuando puede identificarse; el texto histórico se muestra explícitamente. Si no hay ámbito fiable, se conserva y se requiere selección administrativa explícita para cambiar la vinculación. Un cambio laboral crea otro hecho desde hoy, conserva el anterior hasta ayer y mantiene sus relaciones; sigue protegido el cambio en el mismo día.
13. **Backend:** 286 pruebas completas, 1.680 aserciones, todas pasan. Base aislada `scl_certificate_types_test`, transacciones y bootstrap que prohíbe migraciones destructivas. Incluye trece pruebas nuevas para dependencias, perfiles similares, búsquedas, manipulación, ambigüedad específica/transversal, fechas, estado, cambios, legacy, sencillo incompleto y autorización de autoservicio. También se ejecutaron las regresiones previas de Manual, certificados, PDF, identidad y seguridad.
14. **Frontend:** 99 pruebas en 22 archivos; ESLint, TypeScript y build Vite pasan. Incluye selector cerrado y búsqueda, cargo deshabilitado/habilitado, consultas por unidad/fecha, limpieza y respuesta tardía, loading/empty/error/reintento, precarga, validación y payload de IDs sin ficha técnica.
15. **E2E:** 37 pruebas completas, todas pasan. Fixtures exclusivamente sintéticos en `scl_certificate_types_e2e`. Los casos nuevos recorren alta, filtrado, búsqueda, cambio de unidad, edición y generación de ambos certificados para A/B con el mismo cargo genérico; verifican snapshot/ficha/función exactos y la aplicabilidad transversal. Se mantienen las regresiones de autoservicio, PDF, pagos, identidades, MFA, CSRF, IDOR y CSP. El fixture MFA de esta nueva spec utiliza un rango exclusivo; no se modificó el código de autenticación.
16. **Manual real CT:** versión **10 publicada**, **Decreto 1000-24/015 de 2023**, **344 fichas**, **3.115 funciones**, **2.326 conocimientos**. Digest agregado de fichas `2ccdb15add1bedbe5c83aa978cf4fa45`, coincidente con el baseline. MF-0167 mantiene área/propósito vacíos y sus ocho conocimientos. Las 50 combinaciones ambiguas quedan detalladas en `evidence/dependency-position-selection/manual-integrity.txt`; no se escoge la primera ni se inventa contenido. No se fabricaron casos en CT desarrollo.
17. **Evidencia visual:** `frontend/react-app/evidence/dependency-position-selection/`: `01-create-employee-dependency.png`, `02-position-filtered.png`, `03-position-search.png`, `04-edit-employee.png`, `05-mobile.png`, `06-tablet.png`. Revisión visual a 360, 768 y 1440 px, sin overflow; labels visibles y navegación de teclado. Son cuentas/funcionarios sintéticos y no se capturan credenciales, secretos MFA ni datos reales sensibles. Los selects nativos no quedan recortados por contenedores.
18. **Archivos:** inventario completo al final de este informe. El [modelo documentado](DEPENDENCY_POSITION_SELECTION_MODEL.md) incluye el diagrama de la proyección y reglas de compatibilidad. Los README enlazan ese documento; la auditoría SQL es de solo lectura.
19. **Git:** se excluyen de la entrega las capturas históricas de otras fases regeneradas por esta corrida. Solo se restaura esa salida generada por nuestras pruebas; el precheck estaba limpio. El estado final después del commit se verifica e informa en la respuesta final.
20. **Alcance confirmado:** sin segundo catálogo de cargos, copia del Manual ni funciones paralelas; sin estructura organizacional inventada. Autenticación/MFA de aplicación, pagos/Secretaría, cuotas, tipos de certificado, PDF, snapshot, QR documental, firma y oficialización intactos. El PDF sigue **BORRADOR — SIN VALIDEZ OFICIAL**. **Fase 3B.2 no iniciada. NO PUSH.**

## Archivos de la entrega

- `DEPENDENCY_POSITION_SELECTION_MODEL.md`
- `DEPENDENCY_POSITION_SELECTION_REPORT.md`
- `backend/laravel-app/README.md`
- `backend/laravel-app/app/Console/Commands/PrepareAutoserviceE2E.php`
- `backend/laravel-app/app/Http/Controllers/Api/V1/DependenciaManualController.php`
- `backend/laravel-app/app/Http/Controllers/Api/V1/FuncionarioController.php`
- `backend/laravel-app/app/Http/Requests/StoreFuncionarioRequest.php`
- `backend/laravel-app/app/Http/Requests/UpdateFuncionarioRequest.php`
- `backend/laravel-app/app/Http/Resources/CargoDependenciaResource.php`
- `backend/laravel-app/app/Http/Resources/FuncionarioResource.php`
- `backend/laravel-app/app/Models/FuncionarioCargo.php`
- `backend/laravel-app/app/Services/SeleccionarFichaManualService.php`
- `backend/laravel-app/app/Services/UnidadesManualService.php`
- `backend/laravel-app/database/migrations/2026_10_05_000001_add_employment_unit_scope.php`
- `backend/laravel-app/routes/api.php`
- `backend/laravel-app/tests/Feature/DependencyPositionSelectionTest.php`
- `backend/laravel-app/tests/Feature/FaseCAutoservicioTest.php`
- `backend/laravel-app/tests/Feature/ManualImportTest.php`
- `frontend/react-app/README.md`
- `frontend/react-app/e2e/autoservice.spec.ts`
- `frontend/react-app/e2e/control-screenshots.spec.ts`
- `frontend/react-app/e2e/dependency-position-selection.spec.ts`
- `frontend/react-app/e2e/dependency-selection.ts`
- `frontend/react-app/e2e/identity-lifecycle.spec.ts`
- `frontend/react-app/e2e/payment-workflow.spec.ts`
- `frontend/react-app/e2e/security-hardening.spec.ts`
- `frontend/react-app/e2e/security-session.ts`
- `frontend/react-app/evidence/dependency-position-selection/01-create-employee-dependency.png`
- `frontend/react-app/evidence/dependency-position-selection/02-position-filtered.png`
- `frontend/react-app/evidence/dependency-position-selection/03-position-search.png`
- `frontend/react-app/evidence/dependency-position-selection/04-edit-employee.png`
- `frontend/react-app/evidence/dependency-position-selection/05-mobile.png`
- `frontend/react-app/evidence/dependency-position-selection/06-tablet.png`
- `frontend/react-app/evidence/dependency-position-selection/backend-tests.txt`
- `frontend/react-app/evidence/dependency-position-selection/e2e-tests.txt`
- `frontend/react-app/evidence/dependency-position-selection/frontend-build.txt`
- `frontend/react-app/evidence/dependency-position-selection/frontend-tests.txt`
- `frontend/react-app/evidence/dependency-position-selection/manual-integrity.txt`
- `frontend/react-app/src/features/funcionarios/components/DependenciaSelect.tsx`
- `frontend/react-app/src/features/funcionarios/components/FuncionarioForm.test.tsx`
- `frontend/react-app/src/features/funcionarios/components/FuncionarioForm.tsx`
- `frontend/react-app/src/features/funcionarios/pages/FuncionarioFormPage.tsx`
- `frontend/react-app/src/features/funcionarios/schemas/funcionario.schema.ts`
- `frontend/react-app/src/features/funcionarios/services/dependencias.service.ts`
- `frontend/react-app/src/features/funcionarios/services/funcionarios.service.test.ts`
- `frontend/react-app/src/features/funcionarios/services/funcionarios.service.ts`
- `frontend/react-app/src/features/funcionarios/types/funcionario.types.ts`
- `scripts/dependency_manual_integrity.sql`

## Ampliación: UX de combinaciones ambiguas (5 de octubre de 2026)

Baseline verificado: árbol limpio, rama `feat/dependency-scoped-position-selection`, commit `3b5fa6e8e070f3eb0f298a69696367e3bd85d2c2`. Trabajo local en `fix/manual-profile-ambiguity-ux`, sin push. Los resultados de las secciones anteriores corresponden a la entrega del baseline.

La causa era local al formulario: cualquier error del resolver mostraba el primer mensaje de validación y un botón fijo «Reintentar resolución». TanStack Query ya tenía `retry: false` para esa consulta. `/dependencias/resolver` y la validación de guardado usaban HTTP 422 y `errors.cargo_id`, pero no devolvían código de dominio para cero/múltiples candidatas.

Se añade únicamente `code` a esas respuestas de validación, conservando HTTP 422, mensaje y errores por campo: `MANUAL_FICHA_AMBIGUA` y `MANUAL_FICHA_NO_ENCONTRADA`, reutilizados del dominio existente. La consulta exitosa conserva su contrato; no se exponen candidatos, IDs de elección ni funciones completas. El backend sigue exigiendo exactamente una ficha y rechazando un ID enviado para forzar la elección.

Texto final de ambigüedad:

> **Este cargo requiere revisión del Manual**
>
> El Manual de Funciones contiene más de un perfil aplicable para este cargo, dependencia y fecha. El sistema no puede seleccionar uno automáticamente porque podría asignar funciones incorrectas.
>
> Seleccione otro cargo o revise esta situación con la Dirección de Personal antes de registrar la vinculación.
>
> No se puede guardar la vinculación hasta validar un perfil único del Manual.

El cargo permanece visible en el catálogo con «Requiere revisión del Manual». «Elegir otro cargo» limpia cargo, búsqueda y perfil mostrado, conserva dependencia y datos personales, y enfoca el selector de cargo. «Cambiar dependencia» limpia dependencia, cargo, búsquedas y perfil mostrado, conserva los datos personales, y enfoca la dependencia. El mensaje usa `role="alert"`, `aria-describedby` en cargo y botón principal, y color informativo institucional. El botón principal está deshabilitado y el submit verifica también el bloqueo. Se maneja la misma condición si el servidor la detecta al guardar después de una consulta exitosa.

La ambigüedad y las condiciones normativas son **no retryable**. Cero resultados muestra «No se encontró un perfil del Manual compatible con este cargo, dependencia y fecha», con acciones para cambiar la selección. «Reintentar» se reserva a fallos técnicos de consulta: red/sin respuesta, timeout, HTTP 5xx, 408 y 429. En ellos se muestra «No pudimos consultar el Manual» y «Revise la conexión o inténtelo nuevamente». Los códigos de dominio tienen prioridad; las demás respuestas 4xx tampoco ofrecen retry. Los errores de validación requeridos ya no muestran reintento de carga de selectores.

La resolución normativa no se delega al usuario. No se ofrece elegir entre fichas y no se prefiere una específica sobre una transversal. La edición personal conserva la relación histórica exacta aunque el catálogo actual sea ambiguo, con unidad persistida o legacy. Se mantiene la regla anterior de fichas incompletas con ámbito fiable; no se cambió su efecto sobre certificados con funciones.

Evidencia segura en [`frontend/react-app/evidence/manual-ambiguity-ux/`](frontend/react-app/evidence/manual-ambiguity-ux/): cinco capturas (`01-ambiguous-position.png`, `02-choose-another-position.png`, `03-resolved-position.png`, `04-network-retry.png`, `05-mobile.png`), logs de pruebas y auditoría de solo lectura. Las capturas usan personas y cuentas sintéticas, sin credenciales ni secretos. Se verificaron foco y ausencia de overflow a 360 y 1440 px. Los nuevos perfiles de prueba se preparan solo detrás del guard de entorno testing/base exacta E2E, desde borradores sintéticos independientes.

Integridad auditada: versión 10 publicada, 344 fichas, 3.115 funciones, 2.326 conocimientos y digest `2ccdb15add1bedbe5c83aa978cf4fa45`, coincidente con baseline. Permanecen los 73 ámbitos, 335 fichas con ámbito concreto, 50 combinaciones ambiguas y ocho perfiles transversales ya documentados. MF-0167 conserva área/propósito vacíos y ocho conocimientos. No se modificó el Manual real ni se cambió ningún conteo sin auditoría.

Autenticación/MFA, pagos/Secretaría, cuotas, tipos de certificado, snapshot, PDF, firma, QR documental y oficialización intactos. **3B.2 no iniciada. NO PUSH.**

Validación de esta ampliación:

- Backend completo: **288 pruebas / 1.698 aserciones**, todas pasan en `scl_certificate_types_test`. La regresión focalizada también pasa: 15 pruebas / 103 aserciones. Incluye resolución única, códigos distintos, payload manipulado, no exposición de funciones/candidatos, históricos exactos y ambigüedad específica/transversal. Se corrigieron dos fixtures nuevos que inicialmente intentaban escribir sobre una versión sintética publicada: ahora crean y publican un borrador independiente, respetando la inmutabilidad existente.
- Frontend completo: **114 pruebas en 22 archivos**, todas pasan. Incluye explicación accesible, ausencia de retry de dominio, limpieza/foco/conservación de datos, bloqueo de submit directo, retry de red/HTTP técnicos, cero resultados, históricos legacy/con unidad y ambigüedad detectada al guardar.
- **ESLint, TypeScript y Vite build** pasan. Pint pasa para los cuatro archivos PHP de la entrega.
- E2E completa: **39 escenarios**, todos pasan (11,3 minutos), en `scl_certificate_types_e2e`. Los dos nuevos recorren ambigüedad real → explicación sin retry → bloqueo → elegir otro cargo con foco → ficha única → guardado 201, y un 503 controlado → retry → respuesta del backend real. La interceptación se limita al fallo técnico de prueba, conforme a las convenciones existentes de Playwright; el caso normativo usa fixtures reales y no mockea el resolver. Se mantienen las regresiones de perfiles transversales, certificados, PDF, pagos, identidad, MFA, CSRF, IDOR y CSP. Log: [`e2e-tests.txt`](frontend/react-app/evidence/manual-ambiguity-ux/e2e-tests.txt).
- Los dos escenarios nuevos se repitieron con éxito al cerrar las capturas: [`e2e-evidence-tests.txt`](frontend/react-app/evidence/manual-ambiguity-ux/e2e-evidence-tests.txt). La captura fuerza scroll instantáneo al inicio para evitar posiciones intermedias de la cabecera durante el scroll suave. Solo se entregan las capturas de esta mejora; las capturas históricas regeneradas por la suite se restauraron al baseline, que estaba limpio.
- Logs: [`backend-tests.txt`](frontend/react-app/evidence/manual-ambiguity-ux/backend-tests.txt), [`frontend-tests.txt`](frontend/react-app/evidence/manual-ambiguity-ux/frontend-tests.txt), [`frontend-lint.txt`](frontend/react-app/evidence/manual-ambiguity-ux/frontend-lint.txt), [`frontend-build.txt`](frontend/react-app/evidence/manual-ambiguity-ux/frontend-build.txt), [`manual-integrity.txt`](frontend/react-app/evidence/manual-ambiguity-ux/manual-integrity.txt).
