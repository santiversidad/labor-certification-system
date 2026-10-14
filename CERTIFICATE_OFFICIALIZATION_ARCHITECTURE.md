# Arquitectura propuesta de oficialización — 3B.1

Fecha: 2026-10-04, Bogotá. **PROPUESTA TÉCNICA PENDIENTE DE APROBACIÓN JURÍDICA E INSTITUCIONAL.** No hay implementación, migración, nuevos permisos/endpoints o cambio de snapshot. PDF actual: **BORRADOR — SIN VALIDEZ OFICIAL**.

## 1. Situación actual y límite de integración

`ExpedirCertificacionService` valida identidad/actividad, asignación, cupo mensual por tipo y crea solicitud/radicado bajo locks. Pago OFF llama `GenerarCertificadoService` dentro del flujo transaccional; ON crea orden y posterior confirmación habilita generación. `GenerarCertificadoService` congela v3, renderiza Dompdf y guarda PDF privado/hash; rechaza duplicado por solicitud. El estado documental `vigente` y respuesta pública `valido` actuales **no prueban oficialidad**.

`CertificadoPdfService`, `CertificateDocumentData`, `ConstruirSnapshotCertificadoService`, pagos, cuotas e identidades permanecen sin cambio. Una llamada remota no debe añadirse dentro de la transacción larga actual: el trabajo futuro delimitará transacción local y despacho fiable. No se regenera ni firma ningún ID/snapshot/PDF histórico por esta fase.

## 2. Diagrama textual candidato (sin UML final)

```text
Solicitud elegible (owner/identidad/cuota/pago confirmado si ON)
  ↓
ExpedirCertificacionService / generación local existente
  ↓
Snapshot laboral v3 inmutable + identificador lógico/radicado existente
  ↓
Reservar capability aleatoria inactiva y contexto de emisión autorizado
  ↓
Render PDF con QR + dominio visible + assets APROBADOS (antes de firmar)
  ↓
Bytes pre-firma inmutables → input_sha256 → staging privado
  ↓
Transacción local: oficialización pendiente + evento durable de despacho
  ↓ commit
Outbox/cola → OfficializationService (revalida habilitación de firmante)
  ↓ HTTPS validado / gateway institucional
CertificateSigningProvider → servicio remoto o HSM autorizado
  ↓
PDF con firma/evidencia del perfil aprobado → staging privado
  ↓
CertificateSignatureVerifier independiente + política institucional
  ↓ solo si evidencia aceptable
SHA-256 del PDF FINAL → objeto privado inmutable → publicación DB atómica
  ↓
Activar capability / estado oficializado → entrega autenticada del mismo binario
  ↓
Tercero escanea QR → HTTPS dominio oficial → validación mínima
  + software PDF verifica la firma de su propia copia
```

No generar primero borrador y luego borrar watermark al firmarlo. En 3B.2 habrá una ruta explícita para documentos **nuevos** con plantilla autorizada y gates habilitados; hasta aprobación seguirá borrador. Lo firmado incluye todo contenido visible, QR, dominio, paginación y representación institucional. Prohibido agregar QR, texto o assets después. QR no es firma.

El snapshot v3 ya contiene `expedicion.url_validacion_tecnica`; puede seguir suministrando URL a render nuevo bajo política aprobada. Contexto de firma/proveedor, identidad de firmante, perfil/plantilla y aprobación se registran aparte. No introducir `provider_data` en hechos laborales ni reescribir snapshot histórico.

## 3. Hash, formato e inmutabilidad

`input_sha256 = SHA256(bytes preparados antes de firma)` fija el input y su asociación con documento, contexto y clave idempotente. No es la firma; tampoco se asume idéntico al digest CMS: preparar objetos y rangos PDF tiene reglas específicas. El adaptador debe verificar relación entre input autorizado y contenido protegido, permitiendo solo cambios de estructura/evidencia de firma autorizados, nunca re-render, sustitución de páginas o contenido del proveedor.

`final_sha256 = SHA256(bytes finales después de firma, TSA y material del perfil aprobado)`. Es distinto del hash pre-firma y del `hash_pdf` del borrador actual. Descargar exactamente ese objeto y comprobar hash antes de entrega; no re-render desde snapshot para sustituir binario firmado. Estado del proveedor `completed` no demuestra firma válida: inspeccionar firma, cadena, contenido, timestamp, revocación y revisiones posteriores con verificador independiente.

