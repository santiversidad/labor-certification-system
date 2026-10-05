# Fase 3B.1 — Investigación de oficialización

Fecha de consulta y elaboración: **2026-10-04, America/Bogota**. Proyecto: Sistema de Certificaciones Laborales, Alcaldía de Villavicencio. Alcance exclusivamente en `C:\Users\mondr\source\labor-certification-recovery`.

**Investigación y propuestas técnicas; PENDIENTES DE VALIDACIÓN JURÍDICA E INSTITUCIONAL.** No se implementa firma real ni se autoriza producción. El PDF conserva **BORRADOR — SIN VALIDEZ OFICIAL**. No hay proveedor seleccionado, grado de confianza asignado ni firmante confirmado.

## 1. Precheck y alcance entregado

`git status`: limpio; rama de origen `feat/security-hardening`; HEAD `051d312`, `security: harden authentication authorization and application boundaries`. `git log --oneline -20` confirmó Certificate Types (`b1423bf`, `092347c`), UX 2.6 (`c0750b2`), pagos 2.7 (`6b29b9b`), identidades 2.8 (`7670f3f`), seguridad 2.9 (`051d312`), Manual (`6d85e46`), React Bits (`b48a83d`) y PDF 3A (`dbd5ad0`, `6d32fcf`). Rama creada: `feat/certificate-officialization-research`. No push.

Se entregan investigación, matriz de opciones, decisión de confianza, decisión de firmante, arquitectura, contratos documentales, mock sin firma, amenazas, delta RF/RNF, requisitos de assets y plan 3B.2. No se cambia runtime, esquema, snapshot, permisos ni UI. No se compran servicios, crean cuentas, contactan proveedores o generan claves/certificados.

Convenciones: **NORMA** identifica reglas de fuentes oficiales; **INTERPRETACIÓN TÉCNICA** identifica consecuencias de diseño; **RECOMENDACIÓN** identifica propuestas; **DECISIÓN PENDIENTE** identifica aprobaciones que desarrollo no puede sustituir.

## 2. Registro normativo y vigencia examinada

Cada fila contiene regla parafraseada, artículo, impacto y URL oficial. Fecha de consulta de **todas las filas: 2026-10-04 (Bogotá)**. Los decretos fuente compilados se estudian como antecedentes; el DUR consolidado es la referencia operativa. No se afirma que un texto original de 2012 siga siendo un reglamento separado después de su compilación.

