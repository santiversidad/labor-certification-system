# Auditoría técnica integral — labor-certification-system

**Fecha de corte:** 2026-08-31 (actualizado tras Fase C)
**Alcance:** código, configuración, esquema PostgreSQL real, contratos API/TypeScript, pruebas automatizadas, compilación, lint, auditorías de dependencias y comprobaciones HTTP/visuales locales.  
**Entorno:** Docker de desarrollo existente; Fases B y C reconstruyeron/recrearon imágenes para validar lockfiles y ejecución, sin borrar volúmenes ni ejecutar comandos destructivos.
**Criterio de evidencia:** **Confirmado** significa reproducido o demostrado directamente; **Probable** es una consecuencia técnicamente sustentada que no pudo reproducirse con los datos actuales; **Requiere validación funcional** identifica una decisión de negocio o jurídica no deducible solo del código.

## 1. Resumen ejecutivo

La aplicación implementa un flujo React/Vite → API Laravel → PostgreSQL, con autenticación Bearer mediante Sanctum y autorización mediante Spatie Permission. Fase C añadió expedición automática 24/7, ciclo seguro de contraseña inicial, administración integral de funcionarios y un borde explícito para pago real. Composer y npm no reportan advisories; build, ESLint, Vitest y Laravel terminan correctamente. Los indicadores reproducibles se detallan en `PHASE_C_REPORT.md`.

El sistema **no está preparado para producción**. Dos bloqueantes sobresalen:

1. El documento generado se identifica en su propia plantilla como **“CERTIFICADO LABORAL TEMPORAL”** y carece de formato oficial, firma, QR y evidencia verificable (`resources/views/certificados/laboral.blade.php:1-24`).
2. No existe ningún mecanismo de firma escaneada, electrónica o digital, ni identidad del firmante, evidencia del acto, sellado de tiempo o validación jurídica del hash. Fase A añadió snapshot técnico inmutable, pero no equivale a firma ni custodia documental oficial.

Fase A corrigió el alcance del frontend funcionario: ya no ofrece historial, detalle ni métricas; presenta los dos cupos mensuales, radica y confirma usando el radicado real. El cierre visual ahora revoca el token del servidor y el login aplica cinco intentos/minuto por cédula+IP. Permanecen sin resolver la expiración de tokens y el riesgo del Bearer token en `localStorage`.

La autorización por objeto existe y Fase A añadió pruebas con dos titulares: el campo `funcionario_id` se rechaza y el rol funcionario recibe 403 en expedientes y endpoints internos. No se confirmó IDOR en el flujo de radicación. La revisión de IDOR deberá repetirse cuando aparezcan nuevos endpoints de archivos, certificados oficiales o administración delegada.

### Estabilización realizada

| Control | Antes | Después |
| --- | --- | --- |
| Build React | Fallaba por tipos de identificador, wrappers `data` y DTO/estados | Correcto; Vite 8.0.14, 2024 módulos |
| ESLint | 5 imports sin usar | 0 errores |
| Laravel | 37 correctas, 1 fallida | 38 correctas, 0 fallidas, 123 aserciones |

### Actualización Fase A

| Control | Estado posterior |
| --- | --- |
| Suite Laravel | 52 correctas, 0 fallidas, 207 aserciones |
| Regla mensual | UNIQUE por funcionario + `periodo_mes` + modalidad; 409 controlado |
| Dashboard funcionario | Solo disponibilidad, radicación y confirmación |
| Manual de Funciones | 4 tablas versionables, resolver por cargo/fecha y permisos Spatie |
| Snapshot | JSONB esquema 1; PDF temporal generado desde datos congelados |
| Login/logout/401 | Rate limit, logout real y 401 JSON corregidos |

### ESTADO ACTUAL — Fase B

| Control | Estado actual |
| --- | --- |
| Archivos privados | Soporte descargable por admin/secretario mediante Policy, headers seguros y auditoría; reemplazo compensado |
| Certificación técnica | Generación/descarga React alineadas; SHA-256 se recalcula en descarga y validación pública |
| Contratos | Reportes y validación pública alineados; errores API modificados usan envelope común |
| Integridad BD | `UNIQUE` usuario↔funcionario y pago↔solicitud; checks de orden y snapshot completo |
| Dependencias | Composer: 23 advisories → 0; npm React: 9 vulnerabilidades → 0 |
| Firma/oficialidad | `SIGN-001` y `CERT-001` continúan abiertos deliberadamente; el PDF conserva “TEMPORAL” |

### ESTADO ACTUAL — Fase C

| Control | Estado actual |
| --- | --- |
| Autoservicio sin pago | Validación, radicación, snapshot, PDF, certificado y descarga propia inmediata en una operación transaccional |
| Autoservicio con pago | Orden pendiente y cero certificado; contrato para proveedor real, sin pasarela ficticia ni comprobante manual |
| Cuota mensual | Una CON salario y una SIN salario por funcionario/mes; constraint e invariantes de servicio |
| Identidad | Alta atómica usuario-funcionario-asignación; contraseña inicial cifrada y cambio obligatorio impuesto por middleware |
| Administración | Búsqueda, estado, paginación, alta, edición, activación/desactivación, reset y configuración de pago auditada |
| Secretaría | Fuera del camino crítico de nuevas expediciones; rutas manuales de decisión retiradas con `410 Gone` |
| Verificación | 82 pruebas Laravel/398 aserciones y 11 pruebas React; builds, ESLint, Pint (156 archivos) y tres auditorías verdes |
| Firma/oficialidad | Continúan abiertos `SIGN-001` y `CERT-001`; la automatización no sustituye la decisión jurídica/institucional |

El test defectuoso autenticaba al secretario en un helper y después ejecutaba el escenario “sin autenticación” con el guard todavía resuelto. Se agregó `$this->app['auth']->forgetGuards()` antes del escenario anónimo en `tests/Feature/CertificadoValidacionTest.php`. La corrección restablece el aislamiento del test; no se modificaron middleware ni comportamiento de autenticación.

### Reglas de negocio confirmadas

Estas reglas sustituyen cualquier supuesto anterior de la auditoría:

1. Cada funcionario puede radicar como máximo **una certificación CON salario y una certificación SIN salario por mes calendario**. Puede, por tanto, radicar hasta dos en el mismo mes si son de modalidades distintas; no puede radicar dos de la misma modalidad.
2. Ambas modalidades se gestionan en un único flujo y se persisten mediante `requiere_salario`, obligatorio y sin crear un campo duplicado.
3. Una solicitud constituida consume el cupo mensual de **su modalidad**, aunque después sea rechazada, cancelada o anulada. En Fase C, un fallo técnico durante la generación sin pago revierte solicitud, certificado y archivo, por lo que no consume cupo.
4. El límite usa mes calendario y timezone institucional. Fase A configuró `America/Bogota`; Laravel calcula `periodo_mes` y PostgreSQL impone la unicidad.
5. El funcionario inicia sesión, completa el cambio obligatorio cuando aplica, consulta ambos cupos, selecciona modalidad y recibe descarga inmediata o referencia de pago pendiente. No consulta expediente, historial laboral, salarios, actuaciones, reportes, auditoría ni listados históricos.
6. El titular se deriva de `usuario autenticado → funcionario asociado`; `funcionario_id` enviado por el cliente no puede decidir el titular.
7. Las funciones certificadas provienen del Manual Específico de Funciones asociado al cargo/empleo, no de texto libre ni de una copia por funcionario.
8. El salario, cuando la modalidad lo incluye, proviene de la vinculación, cargo y vigencia salarial institucional aplicables. La modalidad sin salario nunca muestra información salarial.
9. Todo certificado emitido debe conservar un snapshot estructurado de los datos y fuentes utilizados; cambios posteriores de cargo, dependencia, salario o Manual no pueden alterar el documento emitido.
10. El Manual de Funciones debe ser versionable por vigencia y acto administrativo, reutilizando `cargos` como empleo institucional. Su administración requiere permisos Spatie específicos y queda fuera del rol funcionario.
11. La implementación oficial de firma, evidencia del acto, inmutabilidad y validación jurídica continúa pendiente.

## 2. Arquitectura actual

### Stack

| Capa | Tecnología real |
| --- | --- |
| Backend | Laravel 13.29.0, PHP 8.4.25 en Docker; `composer.json` requiere PHP `^8.3` y Laravel `^13.8` |
| Autenticación | Laravel Sanctum 4.3.2, tokens Bearer |
| Autorización | Spatie Laravel Permission 7.4.1, roles `admin`, `secretario`, `funcionario` |
| Base de datos | PostgreSQL 16, 31 tablas, 23 migraciones ejecutadas |
| Frontend | React 19.2.6, TypeScript 6.0.2, Vite 8.0.14, React Router 7.15.1, TanStack Query 5.100.11, Axios 1.16.1, React Hook Form, Zod, Tailwind 3.4.17 |
| Infraestructura local | Nginx 1.28, PHP-FPM, Node 24, Docker Compose |
| Archivos | Disco privado `storage/app/private` |

