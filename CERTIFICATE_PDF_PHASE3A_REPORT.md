# Fase 3A — PDF institucional borrador

Fecha: 2026-09-24. Rama: `feat/pdf-institutional-draft`, creada desde `feat/certificate-types` en `b48a83d` (incluye el pulido React Bits). El alcance es exclusivamente presentación documental, generación de evidencia y pruebas PDF. No hubo cambios de dominio, frontend, migraciones, cuotas, pagos, Manual ni storage histórico.

## 1. Estado inicial y motor

El punto de partida ya tenía Dompdf 3.1.6, `smalot/pdfparser` 2.12.5 para pruebas y `pdfjs-dist` 6.3.289 para E2E. El PDF anterior era un borrador técnico con estilos dentro de Blade, paginación por bloques fijos y evidencia de funciones ficticias. Se conservó Dompdf y su `composer.lock`; no se instalaron ni actualizaron dependencias. El motor soporta A4, DejaVu Sans Unicode, CSS de impresión y contador de páginas por canvas. La QA visual encontró que un contenedor `<main>` provocaba pérdida del margen visible en algunas páginas de continuación; se retiró y se verificaron de nuevo todas las páginas.

Dompdf opera con red y ejecución de PHP deshabilitadas, `chroot` local y subsetting de DejaVu Sans. No se hizo investigación de firma ni se incorporó material de oficialización.

## 2. Cambios y arquitectura

- `CertificateDocumentData` convierte únicamente el snapshot v3 a un ViewModel: redacta cuerpo y cierre sin género supuesto ni valores ausentes, formatea fechas, filtra marcadores `null`/`undefined`/`N/A`, mantiene el orden y el texto original de cada función. Agrupa para la paginación hasta seis funciones o 1.500 caracteres por fragmento, sin resumirlas.
- `CertificadoPdfService` compone PDF A4 desde el ViewModel. Encabezado tipográfico provisional, marca BORRADOR, pie y `Página X de Y` se dibujan en cada página mediante canvas. El CSS vive en `resources/certificate-assets/document.css`; Blade solo contiene estructura y campos recibidos. No hay consultas a la base desde Blade ni desde el adaptador de presentación.
- `CertificateAssetResolver` lee CSS local y reserva una marca PNG/JPG aprobada por configuración; `approved_mark` sigue en `null`. No se muestran escudos, logos, firmas, sellos ni QR inventados.
- `GenerateCertificatePdfEvidence` usa el parser oficial y el JSON versionado `manual_funciones_decreto_015_2023.json` (SHA-256 `261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e`) para MF-0002. El nombre y documento del empleado son ficticios; cargo 020/02, dependencia, ficha y las 11 funciones provienen de esa fuente. El tercer PDF emplea 32 funciones sintéticas para forzar varias páginas.

## 3. Estructura documental y contenido

Todas las páginas A4 tienen encabezado `ALCALDÍA DE VILLAVICENCIO / SECRETARÍA DE DESARROLLO INSTITUCIONAL / DIRECCIÓN DE PERSONAL`, rotulado como tipográfico provisional; título `CERTIFICACIÓN LABORAL`, tipo, `BORRADOR — SIN VALIDEZ OFICIAL`, fórmula `CERTIFICA:`, cuerpo laboral, cierre, bloque de oficialización pendiente y pie con `Página X de Y`. El azul sobrio sigue la identidad general del sistema; no se trasladaron React Bits ni efectos visuales de dashboard. Se confirmó legibilidad en color y escala de grises.

El **sencillo** muestra nombre, documento, empleo, código, grado, dependencia, tipo/naturaleza de vinculación, fechas disponibles, expedición, radicado y código técnico si ya existe. No muestra Manual, ficha, propósito, funciones, conocimientos ni salario. El de **funciones** agrega referencia del Manual capturado, ficha, área, propósito y texto íntegro de funciones esenciales, numeradas en orden de snapshot. La evidencia MF-0002 contiene exactamente las once funciones y el cargo 020/02 de la fuente. El código técnico, cuando existe, se etiqueta como tal; no se califica como firma. Las evidencias no fabrican código ni URL de validación.

La zona final solo dice `Mecanismo de oficialización pendiente de definición institucional.` No contiene nombre de firmante, firma, sello ni QR.

## 4. Snapshot y compatibilidad legacy

Los documentos nuevos exigen `snapshot_schema_version = 3`. El render recibe exclusivamente `snapshot_datos`; no recompone desde funcionarios, cargos o fichas actuales. Una prueba emite un certificado, modifica después el nombre vivo del funcionario y vuelve a descargar: el snapshot y los bytes almacenados siguen idénticos. Los snapshots v2 y sus archivos históricos permanecen intactos. El renderer v3 rechaza explícitamente v2 para regeneración; no se declara una compatibilidad que no existe. No se tocaron los IDs 31, 32, 33 ni sus hashes, y no se sustituyeron binarios perdidos.

