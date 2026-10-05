# Revisión resuelta e importación de escala salarial 2024–2025

Fecha: 2026-10-05. Repositorio: `C:\Users\mondr\source\labor-certification-recovery`. Rama `feat/salary-scale-management`, HEAD base `c75132740113f1aba04aba00c49e6d34d34d52d0`.

## Resultado

**Importación autorizada y ejecutada en CT dev.** Dos vigencias en borrador, 100 ítems, 96 relaciones y 4 filas sin FK. Los datos fuente se preservaron completos; no se publicaron automáticamente años ni se inventaron actos o fechas jurídicas. 2026 no existe.

| Año | Estado | Ítems | Suma N.º cargos | Con FK | Sin FK |
| --- | --- | ---: | ---: | ---: | ---: |
| 2024 | borrador | 50 | 701 | 48 | 2 |
| 2025 | borrador | 50 | 701 | 48 | 2 |
| 2026 | Pendiente de registrar; no creado | 0 | — | — | — |

## Fuente y controles

- CSV recibido: `C:\Users\mondr\Downloads\salary-scale-2024-2025-transcripcion.csv`.
- Archivo versionado: `backend/laravel-app/database/data/salary-scale/salary-scale-2024-2025.csv`. Sustituye la plantilla sin cifras del baseline; conserva los bytes del CSV recibido.
- SHA-256 recibido/copia, antes y después de importar: `a4fed0b1acf3fc016ee7ccc5a41f1c37052594e42acece0fe8d37067bf57a41b`.
- Encabezados exactos: `anio,codigo,grado,denominacion,numero_cargos,asignacion_basica_mensual`.
- 100 filas; 50 combinaciones únicas por año; suma de cantidades 701 en 2024 y 701 en 2025. Códigos/grados como strings, incluidos ceros iniciales. Salarios enteros positivos, sin símbolos, separadores de miles ni decimales. Sin duplicados ni errores estructurales. No existe 2026 en el archivo.
- La revisión previa contrastó las 100 filas con `Importador_largo!A1:F101` del XLSX `escala_salarial_2024_2025_revision.xlsx` y las 50 combinaciones con `Revision_fuente!A1:J51`: sin diferencias de cantidades o salarios. El usuario confirmó ahora la revisión de la fotografía fuente.
- Las marcas visuales `**` ya estaban excluidas de las denominaciones de importación entregadas. No se les asigna significado jurídico.
- No se aplicaron porcentajes, no se recalculó 2025 desde 2024 y no se cambiaron salarios, cantidades, códigos, grados ni denominaciones salariales.

## Decisión de revisión aplicada

El primer dry-run de la fase anterior dio 90 relaciones exactas y 10 filas sin relación. Se descartó la propuesta anterior de modificar una copia de las denominaciones: la decisión del usuario exige conservar la fuente y utilizar únicamente equivalencias explícitas para `cargo_id`.

| Fuente (ambos años) | Destino exacto en catálogo | Cargo ID CT | Decisión | Motivo aprobado |
| --- | --- | ---: | --- | --- |
| 290/10 Comandante de Tránsito | 290/10 Comandante de Transito | 8 | RESUELTO POR MAPPING EXPLÍCITO | Diferencia exclusiva de tilde |
| 233/09 Inspector de Policía Urbano 1ra Categoria | 233/09 Inspector de Policía Urbano Primera Categoría | 9 | RESUELTO POR MAPPING EXPLÍCITO | Abreviatura y ortografía |
| 438/12 Secretario Ejecutivo del Despacho del alcalde | 438/12 Secretario Ejecutivo del Despacho del Alcalde | 42 | RESUELTO POR MAPPING EXPLÍCITO | Capitalización |
| 202/13 Comisario de Familia | Existe 202/09 Comisario de Familia | — | PENDIENTE INSTITUCIONAL; importado con FK NULL | Diferencia de grado, sin equivalencia aprobada |
| 312/08 Inspector de Tránsito | Existe 312/08 Inspector de Tránsito y Transporte – | — | PENDIENTE INSTITUCIONAL; importado con FK NULL | Denominación con contenido adicional, sin equivalencia aprobada |

