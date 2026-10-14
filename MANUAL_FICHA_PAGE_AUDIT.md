# Auditoría documental del Manual de Funciones

Fecha: 12 de octubre de 2026, America/Bogota. Versión publicada: 10, Decreto 1000-24/015 de 2023.

## Fuente y criterio aprobado

Archivo original: `C:\Users\mondr\Downloads\DECRETO   015 DE 2023 MANUAL DE FUNCIONES.pdf`.

- SHA-256 PDF: `18a3d6c33469f517f09064f2b9e02afa062323997279ef7390f94cf3bd428e35`.
- Tamaño: 5.846.005 bytes. Páginas reales: **667**.
- La portada ocupa la página PDF 1 sin folio impreso. Los folios impresos 2–667 coinciden con su posición PDF: no hay desplazamiento.
- JSON original: `backend/laravel-app/database/data/manual_funciones_decreto_015_2023.json`.
- SHA-256 JSON: `261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e`; archivo conservado byte por byte.
- Criterio confirmado por el usuario: **usar la página donde aparece la denominación del cargo en todos los casos**. Un encabezado aislado en la página anterior no cambia esta regla.
- Decisión expresa: **MF-0343 = 664; MF-0344 = 665**. El PDF muestra la ficha 343 y solo el encabezado de la 344 en 664; denominación y funciones de la 344 en 665.

## Resultado y método

344 fichas verificadas, 344 páginas iniciales confirmadas y distintas dentro de la versión. Cero páginas sin confirmar, cero fichas sin correspondencia, cero colisiones finales. Se compara identificación completa (nivel, denominación, código, grado, número de cargos, dependencia y jefe), área y propósito presentes, y la sección completa de funciones con numeración, orden y grupos. Se normalizan únicamente espacios, saltos de línea y pies de página para el cotejo; los textos persistidos no se reescriben.

Las **3.115 funciones** coinciden con el documento. La base CT dev coincide con los 344 registros JSON y sus arrays de funciones. Se conservan 2.326 conocimientos; su conteo y contenido importado quedan protegidos por los hashes originales, sin afirmar una nueva auditoría semántica individual de cada conocimiento.

`python scripts/audit_manual_source_pages.py --pdf "<PDF original>" --output backend/laravel-app/database/data/manual_paginas_decreto_015_2023_verificadas.json` reproduce el cotejo y rechaza archivos con checksum distinto. El manifiesto contiene source_id, hash original, página aprobada, página PDF, página de extracción original y conteo de funciones. No utiliza el número secuencial de ficha como fórmula para calcular páginas.

## Referencias corregidas

11 referencias cambian al aplicar la regla de denominación. La página de extracción original se conserva en `metadata_manual` y en la evidencia documental; `pagina_inicio` contiene la referencia aprobada.

| Ficha | Referencia importada | Página aprobada |
|---|---:|---:|
| MF-0054 | 131 | 132 |
| MF-0079 | 183 | 184 |
| MF-0082 | 189 | 190 |
| MF-0084 | 193 | 194 |
| MF-0088 | 201 | 202 |
| MF-0104 | 233 | 234 |
| MF-0106 | 237 | 238 |
| MF-0108 | 241 | 242 |
| MF-0109 | 243 | 244 |
| MF-0111 | 247 | 248 |
| MF-0344 | 664 | 665 |

MF-0041, MF-0196 y MF-0197 conservan 103, 419 y 421: la denominación está allí aunque otros datos de identificación continúen en la página siguiente. La extracción importada de MF-0344 dice `pagina_fin=668`, fuera de las 667 páginas; se documenta el defecto, sin corregir silenciosamente la fuente ni usar página final en este flujo.

## Particularidades de importación conservadas

