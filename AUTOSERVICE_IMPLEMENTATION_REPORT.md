# Autoservicio de certificaciones laborales

Pruebas ejecutadas: 2026-09-19. Cierre y comprobación de integridad/Docker: 2026-09-20.

## Flujo implementado

Administrador registra funcionario y selecciona cargo/ficha → creación transaccional de funcionario, cuenta, rol, asignación y relación normativa → ingreso cédula/cédula → cambio obligatorio → funcionario solicita modalidad → expedición automática y descarga propia.

No existe aprobación humana en el proceso ordinario. Administrador y secretario no necesitan tener sesión. La administración de fuentes institucionales ocurre previamente, no por cada certificado.

## Alta, acceso y administración

- `/admin/funcionarios`: paginación server-side, búsqueda por cédula/nombre, cargo, dependencia y estado. Incluye ficha/área, cargo/código/grado, estado de funcionario/usuario y cambio pendiente. Alta, edición, detalle, activación/inactivación y reset.
- El alta crea `User`, rol `funcionario`, `Funcionario`, `FuncionarioCargo` y `FuncionarioCargoManualFicha` en la misma transacción; registra auditoría sin secretos.
- Login y contraseña inicial: cédula. Solo se persiste hash Laravel. Esta regla es menos segura que una contraseña temporal aleatoria, recomendada si la Alcaldía cambia posteriormente el requerimiento.
- `EnsurePasswordChanged` protege todas las rutas funcionales, incluida descarga y administración. Solo quedan autenticación, sesión mínima, cambio y logout. La sesión inicial no devuelve ficha administrativa.
- La contraseña nueva exige 12 caracteres, mayúsculas, minúsculas, números, confirmación y contraseña actual; no admite cédula ni contraseña anterior. Conserva el token actual y revoca otros tokens.
- Reset devuelve la contraseña temporal a la cédula mediante hash, obliga cambio y revoca sesiones. Inactivación también revoca sesiones. No hay hard delete, incluso sin expediente.
- La ficha se valida contra cargo y vigencia. Una equivalencia normativa entre cargos requiere lineage registrado; no basta cualquier relación normativa. El usuario final nunca elige ficha ni funciones.

## API operativa

Todas bajo `/api/v1`:

| Método/ruta | Operación |
|---|---|
| POST `auth/login` | Cédula/contraseña; indicador de cambio pendiente |
| GET `auth/me` | Sesión, mínima mientras el cambio esté pendiente |
| PUT `auth/change-password` | `current_password`, `password`, `password_confirmation` |
| POST `auth/logout` | Revocar sesión actual |
| GET/POST `funcionarios` | Listado paginado / alta atómica |
| GET/PUT `funcionarios/{id}` | Detalle / edición y estado |
| POST `funcionarios/{id}/restablecer-acceso` | Reset administrativo auditado |
| GET `manual-funciones/fichas?cargo_id=...` | Opciones normativas administrativas |
| GET/PATCH `configuracion/certificaciones` | Parámetro `requiere_pago_certificado`, solo administración |
| GET `mi-certificacion/disponibilidad` | Cupos del titular |
| POST `solicitudes` | `tipo_certificado` laboral/funciones y `requiere_salario`; prohíbe identidad ajena |
| GET `mi-certificacion/descargar/{token}` | PDF propio recién expedido, token con vencimiento de 15 minutos |

La respuesta exitosa incluye solicitud/radicado backend, modalidad, certificado, fecha y URL/vencimiento de descarga. No necesita `certificados.generar` ni acceso a historial.

## Expedición, cupo y fallos

`ExpedirCertificacionService` coordina identidad, estado, cambio de contraseña, asignación, fuentes normativas, salario, cupo, radicación, pago y generación. `GenerarCertificadoService` crea snapshot, PDF, hash, tokens y certificado.

Antes de generar se verifica asignación única vigente, ficha compatible/completa, versión publicada efectiva, funciones cuando corresponden y salario resoluble para CON salario. SIN salario no invoca el resolver salarial. Se conservan códigos de dominio para ambigüedad, ausencia e inconsistencias.

