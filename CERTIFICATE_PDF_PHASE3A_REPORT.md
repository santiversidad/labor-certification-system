# Certificación laboral PDF — Informe Fase 3A

Fecha: 2026-09-23

Rama: `feat/certificate-types`

Base de Fase 2: `092347c3043ec03c5835531bbcf59591dc14024a`

## 1. Resultado

Fase 3A implementa un documento A4 administrativo para certificados laborales sencillos y con funciones. Todas las páginas se identifican de forma inequívoca como `BORRADOR - SIN VALIDEZ OFICIAL` y no contienen firma, imagen de firma, firmante real, sello, certificado X.509, TSA ni QR oficial.

La generación y las verificaciones se ejecutaron exclusivamente en el entorno CT de puertos `3200/8280` y bases `scl_certificate_types_*`.

## 2. Motor PDF

El generador mínimo propio `PdfBasicoService` no ofrecía soporte suficiente para composición institucional, Unicode, encabezado/pie repetible y paginación multipágina. Se sustituyó por `dompdf/dompdf`, fijado por `composer.lock` en la versión `3.1.6`.

Dompdf se configura con:

- papel A4 vertical y márgenes administrativos;
- fuente Unicode DejaVu Sans con subsetting;
- acceso remoto y ejecución PHP deshabilitados;
- `chroot` limitado a assets locales y storage;
- encabezado y pie HTML fijos, con `z-index` explícito;
- canvas reservado para `Página X de Y`.

La suite PHPUnit dispone de 256 MB únicamente en `phpunit.xml`, porque Dompdf y el parser de pruebas cargan fuentes Unicode durante el proceso monolítico. El límite del runtime productivo no fue alterado.

## 3. Arquitectura documental

`CertificateDocumentData` transforma exclusivamente un array snapshot schema v3 en datos de presentación. No tiene dependencia de modelos, relaciones Eloquent ni base de datos. Rechaza versiones de snapshot distintas de v3 para documentos nuevos y conserva intactos los snapshots históricos v2.

`CertificadoPdfService` recibe el snapshot, renderiza la vista Blade documental y produce los bytes PDF. `GenerarCertificadoService` entrega a este servicio solamente el snapshot ya construido; no reconstruye certificados emitidos desde relaciones vivas.

La vista `resources/views/certificados/laboral.blade.php` contiene CSS PDF propio. No reutiliza CSS del frontend ni carga URLs remotas. Los helpers de datos normalizan texto, etiquetas y fechas sin inventar valores ausentes.

## 4. Datos impresos

El documento imprime, cuando el snapshot los contiene:

- nombre completo y documento;
- denominación, código y grado del empleo;
- dependencia;
- naturaleza de vinculación;
- fechas de ingreso, inicio y finalización aplicables;
- fecha de expedición;
- radicado y código técnico existente.

No imprime salario, rango salarial, conocimientos ni requisitos. El código técnico no se denomina firma digital ni se presenta como prueba jurídica de autoría.

## 5. Diferencias sencillo / funciones

El certificado sencillo contiene únicamente la información laboral base y no incorpora Manual, ficha, propósito ni funciones, aun si esos campos fueran inyectados en el snapshot.

El certificado con funciones añade referencia del Manual, ficha/perfil, área funcional, propósito principal cuando existe y funciones esenciales. Las funciones conservan el orden del snapshot, se numeran desde 1 y se imprimen sin ordenar, resumir ni reescribir.

## 6. Multipágina

Las funciones se agrupan en bloques documentales de hasta seis para producir cortes previsibles y evitar encabezados huérfanos o superposiciones. Cada fragmento activa correctamente el margen de página de Dompdf y repite encabezado, marca de borrador, pie, código técnico y numeración.

La evidencia final genera:

- sencillo: 1 página;
- funciones: 2 páginas;
- funciones multipágina: 7 páginas con 32 funciones ficticias.

La revisión visual confirmó márgenes consistentes, secuencia 1–32, cierre únicamente en la última página y ausencia de cortes defectuosos en las funciones de evidencia.

## 7. Identidad, assets y placeholders

