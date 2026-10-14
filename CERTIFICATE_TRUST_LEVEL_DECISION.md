# Decisión institucional del grado de confianza

Fecha: 2026-10-04, Bogotá. **DECISIÓN PENDIENTE; no se asigna nivel.** Este documento es una ficha para aprobación institucional, no un acto de adopción.

## Fundamento y significado

**NORMA:** Decreto 1789/2021, art. 1, que adiciona al Decreto 1074/2015 art. 2.2.2.47.9.1: las autoridades determinan confianza del proceso con referencia al título 17 del Decreto 1078 y lineamientos MinTIC. [Texto oficial MinTIC](https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_1789_2021.htm), consulta 2026-10-04. Registro de contexto/vigencia: N05, N08–N10 de [investigación](CERTIFICATE_OFFICIALIZATION_RESEARCH.md).

**INTERPRETACIÓN TÉCNICA:** decidir cuánto aseguramiento necesita la atribución/autorización del proceso y qué evidencias/controles serán aceptables. No equivale a escoger una marca de certificado, longitud de clave, perfil PAdES o puntaje OWASP. MFA de backoffice no es homologación automática a nivel SCD; el funcionario solicitante tampoco es necesariamente el titular que firma.

## Responsables propuestos, sujetos a estructura municipal

La autoridad competente de la Alcaldía debe adoptar la decisión. Jurídica identifica quién puede aprobarla y la forma documental exigible; Talento Humano/dependencia emisora caracteriza finalidad/efectos; TI y seguridad proponen arquitectura/controles; protección de datos y gestión documental revisan exposición/retención. El comité/órgano institucional competente puede emitir concepto si sus funciones lo permiten. No se inventa nombre de comité, facultad del alcalde ni delegación.

Desarrollo prepara evidencia y opciones. No tiene autoridad para aceptar riesgos jurídicos, atribuir firma a una persona, contratar ni asignar nivel unilateralmente. Una decisión debe constar con responsable, fundamento, alcance, fecha, condiciones y revisión, no solo comentario en un PR.

## Metodología propuesta

1. Delimitar proceso: certificados nuevos sencillos/con funciones, destinatarios, usos, consecuencias de falsedad, titular solicitante y titular firmante; distinguir confirmación de pago.
2. Identificar competencia y reglas específicas del documento. Determinar con Jurídica su naturaleza y eventual integración SCD; validar versión vigente de lineamientos.
3. Evaluar suplantación, firma indebida, alteración, exposición, disputas y caída del servicio; considerar impacto patrimonial, laboral, reputacional y de derechos, volumen y automatización.
4. Exigir evidencias verificables de identificación inicial, autorización automática acotada, control del titular/custodio, integridad del contenido, tiempo, revocación y continuidad; no suplirlas con un login.
5. Mapear esa evaluación a las categorías normativas **bajo, medio, alto, muy alto**, usando lineamientos aplicables y concepto jurídico; no convertir una suma de puntos técnicos en clasificación legal.
6. Comparar alternativas que cumplan lo anterior y **24/7 sin OTP/clic por PDF**. Descartar opciones incompatibles; documentar cualquier cambio formal de requisito.
7. Aprobar nivel/modalidad/condiciones y riesgos residuales mediante instrumento competente. Establecer revisión por cambio de proveedor, firmante, marco normativo, amenaza o propósito.

Guías de referencia, consulta 2026-10-04: [Resolución MinTIC 2160/2020, arts. 1–5 y anexos](https://normograma.mintic.gov.co/mintic/compilacion/docs/resolucion_mintic_2160_2020.htm), [DUR TIC título 17 consolidado](https://www.cancilleria.gov.co/normograma/compilacion/docs/decreto_1078_2015.htm). La ficha siguiente es una metodología propuesta; no pretende ser una clasificación normativa nueva.

## Ficha pendiente de completar

| Campo | Estado / evidencia exigida |
|---|---|
| Proceso y destinatarios | Producto delimitado; finalidad/destinatarios institucionales por confirmar. |
| Autoridad aprobadora/fundamento | PENDIENTE: norma/acto que habilita aprobación. |
| Nivel de confianza | **PENDIENTE: ninguno asignado.** |
| Informe de riesgos e impactos | Modelo 3B.1 como insumo; validar con información institucional. |
| Modalidad y relación SCD | PENDIENTE: electrónica/digital y aplicabilidad/suministrador. |
| Autorización automática y control | PENDIENTE: demostrar quién aprueba contenido/reglas y cómo revoca autorización. |
| Evidencia/perfil y validación | PENDIENTE: formatos, TSA, revocación, validadores y archivo. |
| Tratamiento/retención y D04 | PENDIENTE: finalidad, mínimo público, TTL y TRD. |
| Riesgos residuales/aceptaciones | PENDIENTE: responsables y controles compensatorios, no aceptación implícita. |
| Instrumento, fecha y próxima revisión | PENDIENTE: documento aprobado y condiciones de vigencia. |

Gate 3B.2: puede desarrollarse diseño local con fixtures; la selección/integración real depende de esta ficha. Gate producción: aprobación institucional vigente y pruebas de cumplimiento de sus condiciones.
