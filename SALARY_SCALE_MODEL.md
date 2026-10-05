# Escala salarial administrativa

Catálogo exclusivo de administración para consultar y mantener asignaciones básicas mensuales por cargo y año. Es independiente del Manual de Funciones, los funcionarios, las vinculaciones y las certificaciones. No resucita `rangos_salariales`, `salario_override`, `requiere_salario` ni la antigua modalidad con/sin salario. Los certificados siguen siendo `sencillo` y `funciones`.

## Fuente y estado de los datos

El CSV `backend/laravel-app/database/data/salary-scale/salary-scale-2024-2025.csv` conserva la transcripción institucional entregada: 100 filas, 50 combinaciones y 701 cargos por año. El usuario confirmó la revisión contra la fotografía y autorizó tres equivalencias textuales exclusivamente para relacionar cargos; las otras dos combinaciones permanecen pendientes de conciliación y se conservan sin FK. Los resultados de carga y estados están en `SALARY_SCALE_IMPORT_REVIEW.md`. No hay valores oficiales 2026 ni se crea ese año con esta importación. La UI muestra el año actual como pendiente cuando todavía no está registrado; las columnas consultan años existentes sin hardcodear 2024/2025/2026.

La fuente es “Tabla salarial entregada por Dirección de Personal”. Acto y fecha permanecen sin registrar hasta obtener evidencia. La fuente es obligatoria. Publicar es un estado operativo y no acredita un decreto. El importador crea borradores, sin publicación automática.

## Modelo y restricciones

Migración aditiva: `2026_10_05_000002_create_salary_scale_management.php`.

| Tabla | Datos principales |
| --- | --- |
| `escala_salarial_vigencias` | Año único; estado; porcentaje de referencia; base nullable; fuente; acto/fecha/observaciones nullable; actores de creación/publicación; momento de publicación; revisión; timestamps |
| `escala_salarial_items` | Vigencia; cargo nullable; código, grado, denominación fuente; cantidad de cargos nullable por año; asignación `decimal(12,0)` nullable; origen; revisión; timestamps |

Código y grado son strings: se conservan los ceros iniciales. La FK a `cargos` se asigna si código, grado y denominación coinciden exactamente con una única fila. Como excepción explícitamente revisada para 2024/2025, el importador permite las tres equivalencias cerradas 290/10, 233/09 y 438/12, comprobando también la denominación destino exacta y única. No modifica los campos fuente y registra cada mapping en el resumen y la auditoría. No hay fuzzy matching ni normalización general. Sin coincidencia cierta se conserva la fila fuente con FK null y se reporta para revisión; no se crea ni se interpreta un cargo o ficha del Manual. 202/13 y 312/08 permanecen pendientes sin FK y visibles con «Sin vinculación al catálogo».

El catálogo maestro tiene UNIQUE(código, grado) y no tiene duplicados. La transcripción revisada contiene 50 combinaciones únicas por año. Se usa UNIQUE(vigencia, código, grado, denominación) para bloquear filas idénticas y conservar posibles excepciones sin descartar datos. No se fuerza una FK solo por código/grado cuando la denominación difiere. Antes de importar, revisar el resumen de coincidencias.

Cantidad de cargos vive en el ítem anual y puede variar. La tabla muestra “N.º cargos” en cada celda anual; no existe una columna común que sustituya la historia. Se omite “Total x Nivel” en esta versión; no se persisten totales manuales ni se infieren niveles de filas sin correspondencia segura.

## Estados y operaciones

- `borrador`: edición individual de importe/cantidad; cálculo opcional si todavía no hay valores; publicación después de revisión.
- `vigente`: referencia operativa publicada más reciente; valores inmutables.
- `archivada`: año publicado anterior; valores inmutables.

No existe borrado, reapertura ni edición de valores publicados en la API. La publicación de un año más nuevo archiva referencias vigentes anteriores; publicar un año anterior al vigente lo deja archivado. Registrar acto, fecha, fuente y observaciones sí es posible posteriormente y se audita. Los triggers PostgreSQL protegen también los ítems frente a INSERT/UPDATE/DELETE directos sobre años publicados y evitan moverlos de año. El rollback automático de la migración se bloquea: cualquier retiro requiere revisar y respaldar los históricos.

Crear sin base toma la estructura de cargos activos sin valores inferidos. Crear desde base copia únicamente estructura y cantidad de cargos; los importes quedan pendientes si no hay porcentaje. La base debe ser anterior al destino. Para calcular necesita importes compatibles y completos. Ninguna operación modifica los importes de la base.

## Cálculo y precisión

El servidor es autoridad. La vista previa muestra base, destino, porcentaje, cantidad total y hasta cinco ejemplos; el frontend no persiste resultados propios. Generar con porcentaje y publicar requieren confirmación explícita. Si la base cambió después de la vista previa, se rechaza por revisión desactualizada.

Porcentaje decimal como string, coma o punto, hasta dos decimales; rango preventivo de captura 0–100 %, **no límite legal**. Negativos, NaN, infinito, notación exponencial, decimales adicionales y desbordamientos se rechazan. Año técnico 1900–2199. Cantidad de cargos nullable, entero 0–1.000.000. Importes positivos, máximo 999.999.999.999 pesos; vacíos quedan pendientes en borrador.