No existen Repositories, Events, Listeners, Jobs de aplicación, Commands propios, Notifications ni Mailables. Fase C añadió el middleware de cambio obligatorio y el contrato `PaymentGateway`, pero todavía no existe adaptador externo. Existen tablas de cola, aunque desarrollo usa `QUEUE_CONNECTION=sync`; no hay worker, scheduler ni WebSockets. Redis no se usa y el correo se dirige a log.

### Estructura backend

- 15 controladores API, 18 Form Requests, 10 Resources, 17 modelos, 3 Policies, 11 Services, 2 Actions y 1 middleware propio.
- `SolicitudCertificacionController` delega la expedición nueva en `ExpedirCertificacionService` y conserva consulta administrativa y respuestas `410` para decisiones manuales retiradas.
- `GenerarCertificadoService` ensambla datos, crea el PDF, persiste archivo/BD/tokens y cambia el estado; `ExpedirCertificacionService` coordina cuota, pago y transacción de autoservicio.
- `RegistrarAuditoriaAction` implementa auditoría manual, no transversal.

### Estructura frontend

- Organización por `features` con páginas, componentes, servicios y tipos.
- Axios centralizado en `src/lib/api/apiClient.ts`; adjunta Bearer y limpia sesión ante 401.
- TanStack Query gestiona consultas/mutaciones; no hay store global adicional.
- `RoleRoute` y navegación restringen visualmente por rol. Estas restricciones no reemplazan la autorización Laravel.

### Mapa de flujos principales

| Flujo | Ruta → middleware → controlador → validación → servicio/modelo → BD → respuesta |
| --- | --- |
| Login | `POST /api/v1/auth/login` → `api` → `AuthController@login` → validación inline → `User`, Hash, Sanctum → `users`, `personal_access_tokens`, `audit_logs` → token + `UserResource` |
| Crear solicitud | `POST /solicitudes` → `auth:sanctum` → `SolicitudCertificacionController@store` → `StoreSolicitudCertificacionRequest` + Policy → `SolicitudCertificacion` → `solicitudes_certificacion` + audit → `SolicitudCertificacionResource` |
| Consultar solicitud | `GET /solicitudes/{id}` → Sanctum → controlador → Policy `view` → Eloquent con relaciones → varias tablas → Resource |
| Cambiar estado | `PATCH /solicitudes/{id}/...` → Sanctum → Request/Policy → mapa de transición privado → solicitud + audit → Resource |
| Cargar pago | `POST /solicitudes/{id}/soporte-pago` → Sanctum → `PagoSoporteController@cargar` → `StorePagoSoporteRequest`, propietario y estado → `PagoSoporteService` → archivo privado + `pagos_soportes` → Resource |
| Validar pago | `PATCH /pagos/{id}/validar|rechazar` → Sanctum → Policy + request de rechazo → `ValidarPagoAction` → pago/solicitud + audit → Resource |
| Generar certificado | `POST /solicitudes/{id}/generar-certificado` → Sanctum → `CertificadoController@generar` → Policy → `GenerarCertificadoService` → PDF, certificado, token, solicitud → archivo + 3 tablas → Resource + token/URL |
| Descargar | `GET /certificados/{id}/descargar` → Sanctum → Policy → Storage → estado/audit → descarga binaria |
| Validación pública | `GET /validar-certificado/{token}` → API pública → `ValidacionPublicaController@show` → hash del token → certificado/funcionario → audit → datos mínimos sin salario |

## 3. Mapa funcional

| Módulo | Propósito y entidades | Endpoints/pantallas | Acceso | Estado |
| --- | --- | --- | --- | --- |
| Autenticación | Login, perfil y logout; `User`, Sanctum | `/auth/login`, `/auth/me`, `/auth/logout`, `/login` | Todos/autenticados | **Parcial**: logout real y throttle; sin recuperación ni expiración de token |
| Roles/permisos | Sembrar 3 roles y 37 permisos | Sin CRUD/API/pantalla de gestión | Seeder/admin implícito | **Parcial**: enforcement existe, administración no |
| Funcionarios | Hoja básica, cargo y relación con usuario | API CRUD; `/admin/funcionarios*` | Admin | **Parcial**: list/detail reales; formularios no persisten |
| Cargos | Catálogo de cargos | API list/create/show/update; `/admin/cargos*` | Admin; secretario solo lectura API | **Parcial**: formulario React no ejecuta submit |
| Rangos salariales | Salario por código/grado/vigencia | API + consulta; `/admin/rangos-salariales*` | Admin; secretario lectura | **Parcial**: formulario React no persiste |
| Historial cargo | Trayectoria del funcionario | Modelo `FuncionarioCargo`, `PeriodoPruebaService` | Sin ruta/pantalla | **Aparentemente no utilizado** |
| Actuaciones | Actos administrativos | API list/create/show/update | Admin según Request/permisos | **Backend parcial**, sin frontend |
| Solicitudes | Radicación y estados | API completa; dashboard restringido y `/admin/solicitudes*` | Funcionario solo radica; gestores consultan/revisan | **Completo para Fase A**: modalidad mensual, identidad y confirmación protegidas |
| Pagos/soportes | Cargar, visualizar y validar comprobante | Carga + list/show/archivo/validar/rechazar; `/admin/pagos` | Gestores; funcionario sin permiso | **Completo para Fase B**: descarga privada, Policy, auditoría y lifecycle compensado |
| Certificados | Generar PDF, listar, ver, descargar, anular | API + `/app/solicitudes/:id`, `/app/certificados/:id` | Gestores | **Prototipo**: UI genera por endpoint incorrecto y descarga usa `#` |
| Validación pública | Validar token sin exponer salario | Dos alias API; `/validar-certificado/:token` | Público | **Alineado en Fase B**: válido/no encontrado/anulado/integridad comprometida |
| Auditoría | Registro manual y consulta | `/auditoria`, `/admin/auditoria` | Admin | **Parcial**: buena base, cobertura incompleta |
| Reportes | Conteos y tiempo promedio | `/reportes`, `/reportes/resumen`, `/admin/reportes` | Admin/secretario | **Backend básico / frontend roto** por camelCase vs snake_case |
| Parámetros | Activar pago global | Tabla/seeder `requiere_pago_certificado` | Sin API/UI | **Solo configuración sembrada** |
| Manual de Funciones | Fuente versionable de propósito, funciones y requisitos por cargo | 4 tablas y API administrativa; sin pantalla | Admin por permisos Spatie | **Base backend implementada**; falta carga oficial/UX |
| Disponibilidad mensual | Informar cupos con/sin salario sin revelar historial | `GET /mi-certificacion/disponibilidad` | Funcionario autenticado | **Implementado** |
| Firma | Firma del documento | Ninguno | Ninguno | **No implementado** |

## 4. Modelo de datos

PostgreSQL contiene 31 tablas tras Fase C. Las claves primarias, foráneas y nuevos constraints declarados por migración existen en la base real.

### Relaciones principales

```text
users 1 ── 0..n funcionarios (el modelo asume 1:1, pero la BD no impone UNIQUE)
funcionarios n ── 1 cargos
funcionarios 1 ── n actuaciones_administrativas
funcionarios 1 ── n funcionario_cargo ── 1 cargos
funcionarios 1 ── n solicitudes_certificacion
solicitudes_certificacion 1 ── 0..n pagos_soportes (el modelo asume hasOne)
solicitudes_certificacion 1 ── 0..1 certificados (UNIQUE desde Fase A)
certificados 1 ── n tokens_validacion
cargos 1 ── n manual_cargo_versiones ── 1 manual_funciones_versiones
manual_cargo_versiones 1 ── n manual_funciones_esenciales
users ── roles ── permissions (Spatie)
```

### Hallazgos de integridad

