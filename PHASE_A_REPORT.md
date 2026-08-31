# Fase A — Informe de implementación

**Proyecto:** `labor-certification-system`  
**Fecha:** 2026-08-30  
**Estado general:** implementada y verificada en Docker.  
**Alcance documental:** este informe registra el comportamiento técnico implementado; no declara validez jurídica ni aptitud para producción.

## 1. Resultado ejecutivo

La Fase A implementa una línea base funcional para la radicación restringida del funcionario y prepara la generación documental trazable:

- una solicitud **CON salario** y una **SIN salario** por funcionario y mes calendario;
- modalidad explícita y obligatoria mediante `requiere_salario`;
- autoridad de identidad en la sesión autenticada;
- constraint PostgreSQL para concurrencia;
- endpoint limitado de disponibilidad;
- dashboard sin expediente ni historial;
- Manual Específico de Funciones estructurado, versionable y vinculado a `cargos`;
- resolución centralizada de funciones y salario;
- snapshot JSONB inmutable para el certificado temporal;
- logout real, rate limit de login y 401 JSON consistente.

La suite pasó de **38 pruebas / 123 aserciones** a **52 pruebas / 207 aserciones**, sin fallos. `npm run build` y `npm run lint` terminan correctamente.

## 2. Reglas de negocio confirmadas

1. Cada funcionario puede radicar como máximo una certificación CON salario y una SIN salario por mes calendario.
2. Los cupos son independientes; puede radicar ambas modalidades en el mismo mes, pero no repetir una modalidad.
3. `requiere_salario` es obligatorio. Omitirlo o enviar un valor inválido produce 422.
4. Una solicitud radicada consume el cupo de su modalidad aunque luego sea rechazada, cancelada, anulada, no procesada o no genere certificado.
5. La regla anterior es una **regla provisional confirmada para implementación, susceptible de revisión institucional posterior**.
6. El funcionario solo inicia sesión, consulta disponibilidad, radica, recibe el radicado y cierra sesión; no consulta expediente ni historial.
7. El titular se obtiene de `usuario autenticado → funcionario asociado`; `funcionario_id` está prohibido en el POST público del funcionario.
8. Las funciones provienen de una versión vigente del Manual Específico de Funciones asociada al cargo.
9. El salario proviene de fuentes institucionales estructuradas; nunca se recibe como texto o valor libre en la solicitud.
10. Un certificado generado conserva un snapshot y deja de depender de datos vivos.
11. El Manual conserva versiones, vigencias y referencia estructurada al acto administrativo.
12. La firma oficial continúa pendiente y no fue simulada ni implementada.

## 3. Regla mensual e integridad concurrente

La migración `2026_08_30_000001_add_periodo_mes_to_solicitudes_certificacion.php` añadió:

- `solicitudes_certificacion.periodo_mes DATE NOT NULL`;
- backfill no destructivo desde el mes de `created_at`;
- `CHECK (EXTRACT(DAY FROM periodo_mes) = 1)`;
- `UNIQUE (funcionario_id, periodo_mes, requiere_salario)`.

El periodo se calcula en Laravel con `America/Bogota` y siempre almacena el primer día del mes. Los timestamps siguen bajo el manejo normal Laravel/PostgreSQL; la clasificación del mes de negocio usa explícitamente la timezone institucional configurada.

La radicación sigue este flujo:

1. valida el contrato y prohíbe `funcionario_id`;
2. resuelve el funcionario desde Sanctum;
3. consulta previamente el cupo para una respuesta amigable;
4. abre una transacción;
5. serializa el consecutivo anual del radicado con `pg_advisory_xact_lock`;
6. intenta insertar;
7. PostgreSQL resuelve cualquier carrera mediante el UNIQUE;
8. una colisión `23505` del constraint mensual se convierte en `409`, código `MONTHLY_CERTIFICATE_LIMIT`, nunca en 500.

Prueba confirmada con tiempo congelado:

| Escenario | Resultado |
| --- | --- |
| A, agosto, primera SIN | 201 |
| A, agosto, segunda SIN | 409 |
| A, agosto, primera CON | 201 |
| A, agosto, segunda CON | 409 |
| B, agosto, SIN y CON | ambas 201 |
| A, septiembre, SIN y CON | ambas 201 |
| A rechaza SIN e intenta otra SIN en agosto | 409 |
| A rechaza SIN e intenta CON en agosto | 201 |

## 4. Identidad, IDOR y alcance del funcionario

`StoreSolicitudCertificacionRequest` aplica `prohibited` a `funcionario_id`. El controlador solo usa `$request->user()->funcionario`.

Se verificó con funcionarios A y B que A no puede radicar para B. También se verificaron respuestas 403 para el rol funcionario en:

- funcionarios y expediente individual;
- cargos administrativos;
- rangos salariales;
- actuaciones administrativas;
- reportes;
- auditoría;
- listado de solicitudes.

