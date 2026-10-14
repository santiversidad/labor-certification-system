# CT: bootstrap del Manual de Funciones

Fecha: 2026-09-24. Rama: `feat/certificate-types`. Alcance: solo `C:/Users/mondr/source/labor-certification-recovery` y base destino `scl_certificate_types_dev`. Fase 3A no iniciada.

## 1. Causa y estado antes

El **schema fresh install** de CT había migrado las tablas del Manual, pero no sus datos. `DatabaseSeeder` ejecuta `RolesPermisosSeeder`, `UsuariosInicialesSeeder`, `DatosEjemploSeeder` y `ParametrosSistemaSeeder`; no llama a `manual:import`. El JSON original completo está versionado en `backend/laravel-app/database/data/manual_funciones_decreto_015_2023.json`, pero ninguna migración ni seeder lo importa. `manual:import` crea contenido en borrador y `manual:publish` es un paso distinto. Por tanto, la causa es **A + B + C**: importador y fuente presentes, bootstrap omitido, mientras los tests usan fixtures pequeños. **D no aplica**: recovery no es la única fuente completa. La migración de Certificate Types no alteró el dominio del Manual.

La inspección se hizo antes de escribir, con transacciones `READ ONLY` y nombres obtenidos de `information_schema`. CT tenía 1 cargo, 1 funcionario sintético, 1 relación `funcionario_cargo` sin ficha, 0 manuales, 0 versiones, 0 fichas, 0 funciones, 0 conocimientos, 0 importaciones y 0 relaciones normativas. No había conflicto de ID de versión ni de cargos; el cargo preexistente `219/02` era compatible con la fuente. Ese funcionario y sus datos se preservaron.

## 2. Fuente y mecanismo

Se inspeccionó `scl_recovery_db` **solo en lectura**. Conserva la versión ID **10**, `Decreto 1000-24/015 de 2023`, `publicado`, 344 fichas, 3.115 funciones y 2.326 conocimientos. El SHA-256 de sus dos importaciones registradas y del JSON versionado es `261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e`.

El comando `manual:bootstrap-development --actor-id=1` usa el importador oficial `ImportarManualFuncionesService` y el publicador oficial `PublicarVersionManualService`. Está restringido a `APP_ENV=local` y al nombre exacto `scl_certificate_types_dev`. Comprueba el hash de la fuente, que el actor sea administrador CT y que todas las tablas del Manual estén vacías. Ejecuta el dry-run del importador antes de escribir; un cargo incompatible o cualquier error detiene la carga. En una transacción crea la raíz y la versión borrador ID 10, importa la fuente, comprueba los conteos y la única ficha incompleta conocida, publica por el flujo de dominio y ajusta la secuencia de versiones. Una segunda ejecución se rechazó con `MANUAL_BOOTSTRAP_DESTINO_NO_VACIO` sin cambiar los datos.

El administrador CT ID 1 es actor de esta **nueva** publicación de desarrollo; no se copió el usuario de recovery ID 172 ni su auditoría histórica. La fecha de publicación CT es nueva. Se conservan los `source_id`, `import_key`, `natural_key`, `content_hash`, contenido JSON, orden de funciones y SHA de fuente. El digest agregado por `source_id` + `content_hash` fue `2ccdb15add1bedbe5c83aa978cf4fa45` en ambas bases; el digest de funciones ordenadas por fuente y `orden` fue `adc3566f7f6d92b44f95124381a95648` en ambas. No se inventaron fichas ni se usó el JSON reconciliado de propuesta no aplicada.

## 3. Tablas y conteos

| Entidad real | CT antes | CT después del bootstrap | Recovery, lectura |
| --- | ---: | ---: | ---: |
| `manuales_funciones` | 0 | 1 | 1 |
| `manual_funciones_versiones` | 0 | 1 | 1 |
| `manual_cargo_versiones` | 0 | **344** | **344** |
| `manual_funciones_esenciales` | 0 | **3.115** | **3.115** |
| `manual_cargo_versiones.metadata_manual->conocimientos` | 0 | **2.326** | **2.326** |
| `manual_importaciones` | 0 | 1 nueva importación CT | 2 históricas |
| `cargos` | 1 | 50 | 50 |
| `manual_funciones_comunes_nivel` | 0 | 0 | 0 |
| `manual_cargo_lineages` | 0 | 0 | 0 |
| `manual_actualizaciones_asignaciones` | 0 | 0 | 0 |
| `funcionario_cargo_manual_fichas` | 0 | 0 antes de las pruebas manuales | 3 de recovery, no copiadas |

El bootstrap escribió solo en `cargos`, `manuales_funciones`, `manual_funciones_versiones`, `manual_cargo_versiones`, `manual_funciones_esenciales`, `manual_importaciones` y una nueva fila de `audit_logs` por publicación. No importó usuarios, funcionarios, asignaciones laborales, solicitudes, certificados, tokens, auditorías históricas ni parámetros de recovery. Las tablas `manual_cargo_lineages` y `manual_funciones_comunes_nivel` están vacías también en la fuente validada: no hay relaciones interversión adicionales que copiar.