La firma PDF protege `ByteRange` y estructuras según formato. Agregar/modificar contenido tras firma queda prohibido por política del sistema; puede invalidar cobertura o producir revisión posterior no permitida aunque la firma original siga matemáticamente verificable. No aceptar como oficial un PDF con contenido nuevo sin protección. Las ampliaciones normalizadas DSS/timestamps de archivo son un caso técnico específico que no debe confundirse con insertar QR.

**Preservación B-LTA futura:** si se aprueba, conservar binario entregado sin sobrescribir; una ampliación válida de evidencia será nueva representación archivística enlazada por hash/version y con verificación de revisiones. No es reexpedición laboral ni autoriza cambiar contenido. Hasta definir política, no automatizar ampliación. Expiración de certificado no borra firma previa; resultado depende de tiempo/evidencia/confianza histórica.

## 4. Estados: mínimo en eje separado

No extender hoy enums/tablas. Candidato de oficialización separado de `EstadoSolicitudEnum` (pago/generación) y `EstadoCertificadoEnum` (vigencia/anulación):

| Estado propuesto | Significado / salida permitida |
|---|---|
| `pendiente` | Input fijado; falta oficializar. Sin entrega oficial. |
| `en_proceso` | Operación reclamada; transacción remota puede estar pendiente o resultado ambiguo. |
| `fallida` | Fallo conocido y clasificado; retry seguro si reintentable. No incluye timeout ambiguo. |
| `oficializada` | Evidencia validada + archivo/hash privados confirmados y referencia durable. |

`generado` ya está representado por flujo actual, no nuevo estado necesario. No necesita `revocado` en firma si la anulación documental existente sirve: diseñar su efecto sin duplicar eje. Estado de verificación tiene `verificada/no_verificada/indeterminada`, y estado de credencial `good/revoked/unknown` según método, distintos del documento. `oficializada` no equivale a documento eternamente vigente.

Propuesta condición de publicación: documento vigente + oficialización aceptada + hash coincidente + resultado según política de verificación. Evidencia faltante/indeterminada nunca se presenta como firma confirmada. Legacy continúa identificado como borrador técnico en evolución de validación.

## 5. Fallos, retry e idempotencia

Invariante: **una solicitud → un certificado lógico → una oficialización aceptada por versión**. Pago, cupo y radicado ya consumidos no se ejecutan otra vez. Retry opera sobre ID oficialización y bytes/contexto fijados, no llama de nuevo a expedir. Usar constraint de una oficialización lógica por certificado/version, claims transaccionales y compare-and-set/version para resultados concurrentes. Jobs únicos son defensa adicional; no garantizan por sí mismos una llamada remota única.

Clave candidata: `officialize:v1:{internal_officialization_uuid}:{document_version}`. Es identificador interno aleatorio, sin cédula/nombre/email y único por operación; no es capability ni consecutivo público. Vincularla permanentemente a `input_sha256`, contexto de firmante, perfil y proveedor aprobado. Mismo retry usa misma clave y mismos bytes. Otro input bajo misma clave es error de seguridad.

| Punto de fallo | Recuperación propuesta |
|---|---|
| Input no escrito / transacción local abortada | No despachar ni activar token; reconciliar staging huérfano. |
| Commit local pero enqueue perdido | Evento outbox durable permite dispatcher reintentar sin segunda solicitud. `afterCommit()` solo no cubre pérdida después de commit. |
| TLS/DNS falla antes de operación confirmada | Clasificar; backoff limitado con misma clave. Nunca `verify=false`. |
| Timeout después de aceptación posible | Mantener `en_proceso` / subresultado desconocido; consultar por transacción o clave idempotente, no emitir otra firma a ciegas. |
| Proveedor reporta éxito pero PDF inválido/alterado | Cuarentena privada, `fallida` no reintentable automáticamente, alerta y revisión; no publicación. |
| Proveedor completó y worker murió | Reconciliar clave/transacción, recuperar mismos bytes/evidencia, verificar y completar. |
| Storage final no disponible | Conservar transacción/evidencia; retry almacenamiento, no firma nueva. |
| Archivo final escrito y DB no confirmó | Reconciliar objeto privado por hash; publicar por CAS, limpiar huérfanos sin borrar evidencia vigente. |
| Callback duplicado o tardío | Autenticar, vincular transacción/input y deduplicar; no revertir terminal ni escribir binario contradictorio. |
| Cambio de firmante/proveedor durante operación | Congelar/reconciliar operación anterior; no failover automático con otra clave. Nuevo intento lógico solo después de certeza terminal y autorización/versionado. |
| Anulación concurrente | Revalidar estado antes de publicación; conservar evidencia si ya se firmó, impedir entrega/activación como vigente. |