El rol funcionario conserva únicamente `solicitudes.crear`. Se retiró `pagos.cargar`, coherente con el alcance confirmado. No se asignó `solicitudes.ver`.

## 5. Endpoint de disponibilidad

Se añadió:

```text
GET /api/v1/mi-certificacion/disponibilidad
```

Requiere `auth:sanctum`, permiso de creación y asociación a funcionario. Devuelve solo:

- periodo;
- disponibilidad CON salario;
- disponibilidad SIN salario;
- próxima fecha disponible por modalidad.

No expone IDs, solicitudes, salario, cargo, actuaciones ni historial.

## 6. Frontend funcionario

El dashboard ahora presenta dos acciones independientes:

- certificación laboral SIN salario;
- certificación laboral CON salario.

Cada acción informa disponibilidad y próxima fecha. El formulario mantiene `tipo_certificado=laboral`, exige una modalidad booleana real y usa la respuesta del POST para mostrar:

- solicitud recibida;
- modalidad;
- radicado real;
- fecha de radicación.

Se retiraron de las rutas/navegación del funcionario:

- “Mis solicitudes”;
- detalle histórico de solicitudes;
- métricas e historial.

Las pantallas administrativas de solicitudes se conservan. El botón Salir invoca `/auth/logout` y limpia el almacenamiento local en `finally`.

## 7. Manual Específico de Funciones

La migración `2026_08_30_000002_create_manual_funciones_tables.php` creó:

| Tabla | Propósito |
| --- | --- |
| `manuales_funciones` | identidad del manual institucional |
| `manual_funciones_versiones` | versión, vigencias, estado y acto administrativo |
| `manual_cargo_versiones` | contenido del cargo dentro de una versión |
| `manual_funciones_esenciales` | funciones ordenadas, una por fila |

`cargos` continúa siendo el catálogo canónico; no se creó un catálogo paralelo.

Las versiones publicadas/inactivas se tratan como inmutables desde la API. La publicación rechaza solapamientos de vigencia para un mismo cargo. `ResolverFuncionesCargoService` resuelve:

```text
cargo + fecha → única versión publicada vigente → propósito/requisitos/funciones
```

Si hay más de una versión aplicable, lanza una inconsistencia de dominio en vez de escoger arbitrariamente.

Endpoints añadidos:

```text
GET  /api/v1/manual-funciones
POST /api/v1/manual-funciones
POST /api/v1/manual-funciones/{manual}/versiones
PUT  /api/v1/manual-funciones/versiones/{version}
PUT  /api/v1/manual-funciones/versiones/{version}/cargos/{cargo}
POST /api/v1/manual-funciones/versiones/{version}/publicar
```

Permisos nuevos:

- `manual_funciones.ver`
- `manual_funciones.crear`
- `manual_funciones.editar`
- `manual_funciones.publicar`

Admin los recibe mediante su matriz de todos los permisos. No se asignaron al funcionario ni al secretario.

## 8. Resolución salarial

`ResolverSalarioFuncionarioService` centraliza:

```text
funcionario + fecha de referencia
→ asignación funcionario_cargo vigente
→ cargo
→ salario_override de la asignación, si existe
→ o rango salarial activo por código, grado y año de referencia
```

Si hay dos asignaciones vigentes simultáneas, se informa inconsistencia y no se elige una al azar.

**Decisión temporal:** la fecha de generación se usa como referencia salarial. Esta regla y el uso institucional permitido de `salario_override` deben validarse con la Alcaldía antes del certificado oficial. La regla está encapsulada para poder cambiarla sin dispersar `now()->year`.

La modalidad SIN salario no invoca el resolver ni guarda un valor salarial certificable.

`TipoCertificadoEnum` se conserva por compatibilidad histórica. Los valores heredados `salario` y `laboral_salario` ya no se aceptan para nuevas radicaciones; `laboral`/`funciones` describen el contenido documental y `requiere_salario` es la única fuente de verdad de la modalidad salarial. No se borraron valores ni datos históricos.

## 9. Snapshot y certificado temporal

La migración `2026_08_30_000003_add_snapshot_to_certificados.php` añadió:

- `snapshot_schema_version SMALLINT NULL`;
- `snapshot_datos JSONB NULL`;
- `UNIQUE (solicitud_certificacion_id)`.

Los registros anteriores pueden permanecer sin snapshot; los nuevos certificados generados usan esquema 1. El backend conserva funcionario, cargo, modalidad, fecha, generador, salario/fuente solo si aplica y Manual/funciones cuando el tipo lo requiere.

`GenerarCertificadoService` ahora:

