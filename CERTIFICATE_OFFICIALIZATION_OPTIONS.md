# Alternativas de oficialización — 3B.1

2026-10-04 (Bogotá). **RECOMENDACIÓN TÉCNICA PRELIMINAR — PENDIENTE DE VALIDACIÓN JURÍDICA E INSTITUCIONAL.** Costes desconocidos; ningún proveedor seleccionado. Reglas normativas y fechas en N01–N13 de [investigación](CERTIFICATE_OFFICIALIZATION_RESEARCH.md#2-registro-normativo-y-vigencia-examinada).

## Firma electrónica y digital: comparación con contexto

| Criterio | Firma electrónica | Firma digital |
|---|---|---|
| Identificación/aprobación | Evidencia del método y su vinculación al mensaje y persona (N03/N04). | Vínculo criptográfico y evidencia de identidad/competencia del suscriptor (N02). |
| Control | Datos de creación atribuibles exclusivamente al firmante; custodia demostrable. | Control exclusivo exigido; HSM/remoto debe demostrarlo, no meramente alojar una clave. |
| Alteración | Mecanismo que permita detectarla. SHA-256 editable junto al PDF no basta. | Verificación matemática sobre contenido firmado; revisar también revisiones posteriores. |
| Presunciones | Acuerdo regulado puede producir presunción controvertible; no se inventa acuerdo con terceros (N04). | Presunción de intención y atributos de art. 28; no garantía absoluta contra repudio (N02). |
| Evidencia | Identidad, autorización, método, contenido, tiempo y auditoría protegida. | Lo anterior más cadena, certificado, firma, política y estado de revocación. |
| Certificado/ECD | X.509 no es requisito universal; reglas SCD pueden condicionar suministrador (N05). | La solución propuesta verificará certificado, ECD/alcance y DPC conforme al régimen aplicable. |
| 24/7 | Condicional: mecanismo autorizado para actuar automáticamente. | Condicional: autorización desatendida, API/custodia adecuadas. OTP por PDF incompatible. |
| PDF/terceros | Puede necesitar portal/paquete probatorio propio; no asumir firma embebida. | PAdES es candidato para validadores PDF, sujeto a confianza de cadena. |
| Revocación | Invalidación del mecanismo/credenciales y documento según política. | Revocación de certificado OCSP/CRL más anulación documental separada. |
| Largo plazo | Conservación del expediente probatorio propio. | Evidencia histórica, TSA y conservación/mantenimiento según perfil. |
| Operación/coste | Debe sostenerse mecanismo y prueba; coste DESCONOCIDO. | Licencias/servicios/TSA/HSM/soporte: coste DESCONOCIDO. |

Las celdas operativas son **INTERPRETACIONES TÉCNICAS**. La comparación legal se fundamenta en [Ley 527, art. 28](https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=4276), [Decreto 2364, arts. 3–8 compilados](https://normograma.dian.gov.co/dian/compilacion/docs/decreto_2364_2012.htm) y [Decreto 1789, art. 1](https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_1789_2021.htm), consulta 2026-10-04. Ninguna etiqueta por sí sola decide validez del caso.

## Matriz de opciones candidatas

Evaluación cualitativa de diseño, no adjudicación ni nivel de confianza legal. Cada opción debe pasar primero gates de competencia, confianza, autorización automática y evidencia; después puede ponderarse disponibilidad/interoperabilidad/custodia/coste con pesos aprobados por la entidad.

| Criterio | A: electrónica institucional propia | B: digital remota mediante ECD | C: criptografía institucional/HSM |
|---|---|---|---|
| Validez/evidencia | Condicionada a mecanismo y prueba aprobados. | Condicionada a titular, alcance, DPC y autorización. | HSM solo no concede validez; definir certificado/cadena y autoridad. |
| AUTOMATIZABLE 24/7 | Sí, **condicional** a diseño aprobado. | Sí, **condicional** a API y política desatendidas. No acreditado por directorio. | Sí, **condicional** a servicio autorizado y operación continua. |
| Custodia | Entidad diseña y demuestra control. | Servicio custodio; revisar HSM/no exportación y control del titular. | Entidad opera HSM no exportable y segregación. |
| Integridad | Evidencia propia vinculada al binario. | PAdES candidato + verificador independiente. | PAdES candidato; ensamblado/verificador a cargo de integración. |
| No repudio | Evidencia evaluable; no promesa absoluta. | Mejor soporte criptográfico posible, sujeto a condiciones legales. | Evidencia criptográfica posible, sujeto a identidad y autorización. |
| Interoperabilidad PDF | No presumir compatible con validadores. | Exigir formato probado con varios validadores. | Construir/probar formato, no basta sign(hash). |
| Largo plazo | Archivo de expediente/evidencia propios. | TSA/material validación/exportación y continuidad por contrato. | TSA/cadena/OCSP/CRL/preservación operados por entidad o servicio. |
| Revocación | Política de mecanismo y documento. | ECD para certificado; Alcaldía para documento. | Coordinación CA/ECD y política documental. |
| Complejidad | Alta para demostrar confiabilidad/evidencia. | Media/alta por integración, autorización y validación. | Alta por PKI/HSM, alta disponibilidad y especialistas. |
| Auditabilidad | Diseñar evidencia resistente a alteración. | Correlacionar auditoría local/proveedor y exportar. | Auditoría HSM + dominio, segregada. |
| Disponibilidad | Depende de app/infra y mecanismo. | App + proveedor + TSA/estado; degraded mode recuperable. | App + HSM/cluster + cadena/TSA/operadores. |
| Dependencia / lock-in | Menor tercero; mayor mantenimiento propio. | Alta si evidencia/API no exportables. Mitigar con contrato e interfaz. | Hardware/firmware/integrador; plan de salida y renovación. |
| Integración API | Diseñada internamente, sin endpoint de firma arbitrario. | Contrato sign/status/reconciliación, sandbox, idempotencia. | Gateway institucional; no exponer HSM al navegador. |
| Operación | Seguridad, privacidad, archivo y atención permanentes. | Soporte 24/7, renovaciones, incidentes y continuidad contractual. | Custodios, ceremonias, backup HSM, NTP y respuesta 24/7. |
| Coste total | **DESCONOCIDO**. | **DESCONOCIDO**. | **DESCONOCIDO**. |
| Madurez en este proyecto | No implementada. | No implementada; hay ecosistema acreditado. | No inventariada ni disponible confirmada. |
| Riesgo dominante | Confundir autenticación/auditoría con firma válida. | Firma indebida o duplicada y dependencia remota. | Custodia/operación insuficientes y falsa confianza en hardware. |

Opción D: servicio institucional existente con firma electrónica/digital y evidencias exportables, o mecanismo vinculado a SCD si Jurídica confirma aplicabilidad. Debe superar mismos gates. Firma manuscrita escaneada/sello/QR solos se descartan como oficialización. Firma manual por clic/OTP por documento **DESCARTADA** mientras rija 24/7; un requisito formalmente cambiado exigiría nueva arquitectura.

Recomendación preliminar: evaluar B con autorización desatendida y evidencia PAdES verificable; comparar C si ya existe infraestructura institucional; mantener A para análisis jurídico de un mecanismo completo. No se concluye que B sea la única solución legal. Si ningún servicio respeta control/competencia y 24/7, registrar incompatibilidad y elevar decisión; no automatizar un OTP humano de manera encubierta.

## ECD: solo Directorio Oficial ONAC

Consulta: **2026-10-04, Bogotá**. [Resultado oficial filtro ECD](https://onac.org.co/directorio3/index.php/buscador/resultado_busqueda_especifica/ECD/0/0/0/0). Inventario informativo parcial, no ranking. Estado dinámico: volver a verificar antes de contratación e integración. No se contactaron entidades.

| Entidad | Código | Estado consultado | Alcance relevante comprobado | Fuente oficial | Observación |
|---|---|---|---|---|---|
| Sociedad Cameral de Certificación Digital – Certicámara S.A. | 16-ECD-002 | Acreditado / abierta | Titular de función pública local/centralizado; estampado cronológico; generación digital. | [Ficha](https://onac.org.co/directorio3/index.php/acreditaciones/16-ECD-002), [alcance pp. 5, 8, 10](https://onac.org.co/certificados/16-ECD-002.pdf) | API automática, perfil y autorización sin OTP pendientes. |
| Gestión de Seguridad Electrónica S.A. – GSE | 16-ECD-001 | Acreditado / abierta | Función pública local/centralizada; estampado cronológico; firmas electrónicas certificadas. | [Ficha](https://onac.org.co/directorio3/index.php/acreditaciones/16-ECD-001), [alcance pp. 4, 9–10](https://onac.org.co/certificados/16-ECD-001.pdf) | Alcance no prueba SLA ni producto desatendido disponible. |
| Andes Servicio de Certificación Digital S.A. | 16-ECD-004 | Acreditado / abierta | Certificados de persona natural/jurídica locales/centralizados; TSA; firmas electrónicas certificadas. | [Ficha](https://onac.org.co/directorio3/index.php/acreditaciones/16-ECD-004), [alcance pp. 4, 6–7](https://onac.org.co/certificados/16-ECD-004.pdf) | Aptitud del perfil para firmante público y API pendiente. |
| Olimpia IT S.A.S. | 21-ECD-001 | Acreditado / abierta | Categoría abierta en ficha; no se atribuye servicio específico. | [Ficha](https://onac.org.co/directorio3/index.php/acreditaciones/21-ECD-001) | Alcance por servicio no examinado; no habilita selección. |
| Policía Nacional – Dirección Logística y Financiera | 16-ECD-003 | Acreditado / cerrada | Categoría cerrada. | [Ficha](https://onac.org.co/directorio3/index.php/acreditaciones/16-ECD-003) | No se presume oferta general para Alcaldía/terceros. |

La acreditación debe coincidir con servicio, categoría y sitio pertinente. El directorio no confirma confianza automática en todos los lectores PDF ni reconocimiento internacional universal. Tampoco acredita el cargo/delegación de quien firmará este sistema. Comparar DPC/política de certificado, key usage, cadena, TSA, OCSP/CRL, custodia, servicio autorizado, PAdES, sandbox/API y exportación de evidencias. Precios **DESCONOCIDOS** para todas.

## Información técnica a obtener después de decisión institucional

Identidad/titular y propósito del certificado; delegación de automatización compatible con control del firmante; vigencias; cadena/raíces; revocación; autenticación de máquina sin secreto embebido; API/idempotencia/consulta por clave tras timeout; callbacks autenticados; límites/SLA/soporte y salida de contrato; recepción del documento frente a digest y flujo PAdES completo; conservación/exportación; subencargados/localización y minimización. No se valida producto comercial por la mera aparición en ONAC.