- **ESTADO ORIGINAL:** `funcionarios.user_id` y `pagos_soportes.solicitud_certificacion_id` no tenían `UNIQUE`; eliminar un funcionario podía activar cascadas históricas.
- **CORREGIDO EN FASE A:** Fase A añadió `UNIQUE` a `certificados.solicitud_certificacion_id`, pero dejó pendientes las otras dos cardinalidades y el hard delete.
- **ESTADO ACTUAL:** Fase B comprobó cero duplicados y aplicó índices `UNIQUE` incrementales a usuario↔funcionario y pago↔solicitud. El controlador responde 409 y audita cuando el funcionario tiene relaciones; el hard delete queda temporalmente permitido solo sin historial.
- **Confirmado:** ningún modelo de dominio usa `SoftDeletes`.
- **Corregido Fase A:** el radicado mantiene `count()+1`, pero el controlador serializa el consecutivo anual con advisory lock transaccional.
- **Corregido Fase A:** `periodo_mes` + UNIQUE garantizan una solicitud por funcionario/modalidad/mes; la API transforma la carrera en 409.
- **Estado actual:** generación y reemplazo de pago usan lock/transacción/compensación; los caminos de éxito, fallo BD y fallo al eliminar archivo anterior tienen pruebas.
- **Confirmado:** los estados son strings sin constraints `CHECK` de negocio en PostgreSQL.
- **Confirmado:** faltan índices compuestos para consultas frecuentes por estado/fecha/funcionario y auditoría por acción/modelo/fecha. Las FK sí disponen de soporte lógico, pero el inventario `pg_indexes` muestra solo 50 índices, mayormente PK/UNIQUE.
- **Confirmado:** `funcionarios.cargo_id` y `funcionario_cargo` duplican el concepto de cargo actual/histórico. La generación usa el primero; `PeriodoPruebaService` usa el historial y no es invocado.
- **Confirmado:** `tokens_validacion.expires_at` y `used_at` existen, pero no se llenan en el flujo actual.

## 5. Autenticación

### Funcionamiento actual

- Login por documento y contraseña, mensaje genérico para credenciales incorrectas y bloqueo de usuario inactivo (`AuthController.php:29-68`).
- Cada login elimina todos los tokens anteriores del usuario y crea uno nuevo (`AuthController.php:53-58`). Es una política de sesión única no documentada al usuario.
- `/auth/me` devuelve roles/permisos; logout API revoca el token actual.
- `config/sanctum.php:53` establece expiración `null`: los tokens no caducan automáticamente.

### Riesgo de `localStorage`

`authStorage.ts:7-27` almacena token y perfil completo en `localStorage`. Cualquier XSS ejecutado en el origen puede leer y exfiltrar el Bearer, cuyo impacto aumenta porque no expira. Alterar el perfil local también permite falsificar la navegación/rol visible, aunque Laravel sigue protegiendo la API.

La alternativa preferente para una SPA de primera parte es Sanctum stateful con cookie de sesión `HttpOnly`, `Secure` y `SameSite` adecuado. Requiere: obtener `/sanctum/csrf-cookie`, `withCredentials`, sesión compartida, CORS/orígenes de producción, protección CSRF real y definición de dominios stateful. Es un cambio arquitectónico controlado y no se aplicó. Otra alternativa es mantener el token solo en memoria con renovación de corta duración; sigue requiriendo un diseño de refresh/revocación.

### ESTADO ORIGINAL

- El logout visual sólo borraba `localStorage` y conservaba el token Sanctum.
- 65 logins fallidos consecutivos no producían 429.
- Una petición protegida sin `Accept: application/json` podía producir 500 y exponer stack con `APP_DEBUG=true`.
- Las credenciales demo se incluían en la pantalla sin condicionar el ambiente.

### CORREGIDO EN FASE A

- Logout React llama la API, revoca el token actual y limpia la sesión local en `finally`.
- Login está limitado a cinco intentos por minuto por cédula+IP.
- Las rutas API protegidas responden 401 JSON aunque falte `Accept`; se añadieron regresiones backend.

### ESTADO ACTUAL

- Fase B conserva los controles anteriores y añade test React de logout.
- Los helpers demo sólo se renderizan con `import.meta.env.DEV`; el bundle de producción no contiene las credenciales.
- `bootstrap/app.php` normaliza 401, 403, 404, 409, 422 y 429 para `/api/*` y no entrega stack traces de excepciones no controladas.
- Permanece abierto `AUTH-001/AUTH-004`: Bearer en `localStorage`, sesión local no revalidada y expiración global de Sanctum en `null`.

## 6. Autorización

### Matriz real

| Rol | Permisos principales | Resultado HTTP observado |
| --- | --- | --- |
| Admin | 33 permisos | 200 en cargos, funcionarios, solicitudes, pagos, certificados, reportes y auditoría |
| Secretario | 17 permisos de consulta/flujo | 200 en los módulos operativos; 403 en auditoría |
| Funcionario | `solicitudes.crear` | 403 al listar solicitudes, pagos, certificados, cargos y funcionarios |

`RolesPermisosSeeder.php:80-111` es la fuente de esta matriz. Las operaciones sensibles del backend normalmente llaman Policy, Gate o Form Request; ocultar botones no es el único control.

El permiso heredado `pagos.cargar` no forma parte del alcance mínimo confirmado del funcionario en esta adenda. Debe revisarse en Fase A y no conservarse por inercia si el flujo institucional ya no exige que el solicitante cargue pagos.

### Alcance confirmado del funcionario

El funcionario puede iniciar sesión, elegir modalidad con/sin salario, radicar una solicitud, recibir el radicado en la respuesta del POST y cerrar sesión. **No debe obtener `solicitudes.ver` ni un listado histórico.** El 403 de `GET /solicitudes` es coherente con este alcance; el defecto está en la navegación, dashboard y pantalla heredada “Mis solicitudes”, que deben retirarse o limitarse.

`SolicitudCertificacionController@store` deriva `funcionario_id` desde `$request->user()->funcionario` y no usa un ID enviado por React. Fase A añadió el test explícito A/B que rechaza `funcionario_id` enviado por el cliente.

`CertificadoPolicy.php:10-27` prohíbe consulta/descarga al funcionario. Esta restricción se conserva mientras no exista una decisión institucional que amplíe expresamente el alcance; los enlaces heredados de certificados/descargas del frontend deben eliminarse.

### IDOR

- **ESTADO ORIGINAL:** la revisión estática mostraba Policies, pero no existía una prueba A/B y la base local sólo tenía un funcionario.
- **CORREGIDO EN FASE A:** la suite crea dos titulares, prohíbe `funcionario_id` del cliente y confirma 403 del rol funcionario en expedientes/endpoints internos.
- **ESTADO ACTUAL:** el nuevo endpoint privado de soporte también usa Policy y prueba admin, secretario, funcionario y anónimo. Certificados siguen restringidos a gestores. No se confirmó IDOR en los flujos cubiertos.
- **Riesgo residual:** admin/secretario operan globalmente sin scope por dependencia; debe confirmarse si ese es el modelo institucional.

## 7. Certificaciones

### Flujo actual exacto

1. Un funcionario crea una solicitud con modalidad explícita con/sin salario; el titular se deriva de la sesión. Puede radicar como máximo una solicitud de cada modalidad por mes calendario.
2. Si se requiere pago, debe existir soporte aprobado.
3. Admin o secretario con `certificados.generar` invoca `POST /solicitudes/{id}/generar-certificado`.
4. Se obtiene funcionario y cargo actual, y el rango salarial activo del año actual si aplica.
5. Se renderiza Blade, se eliminan etiquetas HTML y `PdfBasicoService` crea un PDF de texto de una sola página.
6. El archivo se guarda en `storage/app/private/certificados/{año}`.
7. Se crea `certificados`, hash SHA-256, token de validación y se cambia la solicitud a `certificado_generado`.
8. Un gestor puede descargarlo; la primera descarga cambia el estado a `descargado` y registra auditoría.
9. Un gestor puede anularlo con motivo. La validación pública deja de considerarlo válido.

### Propiedades del documento

| Pregunta | Estado actual |
| --- | --- |
| Quién crea | Admin/secretario con permiso; se registra `generado_por` |
| Modalidad | `requiere_salario` persiste un booleano explícito, aunque el nombre de dominio debería consolidarse como `incluye_salario` |
| Quién modifica | No hay endpoint para editar certificado; el archivo sigue siendo mutable en storage |
| Aprobación | Estado de solicitud, no acto formal separado |
| Firma | No existe |
| Plantilla | Blade explícitamente temporal |
| PDF | Ensamblador manual, texto ASCII, una página |
| Regeneración/versiones | Se rechaza segundo certificado; no hay versiones |
| Consecutivo | Código aleatorio `CL-año-8hex`, no consecutivo institucional |
| Verificación pública | Sí, por token hasheado; frontend y backend alineados en Fase B |
| QR | No; existe TODO |
| Hash | SHA-256 recalculado y comparado en descarga/validación; no equivale a firma |
| Emisión/descarga | Auditadas manualmente |
| Vencimiento | Enum existe, no proceso de vencimiento |

### ESTADO ORIGINAL

- La generación no usaba transacción, lock ni compensación; el token se reducía a 64 bits.
- El hash se persistía, pero no se recalculaba en descarga o validación.
- React intentaba cambiar directamente la solicitud a `certificado_generado` y el botón de descarga apuntaba a `#`.
- La respuesta pública y el DTO React eran incompatibles.

### CORREGIDO EN FASE A