## 5. Paginación, evidencia y rendimiento

Las funciones se imprimen como lista numerada, no tabla. Los grupos reciben salto controlado, margen de 39/22/27/22 mm, encabezado y pie repetidos. Los tres PDFs se generaron con `php artisan certificates:generate-pdf-evidence` en `output/pdf/` (ignorado por Git):

| Archivo | Datos | Páginas A4 | Tiempo aproximado |
| --- | --- | ---: | ---: |
| `certificado-sencillo-borrador.pdf` | Empleado ficticio | 1 | 0,2–0,5 s |
| `certificado-funciones-mf0002-borrador.pdf` | Empleado ficticio, MF-0002 real, 11 funciones | 3 | 0,2–0,3 s |
| `certificado-funciones-multipagina-borrador.pdf` | Fixture ficticio de 32 funciones | 7 | 0,2–0,3 s |

Se renderizaron las páginas con Poppler y se revisaron márgenes, títulos, acentos, saltos, numeración, secuencia de funciones, marca BORRADOR y cierre. También se renderizó la página 2 de MF-0002 en escala de grises. Los PNG de QA están en `tmp/pdfs/`, ignorados por Git. `smalot/pdfparser` omite algunos fragmentos de líneas justificadas aunque Poppler los muestra completos; por ello las pruebas comparan el texto íntegro en el HTML preparado desde el snapshot, además de comprobar páginas y numeración en el PDF binario.

## 6. Pruebas y regresión

Pruebas PDF: generación sencilla y con funciones, `%PDF-`, separación de tipos, MF-0002 con 11 funciones en orden, ausencia de salario/`requiere_salario`, Unicode, campos vacíos, BORRADOR y `Página X de Y` en todas las páginas, multipágina, ausencia de firma/QR, fuente exclusiva snapshot v3, cero consultas a relaciones vivas y descarga inmutable tras cambiar datos vivos. No se cargaron 344 fichas en la suite; los tests ordinarios conservan fixtures mínimos y la prueba de evidencia lee el JSON versionado.

| Validación | Resultado |
| --- | --- |
| Backend completo | 157 pruebas, 826 aserciones, pasa |
| Frontend Vitest | 33/33, pasa |
| Playwright E2E | 7/7, pasa tras reiniciar exclusivamente la base aislada `scl_certificate_types_e2e` por cuota consumida de una corrida anterior |
| ESLint, TypeScript y build Vite | Pasan |
| Pint PHP | Pasa |
| Manual CT desarrollo | Versión ID 10 publicada, Decreto 1000-24/015 de 2023; 344 fichas / 3.115 funciones / 2.326 conocimientos |
| Admin | 36 permisos |
| Certificate Types | `sencillo` / `funciones`; sin tabla `rangos_salariales` ni columnas `requiere_salario`/`salario_override` |
| UI salarial activa | 0 referencias; solo quedan archivos legados con sufijo `-camilo_ortega` fuera de la navegación activa |

La primera ejecución E2E obtuvo 5 pases y falló por cuota ya consumida en un fixture sintético. Se verificó que el contenedor apuntaba a `scl_certificate_types_e2e`, se ejecutó `migrate:fresh` **solo en esa base aislada**, se sembró `e2e:prepare-autoservice` y se volvió a correr la suite. CT desarrollo y su Manual no se borraron ni migraron de nuevo.

No se ejecutó `composer audit` porque no cambió ninguna dependencia ni lockfile.

## 7. Assets, pendientes y commit

No hay asset oficial aprobado con procedencia utilizable. `CERTIFICATE_ASSETS_REQUIRED.md` distingue los insumos necesarios para diseño de las decisiones necesarias para oficialización: escudo SVG, logo y variante negativa, manual de identidad, membrete, footer, fuente institucional, contacto, certificados vigentes, responsable, validación, QR y mecanismo de firma. DejaVu Sans proviene de Dompdf y no se copió fuente adicional.

Archivos de esta fase: `CertificateDocumentData.php`, `CertificadoPdfService.php`, `CertificateAssetResolver.php`, `GenerateCertificatePdfEvidence.php`, `certificate_pdf.php`, `laboral.blade.php`, `resources/certificate-assets/document.css` y su `README.md`, `CertificatePdfTest.php`, `SnapshotCertificadoTest.php`, `CERTIFICATE_ASSETS_REQUIRED.md` y este informe.

Commit de cierre: `feat(pdf): add institutional draft certificate layout`. Fase 3B sigue pendiente; no se inició firma, QR, oficialización ni investigación jurídica.