El snapshot guarda datos de funcionario, asignación (incluidas fechas y naturaleza), cargo, relación normativa, versión/ficha/fuentes, funciones, modalidad, salario cuando aplica y fecha. Es una ampliación compatible del schema 2; no se reescriben snapshots antiguos ni se reconstruyen con datos vivos.

Se conserva el constraint `solicitudes_funcionario_periodo_modalidad_unique`: una CON y una SIN por mes. El bloqueo transaccional PostgreSQL serializa radicación/expedición y evita dobles emisiones. Por conservar el radicado existente el bloqueo es anual/global: correcto funcionalmente, pero debe medirse bajo carga antes de producción.

Un fallo de PDF, almacenamiento o BD anterior a una expedición válida revierte solicitud/certificado/tokens, elimina el archivo parcial y registra auditoría del fallo por separado. No queda fila fallida ocupando permanentemente el cupo. El reintento está probado. Los registros históricos no se eliminan ni se liberan masivamente. Un fallo de comunicación después de una expedición válida no anula esa expedición ni su cupo; no se incorpora todavía una clave HTTP de idempotencia para recuperar una respuesta perdida.

## Pago y estados legacy

Pago OFF: `generando → generada`, sin tarea ni transición administrativa.

Pago ON: solicitud `pendiente_pago` + orden `pendiente`; no PDF ni certificado. Reserva la modalidad mientras la orden esté pendiente. No existe confirmación de pago desde el frontend. El contrato `PaymentGateway` ofrece crear orden, consultar estado y validar webhook; no tiene proveedor concreto. La confirmación autenticada/idempotente por webhook y la política de expiración de órdenes quedan para la integración futura.

`pendiente`, `en_revision`, `pago_en_revision`, `aprobada`, `rechazada`, `certificado_generado` y `cerrada` se conservan para compatibilidad histórica, pero no forman parte del autoservicio ordinario. `fallida` permanece en el enum; los errores técnicos nuevos se auditan sin persistir una solicitud que bloquee el cupo.

Las rutas manuales de aprobar/rechazar/cambiar estado/marcar pago responden 410. La carga manual de soportes está bloqueada para autoservicio. Controlador y servicio de soportes están marcados deprecated. Consulta/mantenimiento de soportes y generación administrativa de solicitudes históricas aprobadas permanecen como compatibilidad legacy, no se usan en la nueva UX ni son requisito de expedición. No se borraron permisos, tablas ni datos históricos.

## Manual y preservación

No se rediseñó el Manual. La excepción sin fecha inicial está limitada al ID interno 10 publicado, baseline adoptado. Otras versiones necesitan fecha efectiva, incluso por CLI/servicio de publicación. Al cerrar en el futuro el baseline se conserva su aplicabilidad histórica hasta la fecha de cierre.

En `scl_db` se verificaron 344 fichas, 3.115 funciones y 2.326 conocimientos para versión 10; continúa publicada con fechas nulas. MF-0167 sigue incompleta y bloqueada individualmente. Los tres certificados históricos se conservaron; checksum agregado de `snapshot_datos::text`, concatenado sin separador por ID, antes/después: `a75d046680d7afce1f0fc146551662e8`.

No se ejecutaron migraciones destructivas, truncados, borrados de volúmenes ni eliminación masiva. Esta fase reutiliza las migraciones incrementales existentes de autoservicio (`2026_08_31_000005_add_phase_c_self_service_foundation`) y relación normativa (`2026_09_05_000001_add_manual_versioning_workflow`); no requiere otra migración. En la base ordinaria no se migró ni se sembraron fixtures.

## Verificación reproducible

Backend usa exclusivamente `scl_db_test`, con transacciones y protección de bootstrap que impide `RefreshDatabase` destructivo:

```powershell
docker compose exec -T -e DB_DATABASE=scl_db_test app php artisan migrate --force
docker compose exec -T -e APP_ENV=testing -e CACHE_STORE=array -e SESSION_DRIVER=array -e DB_DATABASE=scl_db_test app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T frontend npm test
docker compose exec -T frontend npm run build
docker compose exec -T frontend npm run lint
./scripts/test-autoservice-e2e.ps1
```