- La generación incorporó transacción, `FOR UPDATE`, compensación de archivo, `UNIQUE` y snapshot JSONB.
- Los nuevos tokens comenzaron a usar 32 bytes aleatorios (256 bits; 64 caracteres hexadecimales).
- Funciones y salario se centralizaron; la modalidad sin salario no invoca resolución salarial.

### ESTADO ACTUAL

- `VerificarIntegridadCertificadoService` recalcula SHA-256 y usa `hash_equals` en descarga protegida y validación pública. Archivo ausente o alterado produce respuesta controlada y evento de integridad; jamás se reescribe el hash silenciosamente.
- React usa el endpoint dedicado de generación, invalida queries y descarga el blob autenticado con filename seguro y revocación del Object URL.
- La validación pública distingue válido, no encontrado, anulado e integridad comprometida sin exponer detalles técnicos.
- `expires_at` representa una futura caducidad institucional y `used_at` una futura política de consumo/primer uso. Ambos permanecen `null`: no se activó una expiración arbitraria sin regla institucional.
- La resolución CON salario falla con error de dominio ante asignación/rango ausente o ambiguo. Se conserva deliberadamente la fecha de generación como referencia temporal hasta decisión institucional.
- Cada nueva generación exige `snapshot_schema_version` y `snapshot_datos`; el modelo prohíbe modificarlos luego.

Un SHA-256 detecta cambios respecto del valor persistido, pero **no es firma electrónica ni digital**, no atribuye autoría y no convierte el PDF temporal en certificado oficial.

## 8. Firma — informe técnico separado

**Conclusión confirmada:** la aplicación no implementa firma de certificaciones.

| Control solicitado | Evidencia actual |
| --- | --- |
| Imagen de firma | No hay columnas, modelos, rutas, assets ni almacenamiento de firmas |
| Inserción de imagen | No existe en Blade/PDF |
| Identidad de firmante | Solo `generado_por`; no equivale a firmante ni acto de firma |
| Fecha/hora de firma | No existe; solo `fecha_generacion` |
| Usuario responsable | Se registra generador, no firmante |
| Evidencia del acto | No existe aceptación, segundo factor, certificado, IP específica ni sello de tiempo |
| Hash firmado | Digest SHA-256 verificado técnicamente; no está firmado y no hay criptografía asimétrica |
| Bloqueo después de firma | No aplica; no existe firma ni almacenamiento inmutable |
| Revocación | Hay anulación administrativa del certificado, no revocación de firma/certificado digital |
| Versionamiento | No existe |

Fase B detecta una alteración del PDF comparando su SHA-256 con el persistido y deja de servirlo como válido. Ese control técnico no impide por sí solo la sustitución del archivo ni constituye firma digital. Antes de un uso oficial deben definirse tipo de firma, identidad/autorización del firmante, evidencia, conservación, validación pública, sellado de tiempo, custodia de claves, revocación y requisitos jurídicos aplicables a una entidad pública colombiana. Este informe no emite concepto jurídico.

## 9. Archivos

### ESTADO ORIGINAL

- El soporte se almacenaba en privado, pero secretario/admin no tenían endpoint para inspeccionarlo.
- `updateOrCreate` cambiaba la fila sin eliminar el archivo anterior y existía un helper que retornaba una ruta interna.

### ESTADO ACTUAL

- La subida conserva allowlist PDF/JPG/JPEG/PNG y máximo 5 MiB; el nombre físico se normaliza y nunca se publica en `/public`.
- `GET /api/v1/pagos/{pago}/archivo` requiere Sanctum, Policy y permiso `pagos.ver`; admin y secretario reciben el MIME real, `Content-Disposition` seguro y `X-Content-Type-Options: nosniff`.
- La ruta física no aparece en Resource, JSON, filename ni cuerpo de error; cada acceso autorizado se audita.
- El reemplazo almacena primero, confirma BD en transacción y sólo entonces elimina el anterior. Si falla BD elimina el nuevo; si falla el delete antiguo conserva la referencia nueva y audita el error.
- Permanece pendiente una política global de retención/reconciliación para archivos eliminados fuera de este flujo y un control antivirus de contenido.

No se encontró concatenación de ruta controlada por el usuario que confirme path traversal. La validación MIME es razonable como base, pero para documentos sensibles conviene inspección de contenido, antivirus y nombres de descarga seguros.

## 10. Seguridad

### Validación y mass assignment

- Los endpoints de escritura usan Form Requests o `$request->validated()`; no se halló `Model::create($request->all())`.
- `$fillable` está acotado en modelos.
- No se identificó SQL Injection: búsquedas `ILIKE` usan bindings de Eloquent; el único `selectRaw` es una expresión constante de promedio (`ReporteController.php:28`).
- **ESTADO ACTUAL:** los listados modificados usan `IndexQueryRequest`, `per_page` entre 1 y 100, enums/fechas validados y allowlists de ordenamiento. Permanecen por revisar endpoints futuros o no incluidos.
- Login exige password pero no impone longitud máxima.
- **CORREGIDO EN FASE B:** los updates compuestos resuelven los valores actuales y `UpdateFuncionarioRequest` replica la regla temporal de retiro.

### Manejo de errores

- **ESTADO ORIGINAL:** 401/403/404 tenían handlers parciales; 422, 409, 429 y 500 no compartían envelope y un 500 local podía exponer stack.
- **ESTADO ACTUAL:** las APIs modificadas responden `{success:false,message}` y 422 añade `errors`; hay manejo explícito para 401/403/404/409/422/429 y fallback API 500 sin stack incluso con debug local.
- Los formularios conectados de cargo, rango y funcionario muestran errores 422 por campo; otros módulos heredados pueden conservar feedback genérico.

### CORS

`config/cors.php:19-36` restringe orígenes a localhost/127.0.0.1, permite todos los métodos/headers y `supports_credentials=true`. Es apropiado solo para desarrollo. No hay wildcard de origen. Producción requiere una lista explícita por configuración, HTTPS, cookies/credentials coherentes y pruebas de preflight; no se deben inventar dominios ahora.

### Rate limiting

Fase A registró `throttle:login` con 5 intentos/minuto por cédula+IP y prueba 429. Validación pública, generación, búsquedas y descargas siguen sin límites específicos; producción necesitará una política distribuida y métricas.

### Secretos

- `.env` está ignorado y no aparece versionado.
- El escaneo no halló API keys, tokens, claves privadas ni contraseñas reales. Las credenciales PostgreSQL y de usuarios demo son de desarrollo, pero están en Compose/UI y nunca deben reutilizarse.
- `.claude/settings.local.json` está versionado y solo contiene permisos locales, sin patrón de secreto. Es configuración específica de máquina: se agregó `.claude/` a `.gitignore`; el archivo ya rastreado deberá desversionarse deliberadamente en una fase de limpieza, sin borrarlo del equipo.

## 11. Vulnerabilidades de dependencias

Los inventarios siguientes conservan el **ESTADO ORIGINAL**. Fase B aplicó actualización focalizada y volvió a ejecutar las auditorías.

### Composer — 23 advisories / 7 paquetes

Resumen: **5 altos, 17 medios, 1 bajo, 0 críticos**. Laravel es dependencia directa; el resto llega transitivamente desde Laravel/Guzzle/Symfony. Versiones “corregidas” son mínimos derivados de los rangos publicados por la auditoría.