- MF-0167: página verificable, pero área funcional y propósito vacíos en el modelo original. Sigue incompleta y no elegible para una nueva selección o certificado con funciones; no se rellenan datos.
- MF-0001: se conservan 58 funciones (57 numeradas y la última sin número). El importador etiqueta el marcador fuente `G)` como `G) Función legal`; es una etiqueta de agrupación preexistente, no texto de una función nueva.
- MF-0001, MF-0061 y MF-0062 tienen introducciones contextuales no numeradas en el PDF que el modelo original no almacena como funciones. El auditor distingue explícitamente esos párrafos y compara íntegra la sección restante. No se incorporan como nuevas funciones ni se altera el modelo normativo en esta tarea.
- Se preservan las diferencias literales, errores tipográficos y repeticiones del original. No se deduplican funciones.
- MF-0110 presenta un rótulo de área con numeral IV y MF-0109 uno con numeral III; el cotejo admite esos rótulos documentales. No se reescriben.

## Inventario completo de páginas

Las denominaciones, códigos y grados siguientes provienen del JSON cotejado contra el PDF. La clave de selección sigue siendo el ID interno de ficha, nunca la página.

| Ficha | Denominación | Código | Grado | Página inicial |
|---|---|---|---|---:|
| MF-0001 | Alcalde 005-03 | 005 | 03 | 14 |
| MF-0002 | Secretario de Despacho 020-02 | 020 | 02 | 20 |
| MF-0003 | Secretario de Despacho | 020 | 02 | 22 |
| MF-0004 | Secretario de Despacho | 020 | 02 | 24 |
| MF-0005 | Secretario de Despacho | 020 | 02 | 26 |
| MF-0006 | Secretario de Despacho | 020 | 02 | 29 |
| MF-0007 | Secretario de Despacho | 020 | 02 | 31 |
| MF-0008 | Secretario de Despacho | 020 | 02 | 33 |
| MF-0009 | Secretario de Despacho | 020 | 02 | 35 |
| MF-0010 | Secretario de Despacho | 020 | 02 | 38 |
| MF-0011 | Secretario de Despacho | 020 | 02 | 41 |
| MF-0012 | Secretario de Despacho | 020 | 02 | 44 |
| MF-0013 | Secretario de Despacho | 020 | 02 | 46 |
| MF-0014 | Secretario de Despacho | 020 | 02 | 48 |
| MF-0015 | Secretario de Despacho | 020 | 02 | 50 |
| MF-0016 | Secretario de Despacho | 020 | 02 | 52 |
| MF-0017 | Director Técnico 009-01 | 009 | 01 | 54 |
| MF-0018 | Director Técnico | 009 | 01 | 56 |
| MF-0019 | Director Técnico | 009 | 01 | 59 |
| MF-0020 | Director Técnico | 009 | 01 | 61 |
| MF-0021 | Director Técnico | 009 | 01 | 63 |
| MF-0022 | Director Técnico | 009 | 01 | 66 |
| MF-0023 | Director Técnico | 009 | 01 | 68 |
| MF-0024 | Director Técnico | 009 | 01 | 70 |
| MF-0025 | Director Técnico | 009 | 01 | 72 |
| MF-0026 | Director Técnico | 009 | 01 | 74 |
| MF-0027 | Director Técnico | 009 | 01 | 76 |
| MF-0028 | Director Técnico | 009 | 01 | 78 |
| MF-0029 | Director Técnico | 009 | 01 | 80 |
| MF-0030 | Director Técnico | 009 | 01 | 82 |
| MF-0031 | Director Técnico | 009 | 01 | 84 |
| MF-0032 | Director Técnico | 009 | 01 | 86 |
| MF-0033 | Director Técnico | 009 | 01 | 88 |
| MF-0034 | Director Técnico | 009 | 01 | 90 |
| MF-0035 | Director Técnico | 009 | 01 | 92 |
| MF-0036 | Director Técnico | 009 | 01 | 94 |
| MF-0037 | Director Técnico | 009 | 01 | 96 |
| MF-0038 | Director Técnico | 009 | 01 | 98 |
| MF-0039 | Director Técnico | 009 | 01 | 100 |
| MF-0040 | Director Técnico | 009 | 01 | 102 |
| MF-0041 | Director Técnico | 009 | 01 | 103 |
| MF-0042 | Director Técnico | 009 | 01 | 107 |
| MF-0043 | Director Técnico | 009 | 01 | 109 |
| MF-0044 | Director Técnico | 009 | 01 | 111 |
| MF-0045 | Director Técnico | 009 | 01 | 113 |
| MF-0046 | Director Técnico | 009 | 01 | 115 |
| MF-0047 | Director Técnico | 009 | 01 | 117 |
| MF-0048 | Director Técnico | 009 | 01 | 119 |
| MF-0049 | Director Técnico | 009 | 01 | 122 |
| MF-0050 | Director Técnico | 009 | 01 | 124 |
| MF-0051 | Director Técnico | 009 | 01 | 126 |
| MF-0052 | Director Técnico | 009 | 01 | 128 |
| MF-0053 | Jefe de Oficina | 006 | 02 | 130 |
| MF-0054 | Jefe de Oficina | 006 | 02 | 132 |
| MF-0055 | Jefe de Oficina | 006 | 02 | 134 |
| MF-0056 | Jefe de Oficina | 006 | 02 | 136 |
| MF-0057 | Jefe de Oficina | 006 | 02 | 138 |
| MF-0058 | Jefe de Oficina Asesora | 115 | 06 | 141 |
| MF-0059 | Asesor | 105 | 05 | 143 |
| MF-0060 | Comandante de Transito 290-10 | 290 | 10 | 145 |
| MF-0061 | Inspector de Policía Urbano Primera Categoría 233-09 | 233 | 09 | 148 |
| MF-0062 | Corregidor 227-08 | 227 | 08 | 150 |
| MF-0063 | Profesional Especializado 222-12 | 222 | 12 | 152 |
| MF-0064 | Profesional Especializado | 222 | 12 | 154 |
| MF-0065 | Profesional Especializado | 222 | 12 | 156 |
| MF-0066 | Profesional Especializado 222-11 | 222 | 11 | 158 |
| MF-0067 | Profesional Especializado 222-10 | 222 | 10 | 160 |
| MF-0068 | Profesional Especializado | 222 | 10 | 162 |
| MF-0069 | Profesional Especializado | 222 | 10 | 164 |
| MF-0070 | Profesional Especializado | 222 | 10 | 166 |
| MF-0071 | Profesional Especializado | 222 | 10 | 168 |
| MF-0072 | Profesional Especializado | 222 | 10 | 170 |
| MF-0073 | Profesional Especializado | 222 | 10 | 172 |
| MF-0074 | Profesional Especializado | 222 | 10 | 173 |
| MF-0075 | Profesional Especializado | 222 | 10 | 176 |
| MF-0076 | Profesional Especializado | 222 | 10 | 178 |
| MF-0077 | Profesional Especializado | 222 | 10 | 180 |
| MF-0078 | Profesional Especializado | 222 | 10 | 182 |
| MF-0079 | Profesional Especializado | 222 | 10 | 184 |
| MF-0080 | Profesional Especializado 222-09 | 222 | 09 | 186 |
| MF-0081 | Profesional Especializado | 222 | 09 | 188 |
| MF-0082 | Profesional Especializado | 222 | 09 | 190 |
| MF-0083 | Profesional Especializado | 222 | 09 | 192 |
| MF-0084 | Profesional Especializado | 222 | 09 | 194 |
| MF-0085 | Profesional Especializado | 222 | 09 | 195 |
| MF-0086 | Profesional Especializado | 222 | 09 | 197 |
| MF-0087 | Profesional Especializado | 222 | 09 | 200 |
| MF-0088 | Profesional Especializado | 222 | 09 | 202 |
| MF-0089 | Profesional Especializado | 222 | 09 | 204 |
| MF-0090 | Profesional Especializado | 222 | 09 | 206 |
| MF-0091 | Profesional Especializado | 222 | 09 | 208 |
| MF-0092 | Profesional Especializado | 222 | 09 | 210 |
| MF-0093 | Profesional Especializado | 222 | 09 | 212 |
| MF-0094 | Profesional Especializado | 222 | 09 | 214 |
| MF-0095 | Profesional Especializado | 222 | 09 | 216 |
| MF-0096 | Profesional Especializado | 222 | 09 | 218 |
| MF-0097 | Profesional Especializado | 222 | 09 | 220 |
| MF-0098 | Profesional Especializado | 222 | 09 | 222 |
| MF-0099 | Profesional Especializado | 222 | 09 | 224 |
| MF-0100 | Profesional Especializado | 222 | 09 | 226 |
| MF-0101 | Profesional Especializado | 222 | 09 | 228 |
| MF-0102 | Profesional Especializado | 222 | 09 | 230 |
| MF-0103 | Profesional Especializado | 222 | 09 | 232 |
| MF-0104 | Profesional Especializado | 222 | 09 | 234 |
| MF-0105 | Profesional Especializado | 222 | 09 | 236 |
| MF-0106 | Profesional Especializado | 222 | 09 | 238 |
| MF-0107 | Profesional Especializado | 222 | 09 | 240 |
| MF-0108 | Profesional Especializado | 222 | 09 | 242 |
| MF-0109 | Profesional Especializado | 222 | 09 | 244 |
| MF-0110 | Profesional Especializado | 222 | 09 | 246 |
| MF-0111 | Profesional Especializado 222-08 | 222 | 08 | 248 |
| MF-0112 | Profesional Universitario 219-07 | 219 | 07 | 250 |
| MF-0113 | Profesional Universitario | 219 | 07 | 252 |
| MF-0114 | Profesional Universitario | 219 | 07 | 254 |
| MF-0115 | Profesional Universitario | 219 | 07 | 256 |
| MF-0116 | Profesional Universitario | 219 | 07 | 258 |
| MF-0117 | Profesional Universitario | 219 | 07 | 260 |
| MF-0118 | Profesional Universitario | 219 | 07 | 262 |
| MF-0119 | Profesional Universitario | 219 | 07 | 264 |
| MF-0120 | Profesional Universitario | 219 | 07 | 266 |
| MF-0121 | Profesional Universitario | 219 | 07 | 268 |
| MF-0122 | Profesional Universitario | 219 | 07 | 270 |
| MF-0123 | Profesional Universitario | 219 | 07 | 273 |
| MF-0124 | Profesional Universitario | 219 | 07 | 274 |
| MF-0125 | Profesional Universitario | 219 | 07 | 276 |
| MF-0126 | Profesional Universitario | 219 | 07 | 279 |
| MF-0127 | Profesional Universitario | 219 | 07 | 281 |
| MF-0128 | Profesional Universitario | 219 | 07 | 283 |
| MF-0129 | Profesional Universitario | 219 | 07 | 285 |
| MF-0130 | Profesional Universitario | 219 | 07 | 287 |
| MF-0131 | Profesional Universitario | 219 | 07 | 289 |
| MF-0132 | Profesional Universitario | 219 | 07 | 291 |
| MF-0133 | Profesional Universitario | 219 | 07 | 293 |
| MF-0134 | Profesional Universitario | 219 | 07 | 295 |
| MF-0135 | Profesional Universitario | 219 | 07 | 297 |
| MF-0136 | Profesional Universitario | 219 | 07 | 299 |
| MF-0137 | Profesional Universitario | 219 | 07 | 301 |
| MF-0138 | Profesional Universitario | 219 | 07 | 304 |
| MF-0139 | Profesional Universitario | 219 | 07 | 306 |
| MF-0140 | Profesional Universitario | 219 | 07 | 308 |
| MF-0141 | Profesional Universitario | 219 | 07 | 310 |
| MF-0142 | Profesional Universitario | 219 | 07 | 312 |
| MF-0143 | Profesional Universitario | 219 | 07 | 314 |
| MF-0144 | Profesional Universitario | 219 | 07 | 316 |
| MF-0145 | Profesional Universitario | 219 | 07 | 318 |
| MF-0146 | Profesional Universitario | 219 | 07 | 320 |
| MF-0147 | Profesional Universitario | 219 | 07 | 322 |
| MF-0148 | Profesional Universitario | 219 | 07 | 324 |
| MF-0149 | Profesional Universitario | 219 | 07 | 326 |
| MF-0150 | Profesional Universitario | 219 | 07 | 328 |
| MF-0151 | Profesional Universitario | 219 | 07 | 330 |
| MF-0152 | Profesional Universitario | 219 | 07 | 332 |
| MF-0153 | Profesional Universitario | 219 | 07 | 334 |
| MF-0154 | Profesional Universitario | 219 | 07 | 336 |
| MF-0155 | Profesional Universitario | 219 | 07 | 338 |
| MF-0156 | Profesional Universitario | 219 | 07 | 340 |
| MF-0157 | Profesional Universitario | 219 | 07 | 342 |
| MF-0158 | Profesional Universitario | 219 | 07 | 344 |
| MF-0159 | Profesional Universitario | 219 | 07 | 346 |
| MF-0160 | Profesional Universitario | 219 | 07 | 348 |
| MF-0161 | Profesional Universitario | 219 | 07 | 350 |
| MF-0162 | Profesional Universitario | 219 | 07 | 352 |
| MF-0163 | Profesional Universitario | 219 | 07 | 354 |
| MF-0164 | Profesional Universitario | 219 | 07 | 356 |
| MF-0165 | Profesional Universitario | 219 | 07 | 358 |
| MF-0166 | Profesional Universitario | 219 | 07 | 360 |
| MF-0167 | Profesional Universitario | 219 | 07 | 362 |
| MF-0168 | Profesional Universitario | 219 | 07 | 364 |
| MF-0169 | Profesional Universitario | 219 | 07 | 366 |
| MF-0170 | Profesional Universitario | 219 | 07 | 368 |
| MF-0171 | Profesional Universitario | 219 | 07 | 370 |
| MF-0172 | Profesional Universitario | 219 | 07 | 372 |
| MF-0173 | Profesional Universitario | 219 | 07 | 374 |
| MF-0174 | Profesional Universitario | 219 | 07 | 376 |
| MF-0175 | Profesional Universitario | 219 | 07 | 378 |
| MF-0176 | Profesional Universitario | 219 | 07 | 380 |
| MF-0177 | Profesional Universitario | 219 | 07 | 382 |
| MF-0178 | Profesional Universitario | 219 | 07 | 384 |
| MF-0179 | Profesional Universitario | 219 | 07 | 386 |
| MF-0180 | Profesional Universitario | 219 | 07 | 388 |
| MF-0181 | Profesional Universitario | 219 | 07 | 390 |
| MF-0182 | Profesional Universitario | 219 | 07 | 392 |
| MF-0183 | Profesional Universitario | 219 | 07 | 394 |
| MF-0184 | Profesional Universitario | 219 | 07 | 396 |
| MF-0185 | Profesional Universitario | 219 | 07 | 398 |
| MF-0186 | Profesional Universitario | 219 | 07 | 400 |
| MF-0187 | Profesional Universitario | 219 | 07 | 402 |
| MF-0188 | Profesional Universitario | 219 | 07 | 404 |
| MF-0189 | Profesional Universitario | 219 | 07 | 406 |
| MF-0190 | Profesional Universitario | 219 | 07 | 408 |
| MF-0191 | Profesional Universitario | 219 | 07 | 410 |
| MF-0192 | Profesional Universitario | 219 | 07 | 412 |
| MF-0193 | Profesional Universitario | 219 | 07 | 414 |
| MF-0194 | Profesional Universitario | 219 | 07 | 416 |
| MF-0195 | Profesional Universitario | 219 | 07 | 418 |
| MF-0196 | Profesional Universitario | 219 | 07 | 419 |
| MF-0197 | Profesional Universitario 219-06 | 219 | 06 | 421 |
| MF-0198 | Profesional Universitario 219-05 | 219 | 05 | 423 |
| MF-0199 | Profesional Universitario | 219 | 05 | 425 |
| MF-0200 | Profesional Universitario | 219 | 05 | 427 |
| MF-0201 | Profesional Universitario | 219 | 05 | 429 |
| MF-0202 | Profesional Universitario | 219 | 05 | 431 |
| MF-0203 | Profesional Universitario | 219 | 05 | 433 |
| MF-0204 | Profesional Universitario | 219 | 05 | 435 |
| MF-0205 | Profesional Universitario 219-04 | 219 | 04 | 436 |
| MF-0206 | Profesional Universitario | 219 | 04 | 438 |
| MF-0207 | Profesional Universitario 219-03 | 219 | 03 | 440 |
| MF-0208 | Profesional Universitario | 219 | 03 | 442 |
| MF-0209 | Profesional Universitario | 219 | 03 | 444 |
| MF-0210 | Profesional Universitario | 219 | 03 | 445 |
| MF-0211 | Profesional Universitario 219-02 | 219 | 02 | 447 |
| MF-0212 | Profesional Universitario | 219 | 02 | 449 |
| MF-0213 | Profesional Universitario 219-01 | 219 | 01 | 451 |
| MF-0214 | Profesional Universitario | 219 | 01 | 453 |
| MF-0215 | Profesional Universitario | 219 | 01 | 455 |
| MF-0216 | Profesional Universitario | 219 | 01 | 457 |
| MF-0217 | Profesional Universitario | 219 | 01 | 459 |
| MF-0218 | Almacenista General 215-12 | 215 | 12 | 461 |
| MF-0219 | Comisario de Familia 202-09 | 202 | 09 | 463 |
| MF-0220 | Tesorero General 201-13 | 201 | 13 | 465 |
| MF-0221 | Técnico Administrativo 367-08 | 367 | 08 | 467 |
| MF-0222 | Técnico Administrativo | 367 | 08 | 469 |
| MF-0223 | Técnico Administrativo | 367 | 08 | 471 |
| MF-0224 | Técnico Administrativo 367-07 | 367 | 07 | 473 |
| MF-0225 | Técnico Administrativo 367-06 | 367 | 06 | 474 |
| MF-0226 | Técnico Administrativo | 367 | 06 | 475 |
| MF-0227 | Técnico Administrativo | 367 | 06 | 477 |
| MF-0228 | Técnico Administrativo 367-05 | 367 | 05 | 478 |
| MF-0229 | Técnico Administrativo | 367 | 05 | 480 |
| MF-0230 | Técnico Administrativo | 367 | 05 | 481 |
| MF-0231 | Técnico Administrativo | 367 | 05 | 483 |
| MF-0232 | Técnico Administrativo | 367 | 05 | 484 |
| MF-0233 | Técnico Administrativo | 367 | 05 | 486 |
| MF-0234 | Técnico Administrativo | 367 | 05 | 488 |
| MF-0235 | Técnico Administrativo | 367 | 05 | 490 |
| MF-0236 | Técnico Administrativo | 367 | 05 | 492 |
| MF-0237 | Técnico Administrativo | 367 | 05 | 494 |
| MF-0238 | Técnico Administrativo | 367 | 05 | 496 |
| MF-0239 | Técnico Administrativo | 367 | 05 | 498 |
| MF-0240 | Técnico Administrativo | 367 | 05 | 499 |
| MF-0241 | Técnico Administrativo | 367 | 05 | 501 |
| MF-0242 | Técnico Administrativo | 367 | 05 | 503 |
| MF-0243 | Técnico Administrativo | 367 | 05 | 505 |
| MF-0244 | Técnico Administrativo | 367 | 05 | 507 |
| MF-0245 | Técnico Administrativo | 367 | 05 | 509 |
| MF-0246 | Técnico Administrativo | 367 | 05 | 511 |
| MF-0247 | Técnico Administrativo 367-05 | 367 | 05 | 512 |
| MF-0248 | Técnico Administrativo 367-03 | 367 | 03 | 513 |
| MF-0249 | Técnico Administrativo | 367 | 03 | 514 |
| MF-0250 | Técnico Administrativo | 367 | 03 | 516 |
| MF-0251 | Técnico Administrativo | 367 | 03 | 518 |
| MF-0252 | Técnico Administrativo | 367 | 03 | 520 |
| MF-0253 | Técnico Administrativo 367-02 | 367 | 02 | 521 |
| MF-0254 | Técnico Administrativo | 367 | 02 | 523 |
| MF-0255 | Técnico Administrativo | 367 | 02 | 524 |
| MF-0256 | Técnico Administrativo | 367 | 02 | 525 |
| MF-0257 | Técnico Administrativo 367-01 | 367 | 01 | 526 |
| MF-0258 | Agente de Tránsito 340-02 | 340 | 02 | 527 |
| MF-0259 | Técnico Operativo 314-08 | 314 | 08 | 529 |
| MF-0260 | Técnico Operativo | 314 | 08 | 530 |
| MF-0261 | Técnico Operativo | 314 | 08 | 532 |
| MF-0262 | Técnico Operativo | 314 | 08 | 533 |
| MF-0263 | Técnico Operativo | 314 | 08 | 535 |
| MF-0264 | Técnico Operativo | 314 | 08 | 537 |
| MF-0265 | Técnico Operativo | 314 | 08 | 539 |
| MF-0266 | Técnico Operativo 314-07 | 314 | 07 | 541 |
| MF-0267 | Técnico Operativo | 314 | 07 | 542 |
| MF-0268 | Técnico Operativo 314 | 314 | 06 | 543 |
| MF-0269 | Técnico Operativo | 314 | 06 | 545 |
| MF-0270 | Técnico Operativo | 314 | 06 | 547 |
| MF-0271 | Técnico Operativo 314-05 | 314 | 05 | 549 |
| MF-0272 | Técnico Operativo | 314 | 05 | 550 |
| MF-0273 | Técnico Operativo | 314 | 05 | 552 |
| MF-0274 | Técnico Operativo | 314 | 05 | 553 |
| MF-0275 | Técnico Operativo | 314 | 05 | 555 |
| MF-0276 | Técnico Operativo | 314 | 05 | 556 |
| MF-0277 | Técnico Operativo | 314 | 05 | 557 |
| MF-0278 | Técnico Operativo | 314 | 05 | 558 |
| MF-0279 | Técnico Operativo | 314 | 05 | 560 |
| MF-0280 | Técnico Operativo | 314 | 05 | 561 |
| MF-0281 | Técnico Operativo | 314 | 05 | 563 |
| MF-0282 | Técnico Operativo | 314 | 05 | 564 |
| MF-0283 | Técnico Operativo | 314 | 05 | 566 |
| MF-0284 | Técnico Operativo | 314 | 05 | 567 |
| MF-0285 | Técnico Operativo | 314 | 05 | 569 |
| MF-0286 | Técnico Operativo | 314 | 05 | 570 |
| MF-0287 | Técnico Operativo | 314 | 05 | 571 |
| MF-0288 | Técnico Operativo | 314 | 05 | 572 |
| MF-0289 | Técnico Operativo | 314 | 05 | 573 |
| MF-0290 | Técnico Operativo | 314 | 05 | 575 |
| MF-0291 | Técnico Operativo | 314 | 05 | 577 |
| MF-0292 | Técnico Operativo | 314 | 05 | 579 |
| MF-0293 | Técnico Operativo | 314 | 05 | 580 |
| MF-0294 | Técnico Operativo | 314 | 05 | 582 |
| MF-0295 | Técnico Operativo | 314 | 05 | 584 |
| MF-0296 | Técnico Operativo | 314 | 05 | 586 |
| MF-0297 | Técnico Operativo | 314 | 05 | 588 |
| MF-0298 | Técnico Operativo | 314 | 05 | 589 |
| MF-0299 | Técnico Operativo | 314 | 05 | 590 |
| MF-0300 | Técnico Operativo | 314 | 05 | 592 |
| MF-0301 | Técnico Operativo | 314 | 05 | 594 |
| MF-0302 | Técnico Operativo | 314 | 05 | 596 |
| MF-0303 | Técnico Operativo | 314 | 05 | 598 |
| MF-0304 | Técnico Operativo | 314 | 05 | 599 |
| MF-0305 | Técnico Operativo | 314 | 05 | 600 |
| MF-0306 | Técnico Operativo | 314 | 05 | 602 |
| MF-0307 | Técnico Operativo | 314 | 05 | 603 |
| MF-0308 | Técnico Operativo | 314 | 05 | 605 |
| MF-0309 | Técnico Operativo | 314 | 05 | 607 |
| MF-0310 | Técnico Operativo | 314 | 05 | 609 |
| MF-0311 | Técnico Operativo | 314 | 05 | 610 |
| MF-0312 | Técnico Operativo | 314 | 05 | 611 |
| MF-0313 | Técnico Operativo | 314 | 05 | 612 |
| MF-0314 | Técnico Operativo | 314 | 05 | 614 |
| MF-0315 | Técnico Operativo | 314 | 05 | 615 |
| MF-0316 | Técnico Operativo | 314 | 05 | 616 |
| MF-0317 | Técnico Operativo 314-03 | 314 | 03 | 618 |
| MF-0318 | Técnico Operativo | 314 | 03 | 619 |
| MF-0319 | Técnico Operativo | 314 | 03 | 621 |
| MF-0320 | Técnico Operativo | 314 | 03 | 623 |
| MF-0321 | Técnico Operativo | 314 | 03 | 625 |
| MF-0322 | Técnico Operativo | 314 | 03 | 627 |
| MF-0323 | Técnico Operativo | 314 | 03 | 629 |
| MF-0324 | Técnico Operativo | 314 | 03 | 631 |
| MF-0325 | Técnico Operativo | 314 | 03 | 632 |
| MF-0326 | Técnico Operativo | 314 | 03 | 633 |
| MF-0327 | Técnico Operativo | 314 | 03 | 634 |
| MF-0328 | Técnico Operativo | 314 | 03 | 636 |
| MF-0329 | Inspector de Tránsito y Transporte – 312-08 | 312 | 08 | 637 |
| MF-0330 | Conductor Mecánico 482-12 | 482 | 12 | 639 |
| MF-0331 | Conductor 480-12 | 480 | 12 | 641 |
| MF-0332 | Conductor | 480 | 12 | 643 |
| MF-0333 | Auxiliar de Servicios Generales 470-01 | 470 | 01 | 645 |
| MF-0334 | Secretario Ejecutivo del Despacho del Alcalde 438-12 | 438 | 12 | 646 |
| MF-0335 | Auxiliar Administrativo 407-12 | 407 | 12 | 648 |
| MF-0336 | Auxiliar Administrativo 407-09 | 407 | 09 | 650 |
| MF-0337 | Auxiliar Administrativo 407-08 | 407 | 08 | 652 |
| MF-0338 | Auxiliar Administrativo 407-07 | 407 | 07 | 654 |
| MF-0339 | Auxiliar Administrativo 407-06 | 407 | 06 | 656 |
| MF-0340 | Auxiliar Administrativo 407-05 | 407 | 05 | 658 |
| MF-0341 | Auxiliar Administrativo | 407 | 05 | 660 |
| MF-0342 | Auxiliar Administrativo | 407 | 03 | 662 |
| MF-0343 | Auxiliar Administrativo | 407 | 03 | 664 |
| MF-0344 | Auxiliar Administrativo 407-01 | 407 | 01 | 665 |

SHA-256 del manifiesto versionado (LF estable): `bd353e97c31a9744d232cb41955f98c125736e51a484136da0bf5a7c0edb1f33`.

## Integridad y aplicación

La migración añade únicamente `pagina_inicio` y `verificacion_documental` a las fichas existentes, con unicidad por versión. La protección SQL mantiene inmutables los campos normativos de las versiones publicadas/inactivas y permite completar únicamente la evidencia documental. No se duplican fichas ni tablas del Manual.

El comando protegido `php artisan manual:verify-pages --version-id=10 --user-id=<administrador> --dry-run` valida las 344 fichas, hashes, arrays de funciones, checksum de fuente y unicidad antes de escribir. Sin `--dry-run` aplica solo metadatos, en una transacción, con auditoría del actor y checksum. Es idempotente.

Digest normativo esperado: `2ccdb15add1bedbe5c83aa978cf4fa45`. Las evidencias antes/después y el estado de aplicación se registran en `MANUAL_FICHA_RESOLUTION_REPORT.md`.
