# Informe de versionado del Manual de Funciones

Fecha de implementación y verificación: 2026-09-05 (America/Bogota).

## 1. Versión vigente

La versión interna **10**, identificada como **Decreto 1000-24/015 de 2023**, quedó en estado de dominio `publicado` y es el Manual vigente del sistema.

- Acto: Decreto `1000-24/015`.
- `vigencia_desde`: `null`, porque la fuente disponible no documenta de forma suficiente la fecha de expedición. No se inventó una fecha.
- `vigencia_hasta`: `null`, con la regla funcional expresa **“vigente hasta que una nueva versión la sustituya”**.
- Publicado por: usuario administrador ID `172`.
- Publicación formal: `2026-09-05T10:31:19-05:00`.
- Auditoría: acción `publicar_manual_funciones`, estado anterior `borrador`, estado nuevo `publicado`, versión, acto, actor, fecha y preflight completo.

El comando usado fue el flujo de dominio:

```text
php artisan manual:publish --version-id=10 --user-id=172 --adopt-current
```

No se ejecutó un `UPDATE` manual para publicar.

## 2. Arquitectura de versiones

`manual_funciones_versiones` sigue siendo la raíz versionada. Una actualización futura crea otra fila `borrador`; nunca reutiliza el ID 10. La versión publicada conserva su contenido y puede cerrar su intervalo de vigencia cuando una sucesora entra en vigor.

La migración incremental `2026_09_05_000001_add_manual_versioning_workflow.php` añadió:

- `published_by` y `published_at` en la versión;
- `manual_cargo_lineages` para antecesora/sucesora;
- `funcionario_cargo_manual_fichas` para vigencia normativa separada de la relación laboral;
- `manual_actualizaciones_asignaciones` para el mapa controlado de funcionarios;
- índice único para impedir dos versiones publicadas abiertas del mismo Manual;
- triggers PostgreSQL que bloquean inserción, edición o eliminación de fichas/funciones publicadas y cambios del catálogo de cargo que alterarían una ficha publicada.

Las 3 relaciones explícitas existentes se copiaron, sin destruirlas, a `funcionario_cargo_manual_fichas`.

## 3. Lineage

`manual_cargo_lineages` relaciona `predecessor_id` con `successor_id`, conserva clasificación (`SIN_CAMBIOS` o `MODIFICADA`), puntaje, diferencias, estado de revisión, revisor y fecha.

La comparación entre versiones no utiliza `source_id` como criterio. Los `source_id` permanecen como trazabilidad técnica dentro de cada fuente. El matching usa fingerprint canónico de contenido y un puntaje sobre código, grado, denominación, dependencia, área, propósito, nivel, funciones, conocimientos y metadata.

## 4. Actualización futura

Flujo soportado:

1. Crear una versión `borrador`.
2. Cargar JSON o XLSX.
3. Ejecutar dry-run.
4. Comparar la versión vigente con el borrador.
5. Revisar `SIN_CAMBIOS`, `MODIFICADA`, `NUEVA`, `RETIRADA` y `AMBIGUA`.
6. Generar el mapa de funcionarios.
7. Resolver los casos administrativos necesarios.
8. Publicar con fecha efectiva.

Comando de comparación:

```text
php artisan manual:diff --from=10 --to=11
```

La publicación futura exige `vigencia_desde`, cierra la versión anterior el día previo, impide dos versiones abiertas simultáneas y no importa datos de una versión futura inexistente.

## 5. Asignaciones

`funcionario_cargo` continúa representando el hecho laboral: cargo, vinculación, encargo, acto y fechas laborales. `funcionario_cargo_manual_fichas` representa qué ficha normativa aplica a ese mismo hecho en cada intervalo.

Esta separación evita registrar una posesión, traslado o cambio de cargo ficticio cuando solo cambia el Manual. Una migración normativa cierra la relación de ficha anterior y crea la relación de ficha nueva; no cambia `funcionario_cargo.fecha_inicio`, `fecha_fin` ni crea un movimiento laboral.

El mapa de actualización clasifica:

- `AUTO_MIGRABLE`: coincidencia inequívoca sin cambio de dependencia/área;
- `REQUIERE_REVISION`: ambigüedad o cambio organizacional;
- `SIN_EQUIVALENTE`: ficha retirada sin sucesora.

Los casos no resueltos no reciben ficha nueva. Desde la fecha efectiva el resolver devuelve `MANUAL_FICHA_NO_ASIGNADA`; nunca cae automáticamente a la versión anterior ni selecciona por código/grado.