E2E: `docker-compose.e2e.yml` añade API/frontend/Chromium aislados, almacenamiento separado y BD fija `scl_e2e_test`. El comando de fixtures rechaza cualquier otro entorno/base. No se usan datos ordinarios ni se borran fixtures: cada ejecución crea una cédula ficticia nueva. El script detiene solo servidores E2E y preserva la evidencia. Requiere las imágenes base app/frontend previamente construidas.

Playwright ejecuta dos escenarios reales. El primero revalida la sesión administrativa con `/auth/me`, recorre funcionarios, Manual y configuración y cierra sesión. El segundo realiza alta real por UI, logout administrativo, ingreso temporal, bloqueo de URL directa, cambio de contraseña, solicitud SIN salario y descarga con cabecera `%PDF-`; también envía dos peticiones concurrentes CON salario/funciones, esperando exactamente 201 y 409. No hay mocks HTTP.

Resultados finales:

| Verificación | Resultado |
|---|---|
| Backend completo | 140 tests / 739 aserciones, todos aprobados |
| Frontend | 25 tests en 13 archivos, todos aprobados |
| Playwright Chromium | 2 flujos reales aprobados |
| Concurrencia HTTP | Dos peticiones CON salario/funciones: 201 y 409 |
| TypeScript/Vite build | Aprobado; advertencia de tamaño de bundle |
| ESLint | Aprobado, sin errores ni advertencias |
| Laravel Pint global | 183 archivos, aprobado |
| Docker ordinario | app, frontend, nginx y postgres healthy al cierre |
| Datos ordinarios | 344 fichas / 3.115 funciones / 2.326 conocimientos; 3 snapshots con checksum sin cambios |

El E2E detectó y permitió corregir un defecto real: Axios duplicaba `/api/v1` al descargar la URL devuelta por el backend. Se normaliza ahora el prefijo, se restringe la ruta al endpoint propio y se añadieron pruebas de regresión que también impiden enviar el bearer a un origen externo. La ejecución final descargó efectivamente el PDF, no solo verificó la presencia del botón.

## Archivos principales

- Backend: `FuncionarioController`, `AuthController`, `ParametroCertificacionController`, `FuncionarioResource`, `UserResource`, `ExpedirCertificacionService`, `GenerarCertificadoService`, `ConstruirSnapshotCertificadoService`, resolvers y selección normativa.
- Frontend: formulario/listado/tabla/detalle de funcionarios, configuración de pago, pruebas `Onboarding.test.tsx` y `AdministracionAutoservicio.test.tsx`; se conserva dashboard mínimo y confirmación/descarga inmediata.
- Pruebas: `FaseCAutoservicioTest`, `ManualVersioningTest`, suite existente; `e2e/autoservice.spec.ts`, configuración Playwright, comando `PrepareAutoserviceE2E`, script y compose E2E.
- Se corrigieron únicamente detalles de formato preexistentes en tres tests para dejar Pint global en verde.

## Deuda explícita

- No se implementó firma electrónica/digital, imagen de firma, X.509, TSA ni QR oficial. El PDF técnico no se presenta como documento oficialmente firmado. La futura oficialización debe ser automática.
- Proveedor de pago/webhook, conciliación y expiración de pendientes: fuera de esta fase.
- Seguridad de despliegue productivo (HTTPS, secretos, token en almacenamiento del navegador, observabilidad, backups, carga) requiere su fase propia.
- Build conserva advertencia de bundle mayor de 500 kB. Auditoría npm de dependencias productivas: cero vulnerabilidades al comprobarse; la instalación reportó tres en el conjunto de desarrollo y el intento de auditoría completa posterior respondió 503 por mantenimiento del registro. No se hicieron actualizaciones masivas.

## Corrección de regresión de autorización administrativa — 2026-09-21

La cuenta de desarrollo `000000001` estaba activa, tenía rol canónico `admin` (ID 241), 39 permisos y `must_change_password=false`. Las tablas `roles`, `model_has_roles`, `role_has_permissions` y `permissions` eran coherentes. Sin embargo, el caché de Spatie conservaba IDs de otra ejecución: primero `admin=1` y, después de una suite de pruebas, IDs transaccionales de `scl_db_test`. Por eso `getAllPermissions()` listaba los permisos, pero `hasPermissionTo()`/`can()` devolvían `false` y los controladores/Policies producían 403.