| Paquete instalado | Advisory/CVE | Sev. | Directa | Mínimo corregido | Aplicabilidad/impacto |
| --- | --- | --- | --- | --- | --- |
| guzzle 7.10.3 | CVE-2026-69246 | ALTO | No | 7.15.2 | Validación host; relevante si se incorporan HTTP salientes con allowlists |
| guzzle 7.10.3 | CVE-2026-69245 | MEDIO | No | 7.15.2 | Alcance de cookies; hoy no se identificaron clientes Guzzle de negocio |
| guzzle 7.10.3 | CVE-2026-67354 | MEDIO | No | 7.15.1 | Fragmentos en Referer durante redirects |
| guzzle 7.10.3 | CVE-2026-67355 | MEDIO | No | 7.15.1 | Alcance de cookies host-only |
| guzzle 7.10.3 | CVE-2026-67353 | MEDIO | No | 7.15.1 | DoS por cookies de respuesta |
| guzzle 7.10.3 | CVE-2026-59883 | MEDIO | No | 7.12.3 | Cookies en dominios IP |
| guzzle 7.10.3 | CVE-2026-67339 | MEDIO | No | 7.14.2 | Proxy-Authorization al origen |
| guzzle 7.10.3 | CVE-2026-55767 | MEDIO | No | 7.12.1 | Cookie domain solo puntos |
| guzzle 7.10.3 | CVE-2026-55568 | MEDIO | No | 7.12.1 | Downgrade HTTPS proxy |
| psr7 2.10.1 | CVE-2026-59882 | MEDIO | No | 2.12.3 | Host confusion |
| psr7 2.10.1 | CVE-2026-55766 | MEDIO | No | 2.12.1 | CRLF en start-line |
| psr7 2.10.1 | CVE-2026-49214 | MEDIO | No | 2.10.2 | CRLF vía host |
| psr7 2.10.1 | CVE-2026-48998 | MEDIO | No | 2.10.2 | Confusión de authority |
| laravel/framework 13.11.2 | GHSA-crmm-hgp2-wgrp | MEDIO | **Sí** | 13.12.0 | Temporary signed URL path confusion; disco local tiene `serve=true` |
| commonmark 2.8.2 | GHSA-mj63-m3rc-8ppr | MEDIO | No | 2.9.0 | DoS XML; sin markdown de usuario actual |
| commonmark 2.8.2 | GHSA-mh25-x5hq-wrqp | ALTO | No | 2.9.0 | DoS por heading slugs; baja exposición actual |
| commonmark 2.8.2 | GHSA-jfm3-95jq-q3rf | ALTO | No | 2.9.0 | DoS por footnotes; baja exposición actual |
| commonmark 2.8.2 | GHSA-g2gp-3wwq-f4ph | ALTO | No | 2.9.0 | DoS attributes; baja exposición actual |
| commonmark 2.8.2 | CVE-2026-71488 | ALTO | No | 2.9.0 | DoS cuadrático; baja exposición actual |
| commonmark 2.8.2 | CVE-2026-71478 | MEDIO | No | 2.9.0 | Filtro de enlaces unsafe; baja exposición actual |
| http-foundation 8.0.8 | CVE-2026-48736 | MEDIO | No | 8.0.13 | Bypass SSRF en utilidades de IP; no se vio cliente SSRF actual |
| polyfill-intl-idn 1.37.0 | CVE-2026-46644 | BAJO | No | 1.38.1 | Equivalencia IDN insegura |
| symfony/routing 8.0.12 | CVE-2026-48784 | MEDIO | No | 8.0.13 | Normalización de segmentos en URL generada |

### ESTADO ACTUAL Composer

Actualización focalizada: Laravel 13.11.2→13.29.0, Guzzle 7.10.3→8.1.0, PSR-7 2.10.1→3.1.0, CommonMark 2.8.2→2.10.0 y componentes Symfony afectados→8.1.5, junto con transitivas compatibles del lock. `composer validate`, `check-platform-reqs`, `composer audit` y la suite pasan. Resultado: **23 advisories / 7 paquetes → 0 advisories**.

### npm React — 9 paquetes afectados

Metadatos npm: **7 high, 1 moderate, 1 low**, 0 critical. Cuatro paquetes pertenecen al grafo runtime: Axios, form-data, react-router-dom y react-router.

| Paquete instalado | Directa/runtime | Advisories agrupados | Mínimo corregido | Impacto/prioridad |
| --- | --- | --- | --- | --- |
| axios 1.16.1 | Sí / runtime | GHSA-42h9, pmv8, jqh4, mmx7, f4gw, gcfj, hcpx, 7q8q, mwf2, xj6q | 1.18.0 | ALTO; cliente central de toda la API. Varias ramas son Node-only, pero prototype pollution/serialización ameritan actualización |
| form-data 4.0.5 | No / runtime vía Axios | GHSA-hmw2-7cc7-3qxx | 4.0.6 | ALTO en adaptador Node; menor exposición en navegador |
| react-router 7.15.1 | No / runtime | GHSA-wrjc, h8fp, 337j, chx6, qwww | 7.18.2 | ALTO; la app usa BrowserRouter, no RSC/SSR, pero open redirect/route matching siguen siendo relevantes |
| react-router-dom 7.15.1 | Sí / runtime | Hereda react-router | versión compatible ≥7.18.2 | ALTO por transitiva |
| postcss 8.5.15 | Sí / build | GHSA-fxqj-rqcc-2cmp, r28c-9q8g-f849 | >8.5.22 | ALTO; riesgo al procesar source maps no confiables |
| vite 8.0.14 | Sí / desarrollo/build | GHSA-v6wh-96g9-6wx3, fx2h-pf6j-xcff | >8.0.15 | ALTO; servidor dev en Windows, no debe exponerse en producción |
| brace-expansion 5.0.6 | No / tooling | GHSA-3jxr, mh99, rgw5 | 5.0.9 | ALTO DoS en inputs de glob no confiables; tooling |
| nanoid 3.3.12 | No / build | GHSA-28wg, 2v37 | 3.3.18 | ALTO; tamaños controlados por dependencias, no usuario |
| @babel/core 7.29.0 | No / build | GHSA-4x5r-pxfx-6jf8 | >7.29.0 | BAJO; lectura local de archivo vía source map durante build |

### ESTADO ACTUAL npm

Axios 1.20.0, React Router/DOM 7.18.3, Vite 8.2.2 y PostCSS 8.5.26 quedaron fijados; se actualizaron transitivas compatibles. React pasó de **9 vulnerabilidades a 0**. El frontend Blade ahora tiene `package-lock.json`, reporta 0 vulnerabilidades y el Dockerfile ejecuta `npm ci`; el build de imagen verificó esa etapa.

## 12. Frontend

### Aspectos a conservar

- Separación por feature, cliente API único, tipos centrales, React Hook Form/Zod y TanStack Query.
- `RoleRoute` ofrece UX razonable siempre que el backend siga siendo autoridad.
- El build estricto y ESLint quedan limpios sin `any`, `@ts-ignore` ni desactivar reglas.

### ESTADO ORIGINAL

- Dashboard e historial de funcionario chocaban con el 403 esperado; confirmación no recibía el radicado real.
- Reportes usaba camelCase contra snake_case y validación pública inventaba nombres incompatibles.
- Cargo, rango y funcionario eran submits no-op; generación, anulación y descarga llamaban rutas incorrectas o inexistentes.
- No existían tests React.

### CORREGIDO EN FASE A

- Dashboard restringido a dos modalidades/disponibilidad, confirmación con radicado real y anulación alineada a PATCH.

### ESTADO ACTUAL

- Reportes conserva `snake_case` y tiene contract test backend; validación pública consume exactamente la respuesta Laravel y presenta válido/no encontrado/anulado/integridad comprometida.
- Cargo, rango y funcionario ejecutan mutations reales con validación, 422, loading, invalidación, navegación y feedback.
- Generación usa POST dedicado; descarga usa Axios blob con Bearer, filename de `Content-Disposition`, Object URL y `revokeObjectURL`.
- Vitest + React Testing Library cubren modalidades/cupos, radicado, logout, validación pública y descarga protegida.
- Filtros visuales de algunos listados continúan limitados por su UX actual y no se añadió paginador completo.
- `SecretarioDashboardPage`/`HomeRedirect` y otros restos deben depurarse sólo tras confirmar su uso.
- Bundle principal actual de 540.59 kB (gzip 165.16 kB) conserva el warning de 500 kB; lazy loading queda pendiente.

Los únicos efectos temporizados propios (`useDebounce`) limpian correctamente el timeout. TanStack Query reduce el riesgo de updates tras unmount; no se encontraron intervalos o listeners sin cleanup.

### Por qué fallaba TypeScript

1. `Table` exigía `id: number`, aunque varios componentes trabajan con IDs `string | number`; se corrigió la restricción genérica.
2. Dashboards trataban `ApiResponse.data` como si contuviera otro wrapper `{data}`. La API real usa `{success,message,data:[...],meta}`; se retiró el nivel extra.
3. Los union types del frontend usaban estados históricos/alias que no coinciden con los enums reales Laravel. Se alinearon contra `EstadoSolicitudEnum`, `EstadoPagoEnum` y `EstadoCertificadoEnum`.
4. Había cinco imports sin uso en `FuncionarioDashboardPage`.

## 13. Backend

### Aspectos a conservar

- Form Requests en escrituras, Resources explícitos, casts a enum y Policies para entidades sensibles.
- Respuestas de listados incluyen metadata y las consultas principales usan eager loading, evitando N+1 evidentes.
- Token público se almacena hasheado y la respuesta pública no expone salario ni documento completo.
- Mensajes de login no distinguen usuario inexistente/contraseña.

### Deuda y defectos actuales

- Lógica de negocio/transiciones mezclada en `SolicitudCertificacionController` (272 líneas).
- Auditoría manual puede olvidarse al añadir caminos nuevos.
- Los flujos de generación/radicación/reemplazo de soporte ya son transaccionales; otros procesos multientidad requieren revisión caso por caso.
- La validación reutilizable y el envelope cubren las APIs modificadas, no garantizan todavía un contrato global/OpenAPI para endpoints futuros.
- Root Blade conserva welcome genérico con referencias a rutas `login/register` inexistentes; no afecta la API principal, pero es código residual.
- `PdfBasicoService` no soporta Unicode/layout multipágina ni documentos oficiales.
- No hay servicios para backups, colas, mail real, scheduler ni lifecycle de archivos/tokens.