- bloquea la solicitud con `FOR UPDATE`;
- opera en transacción;
- compensa el archivo si falla la BD;
- impide un segundo certificado por solicitud con constraint;
- genera el PDF desde `snapshot_datos`, no desde relaciones vivas;
- usa tokens aleatorios de 256 bits.

La prueba modifica posteriormente denominación, rango salarial y función del Manual y confirma que el snapshot original no cambia. También confirma que el PDF SIN salario no contiene el bloque salarial.

El documento sigue identificado como **CERTIFICADO LABORAL TEMPORAL**. No se implementó firma, PKI, sello de tiempo, QR institucional ni declaración de validez oficial.

## 10. Seguridad rápida

| Control | Estado |
| --- | --- |
| Logout React revoca token y limpia local | CORREGIDO |
| Login limitado a 5 intentos/minuto por cédula + IP | CORREGIDO |
| Ruta API sin `Accept: application/json` devuelve 401 JSON | CORREGIDO |
| Token almacenado en `localStorage` | PENDIENTE |
| Expiración/rotación de tokens | PENDIENTE |
| Dependencias vulnerables | PENDIENTE |

## 11. Migraciones sobre la base principal

Antes de migrar `scl_db` se verificó nuevamente:

- solicitudes: 0;
- certificados: 0;
- duplicados mensuales: 0;
- certificados duplicados por solicitud: 0.

Las tres migraciones se aplicaron como batch 2 y quedaron `Ran`. No se borraron registros, tablas previas ni volúmenes.

## 12. Verificación

| Control | Antes | Después |
| --- | --- | --- |
| Laravel | 38 pruebas / 123 aserciones | 52 pruebas / 207 aserciones, 0 fallos |
| ESLint | verde tras auditoría inicial | verde, 0 errores/advertencias |
| React build | verde tras auditoría inicial | verde, 2020 módulos, bundle principal 529.02 kB |
| HTTP protegido sin token | 500 sin Accept en auditoría | 401 JSON |
| Contenedores | funcionales | app/frontend/nginx/postgres healthy |

Comandos finales relevantes:

```text
docker compose exec -e APP_ENV=testing -e CACHE_STORE=array -e SESSION_DRIVER=array -e DB_HOST=postgres -e DB_DATABASE=scl_db_test app php artisan test
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
docker compose exec app composer audit --locked
docker compose exec frontend npm audit
```

## 13. Auditorías de dependencias

- Composer: **23 advisories en 7 paquetes**; no se ejecutó `composer update`.
- npm completo: **9 vulnerabilidades** (1 baja, 1 moderada, 7 altas); de ellas, el árbol runtime (`--omit=dev`) reporta **4** (1 moderada, 3 altas) en Axios/form-data/React Router. No se ejecutó `npm audit fix`.
- La actualización focalizada y sus regresiones quedan para una fase posterior autorizada.

## 14. Archivos principales creados

- 3 migraciones Fase A.
- 4 modelos del Manual.
- 4 servicios de dominio: disponibilidad, funciones, salario y snapshot.
- 2 controladores: disponibilidad y Manual.
- 3 suites feature nuevas: mensualidad/IDOR, Manual y snapshot.

El detalle completo de archivos modificados puede obtenerse con `git status --short` y `git diff --stat`.

## 15. Hallazgos cerrados y parciales

### CORREGIDO

- `REQ-001`: regla mensual por modalidad y concurrencia.
- `AUTHZ-001`: UX de funcionario restringida, sin conceder lectura histórica.
- `AUTH-002`: logout real.
- `AUTH-003`: rate limit de login.
- `BACK-001`: 401 JSON sin Accept.
- `FRONT-004`: confirmación usa radicado backend.
- `MANUAL-001`: estructura versionable base.
- `CERT-008`: snapshot versionado.
- `IDOR-001`: prueba con dos funcionarios para radicación y endpoints internos.

### PARCIAL

- `CERT-003` / `DB-002`: radicación y generación son atómicas y tienen constraints; otros flujos de pago y borrado requieren hardening adicional.
- `CERT-005`: resolver salarial centralizado, pendiente validar fecha y `salario_override` institucionalmente.
- `TEST-001`: reglas críticas Fase A cubiertas; sigue sin haber tests de componentes React.

### PENDIENTE

- firma oficial y evidencia jurídica;
- PDF/plantilla oficial;
- estrategia HttpOnly o tokens breves;
- expiración de Sanctum;
- hard delete y retención legal;
- verificación del hash al descargar/validar;
- dependencia vulnerable y bundle frontend grande;
- fecha de referencia salarial definitiva;
- alcance final de certificados de funciones;
- interfaz administrativa completa del Manual.

## 16. Confirmación de no destrucción

No se ejecutaron `migrate:fresh`, `db:wipe`, `docker compose down -v`, eliminación de volúmenes, truncados, borrados masivos ni comandos equivalentes. Las migraciones fueron incrementales y compatibles con los datos inspeccionados.