Mecanismo: lista privada `REVIEWED_CARGO_MAPPINGS` en `SalaryScaleImportService`, aprobada el 2026-10-05, limitada a 2024/2025. Primero se busca una coincidencia exacta; solo si no existe se comprueba la triple clave fuente y una triple clave destino explícita. Debe existir un único destino. Los IDs de esta tabla son el resultado observado en CT, no constantes de implementación. No hay normalización general, fuzzy matching, selección por código/grado ni preferencia entre candidatos. No se agregaron migraciones ni tablas de aliases.

El resumen muestra cada mapping con fuente, destino, año, ID y motivo. Se conservaron tres mappings por año en cada auditoría `salary_scale_year_imported`. Las denominaciones salariales almacenadas siguen siendo las del CSV.

Una relación ambigua produce error y bloquea la importación completa. Una fila sin relación segura, pero estructuralmente válida, se preserva con FK nula. Duplicados y errores estructurales siguen abortando todo. No se alteró el catálogo ni el Manual.

## Dry-run final y carga

```powershell
docker compose -f docker-compose.certificate-types.yml exec -T app php artisan salary-scale:import --file=database/data/salary-scale/salary-scale-2024-2025.csv --dry-run
```

Exit code 0. Sin actor en dry-run. Resumen (además, la salida contiene las 6 entradas de mapping):

```json
{
  "filas_leidas": 100,
  "validas": 100,
  "duplicadas": 0,
  "con_cargo_relacionado": 96,
  "sin_cargo_relacionado": 4,
  "ambiguas": 0,
  "errores": [],
  "vigencias": [
    2024,
    2025
  ]
}
```

Las cuatro filas sin relación fueron comprobadas contra las dos combinaciones pendientes esperadas. Se confirmó también que CT dev seguía con 0 vigencias y 0 ítems antes de importar.

El actor se resolvió por consulta controlada de la cuenta admin utilizada en la sesión CT, filtrando rol admin y estado activo; se comprobó el permiso `escala_salarial.gestionar`. Resultado: **ID 14**, cuenta existente, sin crear actor ni cambiar credenciales, roles o MFA.

```powershell
docker compose -f docker-compose.certificate-types.yml exec -T app php artisan salary-scale:import --file=database/data/salary-scale/salary-scale-2024-2025.csv --actor-id=14 --fuente="Tabla salarial entregada por Dirección de Personal"
```

Resultado real: `{"filas_importadas":100,"sin_cambios":false}`. Dos vigencias nuevas, IDs CT 1 (2024) y 2 (2025), ambas en borrador. Fuente: **Tabla salarial entregada por Dirección de Personal**. `acto_administrativo`, `fecha_vigencia`, `publicado_at`, `porcentaje_referencia` y `vigencia_base_id` permanecen NULL. Sin declaración de oficialidad jurídica ni archivado/publicación automática.

## Verificación posterior

Una consulta de verificación en transacción `BEGIN READ ONLY`, cerrada con rollback, contrastó las 100 filas persistidas contra el CSV: igualdad de código, grado, denominación, número de cargos y asignación, además de la FK exacta o explícita esperada. Origen de los 100 valores: `importado`. Ninguna diferencia. 100 ítems, 2 vigencias, 50 ítems / 701 cargos por año, 96 FK no nulas y 4 nulas. Dos auditorías de importación con actor 14 y tres mappings por año.

| Año | Código | Grado | Denominación fuente preservada | N.º cargos | Asignación COP | cargo_id |
| --- | --- | --- | --- | ---: | ---: | --- |
| 2024 | 202 | 13 | Comisario de Familia | 6 | 11284768 | NULL |
| 2025 | 202 | 13 | Comisario de Familia | 6 | 12074702 | NULL |
| 2024 | 312 | 08 | Inspector de Tránsito | 4 | 4183337 | NULL |
| 2025 | 312 | 08 | Inspector de Tránsito | 4 | 4476171 | NULL |

202/13 sigue siendo 202/13, no 202/09. 312/08 conserva «Inspector de Tránsito», sin añadir «y Transporte –». Los pendientes se importaron sin FK, no se descartaron ni reinterpretaron.

## UI smoke real en CT