## 14. Base de datos

La estructura sigue siendo de prototipo. Fase A añadió integridad mensual, Manual y snapshot; Fase B impuso cardinalidades usuario↔funcionario y pago↔solicitud, checks documentales y bloqueo aplicativo del hard delete con expediente. Fase C agregó el ciclo de cambio obligatorio, tokens temporales de descarga, órdenes de pago y búsquedas administrativas. Sigue pendiente definir retención, firma y operación productiva antes de poblar datos oficiales.

Fase A aplicó tres migraciones incrementales y Fase B una migración adicional. Fase C agregó `2026_08_31_000005_add_phase_c_self_service_foundation.php` y verificó índices, ausencia de huérfanos/duplicados y cero migraciones pendientes. La base principal local estaba vacía de registros de negocio. No se ejecutó `migrate:fresh`, wipe, truncate, eliminación masiva ni borrado de volúmenes; no se eliminó información.

## 15. Testing

**ESTADO ORIGINAL/Fase A:** 52 tests Laravel y 207 aserciones, sin infraestructura React.

**ESTADO ACTUAL:** 82 tests Laravel, 398 aserciones, todos verdes; 7 archivos Vitest con 11 tests React, todos verdes. Además de la cobertura de Fases A y B, Fase C cubre:

- Acceso privado a soporte por rol, headers/ruta física y lifecycle en éxito, fallo BD y fallo de delete antiguo.
- Token de 256 bits, PDF alterado/ausente, anulación explícita e integridad pública/descarga.
- Hard delete bloqueado con expediente y permitido temporalmente sin historial; cardinalidades UNIQUE.
- Paginación/filtros/orden inválidos, contrato de reporte snake_case y 500 API sin stack.
- Manual: fechas, orden positivo/único, publicación con funciones e inmutabilidad publicada.
- Salario ausente/ambiguo/rango ausente; SIN salario no resuelve salario.
- Snapshot obligatorio e inmutable.
- React: dos modalidades, bloqueo selectivo, radicado real, logout API, validación pública y descarga blob autenticada.
- Alta atómica de funcionario/cuenta/asignación, cambio obligatorio y reset con revocación de tokens.
- Expedición y descarga inmediata cuando el pago está apagado; orden pendiente sin certificado cuando está encendido.
- Inactividad, configuración auditada, titularidad de descarga y retiro del flujo manual nuevo.

Cobertura crítica todavía pendiente:

- Matriz completa rol-permiso-endpoint más allá del flujo funcionario cubierto.
- CRUD completo de cargos/funcionarios más allá de las reglas focales y todos los casos de cascada/FK.
- Concurrencia sostenida multiproceso para pago y expedición; la cuota mensual tiene constraint, bloqueo transaccional y simulación de colisión.
- Expiración/rotación/uso de tokens cuando exista regla institucional.
- Contrato uniforme global para endpoints no modificados y CORS de producción.
- Fecha salarial institucional definitiva y formato oficial del documento.
- E2E Playwright. Se aplazó para no introducir en esta fase servidor/fixtures/usuarios adicionales; Vitest/RTL y smoke HTTP cubren la base.

No se generó porcentaje porque no hay driver de cobertura configurado; la brecha más importante es de reglas, no de líneas.

## 16. Producción

### Brechas obligatorias

- `APP_ENV=production`, `APP_DEBUG=false`, APP_KEY/DB/secrets desde secret manager; rotación y separación por ambiente.
- HTTPS extremo a extremo, HSTS y cabeceras de seguridad/CSP; trusted proxies/hosts.
- CORS y Sanctum para dominios reales; decidir cookie HttpOnly o token de corta duración.
- Eliminar credenciales demo del bundle/seed productivo.
- PHP/Nginx imágenes separadas de desarrollo, sin bind mounts ni Vite dev server; servir build estático.
- Usuario de proceso no privilegiado y filesystem de solo lectura salvo storage/cache.
- PostgreSQL gestionado o endurecido, TLS, usuario de mínimo privilegio, backups cifrados y pruebas de restauración/PITR.
- Storage duradero, privado, respaldado, con retención, versionamiento, antivirus y control de integridad.
- Queue/worker y scheduler solo si se introducen tareas; hoy sync. Healthchecks deben probar app/DB, no solo socket.
- Logs estructurados y centralizados sin PII/secrets; monitoreo, alertas y trazabilidad inmutable.
- Rate limiting, límites de request, timeouts y protección contra abuso.
- `composer install --no-dev --classmap-authoritative`; locks npm para ambos builds; pipeline SAST/dependency/test/build.
- Resolver advisories y ejecutar pruebas de seguridad.
- Definir firma/documento oficial y validación jurídica antes de emitir.

El Compose actual es correcto para desarrollo y no debe convertirse sin diseño en plataforma productiva.

## 17. Deuda técnica

1. Contratos frontend/API no generados ni probados.
2. Estados/roles/permisos como strings repetidos entre capas.
3. Controlador de solicitudes con múltiples responsabilidades.
4. Flujos multientidad sin transacciones.
5. Auditoría manual incompleta.
6. Formularios y módulos placeholder visibles.
7. Código/pantallas muertos o documentación de sprint obsoleta.
8. Paginación y filtros no reutilizables.
9. No hay frontend tests ni pruebas de concurrencia/seguridad.
10. Backend assets sin `package-lock.json`.
11. Generador PDF casero no apto para documentos institucionales.
12. Sin estrategia de lifecycle, backup ni recuperación.

## 18. Hallazgos priorizados