El origen reproducible era el aislamiento incompleto de pruebas: Docker inyectaba `CACHE_STORE=file` y PHPUnit no lograba sustituirlo solo mediante `phpunit.xml`. Los tests de permisos escribían IDs de `scl_db_test` en el mismo caché de archivos usado por `scl_db`. La cadena `No tiene permisos para realizar esta acción.` era la normalización de `AuthorizationException` y HTTP 403 en `bootstrap/app.php`; no la generaba `EnsurePasswordChanged`.

`/api/v1/auth/me` ya estaba correctamente ubicado bajo `api` + `auth:sanctum`, fuera de `password.changed` y sin permiso administrativo. Antes del reset respondió 200 en la reproducción directa; el 403 observado pertenecía al estado contaminado previo. Se mantuvo su diseño: admin, secretario, funcionario y usuario con cambio obligatorio reciben 200; anónimo recibe 401. `EnsurePasswordChanged` conserva `PASSWORD_CHANGE_REQUIRED` para endpoints funcionales.

Correcciones aplicadas:

- `tests/bootstrap.php` fuerza `CACHE_STORE=array` antes de iniciar Laravel; una prueba afirma caché `array` y base `*_test`.
- `phpunit.xml` documenta y fuerza el store de prueba.
- `docker/php/entrypoint.sh` invalida el caché de Spatie al arrancar, defensa necesaria porque `storage/` está bind-mounted y sobrevive a restauraciones/reseeds.
- `RolesPermisosSeeder` mantiene `syncPermissions()` idempotente y vuelve a invalidar al terminar. No fue necesario ejecutar el seeder sobre `scl_db` ni sincronizar datos reales.
- El frontend incorpora `AuthProvider`: con token almacenado bloquea rutas privadas, consulta `/auth/me`, reemplaza el usuario cacheado y solo entonces monta autorización visual y queries. Un 401 limpia la sesión; un 403 no la destruye.

Matriz observada antes del reset:

| Endpoint | Middleware | Policy/Gate o permiso | Admin en BD | Resultado antes |
|---|---|---|---:|---:|
| `/auth/me` | `api`, `auth:sanctum` | ninguno | n/a | 200 |
| `/funcionarios` | `auth:sanctum`, `password.changed` | `funcionarios.ver` | sí | 403 |
| `/cargos` | `auth:sanctum`, `password.changed` | `cargos.ver` | sí | 403 |
| `/solicitudes` | `auth:sanctum`, `password.changed` | Policy `solicitudes.ver` | sí | 403 |
| `/certificados` | `auth:sanctum`, `password.changed` | Policy `certificados.ver` | sí | 403 |
| `/reportes` | `auth:sanctum`, `password.changed` | `reportes.ver` | sí | 403 |
| `/configuracion/certificaciones` | `auth:sanctum`, `password.changed` | `parametros.ver` | sí | 403 |
| `/manual-funciones/estado` | `auth:sanctum`, `password.changed` | `manual_funciones.ver` | sí | 403 |

Después de invalidar el caché, los siete endpoints administrativos y `/auth/me` responden 200 para admin. Funcionario mantiene 403 en los siete y 200 en `/mi-certificacion/disponibilidad`. Secretario mantiene 200 solo en funcionarios, cargos, solicitudes, certificados y reportes, y 403 en configuración y Manual. Policies, Sanctum, `EnsurePasswordChanged` y la matriz de permisos permanecen intactos.

Verificación final: backend 140 tests/739 aserciones; frontend 25/25, ESLint, TypeScript y build; Playwright 2/2 sin mocks. La prueba completa ya no modifica el caché local: al terminar, el admin ordinario conserva role ID cacheado 241 y `can('funcionarios.ver')=true`. La navegación manual confirmó dashboard, funcionarios, cargos, Manual, configuración, certificados y reportes sin mensaje de rol no autorizado. No se ejecutaron operaciones destructivas ni se modificaron Manual, fichas, funciones, conocimientos, snapshots, PDFs, cupos o flujo de autoservicio.
