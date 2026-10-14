# Insumos institucionales pendientes para el certificado

El PDF continúa siendo un **BORRADOR — SIN VALIDEZ OFICIAL**. En Fase 3A.2 el usuario aportó una muestra municipal escaneada y se extrajeron únicamente sus dos imágenes de encabezado para las nuevas plantillas. Las versiones legacy conservan su cabecera anterior. No se descargan imágenes en la generación. La recepción de una muestra no acredita aprobación para firma, oficialización ni vigencia del titular citado en ella.

## Recepción Fase 3A.2 — 2026-10-12 (Bogotá)

Fuente: `doc09536920260520143639.pdf`, dos páginas, SHA256 `5a2b78f6fbc36515f6cd10f90519e8b9409f82ecc2924d64ecf346ff5b43c338`, adjunto por el usuario. Procedencia técnica y recortes en `backend/laravel-app/resources/certificate-assets/reference/README.md`. El documento completo permanece fuera del repositorio; nombres, documento, salario y firmas manuscritas de la muestra no se reutilizan.

| Insumo | Estado actual |
|---|---|
| Escudo izquierdo / identidad derecha | Recortes PNG del encabezado recibido, versionados para borrador. Pendientes archivos originales de mayor calidad y aprobación documental final. |
| Composición | Referencia recibida, adaptada a A4 y bandas separadas para evitar la superposición observada en la segunda página escaneada. |
| Dirección | `Calle 40 No. 33 - 64, Barrio Centro, Piso 8`, transcrita de la muestra. |
| NIT | `892.099.324-3`, transcrito de la muestra. |
| Teléfono | `6715815 / 3156495910`, transcrito de la muestra. |
| Código postal | `500001`, transcrito de la muestra. |
| Sitio web / X / ciudad | `https://www.villavicencio.gov.co`, `@villavoalcaldia`, `Villavicencio, Meta`, transcritos de la muestra. |
| Nombre y facultad del firmante actual | Pendientes; el nombre histórico de la muestra no se activa automáticamente. Placeholder seguro. |
| Firmas adicionales | Hasta tres filas de texto configurables; ninguna persona ni rol se presume obligatorio. |
| QR | Reserva bajo el logo derecho en cada página; no se imprime QR funcional ni URL oficial inventada. Pendientes dominio y reglas institucionales. |
| Firma oficial / Fase 3B.2 | No activadas ni iniciadas. |

El administrador puede reemplazar imágenes con PNG/JPEG/WebP estáticos validados y normalizados a PNG privado; no se admite SVG ni imágenes de firmas personales como bloque de firma. La publicación conserva las referencias antiguas y solo afecta nuevas generaciones. Véase [modelo del editor](CERTIFICATE_TEMPLATE_EDITOR_MODEL.md).

Los apartados fechados a continuación conservan el diagnóstico histórico anterior a la recepción; prevalece el estado de esta actualización.

## Necesario para diseño documental

| Insumo | Uso previsto |
| --- | --- |
| Escudo y logo originales aprobados | Reemplazar recortes escaneados por fuentes de mayor calidad. El editor admite PNG/JPEG/WebP, no SVG. |
| Variante negativa del logo | Uso únicamente si el manual de identidad la exige. |
| Manual de identidad vigente | Confirmar tamaños, color, espacios y uso en escala de grises. |
| Membrete y footer documental | Definir composición y campos obligatorios de cada página. |
| Tipografía institucional y licencia de incrustación | Decidir si reemplaza DejaVu Sans, incluida con el motor PDF. |
| Información oficial de contacto | Dirección, teléfono y sitio verificables para el pie, si corresponde. |
| Certificado laboral actual sencillo y con funciones | Comparar fórmula, campos y estructura con documentos vigentes. |

## Necesario para oficialización (Fase 3B o posterior)

| Definición | Estado |
| --- | --- |
| Responsable o firmante, cargo y facultad | Pendiente; placeholder por defecto. El editor permite configurar textos confirmados, sin acreditar facultad ni firma. |
| Reglas oficiales de validación pública | Pendientes; no se inventa URL ni se atribuye validez al código técnico. |
| Decisión institucional sobre QR | Pendiente; no se imprime QR. |
| Mecanismo de firma y demás requisitos de oficialización | Pendiente; no se muestra firma ni sello. |

Los recortes de referencia se conservan en `backend/laravel-app/resources/certificate-assets/reference/`. Los reemplazos del editor se normalizan y almacenan en el disco privado `certificate-template-assets/`, con hash, procedencia y actor en `plantilla_assets`. La aprobación documental final requiere registrar área proveedora, fecha y variante autorizada.

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