| ID | Severidad | Área | Hallazgo | Evidencia | Impacto | Recomendación |
| --- | --- | --- | --- | --- | --- | --- |
| SIGN-001 | CRÍTICO | Firma | No existe firma ni evidencia del acto | Blade/servicios/modelos/rutas sin firma; sección 8 | Documento no atribuible ni verificable | Diseñar flujo legal/técnico de firma antes de producción |
| CERT-001 | CRÍTICO | Certificación | PDF explícitamente temporal y no oficial | `laboral.blade.php:1-24`, `PdfBasicoService.php` | Núcleo del producto no sirve para emisión oficial | Definir plantilla, datos, firma, QR y validación aprobados |
| AUTH-001 | ALTO | Autenticación | Token no expirante en localStorage | `authStorage.ts:7-27`, `sanctum.php:53` | Robo persistente ante XSS | Migrar a cookie HttpOnly o tokens breves y endurecer CSP |
| AUTH-002 | INFORMATIVO | Autenticación | **CORREGIDO:** logout React invoca API y limpia local en `finally` | `Header.tsx`, `auth.service.ts` | Token actual revocado al salir | Mantener prueba de regresión |
| AUTH-003 | INFORMATIVO | Autenticación | **CORREGIDO:** login limitado a 5 intentos/minuto por cédula+IP | `AppServiceProvider.php`, `LoginTest.php` | Reduce fuerza bruta básica | Añadir monitoreo/backoff distribuido en producción |
| AUTHZ-001 | INFORMATIVO | Autorización/UX | **CORREGIDO:** funcionario solo ve disponibilidad/radicación; no se otorgó lectura histórica | dashboard, router, navegación, seeder y tests 403 | Alcance alineado con regla confirmada | Conservar separación frente a futuros cambios UX |
| FILE-001 | INFORMATIVO | Archivos | **CORREGIDO EN FASE B:** soporte privado descargable por gestores autorizados | endpoint protegido, Policy, headers y tests | Evidencia accesible sin publicar storage | Mantener auditoría/antivirus futuro |
| CERT-002 | INFORMATIVO | Certificación | **CORREGIDO EN FASE B:** UI usa endpoint dedicado y backend decide transición | mutation, invalidación y tests/build | Generación administrativa operativa | Añadir E2E cuando exista fixture estable |
| CERT-003 | INFORMATIVO | Integridad | **CORREGIDO:** generación transaccional, `FOR UPDATE`, UNIQUE y compensación de archivo | `GenerarCertificadoService.php`, migración snapshot | Evita duplicados y estado parcial en generación | Extender patrón a otros flujos |
| CERT-004 | INFORMATIVO | Integridad | **CORREGIDO TÉCNICO EN FASE B:** SHA-256 se recalcula en descarga/validación | servicio específico, auditoría y tamper test | Alteración detectada y bloqueada | No confundir con firma; diseñar storage inmutable oficial |
| CERT-005 | ALTO | Negocio | Salario actual puede no representar periodo certificado | servicio 101-115; historial no usado | Certificación materialmente incorrecta | Regla temporal explícita y pruebas históricas |
| DB-001 | MEDIO | BD | **MITIGADO EN FASE B:** 409 si existe historial; sin historial conserva hard delete temporal | conteos relacionales, auditoría y tests | Cascada ordinaria bloqueada | Diseñar soft delete/retención legal antes de producción |
| DB-002 | BAJO | BD | **PARCIAL:** radicado/certificado/pago protegidos; otros flujos requieren revisión | locks, UNIQUE y lifecycle compensado | Riesgo residual acotado | Revisar procesos multientidad restantes |
| REQ-001 | INFORMATIVO | Regla mensual | **CORREGIDO:** cupo por funcionario, modalidad y mes calendario | `periodo_mes`, UNIQUE, servicio y tests agosto/septiembre | Regla concurrente garantizada | Validar institucionalmente estados que consumen cupo |
| MANUAL-001 | INFORMATIVO | Manual de Funciones | **CORREGIDO BASE:** fuente versionable por cargo, vigencia, acto y funciones ordenadas | 4 tablas, modelos, controlador y resolver | Permite trazabilidad estructural | Completar UX/carga de datos oficiales y gobierno documental |
| CERT-008 | INFORMATIVO | Trazabilidad | **CORREGIDO BASE:** snapshot JSONB esquema 1 y PDF temporal desde snapshot | migración, builder, service y prueba de inmutabilidad | Datos históricos no dependen de relaciones vivas | Ampliar al documento oficial y firma |
| DEP-001 | INFORMATIVO | Dependencias | **CORREGIDO EN FASE B:** Composer 23 advisories→0 | lock actualizado, validate/platform/audit/tests | Riesgos publicados corregidos al corte | Repetir auditoría en CI |
| DEP-002 | INFORMATIVO | Dependencias | **CORREGIDO EN FASE B:** npm React 9 vulnerabilidades→0 | lock focalizado, audit/build/lint/tests | Riesgos publicados corregidos al corte | Repetir auditoría en CI |
| BACK-001 | INFORMATIVO | API | **CORREGIDO:** ruta protegida sin Accept responde 401 JSON | `bootstrap/app.php`, prueba HTTP | Evita redirect/500 y fuga de stack | Mantener contract test |
| AUTH-004 | MEDIO | Autenticación | Token nunca expira | `sanctum.php:53` | Ventana indefinida | Expiración y política de renovación/revocación |
| AUTH-005 | INFORMATIVO | Autenticación | **CORREGIDO EN FASE B:** helpers demo sólo con `import.meta.env.DEV` | LoginForm y comprobación bundle productivo | Credenciales ausentes del build | Mantener seed sólo local |
| AUTHZ-002 | MEDIO | Autorización | Gestores acceden globalmente sin scope organizacional | Policies por permiso, sin dependencia | Acceso excesivo si hay separación interna | Validar modelo y añadir scope si aplica |
| IDOR-001 | INFORMATIVO | IDOR | **CORREGIDO EN RADICACIÓN:** prueba A/B y `funcionario_id` prohibido; endpoints internos 403 | `FaseASolicitudMensualTest.php` | Titular no manipulable | Repetir matriz en nuevos endpoints de archivos/documentos |
| FILE-002 | INFORMATIVO | Archivos | **CORREGIDO EN FASE B:** reemplazo compensado y auditado | service + tests happy/BD/delete antiguo | Registro nunca apunta a archivo revertido | Reconciliación global queda futura |
| CERT-006 | BAJO | Certificación | **PARCIAL:** nuevos tokens son 256 bits; expiración/uso sin regla activa | regression test; campos `null` documentados | Entropía corregida, vigencia pendiente | Definir política institucional antes de activar campos |
| CERT-007 | INFORMATIVO | Certificación | **CORREGIDO EN FASE B:** descarga blob autenticada | service/button test | Función UI operativa | Mantener manejo de 401/403/404/409 |
| FRONT-001 | INFORMATIVO | Frontend/API | **CORREGIDO EN FASE B:** contrato snake_case | DTO/component + contract test | Métricas alineadas | Mantener contrato |
| FRONT-002 | INFORMATIVO | Frontend/API | **CORREGIDO EN FASE B:** respuesta pública real y cuatro resultados | frontend/backend/tests | Mensajes inequívocos | Revisar datos públicos jurídicamente |
| FRONT-003 | INFORMATIVO | Frontend | **CORREGIDO EN FASE B:** tres formularios persisten y muestran 422/loading/éxito | mutations y build | CRUD aparente eliminado | Ampliar tests de formulario cuando convenga |
| FRONT-004 | INFORMATIVO | Frontend | **CORREGIDO:** confirmación usa radicado/modalidad/fecha del POST | formulario y confirmación | Usuario recibe referencia real | Añadir test de componente/E2E |
| FRONT-005 | MEDIO | Frontend | Sin paginación; filtros solo primera página | pages y metadata ignorada | Resultados incompletos | Paginación/filtros server-side |
| BACK-002 | INFORMATIVO | Validación | **CORREGIDO EN APIS MODIFICADAS:** request común, máximo 100 y allowlists | IndexQueryRequest + tests | Entrada acotada | Extender patrón a nuevos listados |
| BACK-003 | BAJO | Errores | **PARCIAL FASE B:** envelope común en APIs modificadas y no stack | handlers + contract test | Cliente más estable | Auditar endpoints heredados restantes |
| AUDIT-001 | BAJO | Auditoría | **PARCIAL FASE B:** acciones críticas ampliadas con old/new/IP/UA según soporte del modelo | controladores/services/tests | Mayor trazabilidad | Diseñar retención/inmutabilidad transversal |
| TEST-001 | BAJO | Testing | **PARCIAL FASE C:** backend ampliado y 11 tests React; E2E aplazado | PHPUnit, Vitest/RTL | Riesgo funcional reducido | Incorporar Playwright con fixtures aislados más adelante |
| PROD-001 | MEDIO | Producción | Compose expone Vite dev/debug y credenciales locales | compose líneas 31-46, Dockerfiles | Inseguro si se despliega tal cual | Artefactos e infraestructura productivos separados |
| DB-003 | INFORMATIVO | BD | **CORREGIDO EN FASE B:** dos cardinalidades hasOne impuestas tras comprobar cero duplicados | migración incremental e índices | Duplicados bloqueados | Mantener preflight en despliegues |
| CORS-001 | BAJO | CORS | Configuración solo localhost y methods/headers `*` | `config/cors.php` | No desplegable sin parametrizar | Orígenes explícitos por ambiente |
| SECRET-001 | BAJO | Repositorio | Archivo local Claude versionado | `.claude/settings.local.json` | Ruido/permisos locales compartidos | `.claude/` ignorado; desversionar luego |
| FRONT-006 | BAJO | Frontend | Bundle >500 kB y código muerto | build + router/import graph | Carga/mantenimiento | Lazy routes y eliminar tras comprobar |
| BACK-004 | BAJO | Calidad | Update unique compuesto puede usar campos ausentes | UpdateCargo/UpdateRango Requests | 500 por constraint en casos parciales | Resolver valores actuales antes de validar |
| DB-004 | BAJO | BD | Índices de filtros frecuentes ausentes | `pg_indexes` | Degradación al crecer | Medir EXPLAIN y añadir índices focalizados |
| TIME-001 | INFORMATIVO | Tiempo | **CORREGIDO:** `America/Bogota` y `periodo_mes` explícito | `config/app.php`, migración/servicio | Mes institucional determinista | Confirmar formalmente timezone antes de producción |
| INFO-001 | INFORMATIVO | SQL | No se confirmó SQL Injection | búsquedas Eloquent y raw constante | Control positivo | Mantener allowlists/bindings |
| INFO-002 | INFORMATIVO | Secretos | No se encontraron secretos reales versionados | escaneo Git/regex | Control positivo | Mantener scanning CI |
| INFO-003 | INFORMATIVO | Infraestructura | Docker de desarrollo es reproducible y saludable | Compose/healthchecks | Base útil | Conservar y no mezclar con producción |

## 19. Quick wins

Ya aplicados:

- Corrección de tipos Table/wrappers/estados reales sin `any` ni supresiones.
- Eliminación de imports sin usar.
- Aislamiento del guard en el test defectuoso.
- `.claude/` añadido a `.gitignore`.
- Estado conocido corregido en `DOCKER_SETUP.md`.

Quick wins completados en Fase A: logout real, frontend funcionario restringido, confirmación con radicado, throttle de login y 401 JSON. Completados en Fase B: paginación acotada, contratos de reportes/validación, generación/descarga administrativa, verificación de hash, tests React, actualización focalizada de dependencias y lockfile Blade.

Siguientes cambios de bajo riesgo recomendados:

1. Añadir paginación visual server-side a listados React.
2. Incorporar E2E con fixtures aislados cuando se defina su ejecución reproducible.
3. Añadir monitoreo/reconciliación de storage y alertas de integridad.
4. Depurar código muerto y dividir el bundle por rutas.