La versión CT ID 10 está publicada y es la única versión. Sus 344 `source_id` son distintos, ninguno carece de hashes o claves de trazabilidad, no hay funciones huérfanas y cada ficha tiene `orden` consecutivo desde 1. `manual_funciones_versiones_id_seq` quedó en 10 para que la próxima versión no colisione. MF-0167 mantiene área y propósito vacíos, con 8 conocimientos; se conserva como ficha incompleta del Manual publicado.

La pantalla administrativa CT `/admin/manual-funciones` se abrió en Edge contra los servicios reales y muestra “Manual vigente”, `Decreto 1000-24/015 de 2023`, 344 fichas y 3.115 funciones.

## 4. Prueba de funcionario y certificado reales en CT desarrollo

El script opcional `scripts/manual_ct_dev_smoke.mjs` usó el frontend CT en `localhost:3200`, la API CT en `localhost:8280`, Edge y el administrador sintético CT. Buscó cargos y fichas por código/grado y `source_id` en la API, las seleccionó en el formulario de alta y creó cuatro funcionarios ficticios en dos ejecuciones exitosas. El funcionario original CT `000000003` y su asignación sin ficha permanecieron intactos. Los cuatro nuevos tienen `FuncionarioCargo` y `FuncionarioCargoManualFicha` con la ficha explícita correspondiente. No se importó ningún funcionario de recovery.

- Caso completo: ficha real **MF-0002**, cargo `020/02`. Tras login y cambio obligatorio de contraseña, `sencillo` devolvió HTTP 201 y `generada`. `funciones` devolvió HTTP 201 y `generada`; el snapshot identifica MF-0002 e incluye sus **11 funciones** en orden. La descarga devolvió HTTP 200 y firma `%PDF-`.
- Caso incompleto separado: ficha real **MF-0167**, cargo `219/07`, vinculada explícitamente aunque la API indica que no es certificable para funciones. Tras login y cambio de contraseña, `sencillo` devolvió HTTP 201 y `generada`. `funciones` devolvió HTTP 409 con `MANUAL_FICHA_INCOMPLETA`; no consumió ese cupo ni generó certificado.

Al cierre CT desarrollo tiene 5 funcionarios: 1 previo más 4 ficticios creados por estas pruebas, 5 relaciones laborales y 4 relaciones normativas nuevas. Hay 7 certificados, de los cuales 1 ya existía antes del trabajo y 6 provienen de las dos ejecuciones de prueba. Los datos de prueba son sintéticos y permanecen en CT desarrollo.

## 5. Integridad de Certificate Types y pruebas

La restricción de `solicitudes_certificacion.tipo_certificado` acepta exactamente `sencillo` y `funciones`; la cuota única sigue por funcionario, mes y tipo. No existen columnas `requiere_salario` ni `salario_override` ni tabla `rangos_salariales`. La búsqueda de código frontend activo, excluyendo archivos históricos `-camilo_ortega` y tests negativos, dio **0 referencias salariales**. El admin CT conserva **36 permisos** (confirmados en BD y login).

| Validación | Resultado |
| --- | --- |
| Backend | 153 pruebas, 777 aserciones, pasan en `scl_certificate_types_test` |
| Frontend Vitest | 30/30 pasan en contenedor Node CT |
| Playwright E2E | 7/7 pasan con fixtures sintéticos en `scl_certificate_types_e2e` |
| ESLint | pasa |
| TypeScript | pasa |
| Build Vite | pasa |
| Smoke manual real CT | MF-0002: sencillo y funciones generados; MF-0167: sencillo generado, funciones bloqueado |

La E2E aislada se migró de nuevo y se preparó con `e2e:prepare-autoservice` para evitar cupos consumidos por una corrida previa. No se ejecutó `migrate:fresh`, `db:wipe` ni `TRUNCATE` en CT desarrollo.
Un primer intento de Vitest en el host con Node 25 falló por el `localStorage` del runtime de ese host; la misma suite pasó completa en el contenedor Node del proyecto. No se cambió código de aplicación por esa diferencia de entorno.

## 6. Fresh install y permanencia

Orden para una base CT desarrollo nueva: **`migrate` → `db:seed` (datos estructurales y sintéticos) → `manual:bootstrap-development --actor-id=<admin CT>`**. `migrate` por sí solo no instala el Manual. El procedimiento detallado está en `CERTIFICATE_TYPES_ENVIRONMENT.md`. Las bases de pruebas no cargan 344 fichas; mantienen fixtures pequeños. El comando debe permanecer como bootstrap restringido de desarrollo mientras no exista una fuente oficial importable que reproduzca, además del contenido, la publicación institucional. Usa el JSON versionado y servicios de dominio existentes; no depende de conectarse a recovery.

Archivos modificados: `backend/laravel-app/app/Console/Commands/BootstrapManualDesarrolloCommand.php`, `scripts/manual_ct_dev_smoke.mjs`, `backend/laravel-app/database/seeders/DatabaseSeeder.php` (solo comentario), `backend/laravel-app/README.md`, `CERTIFICATE_TYPES_ENVIRONMENT.md` y este informe. No se alteró código de Certificate Types ni se inició Fase 3A.