Reintentos finitos con jitter; errores permanentes no se repiten. Proveedor debe ofrecer deduplicación y consulta incluso cuando se perdió transaction ID. Si no puede reconciliar por clave, no satisface contrato candidato. Exactamente una transacción remota no se promete sobre red: se diseña aceptación única local y reconciliación de resultados.

## 6. Modelo de datos candidato, sin migración

Separar hechos laborales (`snapshot_datos` v3) de evidencia de oficialización. No agregar tabla por cada metadata. Candidato mínimo: `certificate_officializations` (una fila lógica) + registro append-only de intentos/evidencias si se requiere historia de reintentos.

| Grupo | Campos candidatos y motivo |
|---|---|
| Identidad | `id` interno opaco, `certificate_id`, `document_version`, `status`; unique certificado/version. |
| Autorización fijada | Referencia a decisión/acto y contexto versionado de firmante/cargo/dependencia/vigencias; no depende solo de usuario vivo. |
| Operación | Identificador lógico de proveedor, `idempotency_key` unique, `provider_transaction_id` nullable; intento/next_retry/claim lease/version según queue elegida. |
| Input | `input_path`, `input_sha256`, versión de plantilla/política/perfil solicitado. |
| Resultado | `final_path`, `final_sha256`, `officialized_at`; null hasta aceptación y escritura confirmadas. |
| Evidencia estructurada | `evidence_path` privado + resumen versionado: formato/perfil real, huella/issuer/serial/cadena pública, fecha declarada de firma, tiempo TSA/política si existe, verificador/política/fecha/resultados por componente. |
| Fallos | `failure_code` allowlist/reintentable; no excepción/payload de proveedor indiscriminado. |

No duplicar número de serie en columnas si puede conservarse en evidencia tipada. No JSON libre con credenciales. DPC/actos y materiales OCSP/CRL/cadena se conservan como evidencia pública criptográfica bajo acceso privado/retención aprobada; no keys/PIN. Intentos registran timestamps y referencias, no múltiples certificados vigentes. TTL capability sigue en mecanismo de validación; no equivale a vigencia del documento.

`hash_pdf` actual se conserva para el borrador que referencia. En 3B.2 decidir si downloads de nuevos oficiales resuelven exclusivamente metadata final, con compatibilidad explícita; no cambiar silenciosamente significado de hashes/paths históricos. Metadata de oficialización puede conservar relación con ID actual sin sobrescribir archivo de borrador previo.

## 7. Identificadores sin segundo consecutivo

`SolicitudCertificacion.radicado`: `CL-año-consecutivo`, unique y lock anual; candidato a identificador documental sujeto a aprobación institucional de nomenclatura. No generar otro contador. `Certificado.codigo_unico` actual es código técnico aleatorio (4 bytes) y **no capability**; no debe convertirse en secreto de validación.

Capability de 32 bytes: acceso a validación, puede expirar/rotar. ID transacción proveedor: correlación remota, no radicado. Serial X.509 junto al issuer: identifica credencial criptográfica, no certificado laboral. ID/version oficialización: coordinación interna, nunca derivado de PII. El algoritmo actual de radicado basado en count presupone conservar solicitudes; la fase futura debe verificar política de no borrado/colisiones sin cambiarlo especulativamente.

## 8. Storage, custodia, despliegue y red

App/worker → HTTPS validado → Signing Provider/ECD; alternativa gateway autorizado → HSM institucional. App/worker → storage privado de staging/final/evidencia. Public validation → API/servicio de validación → lectura minimizada de estado/evidencia. Browser no accede a proveedor/HSM ni a credenciales; ningún endpoint público firma PDFs arbitrarios.

Claves privadas reales no exportables en custodia aprobada: HSM/servicio remoto/key management apto para la modalidad, con atribución/control demostrables. Nunca `.env`, DB, `storage/app`, repo, imagen Docker o frontend. Secreto API alto privilegio desde secret manager por identidad de servicio, scopes por función, rotación y auditoría; no logs/PIN plaintext. No todo KMS produce PAdES ni un certificado jurídicamente apto: validar capacidades antes de seleccionarlo.

TLS/cadena/host verificados, destinos API configurados allowlist institucional y no URLs del usuario. Timeouts conexión/lectura/total, tamaño máximo de respuesta, parser aislado, retries limitados, circuit breaker si patrón de fallos lo requiere. DPC/AIA/OCSP/CRL/links devueltos por proveedor son entradas no confiables: egress controlado, rechazo redes internas/redirecciones indebidas y validación de material; el verificador no introduce SSRF. Callback futuro requiere autenticidad, replay protection y deduplicación; no confiar únicamente en IP.

