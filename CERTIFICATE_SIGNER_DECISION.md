# Decisión de firmante y autorización automática

2026-10-04, Bogotá. **Firmante: PENDIENTE.** No se presume nombre, dependencia competente ni delegación. Encabezado provisional del PDF 3A es diseño, no evidencia jurídica de competencia.

## Información institucional mínima

| Campo | Estado | Evidencia necesaria |
|---|---|---|
| Dependencia emisora | PENDIENTE | Estructura y funciones vigentes; responsable de registros laborales. |
| Cargo firmante | PENDIENTE | Facultad de certificar información laboral sencilla y con funciones. |
| Fundamento de competencia | PENDIENTE | Norma/acto vigente, artículos y ámbito; concepto Jurídica. |
| Delegación | PENDIENTE | Acto, autoridad delegante, delegado, límites, vigencia; o confirmación de competencia propia. |
| Titular del cargo | PENDIENTE | Identidad verificada, designación/posesión/vigencia, enlace con credencial. |
| Suplencia/encargo | PENDIENTE | Cargo/titular, acto, inicio/fin, ámbito, prioridad y cese; no mera bandera admin. |
| Autorización de automatización | PENDIENTE | Condiciones de aprobación por reglas, control y suspensión; compatibilidad del mecanismo/certificado/DPC con firma desatendida. |
| Responsable de custodia/integración | PENDIENTE | Custodio, TI, seguridad, suplencia operativa y segregación; no compartir PIN. |
| Credencial digital si aplica | PENDIENTE | Titular/propósito, vigencia, cadena, número de serie, uso, huella, revocación, proveedor y API. |
| Aprobación y revisión | PENDIENTE | Instrumento competente, fecha, versión, evidencia y responsables. |

## Separación de identidades

El empleado solicita sobre sus hechos laborales; Secretaría confirma pago cuando ON; el usuario backoffice configura u opera según permisos 2.9; el titular jurídico aprueba la certificación dentro de su competencia; la identidad de servicio ejecuta reglas autorizadas en el mecanismo custodio. **`generado_por` actual no es identidad de firmante.** El rol `admin` tampoco concede esa facultad. Mantener MFA y reautenticación administrativa; no crear `certificados.firmar` ni botón por documento.

**Fundamento para revisión jurídica:** Ley 527/1999 arts. 7, 16 y 28, [texto oficial](https://www.cancilleria.gov.co/normograma/compilacion/docs/ley_0527_1999.htm); Decreto 2364/2012 arts. 4 y 6 compilados en DUR 1074, [texto y notas](https://normograma.dian.gov.co/dian/compilacion/docs/decreto_2364_2012.htm). Consulta 2026-10-04. **INTERPRETACIÓN:** automatización atribuible necesita autorización y control demostrables; estas normas nacionales no identifican qué cargo municipal está habilitado. Jurídica debe aportar norma/acto local, no desarrollo.

## Vigencias y cambio de titular: propuesta

La selección del firmante se fija para un documento/version y se registra como contexto de oficialización separado del snapshot laboral. Revalidar habilitación inmediatamente antes del envío de firma; si el encargo terminó o autorización se suspendió, pausar sin publicar. Registrar momento de autorización, envío y firma/evidencia temporal; definir tratamiento de transacciones en vuelo al cesar un cargo. No backdate ni confiar únicamente en fecha local.

Director A → cese → Director B: preservar identidad/certificado/acto usados en cada histórico. Los nuevos usan contexto B aprobado. No sustituir el nombre sobre PDF A, regenerar ni re-firmar históricos. Si hay transacción ambigua de A, reconciliar antes de crear cualquier nueva operación; el cambio de titular no permite repetir ciegamente con B.

La designación de suplente y su integración deben estar preparadas para preservar servicio 24/7 dentro de límites aprobados. Si no hay titular habilitado, degraded mode recuperable sin documento oficial. Una representación gráfica de firma solo podría evaluarse como asset aprobado ligado al mecanismo real; no se implementa ni se solicita imagen como sustituto de competencia.

## Criterio de aprobación

Jurídica/dependencia competente valida facultad y autorización automática; seguridad valida control/custodia; TI implementa referencia de servicio y gestión de credenciales; archivo/protección de datos valida historia y retención. No hace falta entregar una clave privada a desarrollo. Datos de certificado público pueden analizarse bajo minimización; datos sensibles del titular no se publican por defecto.

Sin fundamento y firmante vigente aprobado, 3B.2 solo puede usar contextos sintéticos; no habilitar firma real ni entrega oficial.
