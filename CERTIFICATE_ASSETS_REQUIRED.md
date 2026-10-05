# Insumos institucionales pendientes para el certificado

El PDF de Fase 3A es un **BORRADOR — SIN VALIDEZ OFICIAL**. La búsqueda en el repositorio encontró únicamente el `favicon.svg` y `icons.svg` de la aplicación web, más imágenes de referencia/captura; ninguno tiene procedencia o aprobación para uso como escudo, logo o membrete documental. La cabecera usa texto provisional. No se descargan imágenes en la generación.

## Necesario para diseño documental

| Insumo | Uso previsto |
| --- | --- |
| Escudo oficial SVG y logo oficial | Sustituir el encabezado tipográfico una vez aprobadas sus variantes y procedencia. |
| Variante negativa del logo | Uso únicamente si el manual de identidad la exige. |
| Manual de identidad vigente | Confirmar tamaños, color, espacios y uso en escala de grises. |
| Membrete y footer documental | Definir composición y campos obligatorios de cada página. |
| Tipografía institucional y licencia de incrustación | Decidir si reemplaza DejaVu Sans, incluida con el motor PDF. |
| Información oficial de contacto | Dirección, teléfono y sitio verificables para el pie, si corresponde. |
| Certificado laboral actual sencillo y con funciones | Comparar fórmula, campos y estructura con documentos vigentes. |

## Necesario para oficialización (Fase 3B o posterior)

| Definición | Estado |
| --- | --- |
| Responsable o firmante, cargo y facultad | Pendiente; el borrador no nombra a una persona. |
| Reglas oficiales de validación pública | Pendientes; no se inventa URL ni se atribuye validez al código técnico. |
| Decisión institucional sobre QR | Pendiente; no se imprime QR. |
| Mecanismo de firma y demás requisitos de oficialización | Pendiente; no se muestra firma ni sello. |

Los assets aprobados se almacenarán localmente en `backend/laravel-app/resources/certificate-assets/`, registrando archivo fuente, área proveedora, fecha y variante autorizada antes de configurar su uso.

## Actualización documental Fase 3B.1 — 2026-10-04 (Bogotá)

Se revisaron archivos versionados PDF/PNG/SVG/JPG y fuentes de la fase 3A. No se identificó una muestra de certificado laboral municipal **real y aprobada con procedencia** ni assets documentales autorizados. Capturas UI, referencias de diseño y PDFs sintéticos de evidencia no sirven como aprobación institucional. Ausencia de muestra vigente: **bloqueo del diseño final**, no de esta investigación. No se descargaron imágenes externas, fabricaron logos/escudos, firmas o sellos.

| Insumo/decisión adicional | Estado / evidencia requerida |
|---|---|
| Escudo y logo oficiales | PENDIENTE: archivo fuente local, variante, licencia/procedencia y aprobación para documentos. |
| Membrete, footer, contacto y dominio | PENDIENTE: manual vigente y campos obligatorios/continuación de páginas; dirección/teléfono/sitio aprobados. No sustituir con datos buscados sin aprobación. |
| Muestras sencillo y funciones vigentes | PENDIENTE: ejemplar aprobado/anónimo según privacidad, fórmula, estructura, tipo y requisitos de firma/archivo. |
| Dependencia/cargo y nombre del titular firmante | PENDIENTE: no deducir del encabezado provisional. Ver [decisión de firmante](CERTIFICATE_SIGNER_DECISION.md). |
| Competencia, delegación y suplencia | PENDIENTE: acto y vigencias, autorización desatendida con reglas; no son assets gráficos. |
| Requisitos del proveedor/integración | PENDIENTE: modalidad/certificado/DPC/cadena/perfil PAdES/TSA/OCSP/CRL, automatización legal 24/7 y API. No entregar claves/PIN a desarrollo. |
| QR y texto del dominio de validación | PENDIENTE D04/dominio: capacidad aleatoria sin PII, URL institucional aprobada y dominio impreso; incorporar antes de firma futura. |
| Grado de confianza/evidencia | PENDIENTE: aprobación mediante [ficha de confianza](CERTIFICATE_TRUST_LEVEL_DECISION.md); no se asigna nivel. |
| Sello o representación gráfica de firma | Solo si institución los exige/aprueba y están ligados a mecanismo real. PNG/JPG o sello no oficializan por sí solos. No solicitados ni implementados como sustitutos. |

Registro futuro de recepción: área proveedora, referencia de acto/aprobación, fecha/versión, fuente/archivo, checksum, variante autorizada, licencia y alcance de uso. Datos del firmante y proveedor permanecen fuera del snapshot laboral v3. No almacenar private key, PIN o secrets del proveedor en carpeta de assets.

La actualización no altera renderer, PDF, `approved_mark`, config, snapshot, binarios históricos ni plantilla. **BORRADOR — SIN VALIDEZ OFICIAL** y ausencia de QR/firma productivos permanecen.