Storage final write-once por path interno/opaco con permisos mínimos; publicación solo tras verificación, escritura durable y hash. Backups cifrados/restaurados, acceso auditado, versiones y política TRD; WORM/retention lock si institución aprueba y puede operar. No ruta pública nueva ni URL permanente de descarga sin autorización. Referencias de storage que entregue el proveedor se resuelven exclusivamente desde su destino validado, no se convierten en fetch arbitrario.

## 9. Cola y disponibilidad 24/7

Recomendación: integración remota en cola persistente, worker supervisado y recuperación independiente del navegador. Sync conserva respuesta inmediata pero ata latencia/timeout remoto al HTTP; solo evaluar con medición/contrato y sin transacción DB abierta. No añadir Redis por defecto.

| Backend | Ventaja / coste operativo | Condición |
|---|---|---|
| Database | Ya configurado; menos servicios; cola durable sobre DB. | Capacidad, locks/índices, aislamiento y workers; no afectar consultas laborales. |
| Redis | Colas/locks eficientes y monitorización posible. | Servicio institucional durable/HA, política de persistencia/recuperación; no instalar por moda. |
| Servicio institucional de mensajería | HA/operación centralizada posibles. | Contrato, driver/licencia, deduplicación/visibilidad y coste DESCONOCIDO. |

`config/queue.php` actual tiene default database, `retry_after=90`, `after_commit=false`; valores existentes no son SLA ni configuración aprobada para firma. Diseñar despacho after commit + outbox y jobs con identidad/input de DB; no payload con PII/secretos. `worker timeout < retry_after/visibility` con margen, y `HTTP total timeout < worker timeout`; configurar según proveedor, no usar valores candidatos sin pruebas. Locks/cache compartidos y leases para multiinstancia; supervisión, reinicio y drain seguro. Fuente técnica: [Laravel 13 queues, transactions/unique jobs/timeouts](https://laravel.com/docs/13.x/queues), consulta 2026-10-04.

Objetivos candidatos para negociación, no SLA establecido: aceptar solicitud sin esperar indefinidamente; fijar p95 de oficialización, disponibilidad conjunta, máximo de backlog/edad, RPO/RTO y ventana de retries. Si proveedor cae: mensaje técnico recuperable, solicitud conservada y **cero entrega oficial sin evidencia**. Operación 24/7 no significa promesa de cero fallos ni firma humana por documento. Pago ON solo habilita este tramo después de confirmación actual.

## 10. Verificación pública y límites

Estado mínimo propuesto: documento activo/anulado/no oficializado; tipo; fechas de generación/oficialización diferenciadas; dependencia aprobada; integridad del archivo registrado; firma verificada/no verificada/indeterminada y fecha del control. Nombre/cargo exclusivamente con aprobación D04. No publicar PDF, cédula, funciones, historia, email/teléfono, motivo sensible, certificado personal completo ni IDs de proveedor por defecto.

Portal no autentica los bytes de la copia del tercero solo por el token: atacante puede copiar QR de documento válido. Explicar alcance y recomendar verificación PDF de esa copia. Firma PAdES permite análisis sin portal si están disponibles confianza/evidencias/validador; QR necesita red. Offline con firma básica puede necesitar información OCSP/CRL/cadena no incorporada; no garantizar validación offline universal.

TTL/retención/rotación/validación posterior al retiro se deciden institucionalmente. Evitar que expiración de capability publique como "firma inválida" o que revocación documental cambie bytes. Mantener posibilidad de verificación criptográfica histórica según política y evidencia. Anulación actual es base para futura revocación documental: confirmar fundamento, actor, razón, auditoría y reflejo público antes de extenderla.

## 11. Auditoría y observabilidad

Eventos futuros: `certificate_signing_requested`, `certificate_signing_completed`, `certificate_signing_failed`, `certificate_signature_verified`, `certificate_revoked`, `certificate_publicly_validated`. Correlación por ID oficialización/documento, intento y códigos allowlist; identidad operativa referenciada con política de acceso. No claves privadas, provider secrets, PIN, cookies, tokens completos, URL QR o payloads laborales en eventos. Logs actuales 2.9 se preservan; adaptar semántica de anulación con compatibilidad sin duplicación innecesaria.

Métricas: `sign_request_total`, `sign_success_total`, `sign_failure_total`, `sign_latency`, `verification_total`, `provider_errors`, backlog/edad. Sin PII ni tokens/IDs de alta cardinalidad en labels. Alertas por firma inválida, transacción ambigua envejecida, caída/revocación de credencial, expiración de firmante y ausencia de capacidad. Retención/acceso/protección de logs pendientes D03; TSA y auditoría no sustituyen competencia legal.
