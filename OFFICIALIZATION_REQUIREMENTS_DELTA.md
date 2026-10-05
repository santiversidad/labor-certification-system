# Delta candidato RF/RNF e impacto UML — 3B.1

2026-10-04, Bogotá. **Propuesta pendiente de aprobación.** No se modifican requisitos finales ni diagramas UML existentes. Trazabilidad al [informe](CERTIFICATE_OFFICIALIZATION_RESEARCH.md), [arquitectura](CERTIFICATE_OFFICIALIZATION_ARCHITECTURE.md), [contratos](CERTIFICATE_OFFICIALIZATION_CONTRACTS.md) y [amenazas](CERTIFICATE_OFFICIALIZATION_THREAT_MODEL.md).

## Requisitos funcionales candidatos

| ID provisional | Cambio propuesto | Criterio de aceptación futuro |
|---|---|---|
| RF-O01 | Oficializar automáticamente nuevos certificados elegibles. | Pago OFF genera y oficializa por reglas; ON oficializa después de confirmación actual. Cero clic/OTP del firmante por PDF y cero segunda aprobación de Secretaría. |
| RF-O02 | Fijar autoridad, firmante y autorización vigente por documento. | Evidencia de cargo/acto/contexto versionados; cambio/encargo no reescribe historia; `generado_por` no se usa como firmante implícito. |
| RF-O03 | Incorporar validación/QR aprobados antes de firmar. | URL HTTPS + capability256; dominio visible; no PII en QR; contenido posterior a firma no se modifica. |
| RF-O04 | Conservar evidencia y archivo final inmutables. | Hash previo y final separados, perfil/cadena/TSA/revocación/política verificables, objeto privado exacto recuperable. |
| RF-O05 | Gestionar fallo/retry/reconciliación segura. | Timeout ambiguo consulta misma clave; un certificado lógico/una aceptación por versión; pago/cupo/radicado intactos. |
| RF-O06 | Consultar estado mínimo y entregar solo evidencia aceptada. | Owner/permisos/headers 2.9; pendiente/fallida/indeterminada no ofrece descarga oficial. |
| RF-O07 | Evolucionar validación pública con semántica aprobada. | Distingue legacy borrador, anulado y oficializado; muestra mínimo D04, control de integridad/firma y fecha; no publica PDF/historia. |
| RF-O08 | Reflejar anulación/revocación documental institucional. | Reutilizar fundamento/campos existentes cuando proceda; fecha/motivo/actor/auditoría; mantener bytes/evidencia y separar revocación X.509. |
| RF-O09 | Administrar operaciones excepcionales autorizadas. | Retry/revocación según permiso futuro, MFA/step-up/CSRF, auditoría y ámbito; sin endpoint de firma arbitraria. |
| RF-O10 | Preservar históricos y contextos de emisión. | Ningún ID/snapshot/binario previo se firma o sustituye retroactivamente; reexpedición según política formal. |

## Requisitos no funcionales candidatos

| ID provisional | Requisito | Evidencia/gate futuro |
|---|---|---|
| RNF-O01 | Integridad y autenticidad verificables. | Verificador independiente del proveedor; pruebas de contenido/rangos/revisiones/cadena/política; indeterminado no validado. |
| RNF-O02 | Disponibilidad automática 24/7. | SLA aprobado de app/worker/proveedor/TSA, métricas p95/backlog, supervisor, degraded mode y recuperación; no promesa de cero fallos. |
| RNF-O03 | Custodia y autorización seguras. | Clave no exportable externa; secret manager para integración; segregación y revocación ensayadas. |
| RNF-O04 | Privacidad de validación. | D04 aprobado, mínimo público, TTL/rotación, no-store/referrer, redacción logs y protección URL en snapshot/PDF. |
| RNF-O05 | Trazabilidad sin secretos. | Eventos correlacionados, acceso/retención aprobados; no capabilities/PII en metrics. |
| RNF-O06 | Interoperabilidad documental. | Perfil PAdES elegido probado con validadores independientes, sin equiparar formato europeo a obligación legal colombiana. |
| RNF-O07 | Conservación y validación futura. | TRD/evidencia exportable, restore, expiración/revocación histórica, política de preservación versionada. |
| RNF-O08 | Rendimiento y confiabilidad. | Requests sin espera indefinida, cola y timeouts jerarquizados, límites y backoff/idempotencia, pruebas concurrentes. |
| RNF-O09 | Seguridad de integración/supply chain. | HTTPS verificado, egress/parsers acotados, gate dependencias y auditorías; conservar controles 2.9. |
| RNF-O10 | Operación/multiinstancia y salida de proveedor. | Storage/locks/queue compartidos apropiados, monitorización y exportación; recuperar sin nueva firma ni coste de usuario. |

Valores numéricos de SLA y retención **pendientes**, no inventados. Los límites actuales siguen referencia local; no se declaran dimensionamiento productivo.

## Impacto previsto por diagrama, sin generarlos

| Vista UML | Cambio a estudiar en 3B.2 |
|---|---|
| Casos de uso | Oficializar como consecuencia de expedición; tercero valida estado; operación excepcional retry/anulación. Proveedor es actor externo, no humano firmando cada PDF. |
| Clases | OfficializationService, CertificateSigningProvider, CertificateSignatureVerifier y metadata separada; conservar clases laborales/v3. |
| ER | Relación certificado→oficialización/version/evidencia; constraints únicas; evaluar registro de intentos y outbox. Reutilizar tokens y anulación actuales. |
| Componentes | Dominio, renderer, cola/dispatcher, adaptador de firma, verificador, almacenamiento/evidencia privados, validación pública y secret manager. |
| Despliegue | Proxy HTTPS/app/worker/DB/queue/storage; proveedor remoto o gateway HSM; límites de red y custodios definidos en D05. |
| Secuencia | Reservar token→render QR→commit/outbox→firma→verificación→hash/storage→publicación; ramas timeout ambiguo/retry/anulación. |

No adoptar nueva state machine de pagos ni presentar `firmado` como estado de pago. No agregar permiso de firma manual por conveniencia. Los artefactos UML finales se actualizan únicamente después de decisiones institucionales y arquitectura final.