| Ref. / norma / artículo | NORMA: regla resumida | INTERPRETACIÓN TÉCNICA: impacto | Fuente oficial consultada |
|---|---|---|---|
| N01 Ley 527/1999, arts. 2.c, 5–12, 16.3 | Reconocimiento de mensajes de datos; identificación/aprobación y confiabilidad para satisfacer firma; integridad, prueba y conservación. Admite atribución a sistemas automáticos programados por el iniciador o en su nombre. | Firmar un documento determinado, preservar evidencia y bytes. La automatización necesita autorización; no basta programarla. | [Cancillería, texto anotado](https://www.cancilleria.gov.co/normograma/compilacion/docs/ley_0527_1999.htm) |
| N02 Ley 527/1999, arts. 28–30, 35–38; Decreto Ley 019/2012, arts. 160–161 | Firma digital: presunción de intención y atributos de unicidad, verificación, control exclusivo, vínculo al mensaje y reglamentación. La reforma cambia el régimen de ECD a acreditación ONAC; regula certificados y revocación. | Una clave/certificado técnico no acredita por sí mismo competencia funcional ni aprobación del proceso. Conservar cadena e información de estado. | [Función Pública, Ley 527](https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=4276), contrastada con [Cancillería, arts. 28–38 y notas](https://www.cancilleria.gov.co/normograma/compilacion/docs/ley_0527_1999.htm) |
| N03 Decreto 2364/2012, arts. 1–8; Decreto 1074/2015, art. 3.1.1 | Antecedente de firma electrónica compilado en capítulo 47 del DUR: método confiable/apropiado, control/custodia, detección de alteraciones, acuerdo y criterios de seguridad. | Evaluar evidencia del mecanismo completo. Un login o imagen de firma no establece esa evidencia automáticamente. | [DIAN, Decreto 2364 con notas de compilación](https://normograma.dian.gov.co/dian/compilacion/docs/decreto_2364_2012.htm) |
| N04 Decreto 1074/2015, arts. 2.2.2.47.1–8 | Neutralidad tecnológica; pertenencia exclusiva de datos de creación y detección de alteración; obligaciones del firmante. El acuerdo tiene presunción controvertible y deber de demostrar seguridad/confiabilidad. | Para opción propia deben demostrarse identificación, autorización, custodia y trazabilidad, además del hash. | [Cancillería, DUR consolidado](https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_1074_2015.htm) |
| N05 Decreto 1789/2021, art. 1 → DUR 1074, arts. 2.2.2.47.9–10 | En actividad pública la autoridad determina confianza; contempla firmas vinculadas a autenticación SCD, sus suministradores y acceso a medios electrónicos. | El aplicativo no asigna nivel ni equivale a SCD por usar MFA. Revisar vinculación y obligaciones aplicables antes de escoger modalidad. | [MinTIC, Decreto 1789](https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_1789_2021.htm), contrastado con [DUR consolidado](https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_1074_2015.htm) |
| N06 Decreto 333/2014, arts. 1–7, 11–12 → DUR 1074, capítulo 48, especialmente 2.2.2.48.1.1–4, 48.2.2–3, 48.3.1–2 | Régimen de acreditación, categorías abierta/cerrada, DPC e infraestructura. | Verificar alcance, tipo de certificado y condiciones de uso, no solo acreditación genérica. | [DIAN, DUR consolidado](https://normograma.dian.gov.co/dian/compilacion/docs/decreto_1074_2015.htm); [MinCIT, decreto fuente 333](https://www.mincit.gov.co/normatividad/decretos/2014) |
| N07 Decreto 2642/2022, arts. 44 y 46 → DUR 1074, arts. 2.2.2.48.2.3–5 | Actualización a UVT de patrimonio/garantías de ECD abiertas. | Modificación relacionada con el régimen ECD; no cambia el QR ni asigna confianza al proyecto. | [Cancillería, notas vigentes capítulo 48](https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_1074_2015.htm) |
| N08 Decreto 1078/2015, título 17, arts. 2.2.17.1.2, 2.2.17.3.1, 2.2.17.4.7, 2.2.17.5.3–7, 2.2.17.6.1; subrogación Decreto 620/2020, art. 1 | SCD: sujetos, implementación gradual, análisis de riesgos, obligaciones, privacidad y sede electrónica. | Jurídica/TI deben determinar integración aplicable, con canales accesibles y tratamiento limitado. | [Cancillería, DUR TIC consolidado](https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_1078_2015.htm); [Función Pública, Decreto 620](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=118337) |
| N09 Resolución MinTIC 2160/2020, arts. 1–5 y anexos 1–2 | Adopta estándares SCD y guías de vinculación; prevé actualización de guías. | Aplicar versión institucionalmente verificada a la evaluación del proceso y la integración. | [MinTIC, resolución y anexos en normograma](https://normograma.mintic.gov.co/mintic/compilacion/docs/resolucion_mintic_2160_2020.htm) |
| N10 Decreto 767/2022, art. 1 → DUR 1078, capítulo 2.2.9.1, especialmente 2.2.9.1.1.2 y 2.2.9.1.2.1–2 | Actualiza Política de Gobierno Digital y gobernanza para sujetos obligados. | Aprobación del servicio con responsables y arquitectura institucional, no solo equipo de desarrollo. | [Función Pública, Decreto 767](https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=186766) |
| N11 Ley 1437/2011, arts. 53, 53A, 57, 59–60; Ley 2080/2021, arts. 8, 11–12 | Medios electrónicos, autenticidad/integridad/disponibilidad de actos y expedientes y sede electrónica; incluye reformas de 2021. | Jurídica determinará naturaleza del certificado laboral. No se califica unilateralmente como acto administrativo; conservar evidencia documental en cualquier caso. | [Cancillería, CPACA anotado](https://www.cancilleria.gov.co/normograma/compilacion/docs/ley_1437_2011.htm) |
| N12 Ley 1581/2012, arts. 4, 9–10, 17–18 | Finalidad, circulación restringida, seguridad/confidencialidad; bases de autorización/excepciones y deberes de tratamiento. | No inferir permiso de publicación de datos laborales por ser entidad pública. Evaluar D04 y proveedor como encargado. | [Cancillería, Ley 1581](https://www.cancilleria.gov.co/normograma/compilacion/docs/ley_1581_2012.htm) |
| N13 Resolución MinTIC 2277/2025, arts. 1–2 | Actualiza anexo 1 de Resolución 500/2021 y deroga otras disposiciones relacionadas. | Llevar MSPI actualizado al diseño/despliegue 3B.2; no declarar cumplimiento con la auditoría local 2.9. | [CRA, texto oficial compilado](https://normas.cra.gov.co/gestor/docs/resolucion_mintic_2277_2025.htm); [MinTIC, índice actual](https://gobiernodigital.mintic.gov.co/portal/Politica-de-Gobierno-Digital/Normatividad/) |

**Resultado de vigencia:** los textos consolidados consultados mantienen capítulos 47/48, con adición 1789/2021 y ajustes 2642/2022; se contrastaron título 17 y Política de Gobierno Digital y actualización MSPI 2025. No se encontró en esas fuentes una derogación del núcleo estudiado. Esto no certifica ausencia absoluta de reformas: Jurídica debe revalidar versión y aplicabilidad en el momento de aprobar y desplegar.

**Limitaciones de acceso registradas:** aperturas directas de Función Pública y SUIN (`https://www.suin-juriscol.gov.co/viewDocument.asp?id=30043687`, Decreto 1789) fallaron con 502; se contrastó contenido mediante normogramas oficiales MinTIC, Cancillería y DIAN. No se afirma lectura completa de SUIN. MinCIT se consultó como publicación oficial de decretos fuente; no como catálogo comercial. Los PDF anexos MinTIC estuvieron accesibles inicialmente, pero la búsqueda posterior de texto falló: la base usada fue la resolución/normograma oficial. El DUR conserva una remisión en art. 2.2.2.47.5 a 2.2.2.43.3; se registra para revisión jurídica y no se corrige silenciosamente. No se usaron blogs como autoridad.

## 3. Qué debe firmarse y diferencia entre mecanismos

**RECOMENDACIÓN:** aprobar el PDF completo de cada nuevo certificado, con tipo, hechos laborales provenientes de snapshot v3, radicado, dependencia/cargo autorizado, contenido de funciones cuando corresponda y QR/dominio si se aprueban. La evidencia debe vincular exactamente ese contenido con la autorización y el firmante. No basta firmar la solicitud, el login, el pago o una ficha de DB. En PAdES se protege el contenido mediante los rangos de bytes de la firma; el hash del archivo completo es un control adicional de almacenamiento.

La [comparación de opciones](CERTIFICATE_OFFICIALIZATION_OPTIONS.md) distingue identificación, custodia, alteración, presunciones, evidencia, certificados, automatización, revocación y operación. Firma electrónica es una categoría de métodos y no exige universalmente certificado X.509. Firma digital corresponde a un mecanismo criptográfico y sus requisitos legales. No se concluye superioridad legal universal. Firma escaneada, sello gráfico, SHA-256, código técnico y QR no constituyen por sí solos el mecanismo aprobado.

## 4. Actividad pública, confianza y firmante

**DECISIÓN PENDIENTE:** [grado de confianza](CERTIFICATE_TRUST_LEVEL_DECISION.md), modalidad permitida, suministrador e integración SCD cuando corresponda. El numeral 1 del art. 2.2.2.47.9 remite esa decisión a las autoridades. No se asigna bajo/medio/alto/muy alto. Los numerales siguientes se estudian en su contexto SCD; no autorizan afirmar que cualquier firma propia o cualquier certificado comercial satisface cualquier nivel. La cláusula de costos del parágrafo 3 aplica al supuesto allí regulado; no cambia tarifa de pago laboral en esta fase. Fuente: N05.

**Firmante PENDIENTE:** [ficha institucional](CERTIFICATE_SIGNER_DECISION.md). El encabezado tipográfico de 3A no prueba la competencia de esa dependencia. Deben aportarse estructura, funciones/acto/delegación, titular y suplencia con vigencias. Usuario que solicita, administrador, Secretaría que valida pago y titular jurídico de la firma son funciones distintas. Cambios de titular afectan documentos nuevos; no reescriben históricos.

## 5. Autoridades y entidades de certificación

ONAC acredita organismos y alcances; no firma certificados laborales. MinTIC establece marco y lineamientos de Gobierno Digital/SCD; MinCIT participa en regulación del sector y de firmas/ECD; las ECD prestan servicios dentro de su acreditación y políticas. Fuente: N05–N10 y [ONAC, esquema ECD](https://onac.org.co/informacion-por-esquemas-de-acreditacion/certificacion-digital-ecd/), consulta 2026-10-04.

Se verificó el [Directorio Oficial ONAC, filtro ECD](https://onac.org.co/directorio3/index.php/buscador/resultado_busqueda_especifica/ECD/0/0/0/0) y fichas/alcances que se detallan en la matriz de opciones. Tres alcances de ECD abiertas se examinaron en detalle: Certicámara, GSE y Andes. También se registró Olimpia abierta y Policía Nacional cerrada, delimitando lo no investigado. Acreditación no demuestra API desatendida, permiso de uso 24/7, SLA ni compatibilidad PDF; todo eso permanece pendiente. Sin contacto, cotización o preferencia comercial.

## 6. PAdES, TSA y revocación técnica

Referencia técnica: [ETSI EN 319 142-1 V1.2.1 (2024-01)](https://www.etsi.org/deliver/etsi_en/319100_319199/31914201/01.02.01_60/en_31914201v010201p.pdf), cláusulas 4.1, 5.4, 6.1–6.3; consulta 2026-10-04. B-B: firma embebida y referencia a certificado. B-T: añade evidencia temporal. B-LT: incorpora material de validación, cadena y revocación. B-LTA: incorpora protección temporal de archivo y requiere mantenimiento. El [programa ETSI](https://www.etsi.org/technical-groups/esi/?highlight=WyJhcyIsIidhcyIsInRoZSIsIid0aGUiXQ%3D%3D) presenta V1.3.0 (2026-08) **On Approval**; no se trata como publicación definitiva sustituta.

**PROPUESTA TÉCNICA PENDIENTE DE APROBACIÓN:** evaluar salida B-LT para conservación, con B-T como hito técnico y B-LTA si la retención exige preservación prolongada. Estos perfiles no se declaran obligaciones legales universales colombianas. La elección depende de archivo, proveedor y validadores; no se acredita conformidad PAdES por insertar un certificado o una imagen.

Una fecha impresa o `signed_at` de DB proviene del reloj de una parte. Un timestamp criptográfico vincula un resumen y una fecha certificada por TSA; se verifica firma, cadena, política y exactitud de la TSA. Ayuda a establecer existencia anterior a expiración, pero no subsana firma indebida o revocación relevante. Fuente técnica: [RFC 3161, §§2.1–2.4](https://www.rfc-editor.org/rfc/rfc3161), consulta 2026-10-04. No se usa TSA real.

OCSP responde estado del certificado y puede ser good/revoked/unknown; CRL publica revocaciones. Verificar autenticidad, vigencia/frescura y momento pertinente, no tomar `good` como prueba universal de legitimidad. Conservar evidencia histórica permite análisis posterior al vencimiento; ausencia de información puede resultar indeterminada. Revocación de la credencial criptográfica y anulación del documento laboral son ejes distintos. Fuentes técnicas: [RFC 6960, §§2.2–2.4 y 4.2.2](https://www.rfc-editor.org/rfc/rfc6960), [RFC 5280, §§5–6](https://www.rfc-editor.org/rfc/rfc5280), consulta 2026-10-04. No se implementa cliente OCSP/CRL.

## 7. QR, validación pública y privacidad: revisión real

Hallazgos del código activo, excluyendo copias `-camilo_ortega`:

- `TokenValidacionService`: `random_bytes(32)` / 256 bits y SHA-256 en `tokens_validacion`. `TokenValidacion::estaVigente()` respeta `expires_at`; creación actual no fija TTL obligatorio.
- `ValidacionPublicaController` y `ValidacionCertificadoResource`: devuelven código técnico, tipo v3, fecha, estado, nombre y cargo desde snapshot; no documento completo, cédula, email, teléfono, funciones o archivo. Verifican hash del PDF privado y estado; **no verifican firma ni carácter oficial**.
- `routes/api.php`: aliases `validar-certificado/{token}` y `certificados/validar/{token}`, tasa 30/min definida en 2.9; headers sensibles `no-store` y política de referrer general `strict-origin-when-cross-origin`.
- `GenerarCertificadoService` inserta la URL técnica con token en `ConstruirSnapshotCertificadoService::construir()`, `expedicion.url_validacion_tecnica`. Por tanto **no existe almacenamiento exclusivamente hashed en todo el sistema**: DB snapshot y PDF/URL contienen la capability. Esto es riesgo residual D04; se protegen como datos de acceso. Snapshot v3 no se cambia.
- `EstadoCertificadoEnum` ya tiene vigente/descargado/anulado/vencido. Migración `2026_06_22_000003` y controlador ya registran motivo, actor, fecha y auditoría de anulación. No se crea una segunda revocación especulativa.
- `CertificadoPdfService` mantiene BORRADOR en todas las páginas, red/PHP remotos deshabilitados y assets locales. No imprime QR.

**RECOMENDACIÓN:** QR solo URL HTTPS del dominio institucional pendiente + capability aleatoria; sin PII/secretos. Dominio también en texto visible. QR dentro del contenido **antes** de firmar. Portal muestra evidencia de archivo registrado, estado documental, firma/integridad y fecha del último control. No basta escanear un QR para autenticar una copia alterada: el portal no ve los bytes que recibió el tercero. Esa copia se verifica mediante firma PDF o comparación con archivo/hash bajo mecanismo aprobado. Sin descarga pública nueva.

Propuesta D04: mínimo público tipo/fecha/dependencia/estado documental y estado de evidencia; nombre y cargo sujetos a aprobación expresa. No motivo sensible de anulación. Separar TTL capability, vigencia documental, vigencia del certificado criptográfico y retención de archivo. Diseñar expiración/rotación sin reescribir el QR firmado: el código anterior puede quedar cerrado o con estado mínimo según política. La verificación criptográfica del archivo puede continuar tras expiración del enlace, bajo política de confianza y evidencia disponible. `no-store`, `Referrer-Policy: no-referrer` específica para validación, sin analytics/assets terceros, logs/proxy/APM sin token; revisar compatibilidad con límite Sanctum sin debilitarlo globalmente. No se implementa nada de esto todavía.

## 8. Automatización, custodia y arquitectura

Requisito confirmado: **24/7 sin clic/OTP humano por cada PDF**. Pago OFF genera automáticamente; pago ON exige confirmación actual y luego oficialización automática, sin segunda aprobación de Secretaría. El tramo posterior a pago puede operar 24/7; no se afirma que la validación manual del pago tenga disponibilidad automática hoy.

**RECOMENDACIÓN TÉCNICA PRELIMINAR, PENDIENTE DE VALIDACIÓN JURÍDICA E INSTITUCIONAL:** evaluar primero servicio remoto con custodia apropiada y automatización legalmente autorizada, con HSM institucional como alternativa. Firma propia permanece candidata solo si la entidad demuestra el mecanismo completo. Se descartan modalidades con intervención por documento bajo el requisito actual. Costes DESCONOCIDOS.

Pipeline: solicitud elegible → snapshot v3 congelado → reservar capability y contexto autorizado → render con QR/dominio aprobados → persistir input privado/hash previo → oficialización en cola → proveedor/HSM → verificación independiente → PDF final con evidencia del perfil aprobado → hash final → storage privado inmutable → publicación atómica de estado y activación de validación → entrega autenticada. No se firma retroactivamente ningún borrador histórico.

[Arquitectura](CERTIFICATE_OFFICIALIZATION_ARCHITECTURE.md) resuelve estado separado, outbox/after-commit, resultado ambiguo, retry/idempotencia, hash previo/final, storage y mantenimiento de largo plazo. [Contratos](CERTIFICATE_OFFICIALIZATION_CONTRACTS.md) define dos interfaces documentales mínimas, sin abrir endpoints. La clave real permanece en HSM/servicio remoto/KMS autorizado para el tipo de firma; nunca `.env`, DB, storage, imagen Docker, repositorio o frontend. API credentials de alto privilegio se resuelven en secret manager con identidad de servicio y privilegios mínimos.

## 9. Mock, POC y dependencias

Se entrega [mock documental](CERTIFICATE_OFFICIALIZATION_MOCK.md): contexto sintético, resultados sin evidencia, secuencia de fallo/reconciliación y vistas textuales. No es software ejecutable, no devuelve firma verificada y no crea PDF/QR. **POC criptográfica: NO**. El renderer existente no implementa verificación PAdES; agregar una biblioteca de firma sin decidir proveedor/perfil no aporta una prueba representativa de autorización remota ni de cadena confiable. No se afirma que toda biblioteca OSS sea insegura.

No se instaló/evaluó como aprobada ninguna dependencia nueva. Dompdf continúa como renderer ([repositorio del autor](https://github.com/dompdf/dompdf), consulta 2026-10-04). El gate previo a cualquier instalación está en el plan: mantenimiento/licencia/CVEs/PHP 8.4/Laravel 13/PAdES/TSA/claves remotas, verificación independiente y D08. No hay claves efímeras, certificados autofirmados ni resultados tamper ejecutados que se puedan confundir con evidencia jurídica.

## 10. Seguridad, riesgos y bloqueos

Se leyeron íntegramente como fuentes locales `SECURITY_HARDENING_PHASE29_REPORT.md`, `SECURITY_DECISION_REQUIRED.md`, `SECURITY_PRODUCTION_CHECKLIST.md` y `SECURITY_THREAT_MODEL.md`. [Modelo específico](CERTIFICATE_OFFICIALIZATION_THREAT_MODEL.md) mantiene todos los controles 2.9 y añade abuso de custodia/servicio remoto, replay, duplicados, sustitución, MITM, QR, revocación, reloj e insider.

La [clasificación A/B/C](CERTIFICATE_OFFICIALIZATION_THREAT_MODEL.md#riesgos-abiertos-de-29-y-gates) distingue investigación documental sin bloqueos; desarrollo aislado que puede avanzar con fixtures; integración real/diseño dependiente de decisiones; y producción sin autorización hasta cierre/aceptación formal pertinente. D01, D02, D03, D04, D05 y D08 siguen abiertos. No se cierra un riesgo institucional con esta investigación.

## 11. Preguntas concretas para la Alcaldía (14)

1. ¿Qué dependencia tiene competencia para expedir estas certificaciones y mediante qué norma/acto vigente?
2. ¿Qué cargo firma, quién es su titular y cuáles son las reglas/vigencias de encargo y suplencia?
3. ¿Existe delegación y autorización expresa de expedición automatizada, con límites, suspensión y custodia?
4. ¿Quién aprobará el grado de confianza del proceso y su correspondencia con lineamientos MinTIC/SCD?
5. ¿Qué modalidad electrónica/digital y política de evidencia acepta Jurídica para los destinatarios previstos?
6. ¿Existe proveedor institucional contratado y cuál es su alcance, DPC y disponibilidad de API automática sin OTP por PDF?
7. ¿Existe certificado digital apto para el cargo/función, con titular, uso autorizado, cadena, vigencia y revocación verificables?
8. ¿Qué equipo custodiará autorización de firma, secretos, HSM/integración, recuperación y respuesta ante compromiso?
9. ¿Puede entregarse certificado laboral vigente aprobado, membrete, escudo/logo, footer y contacto con procedencia?
10. ¿Se aprueba QR y qué datos mínimos, incluido nombre/cargo si corresponde, puede mostrar la validación pública?
11. ¿Cuál será el dominio oficial HTTPS y quién garantiza continuidad, DNS/TLS y operación del portal?
12. ¿Qué retención/TRD se aplica a PDF/evidencia/logs, qué política de anulación/expiración existe y qué ocurre al retirar al funcionario o expirar la capability?
13. ¿Qué SLA, tiempos de firma, reintentos, mantenimiento y soporte 24/7 se aprobarán, incluyendo indisponibilidad y conservación a largo plazo?
14. ¿Qué cierre/aceptación formal habrá para D01 activación, D02 recuperación MFA, D03 logs, D04 privacidad, D05 infraestructura y D08 supply chain antes de oficializar?

## 12. Plan, validación y cierre de 3B.1

[Delta RF/RNF y UML previsto](OFFICIALIZATION_REQUIREMENTS_DELTA.md); [plan 3B.2 condicionado](PHASE3B2_IMPLEMENTATION_PLAN.md). No se generan UML finales. Assets actualizados en [inventario](CERTIFICATE_ASSETS_REQUIRED.md). Bloqueos de diseño final: muestra aprobada, competencia/delegación, confianza/modalidad, dominio/privacidad/retención y contrato técnico de automatización. Bloqueos de producción adicionales: custodia, operación y riesgos abiertos 2.9.

Baseline heredado **del informe 2.9**, no suites ejecutadas de nuevo: backend 268/1.535; frontend 78; E2E 30; Manual 344 fichas/3.115 funciones/2.326 conocimientos. Esta fase solo añade/modifica Markdown. Verificación: revisión de fuentes, enlaces internos, coherencia de contratos y mock, diff exclusivamente documental y `git diff --check`; sin ejecutar suites runtime o consultas/mutaciones DB. Ni PDFs históricos, JSON/Excel del Manual, composer/npm lockfiles ni controles 2.9 se alteran.

Cierre autorizado: commit `docs(officialization): define certificate signing and verification architecture`, sin push. El hash de cierre se informa tras crearlo (no se incluye circularmente dentro de este archivo). **Detener después del commit. No iniciar 3B.2.**
