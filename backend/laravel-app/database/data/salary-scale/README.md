# Transcripción salarial revisada 2024–2025

El CSV conserva la transcripción institucional entregada y revisada por el usuario: 100 filas, 50 combinaciones por año y 701 cargos por cada vigencia (2024 y 2025). No incluye 2026. No se recalculan salarios ni se alteran códigos, grados, denominaciones o cantidades para forzar coincidencias. El reporte de carga y estados de CT dev está en `SALARY_SCALE_IMPORT_REVIEW.md`, en la raíz del repositorio.

Formato UTF-8, delimitador coma, una fila por combinación y año. Código y grado son texto: conserve ceros iniciales. Repita la combinación con `anio=2024` y `anio=2025` cuando ambos valores hayan sido revisados contra la fotografía. `numero_cargos` es histórico por año y puede quedar vacío si la fuente no lo informa. La asignación es COP entero positivo sin `$`, puntos ni comas de miles; no se aceptan cifras aproximadas ni valores ilegibles. Los ejemplos del prompt no se consideran una tabla transcrita verificada.

Primero ejecute:

```sh
php artisan salary-scale:import --file=database/data/salary-scale/salary-scale-2024-2025.csv --dry-run
```

Inspeccione el resumen de años, filas válidas, duplicadas, relacionadas, sin relación, ambiguas y mappings explícitos. Para este archivo se esperan 100 válidas, 96 relacionadas, 4 sin relación, 0 duplicadas, 0 ambiguas y 0 errores. Solo después de revisión se importa con `--actor-id=<administrador real activo>` y `--fuente="Tabla salarial entregada por Dirección de Personal"`. No se inventa actor, acto ni fecha jurídica.

`SalaryScaleImportService` contiene tres equivalencias cerradas, aprobadas el 2026-10-05 y limitadas a 2024/2025: 290/10 («Tránsito» / «Transito»), 233/09 («1ra Categoria» / «Primera Categoría») y 438/12 («alcalde» / «Alcalde»). Se usan exclusivamente para resolver `cargo_id` contra una denominación destino exacta y única. No cambian el texto salarial. El resumen y la auditoría conservan fuente, destino, año, ID y motivo. No se usan IDs fijos, normalización general, matching solo por código/grado ni fuzzy matching.

202/13 «Comisario de Familia» y 312/08 «Inspector de Tránsito» quedan sin FK en ambos años, pendientes de conciliación institucional. Sus cuatro filas y salarios se preservan y son visibles en la UI con «Sin vinculación al catálogo». No se relaciona 202/13 con 202/09 ni se asume equivalencia con «Inspector de Tránsito y Transporte –». Una relación ambigua bloquea toda la importación; una fila estructuralmente válida sin relación segura puede conservarse con FK nula.

Se importan borradores completos en una transacción, sin publicar automáticamente. Repetir exactamente la misma fuente y valores no cambia datos. Cualquier diferencia contra una vigencia con filas cancela la importación completa; nunca se sobrescriben datos históricos. La publicación operativa se hace después desde la UI y no acredita un decreto.