## 20. Cambios estructurales

- Modelo de firma/documento oficial y custodia de evidencia.
- Expediente inmutable/versionado; retención y soft delete.
- Transacciones, locks y constraints para flujos concurrentes.
- Autenticación SPA con cookies HttpOnly o tokens cortos.
- Servicio de documentos/PDF robusto y storage privado versionado.
- Auditoría transversal e inmutable.
- Contratos OpenAPI/JSON Schema y generación/validación de DTOs.
- Infraestructura productiva, backups/DR, observabilidad y pipeline seguro.

## 21. Roadmap recomendado

### Fase A — Bloqueantes

- Detener cualquier intención de emisión oficial con la plantilla temporal.
- **Completado:** alcance restringido del funcionario: radicar, confirmar y consultar solo disponibilidad por modalidad/mes.
- **Completado:** PostgreSQL/Laravel garantizan una solicitud por funcionario, modalidad y mes calendario.
- **Completado base:** Manual versionable reutilizando `cargos` y snapshot JSONB de emisión.
- **Completado Fase B:** endpoint de generación, descarga y visualización privada de soporte.
- **Completado/mitigado:** generación/radicación/pago atómicos; hard delete bloqueado con expediente.

### Fase B — Seguridad

- **Parcial:** rate limiting y logout real completados; pendientes expiración y estrategia HttpOnly/token corto.
- **Completado:** actualizaciones focalizadas Composer/npm y lockfiles auditados.
- **Completado en APIs modificadas:** 401/403/404/409/422/429 y 500 sin stack.
- **Parcial:** tests IDOR de radicación y archivo privado; pendientes CSP/CORS productivos y scope organizacional.

### Fase C — Integridad funcional

- **Completado:** autoservicio transaccional sin pago y orden pendiente sin certificado con pago.
- **Completado:** una modalidad CON y una SIN salario por mes, datos automáticos y snapshot inmutable.
- **Completado:** ciclo de cuenta inicial, cambio obligatorio, administración de funcionarios y parámetro de pago auditado.
- **Completado:** Secretaría retirada del camino crítico de nuevas expediciones.
- **Pendiente externo:** proveedor real, webhook autenticado/idempotente y conciliación.
- Documento oficial, QR y firma continúan pendientes; token robusto y verificación de hash técnica ya están completados.
- Diseñar firma automática, evidencia, revocación, versiones y trazabilidad con asesoría jurídica.

### Fase D — Calidad

- Ampliar contract tests, E2E y pruebas de concurrencia real.
- Extraer servicios de negocio/transacciones sin rediseño excesivo.
- Paginación visual, limpieza de código muerto y lazy loading.
- Auditoría automática con old/new y política de retención.

### Fase E — Producción

- Imágenes/artefactos productivos, secrets, HTTPS/proxies, DB/storage durables.
- Backups y restauración probados, observabilidad, alertas y runbooks.
- Pipeline con lint/build/tests/audits/SAST y gates.
- Prueba de carga, seguridad y aceptación institucional/jurídica antes de salida.

## Anexo A — Comandos de verificación ejecutados

```text
docker compose exec -T frontend npm run build
docker compose exec -T frontend npm run lint
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -e APP_ENV=testing -e CACHE_STORE=array -e SESSION_DRIVER=array -e DB_HOST=postgres -e DB_DATABASE=scl_db_test app php artisan test
docker compose exec -T app composer audit --format=json
docker compose exec -T frontend npm audit --json
docker run --rm -v "${PWD}/backend/laravel-app/package.json:/app/package.json:ro" -v "${PWD}/backend/laravel-app/package-lock.json:/app/package-lock.json:ro" -w /app node:24-alpine npm audit --json
docker compose exec -T app php artisan route:list --json
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan migrate --pretend
psql: inventario de tablas, constraints, índices y conteos
HTTP: matriz admin/secretario/funcionario/anónimo, logout y 65 intentos fallidos
Navegador local: login, panel y solicitudes de funcionario, logout
```

No se ejecutaron `migrate:fresh`, `db:wipe`, `docker compose down -v`, eliminaciones de volúmenes ni comandos equivalentes.

## Cierre de autoservicio real — 2026-09-20

Este apartado actualiza las conclusiones históricas anteriores. El proceso ordinario de certificación laboral es autoservicio automático, no un workflow de aprobación. Administrador y secretario administran fuentes; no revisan ni aprueban cada expedición y no necesitan estar conectados.

- Alta transaccional de funcionario, usuario/rol, asignación y relación normativa explícita. Cédula como login y contraseña temporal hasheada; cambio obligatorio protegido por backend y frontend. Reset revoca sesiones y exige nuevo cambio.
- Listado administrativo paginado, filtros, ficha normativa vigente, estado de acceso, activación/inactivación, detalle y restablecimiento. Hard delete deshabilitado también para registros sin historial.
- Pago desactivado: validaciones, snapshot, radicado, PDF y descarga inmediata propia. Pago activado: pendiente de pago sin PDF; contrato de pasarela sin proveedor ni confirmación desde el cliente.
- Se reforzó la compensación de archivos/BD: un fallo técnico antes de constituir certificado no deja una solicitud ocupando el cupo. Auditoría de éxito dentro de la transacción y fallo separado sin secretos. Se probaron reintentos tras fallo PDF y tras fallo posterior a escritura.
- Snapshot ampliado con datos de asignación y relación normativa; los anteriores permanecen intactos. La excepción sin fecha efectiva se limita al baseline interno 10; futuras publicaciones la requieren.
- Los estados y endpoints de aprobación manual quedaron obsoletos para nuevas certificaciones. Las transiciones de aprobar/rechazar/marcar pago responden 410. Se conservan registros y compatibilidad histórica de soportes/generación administrativa; no forman parte de la UX ni del camino crítico del autoservicio.
- Playwright encontró una URL de descarga con `/api/v1` duplicado; corregida y cubierta por tests de regresión. El recorrido final descargó un PDF real, con administrador desconectado.

Evidencia: backend **133 tests / 701 aserciones**; frontend **23 tests**; **1 E2E Chromium aprobado** con alta → logout admin → login temporal → cambio → solicitud SIN salario → PDF → logout. Incluye dos peticiones concurrentes CON salario/funciones: exactamente **201 y 409**. TypeScript/Vite, ESLint y Pint global (**183 archivos**) aprobados. Docker app/frontend/nginx/postgres healthy al cierre.

Tests backend en `scl_db_test`; E2E en `scl_e2e_test`, con storage separado y fixture protegido contra otra base. Sin pruebas de escritura en `scl_db`, sin migraciones destructivas ni pérdida de datos. Verificación ordinaria: **344 fichas, 3.115 funciones, 2.326 conocimientos**, tres snapshots históricos sin cambio de checksum. MF-0167 permanece bloqueada individualmente.

Deuda vigente: proveedor/webhook y expiración de órdenes de pago, firma/oficialización automática (no implementadas), endurecimiento productivo, recuperación idempotente de respuesta perdida después de una expedición válida y prueba de carga del bloqueo anual de radicación. Persiste advertencia de bundle >500 kB. Auditoría npm productiva sin vulnerabilidades al consultarse; la auditoría completa posterior devolvió 503 del registro, tras avisar instalación de tres vulnerabilidades de desarrollo. No se hicieron upgrades masivos.

Detalle de endpoints, archivos, migraciones reutilizadas, límites y reproducción en `AUTOSERVICE_IMPLEMENTATION_REPORT.md`. Ejecutar E2E con `./scripts/test-autoservice-e2e.ps1`; conserva fixtures/evidencia y detiene únicamente servicios E2E.

## Corrección de regresión de autorización administrativa — 2026-09-21

Se corrigió una contaminación entre el caché de permisos del entorno ordinario y la suite backend. Aunque el admin real tenía rol ID 241 y 39 permisos, Spatie cargaba IDs antiguos o transaccionales de `scl_db_test`; `can()` devolvía false y los endpoints administrativos producían 403. PHPUnit ahora fuerza `CACHE_STORE=array` desde `tests/bootstrap.php`, el arranque del contenedor invalida el caché persistente y el seeder idempotente invalida después de `syncPermissions`.

No se relajaron Policies, roles ni permisos. `/auth/me` permanece solo con `auth:sanctum`, disponible durante cambio obligatorio. El frontend revalida el token con `/auth/me` antes de montar rutas/queries privadas y no trata 403 como logout. Evidencia actual: backend 140 pruebas/739 aserciones, frontend 25 pruebas, 2 E2E reales, matriz admin 200, funcionario 403 administrativo + disponibilidad 200, y secretario limitado a sus 17 permisos. Sin operaciones destructivas ni cambios de datos funcionales.
