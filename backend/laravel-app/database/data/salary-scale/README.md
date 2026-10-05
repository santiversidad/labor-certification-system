# Transcripción salarial pendiente

La fotografía institucional no está disponible en este repositorio. El CSV contiene **solo encabezados**, sin valores oficiales ni ejemplos que puedan confundirse con datos cargados. 2024 y 2025 no están transcritos ni importados; tampoco existe información oficial de 2026.

Formato UTF-8, delimitador coma, una fila por combinación y año. Código y grado son texto: conserve ceros iniciales. Repita la combinación con `anio=2024` y `anio=2025` cuando ambos valores hayan sido revisados contra la fotografía. `numero_cargos` es histórico por año y puede quedar vacío si la fuente no lo informa. La asignación es COP entero positivo sin `$`, puntos ni comas de miles; no se aceptan cifras aproximadas ni valores ilegibles. Los ejemplos del prompt no se consideran una tabla transcrita verificada.

Primero ejecute:

```sh
php artisan salary-scale:import --file=database/data/salary-scale/salary-scale-2024-2025.csv --dry-run
```

La plantilla vacía debe fallar con «Falta la transcripción revisada». Con datos revisados, inspeccione resumen de años, filas válidas, duplicadas y sin cargo relacionado; luego importe con `--actor-id=<administrador real activo>`. No se inventa actor. La fuente predeterminada es «Tabla salarial entregada por Dirección de Personal»; no se inventa acto administrativo. Las filas sin match exacto de código/grado/denominación se conservan con FK nula para revisión, sin crear cargos ni corregir la fuente.

Se importan borradores completos en una transacción. Repetir exactamente la misma fuente y valores no cambia datos. Cualquier diferencia contra una vigencia con filas cancela la importación completa; nunca se sobrescriben datos históricos. La publicación operativa se hace después desde la UI y no acredita un decreto.