Política explícita: pesos enteros COP, redondeo half-up (mitades hacia arriba). Fórmula implementada con enteros de 64 bits:

`nuevo = intdiv(base * (10000 + puntos_base) + 5000, 10000)`

La multiplicación máxima cabe en int64; no se usa float para persistencia o cálculo. La representación JSON del importe es string. React usa Number únicamente para presentación COP en el rango seguro (<2^53). La transcripción revisada no establece una regla institucional para incrementos futuros; esta es la política técnica de la primera versión, documentada y probada. CSV con fracciones o símbolos monetarios se rechaza para revisión, sin redondear una fuente ilegible.

Origen de ítem: `pendiente`, `manual`, `importado`, `calculado_por_porcentaje`. La vigencia conserva base y porcentaje de referencia incluso si luego se ajustan celdas manualmente. Un segundo cálculo no sobrescribe valores existentes.

## API y autorización

Todas las rutas bajo `/api/v1/escala-salarial` exigen sesión Sanctum, identidad vigente, cambio de contraseña/MFA según middleware existente, rol `admin` y permiso `escala_salarial.gestionar`. Secretario y funcionario reciben 403, incluso si se les asignara accidentalmente ese permiso. La UI usa `RoleRoute` admin y menú filtrado por rol; la página tampoco consulta datos para otros roles. Admin queda con 37 permisos; secretario y funcionario conservan sus permisos.

| Método | Sufijo | Operación |
| --- | --- | --- |
| GET | `/` | Tabla por filas fuente con columnas anuales, búsqueda q y paginación |
| GET / POST | `/vigencias` | Listar / crear borrador |
| POST | `/vigencias/previsualizar` | Vista previa de cálculo, sin escrituras |
| GET / PATCH | `/vigencias/{id}` | Detalle / metadata de procedencia |
| POST | `/vigencias/{id}/calcular` | Calcular borrador vacío, confirmación y revisiones |
| POST | `/vigencias/{id}/publicar` | Publicación operativa confirmada |
| PATCH | `/items/{id}` | Valor y cantidad de borrador, revisión obligatoria |

Ruta frontend: `/admin/escala-salarial`. Búsqueda por código, grado y denominación en servidor. Región de tabla enfocable, scroll horizontal interno y etiquetas accesibles. Modales reutilizan el foco, Escape y trampa de teclado del diseño existente. Las acciones no se liberan tras guardar hasta refrescar la tabla, para usar la revisión nueva.

Payloads aceptan solo campos explícitos: actor, estado, año/base y cargo de una fila no pueden manipularse mediante edición. Validación 422; acceso 403; dominio 409 con códigos estables `SALARY_SCALE_CONFLICT`, `SALARY_SCALE_READ_ONLY`, `SALARY_SCALE_YEAR_EXISTS`, `SALARY_SCALE_ALREADY_CALCULATED`.

## Transacciones, concurrencia y auditoría

Creación/importación se serializan con advisory lock y año UNIQUE. Los cambios bloquean la fila padre con FOR UPDATE; revisiones de ítems/años detectan snapshots desactualizados. Cálculo es atómico y no aplica dos aumentos sobre el mismo borrador. Publicación usa advisory lock global para ordenar el archivado entre años. Tests E2E envían solicitudes paralelas de creación, cálculo, edición y publicación.

Auditoría existente, actores explícitos: creación, cálculo, edición individual, importación, procedencia y publicación. Metadata limitada a año/base/porcentaje/cantidad/IDs o before/after de los campos administrativos modificados; no se registran credenciales, funciones, certificados ni datos personales ajenos.

## Importador

`php artisan salary-scale:import --file=... --dry-run`

Encabezados exactos: `anio,codigo,grado,denominacion,numero_cargos,asignacion_basica_mensual`. Formato largo, una fila por combinación/año para conservar cantidades históricas. UTF-8 con BOM opcional; códigos/grados numéricos string; moneda positiva entera sin símbolos/separadores; denominación preservada. Máximo preventivo 5 MB / 10.000 filas.

Resumen antes de escribir: leídas, válidas, duplicadas, con/sin cargo relacionado, ambiguas, mappings explícitos, errores y vigencias. Dry-run no exige actor y no escribe. Para importar se requiere `--actor-id` de administrador activo con permiso y `--fuente` revisada. Los errores estructurales, duplicados y relaciones ambiguas abortan todo; las filas válidas sin relación se preservan con FK nula. Archivo idéntico y misma fuente es idempotente sin auditorías duplicadas; diferencias con un año poblado se rechazan, incluso en borrador. No se reemplazan históricos. Ver instrucciones en `database/data/salary-scale/README.md`.

## Separación y límites

No hay salario por funcionario, nuevo certificado salarial ni cambios en PDFs, snapshots, cuotas, pagos, firmas, QR documental u oficialización. No se modifican las 344 fichas ni funciones/conocimientos del Manual. Los ámbitos transversales, la ambigüedad normativa y MF-0167 conservan sus reglas. 3B.2 no iniciada.