No se encontró en el repositorio un escudo, logotipo, membrete, footer documental o tipografía institucional oficial con procedencia utilizable. Por ello se usa un bloque tipográfico rotulado `IDENTIDAD VISUAL OFICIAL PENDIENTE`; no se inventaron escudos, logotipos, colores ni datos de contacto.

La estructura local reservada es `backend/laravel-app/resources/certificate-assets/`. La configuración permite incorporar posteriormente contacto y sitio web; ambos permanecen `null` en esta fase.

`CERTIFICATE_ASSETS_REQUIRED.md` documenta los elementos que deben entregar y aprobar las áreas institucionales.

## 8. Oficialización pendiente

El cierre reserva un bloque que muestra únicamente:

`Mecanismo de oficialización pendiente de definición institucional`

Firma electrónica, firma digital, imagen de firma, identidad definitiva del responsable, sello, X.509, TSA y QR oficial quedan expresamente fuera de Fase 3A.

## 9. Pruebas PDF

Se agregaron 14 pruebas que cubren:

1. generación sencilla;
2. generación con funciones;
3. cabecera `%PDF-`;
4. ausencia de funciones en sencillo;
5. función fixture sin reescritura;
6. ausencia de salario, conocimientos y requisitos;
7. Unicode y acentos;
8. multipágina;
9. `Página X de Y`;
10. marca de borrador en todas las páginas;
11. ausencia de firma/firmante;
12. ausencia de QR oficial;
13. exigencia de snapshot schema v3;
14. cero consultas a relaciones vivas durante el render.

`smalot/pdfparser` `2.12.5` quedó en `require-dev` para inspeccionar contenido y páginas reales. El test histórico de Manual también lee el PDF real en lugar de buscar texto dentro de bytes comprimidos.

Resultado backend final: **153 tests / 777 assertions**.

## 10. E2E

Playwright conserva siete escenarios y no usa mocks HTTP. `pdfjs-dist` `6.3.289`, dependencia de desarrollo fijada exactamente, inspecciona las descargas reales.

El flujo sencillo comprueba descarga, `%PDF-`, tipo `SENCILLO` y marca de borrador. El flujo con funciones descarga el PDF real, verifica `CON FUNCIONES`, la función fixture `Atender pruebas sintéticas.` y más de una página; la fixture E2E contiene 24 funciones ficticias.

Resultado final: **7/7**.

## 11. Evidencia PDF

El comando `php artisan certificates:generate-pdf-evidence` produce solamente datos ficticios en el directorio de desarrollo `output/pdf/`, fuera del bundle y del storage productivo:

- `certificado-sencillo-borrador.pdf`;
- `certificado-funciones-borrador.pdf`;
- `certificado-funciones-multipagina-borrador.pdf`.

Los tres archivos fueron renderizados a PNG con Poppler e inspeccionados visualmente. Los PNG temporales de revisión no forman parte del commit.

## 12. Dependencias y auditorías

Cambios de dependencias:

- runtime backend: `dompdf/dompdf` `3.1.6`;
- desarrollo backend: `smalot/pdfparser` `2.12.5`;
- desarrollo frontend/E2E: `pdfjs-dist` `6.3.289`.

`composer audit` y `npm audit --omit=dev` terminan sin vulnerabilidades reportadas. ESLint, TypeScript, build de producción y Pint terminan correctamente.

## 13. Regresiones de dominio

No se modificaron tipos de certificado, cuota mensual, Manual, permisos, onboarding, autenticación, caché de sesión ni configuración de pagos.

La regresión completa conserva:

- Manual: **344 perfiles / 3.115 funciones / 2.326 conocimientos**;
- administrador: **36 permisos**;
- frontend: **30/30**.

## 14. Pendientes para oficialización

Antes de declarar validez oficial se requiere recibir, validar y aprobar la identidad institucional, ejemplos documentales actuales, datos de contacto, dependencia responsable, firmante, calidad en que actúa y reglas jurídicas/técnicas de firma, sello, QR y validación. Ninguno de esos elementos se presume en este borrador.

## 15. Commit

El commit de cierre de esta fase usa el mensaje solicitado:

`feat(pdf): add institutional draft certificate layout`

Los PDFs de evidencia, renders temporales, dependencias instaladas, secretos y storage productivo no forman parte del commit.
