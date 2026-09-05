# Conciliación Excel ↔ JSON ↔ PDF

Estado: **NO PUBLICABLE**. Excel/JSON originales: **COINCIDE: 344**, 3.115 funciones y 2.326 conocimientos iguales.
La igualdad de fuentes estructuradas no prueba que contengan todo el PDF. La versión 10 sigue en BORRADOR.
Este informe se generó antes de preparar el JSON derivado. No se corrigieron fuentes ni BD.

| Resultado | Cantidad |
|---|---:|
| Coinciden completamente (Excel/JSON) | 344 |
| Solo JSON | 0 |
| Solo Excel | 0 |
| Diferencia de campos entre Excel/JSON | 0 |
| Diferencia de funciones entre Excel/JSON | 0 |
| Ambiguas en el matching Excel/JSON | 0 |
| Fichas que requieren revisión humana por contraste PDF | 1 (MF-0167) |

La vigencia requiere además revisión documental global; no se suma como ficha. Hay 0 source_id duplicados, 0 órdenes duplicados y 3 pares de textos de funciones repetidos dentro de ficha, presentes en ambas fuentes. Las 45 identidades descriptivas repetidas se conservan como fichas distintas.

## Método y cobertura

El adaptador Excel usa encabezados explícitos y comprueba campos redundantes al enlazar hojas. Los dos parsers producen FichaManualData y comparten validación. Se compara código, grado, denominación, nivel, dependencia, área, propósito, registro y páginas; perfil_id no decide. Un candidato no exclusivo produce AMBIGUA. La comparación recursiva es estricta: conserva diferencias de texto, espacios, orden, números fuente, grupos y conocimientos.

Las 344 clasificaciones, páginas, filas exactas y conteos individuales se encuentran en [MANUAL_RECONCILIATION_DETAILS.json](MANUAL_RECONCILIATION_DETAILS.json). El esquema completo, tipos, vacíos, hashes y tres ejemplos están en [MANUAL_SOURCE_INSPECTION.md](MANUAL_SOURCE_INSPECTION.md).

## Tabla de comprobaciones documentales puntuales

| Ficha | Campo | JSON | Excel | PDF | Resultado |
|---|---|---|---|---|---|
| MF-0001…MF-0344 | Todos los campos y arrays disponibles | 344 fichas, 3.115 funciones, 2.326 conocimientos | Valores estrictamente idénticos | Comprobación selectiva, no exhaustiva | Ambos equivalentes entre sí |
| MF-0167 | Área funcional | Vacío | Perfiles_Cargo J168; Funciones G y Conocimientos F vacíos en sus 8 filas | p.362: Secretaría de las Tecnologías de la Información y las Comunicaciones – TIC | PDF diferente: corrección inequívoca propuesta |
| MF-0167 | Propósito | Vacío | Perfiles_Cargo K168 vacío | p.362: párrafo «Adelantar actividades…» debajo del área; no aparece encabezado III. PROPÓSITO PRINCIPAL | No resoluble automáticamente: asignación semántica requiere revisión |
| MF-0167 | Funciones / conocimientos | 8 / 8 | 8 / 8 idénticos | p.362 contiene los dos listados | Ambos equivalentes en esta comprobación |
| MF-0167 | Competencias, formación, experiencia, equivalencias | Ausentes | Ausentes | pp.362–363 contienen secciones VI, VII y VIII | Omisión compartida; no se reconstruyen masivamente |
| MF-0167 | Página final | 364 | 364 | p.364 ya inicia la ficha siguiente | Límite de extracción incluye página siguiente; no tomar su propósito como MF-0167 |
| MF-0001 | Texto repetido órdenes 6/15 | Repetido, grupos diferentes | Igual | pp.14–15, función constitucional 6 y legal A.5 | Repetición documental; conservar grupos y numeración |
| MF-0047 | Texto repetido órdenes 1/3 | Repetido | Igual | p.117 repite la función de orientar el componente económico y social | Repetición documental; no deduplicar |
| MF-0154 | Texto repetido órdenes 8/10 | Repetido | Igual | p.336 repite el estudio de devoluciones/compensaciones | Repetición documental; no deduplicar; PDF presenta espaciado tipográfico distinto |
| MF-0228 / MF-0229 | 367-05, áreas y funciones | Jurídica: 10 / Riesgo: 6 | Igual | pp.478 y 480 corresponden a áreas distintas | Fichas distintas, asignaciones y funciones separadas |
| Todas | Funciones comunes | No hay array | No hay hoja ni columnas | pp.11–13: artículo 2, funciones generales y por nivel | Ausentes de fuentes estructuradas; no cargar ni duplicar en fichas |
| Manual | Acto | Decreto No. 1000-24/015 de 2023 | Mismo texto en Fuente | p.666 confirma 1000-24/015; p.10 tiene espacio del número vacío | Número corroborado puntualmente; conservar diferencia documental |
| Manual | Fecha / vigencia | Sin fecha de expedición/vigencia | Sin fecha de expedición/vigencia | Portada: 13/01/2023. p.667 art.10: desde expedición; «Expedido en Villavicencio ___» vacío | VIGENCIA_NO_CONFIRMADA |

## Corrección propuesta antes de producir el derivado

Únicamente MF-0167.area_funcional: de cadena vacía a `Secretaría de las Tecnologías de la Información y las Comunicaciones – TIC`, respaldada por la fila II de p.362 y comprobación visual. No cambian source_id, código, grado, funciones, conocimientos ni páginas.

El párrafo de p.362 dice: «Adelantar actividades para el cumplimiento de los objetivos de la dependencia, según lineamientos y normas vigentes en materia de comunicaciones internas y externas de la entidad.» Se conserva como evidencia pendiente en metadata, sin asignarlo automáticamente a proposito_principal. El título III está ausente, lo cual requiere decisión humana sobre su clasificación.

No hay competencias, formación, experiencia, equivalencias ni funciones comunes en ninguna hoja Excel. No hay información adicional estructurada que pueda cargarse automáticamente. Los conocimientos ya están preservados en JSONB; las funciones específicas normalizadas; las comunes siguen separadas en modelo, resolver y snapshot, con cero filas y sin impresión automática.

## Vigencia y recomendación

**VIGENCIA_NO_CONFIRMADA — NO PUBLICABLE.** El artículo 10 (p.667) hace depender la vigencia de la expedición y el espacio de fecha está vacío. La fecha de portada no se adopta como vigencia. Fechas de acto y vigencia en BD siguen NULL. MF-0167 mantiene pendiente su propósito.

El material menciona antecedentes de 2022, no modificaciones posteriores inequívocas al Decreto 015: p.10 cita Decreto 1000-24/315 de 24/08/2022 y Acuerdo 562 de 30/11/2022; p.667 cita 1000-21/315. Se conserva esa diferencia de referencia. No se consultaron fuentes externas; no se afirma que no existan actos posteriores fuera del material proporcionado.

No se publica el borrador, no se habilitan certificados oficiales y no se recalculan certificados emitidos. La propuesta derivada se admite únicamente para dry-run; el cambio de área activa la protección de identidad existente y exige un flujo explícito posterior para aplicarlo.