## 6. Diferencias normativas versus laborales

Una nueva función, propósito o ficha es un cambio normativo. Solo un acto real sobre el funcionario es un cambio laboral. La tabla normativa permite ambos historiales independientes y permite resolver una fecha de referencia histórica sin pedir al funcionario que seleccione Manual o ficha.

## 7. Publicación

`PublicarVersionManualService` ejecuta una transacción con bloqueo de concurrencia, preflight, comparación, mapa de asignaciones, cierre de vigencia anterior, alta de relaciones normativas automigrables, publicación y auditoría.

La API administrativa expone:

- `GET /api/v1/manual-funciones/estado`
- `POST /api/v1/manual-funciones/{manual}/versiones`
- `POST /api/v1/manual-funciones/versiones/{version}/importar`
- `GET /api/v1/manual-funciones/diff/{from}/{to}`
- `POST /api/v1/manual-funciones/planificar/{from}/{to}`
- `POST /api/v1/manual-funciones/actualizaciones-asignaciones/{actualizacion}/resolver`
- `POST /api/v1/manual-funciones/versiones/{version}/publicar`

## 8. Rollback lógico

No existe rollback destructivo automático para el versionado. Ante un error institucional se crea una nueva versión o acto de rectificación y se conserva el lineage. La migración rechaza `down()` deliberadamente para evitar pérdida de historia.

El backup previo a la publicación está en `output/manual_v10_pre_publish_20260905.dump` (formato custom de PostgreSQL), SHA-256:

```text
4FF0674689733A258AACE47CED8E63FA45E165CC5888BD067F28A3B694BD8298
```

## 9. Certificados históricos

Los certificados continúan usando snapshot schema v2. La publicación no modificó ninguna fila de `certificados`, snapshot, PDF ni hash. El checksum agregado antes y después fue idéntico:

```text
f82f955727bde72d13594fd0dc53354c
```

La prueba sintética genera C1 con ficha A, publica una nueva versión con A2, verifica C1 intacto y genera C2 con A2 y su función nueva.

## 10. Pruebas y verificación

Resultado final:

- Backend: **123 pruebas, 628 aserciones**, todas aprobadas.
- Frontend: **15 pruebas**, todas aprobadas.
- Build TypeScript/Vite: aprobado.
- ESLint: 0 errores; una advertencia preexistente de compatibilidad entre React Compiler y `react-hook-form`.
- Docker Compose: servicios `postgres`, `app`, `nginx` y `frontend` operativos durante la validación.

Escenarios añadidos: cambio antes/después de X, snapshot histórico, ficha equivalente, ficha modificada, ficha retirada, ambigüedad, inmutabilidad en base de datos y aislamiento de ficha incompleta.

Integridad de la versión 10 antes/después:

| Dato | Resultado |
|---|---:|
| Fichas | 344 |
| Funciones | 3.115 |
| Conocimientos | 2.326 |
| Asignaciones | 3 |
| Certificados con snapshot | 3 |
| Checksum de identidades/hashes de fichas | `968639ccff6e35cea7975a9d9f50d47d` |
| Checksum de PK/contenido de funciones | `83c8f772091ceb5eed145b1480bf34fc` |

## 11. MF-0167

MF-0167 permanece documentada como incompleta. No se aplicó la propuesta reconciliada ni se alteró la ficha vigente. El preflight la reporta en `incompletas`; el resolver bloquea únicamente certificados que dependan de esa ficha mediante `MANUAL_FICHA_INCOMPLETA`. Las otras 343 fichas no quedan bloqueadas.

## 12. Procedimiento cuando llegue el nuevo Manual

1. Registrar acto, versión y fecha efectiva como borrador desde el panel.
2. Cargar la fuente y ejecutar primero dry-run.
3. Importar solo en el nuevo `version_id`.
4. Ejecutar `manual:diff` y revisar diferencias de contenido.
5. Ejecutar la planificación de funcionarios.
6. Resolver todas las ambigüedades y cambios de dependencia/área con decisión administrativa.
7. Respaldar y ejecutar preflight.
8. Publicar por API o `manual:publish --version-id=<id> --user-id=<actor> --vigencia-desde=AAAA-MM-DD`.
9. Verificar vigencias, incidencias sin equivalente, snapshots históricos y certificados posteriores.

El panel `/admin/manual-funciones` muestra el Manual vigente, versiones, vigencias, inventario, fecha de importación y el flujo guiado de borrador, carga, dry-run, comparación, mapeo y publicación. El autoservicio no expone ninguna selección de versión, ficha o funciones.