El usuario inició sesión con un admin existente. Se verificó `/admin/escala-salarial` contra CT dev después de recargar:

- Columnas 2024 y 2025, ambas `borrador`, con «Acto pendiente de registrar».
- Estado «2026 · Pendiente de registrar», sin vigencia creada.
- Alcalde 005/03: 20.196.617 (2024), 21.610.381 (2025), 1 cargo en ambos años.
- Búsqueda de Comisario de Familia: 202/13, 11.284.768 / 12.074.702, 6 cargos por año, indicador «Sin vinculación al catálogo» en ambas celdas.
- Búsqueda de Inspector de Tránsito: 312/08, 4.183.337 / 4.476.171, 4 cargos por año, mismo indicador.
- Los pendientes son visibles y consultables, sin error técnico ni respuesta 500 que impida la consulta. Desplazamiento horizontal por teclado conserva acceso a 2025 en el ancho del navegador.
- No se editaron valores ni se pulsó Publicar o Aplicar aumento durante el smoke.

Capturas de tabla con recorte nativo de navegador que excluye la cabecera de sesión: `frontend/react-app/evidence/salary-scale/08-reviewed-import.jpg`, `09-comisario-2025.jpg`, `10-inspector-pending.jpg`. Verificadas visualmente; solo datos salariales por cargo, sin personas, credenciales ni MFA.

## Pruebas ejecutadas en esta fase

| Verificación | Resultado |
| --- | --- |
| Backend salarial | 23 PASS / 237 assertions |
| Backend completo, DB aislada `scl_certificate_types_test` | 311 PASS / 1.935 assertions |
| Frontend completo | 133 PASS en 23 archivos; 19 del módulo salarial |
| ESLint | PASS |
| TypeScript | PASS (`npx tsc -b` y typecheck del build) |
| Vite build | PASS |
| Pint, 2 archivos PHP modificados | PASS |
| Dry-run final real | PASS: 100 / 96 relacionadas / 4 sin FK / 0 errores o duplicadas o ambiguas |
| Verificación real post-importación | PASS: 100 filas fuente preservadas |
| UI smoke CT admin | PASS, consulta real de ambas filas pendientes y años |
| E2E completa | No repetida; sin cambios de contrato HTTP, flujos de autenticación o publicación. El alcance se cubrió con regresión completa backend/frontend y smoke real en CT |

Las nuevas pruebas backend cubren las tres equivalencias sin modificar la fuente, importación nullable de 202/13 y 312/08, importes/cantidades del CSV, límites de año/código/grado/texto, ausencia de fuzzy matching, necesidad de destino exacto, auditoría, idempotencia y bloqueo de un resumen ambiguo. La nueva prueba frontend confirma filas/valores visibles sin FK, con indicador y sin alert/retry técnico.

El primer intento de la nueva prueba frontend falló por el espacio no separable del formato COP frente a la normalización de Testing Library. Se corrigió la aserción para aceptar espacios de presentación; no cambió el formato de producción. La suite completa se repitió y pasó. No se presentan los resultados E2E previos como una ejecución nueva.

## Integridad y límites

Manual, versión 10: **344 fichas / 3.115 funciones / 2.326 conocimientos**, digest antes/después `2ccdb15add1bedbe5c83aa978cf4fa45`. Catálogo completo de cargos, digest de todas sus columnas antes/después: `5b4bb490c4599a8a62440d9946dc3c42`. Ningún cambio en cargos o Manual. Perfiles transversales y resolución normativa intactos.

No existe salario por funcionario ni salario_override nuevo; no se modificaron certificados, PDF, snapshot, auth/MFA, pagos, cuotas, firma, QR documental u oficialización. 2026 ausente. **3B.2 no iniciada.**

## Git

Se conservaron el CSV y el reporte sin commit de la fase anterior. Cambios limitados al importador, indicador UI, pruebas, datos fuente, documentación y capturas seguras. Sin dumps de DB ni archivos temporales de verificación versionados. `git diff` y `git diff --check` revisados antes del commit. Commit solicitado: `data(salary-scale): load reviewed 2024 and 2025 salary scales`; hash y estado final se reportan desde Git después de crearlo. **No push.**
