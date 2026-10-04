# Fase 2.7 — validación de pago por Secretaría

## Estado inicial e inventario (antes de implementar)

Base limpia `c0750b2`, Fase 2.6 consolidada; PDF 3A `dbd5ad0`, Certificate Types, bootstrap Manual y React Bits presentes. Rama `feat/payment-secretary-validation`. Trabajo exclusivo en recovery.

El autoservicio usa `ExpedirCertificacionService` → `GenerarCertificadoService` → snapshot v3 y PDF. `solicitudes_certificacion.requiere_pago` ya captura el régimen inicial. La cuota mensual ya tiene advisory lock PostgreSQL y UNIQUE funcionario/periodo/tipo. `certificados` tiene UNIQUE por solicitud. Hay `ordenes_pago_certificado` (1:1, monto nullable, referencia interna, confirmado_at), y `pagos_soportes` (1:1, ruta privada, estado, revisor y fecha). No hay tarifa oficial ni instrucciones bancarias aprobadas; la referencia OPC es identificador interno, no referencia bancaria.

El rol real se llama `secretario`. Existen 36 permisos, incluidos `pagos.ver`, `pagos.validar`, `pagos.rechazar`, `pagos.cargar`. El funcionario tenía únicamente `solicitudes.crear`. Hay auditoría con actor explícito opcional. Las rutas manuales de solicitud responden 410. El servicio legacy `PagoSoporteService` sobrescribe y elimina evidencia; `ValidarPagoAction` mueve a aprobación de solicitud. No se reutilizan esas acciones para el nuevo flujo. Sus rutas de validación quedan aisladas del autoservicio.

## Arquitectura y máquina de estados acordada antes de migraciones

Reutilizar solicitud, orden y soporte lógico 1:1. Añadir `pago_comprobantes` como versiones de evidencia, sin cambiar cardinalidad ni borrar datos históricos. Campos: soporte, versión, ruta privada, nombre original, MIME detectado, tamaño, SHA-256, referencia declarada, observación del titular, estado, motivo/revisor/fecha. La evidencia anterior permanece. No se crea otro pago lógico ni tarifa ficticia.

Pago OFF: creación → generando → generada dentro de la transacción actual; error revierte creación y cupo. Pago ON: pendiente_pago → pago_en_revision → pago_rechazado → pago_en_revision (nueva evidencia en misma solicitud); pago_en_revision → pago_confirmado (commit independiente) → expedición automática → generada o expedicion_fallida. La evidencia y la orden conservan confirmado ante cualquier fallo de expedición. Reintento técnico explícito mediante el servicio interno, sin endpoint ni UI de retry en esta fase.

La solicitud reserva el cupo al crearse y lo consume al generar; rechazo/fallo no libera ni crea radicado. No hay expiración automática. Una solicitud abandonada retiene su reserva del periodo, nunca bloquea un periodo posterior; cancelación/liberación futura es deuda funcional. El parámetro actual no gobierna solicitudes existentes.

Bloqueo de solicitud como orden común para upload, revisión y generación. Estado, bloqueo y UNIQUE de certificado impiden doble expedición. Confirmación repetida devuelve estado existente; confirmación/rechazo y upload simultáneos admiten una transición. Storage privado sin URL pública, nombre UUID generado, PDF/JPEG/PNG, máximo configurable 5 MB, MIME detectado y extensión validados, hash verificado en lectura, endpoint con ownership/permisos. Secretaría solo valida pago.

## Resultados de implementación y validación

### 1–4. Estado, problema, legacy y decisión

La fase anterior tenía pago OFF operativo y pago ON limitado a una orden pendiente sin proceso completo. La arquitectura previa y la decisión se registraron arriba antes de crear migraciones. Se reutilizan `SolicitudCertificacion`, `OrdenPagoCertificado`, `PagoSoporte`, los permisos existentes, la auditoría y los servicios de expedición. La referencia de la orden ahora describe `validacion_secretaria` en metadata. El rol existente `secretario` es Secretaría; no se creó otro rol.

El endpoint legacy de generación manual también estaba operativo, aunque oculto en UI. Se retiró: `/solicitudes/{id}/generar-certificado` responde 410 junto con las acciones antiguas de aprobación/estado. El método público manual de `CertificadoController` se eliminó. Las rutas antiguas de cargar/validar/rechazar soporte responden 410. La consulta histórica de soporte se conserva con autorización. `PagoSoporteService` y `ValidarPagoAction` se conservan como código histórico, sin rutas activas de mutación; el autoservicio no las invoca. Las pruebas antiguas de snapshot, integridad, PDF y tokens ahora preparan sus certificados con el servicio de dominio/autoservicio; no se redujo esa cobertura.

### 5–6. Modelo y migraciones

Dos migraciones nuevas:

- `2026_10_04_000001_add_payment_receipt_history`: tabla `pago_comprobantes` y columna nullable `solicitudes_certificacion.error_expedicion`.
- `2026_10_04_000002_enable_payment_role_permissions`: habilita consulta propia/carga al funcionario y retira permisos obsoletos de edición/aprobación/generación al secretario, sin eliminar permisos del catálogo.

La evidencia tiene FK restrictiva al soporte, UNIQUE `(pago_soporte_id, version)`, índice SHA-256 y CHECK de estados `en_revision/rechazado/confirmado`. Campos: `id`, soporte, versión, ruta, nombre original, MIME real, bytes, SHA-256, referencia declarada, observación del titular, estado, motivo de rechazo, actor/fecha de revisión, timestamps. No se duplica la entidad de pago 1:1; cada archivo anterior mantiene su fila y su ruta.

El upgrade se ejecutó con `php artisan migrate --force` sobre `scl_certificate_types_upgrade_test`, copia obtenida con `pg_dump` del esquema/datos CT previo. Se comprobó rollback de ambas migraciones y reaplicación. La copia conservó versión 10, sus perfiles y los 36 permisos. Fresh install pasó en TEST y E2E aislados. En CT desarrollo se aplica únicamente `migrate`; no se usa fresh/wipe/truncate.

### 7–8. Estados y transiciones

| Disparador | Estado anterior | Estado posterior |
| --- | --- | --- |
| Crear con pago ON | Sin solicitud | `pendiente_pago` |
| Adjuntar evidencia válida | `pendiente_pago` / `pago_rechazado` | `pago_en_revision` |
| Rechazar con motivo | `pago_en_revision` | `pago_rechazado` |
| Confirmar evidencia íntegra | `pago_en_revision` | `pago_confirmado` (commit durable) |
| Expedición automática correcta | `pago_confirmado` | `generada` |
| Error de fuentes, PDF o persistencia | `pago_confirmado` | `expedicion_fallida` |
| Reintento técnico explícito | `pago_confirmado` / `expedicion_fallida` | `generada` o `expedicion_fallida` |

`generando` se mantiene para pago OFF y estados históricos quedan disponibles para lectura. No se emplea `aprobada` como estado de validación de pago nuevo. Una confirmación HTTP repetida devuelve el estado vigente; no confirma dos veces, no rechaza después de confirmar y no reintenta una expedición fallida implícitamente. Las transiciones se validan dentro del lock del servidor; cliente no envía actor, ownership, ficha ni contenido.

### 9–11. Pago OFF, ON y cuota

OFF conserva el servicio transaccional actual: validar vinculación/fuentes → crear solicitud → snapshot v3 → PDF → `generada`; un fallo revierte solicitud y cupo. No solicita evidencia ni intervención humana. ON valida inicialmente disponibilidad/vinculación/ficha cuando corresponde, reserva cupo, crea orden interna sin monto ficticio y queda pendiente. El titular carga su comprobante; Secretaría revisa únicamente ese pago; confirmar inicia el mismo `GenerarCertificadoService`.

La columna existente `requiere_pago` es el snapshot del régimen de creación. Cambiar el parámetro posteriormente no afecta upload/revisión/expedición de la solicitud. La reserva comienza con el commit de creación y se considera consumida al generar. Rechazo y fallo técnico conservan reserva y radicado; no se liberan automáticamente. Se puede volver a solicitar cada tipo en el siguiente periodo. Deuda institucional: eventual cancelación/liberación de solicitudes abandonadas del periodo; esta fase no introduce expiración.

### 12–14. Evidencias, storage y upload

Los comprobantes nuevos se almacenan en el disk `local`, raíz `storage/app/private`, carpeta `pago-comprobantes/{solicitud}/UUID.ext`. El nombre original se conserva solo como metadata. `serve=false` desactiva las rutas de descarga firmadas automáticas del disk; las descargas pasan por los controllers autorizados. No hay archivos bajo public ni se devuelve la ruta física.

Formatos PDF, JPG/JPEG, PNG. Tamaño inicial 5 MB, configurable con `PAYMENT_RECEIPT_MAX_KB`; Laravel valida archivo, MIME y extensión; el servicio detecta bytes con `finfo` y verifica compatibilidad MIME/extensión, además del límite. La extensión física se deriva del MIME detectado. Se calcula SHA-256 sobre el archivo ya almacenado y se verifica antes de confirmar/descargar. Un archivo no íntegro devuelve `COMPROBANTE_INTEGRIDAD_INVALIDA`. La descarga es attachment con `nosniff` y `private, no-store`. Fallo de persistencia elimina únicamente el archivo nuevo y revierte cambios; nunca se elimina la evidencia previa del flujo nuevo.

Un soporte legacy sin historial no se sobrescribe automáticamente: `COMPROBANTE_LEGACY_REQUIERE_MIGRACION` preserva su archivo y requiere migración técnica explícita. No existían soportes de ese tipo en CT desarrollo. El índice de hash permite detección básica de coincidencias; no se presenta como firma ni se bloquean evidencias coincidentes arbitrariamente.

### 15–16. Permisos y endpoints

Secretaría conserva exactamente `solicitudes.ver`, `pagos.ver`, `pagos.validar`, `pagos.rechazar`, `certificados.ver`, `certificados.descargar`. La confirmación nueva se llama **Confirmar pago**; reutiliza el permiso interno `pagos.validar`. No recibe permisos de administrador ni permisos de edición del contenido. Funcionario: `solicitudes.crear`, `solicitudes.ver`, `pagos.cargar`, siempre con ownership. Admin conserva 36 permisos; lectura de comprobantes exige explícitamente `pagos.ver`. La operación de revisión exige además rol secretario; el admin no opera esa bandeja ni confirma desde el dashboard.

Prefijo `/api/v1`:

| Método / ruta | Autorización / función |
| --- | --- |
| GET /mi-certificacion/disponibilidad | Funcionario; cupo, pago actual y máximo upload |
| POST /solicitudes | Titular; expedición OFF / creación ON |
| GET /solicitudes | Titular: listado filtrado por ownership; consulta administrativa según permiso |
| GET /solicitudes/{id} | Policy; titular propio / backoffice autorizado |
| POST /solicitudes/{id}/pago/comprobantes | Titular propio + `pagos.cargar` |
| GET /solicitudes/{id}/pago/comprobantes/{comprobante} | Titular propio o permiso explícito `pagos.ver`; FK validada |
| GET /solicitudes/{id}/certificado | Titular propio, solo `generada`, verificación de integridad |
| GET /secretaria/pagos | Secretario + `pagos.ver`; paginado y filtro, prioridad en revisión |
| GET /secretaria/pagos/{id} | Secretario + `pagos.ver`; datos mínimos y documento parcial |
| POST /secretaria/pagos/{id}/confirmar | Secretario + `pagos.validar` |
| POST /secretaria/pagos/{id}/rechazar | Secretario + `pagos.rechazar`; motivo obligatorio |
| GET/PATCH /configuracion/certificaciones | Parámetros según permisos existentes |

La descarga inmediata anterior sigue vigente para pago OFF. La nueva descarga autenticada por solicitud permite recuperar documentos expedidos después del pago, sin depender de un token entregado al revisor. Errores conservan `{success,message,code?,errors?}`; códigos estables incluyen `TRANSICION_PAGO_INVALIDA`, `COMPROBANTE_REQUERIDO`, `COMPROBANTE_INTEGRIDAD_INVALIDA`, `CERTIFICADO_NO_GENERADO` y `MONTHLY_CERTIFICATE_LIMIT`. No se devuelven stack traces.

### 17–18. UI del titular y Secretaría

Se reutilizó `Alert` de Fase 2.6. El formulario muestra pago activo antes de crear y cambia su botón a crear solicitud. La confirmación ON comunica solicitud creada/pago pendiente, con enlace al detalle. `Mis solicitudes` usa paginación real. El detalle muestra estado, actualización automática cada 15 segundos, motivo, carga/reemplazo, historial y descarga solo cuando está generado. En ausencia de tarifa/instrucciones oficiales aparece aviso explícito; la OPC está rotulada como referencia interna.

Navegación secretario: **Validación de pagos**. Bandeja con filtros en revisión, rechazados, confirmados, generados, fallo técnico y pendientes de comprobante. Detalle: radicado, titular/documento parcial, tipo, fechas, referencia, comprobantes/versiones, revisión anterior. Confirmación con diálogo; rechazo exige texto no vacío. Los botones solo aparecen con el permiso y estado correspondientes. No hay controles de cargo, ficha, Manual, funciones, snapshot o PDF. Las pantallas operativas no incorporan nuevos efectos React Bits.

### 19–22. Auditoría, concurrencia, idempotencia y fallos

Auditoría: creación autoservicio, `comprobante_cargado`, `comprobante_reemplazado`, `pago_rechazado`, `pago_confirmado`, `generacion_posterior_pago`, `expedicion_pago_fallida` y `comprobante_consultado`, con actor real, IDs/version/hash y motivo donde corresponde. No incluye tokens, contraseñas ni bytes. El error interno se registra con código/clase y solicitud; API y UI reciben respuesta controlada.

Crear conserva advisory lock por funcionario/periodo/tipo y UNIQUE DB. Se agregó serialización del contador anual de radicado para evitar colisiones entre funcionarios/tipos en las creaciones transaccionales. Upload y revisión toman lock de solicitud como orden común. Confirmar guarda evidencia/soporte/orden confirmados y auditoría en una transacción; después otra transacción bloquea solicitud y reutiliza generación. UNIQUE certificado/solicitud y estado impiden doble PDF/certificado persistido. Rechazo concurrente solo gana si el pago aún está en revisión.

Si falla el segundo paso, el pago sigue confirmado y la solicitud queda `expedicion_fallida`. Pruebas fuerzan fallo del PDF y ficha incompleta al llegar a expedición, verifican conservación del pago y reintento explícito. `PagoSolicitudService::expedirConfirmada(id, actor, true)` soporta recuperación técnica desde backend; no existe endpoint abierto ni UI de retry. También recupera un proceso interrumpido después de guardar `pago_confirmado`.

### 23–24. Pruebas y E2E

| Gate | Resultado final |
| --- | --- |
| Backend completo | **176 passed**, 951 assertions, 222.80 s; incluye 19 pruebas nuevas |
| Frontend completo (Vitest) | **57 passed**, incluye 16 casos nuevos |
| Playwright completo | **11 passed** en 4.1 min: 7 escenarios existentes + 4 nuevos |
| ESLint | PASS |
| TypeScript | PASS |
| Vite build | PASS |
| Pint | PASS, comprobación explícita de los **31 archivos PHP** nuevos/modificados en Laravel |
| Instalación fresca TEST/E2E | PASS en bases aisladas |
| Upgrade CT copiado, rollback y reaplicación | PASS |
| Smoke HTTP en CT desarrollo | PASS, cinco certificados sintéticos |
| Git diff whitespace | PASS |

`npm run test:e2e` ahora incluye ambos archivos y enumera los 11 escenarios; previamente el script solo seleccionaba autoservicio. No se cambiaron dependencias. Comandos de regresión: `docker compose -f docker-compose.certificate-types.yml run --rm test php artisan test --compact`; `docker compose -f docker-compose.certificate-types.yml exec -T frontend npm run test`; `npm run lint`, `npm run typecheck`, `npm run build` en el mismo servicio; Playwright completo en `e2e-runner`; `vendor/bin/pint --test` con las rutas PHP modificadas explícitas. La fase no considera un `pint --dirty` sin Git dentro del contenedor como evidencia de revisión de archivos.

Backend nuevo cubre pago ON/no certificado, cuota/duplicado, PDF/PNG/JPEG, MIME falso y extensión incompatible, límite 5 MB/configurable, ownership listado/detalle/carga/descarga, 401/403, permisos, evidencia faltante/corrupta, bandeja/detalle, rechazo vacío, motivo/reemplazo/historial, snapshot v3/PDF, doble confirmación, parámetro histórico, fallo técnico y reintento, ficha incompleta posterior y sencillo independiente del Manual. Pago OFF sencillo/funciones continúa en la regresión existente. Los fixtures de imágenes son ficticios de 10×10 y no requieren GD en PHP.

Playwright nuevo usa API y UI reales: admin configura pago, titular solicita/carga, secretario confirma, titular descarga PDF; rechazo/motivo/segunda evidencia sobre mismo ID; IDOR; carreras paralelas de creación, upload, dos secretarios y confirmar/rechazar. E2E usa `scl_certificate_types_e2e` y backend con cuatro workers. Se mantienen los siete escenarios anteriores. Sus capturas de regresión se redirigieron a `test-results/regression-phase26` para preservar evidencias anteriores versionadas.

### 25. Smoke CT controlado

Ejecutado después de las suites completas, exclusivamente en `scl_certificate_types_dev`, tras `php artisan migrate --force`. Usuarios ficticios: admin **9**, Secretaría **10**, funcionario OFF **11**, funcionario ON **12**, funcionario MF-0167 **13**. Funcionarios ficticios: **7** OFF/MF-0002, **8** ON/MF-0002, **9** MF-0167. Ningún funcionario real se usó.

| Solicitud | Actor ficticio | Tipo y régimen | Resultado |
| --- | --- | --- | --- |
| 9 | Funcionario 7 | Sencillo, OFF | Generada; certificado 9, snapshot v3 |
| 10 | Funcionario 7 | Funciones, OFF | Generada; certificado 10, MF-0002 y 11 funciones |
| 11 | Funcionario 9 | Sencillo, OFF | Generada; certificado 11; funciones devolvió 409 `MANUAL_FICHA_INCOMPLETA` |
| 12 | Funcionario 8 | Sencillo, ON | Pendiente → carga → confirmación → generada, certificado 12 |
| 13 | Funcionario 8 | Funciones, ON | Carga → rechazo → segunda evidencia en el mismo ID → confirmación → generada, certificado 13 |

Comprobantes sintéticos: **1** confirmado de solicitud 12; **2** rechazado y **3** confirmado de solicitud 13. Ambos archivos de la solicitud 13 permanecen consultables por el titular. Confirmación repetida no duplicó certificados; otro funcionario recibió 403 al descargar evidencia ajena. Los dos PDF posteriores al pago tienen cabecera `%PDF-`. Parámetro `requiere_pago_certificado` restaurado a **false**, su valor previo. Los cinco usuarios tienen **0 tokens temporales** después del smoke; el archivo temporal de credenciales fue eliminado. Resultados locales sin secretos en `output/payment-phase27-smoke.json` (ignorado por Git); IDs y resultados esenciales se conservan en este informe. No hay soportes CT legacy sin historial a la fecha del smoke.

Scripts opcionales `scripts/payment_ct_prepare.php` (guard local + base CT exacta) y `scripts/payment_ct_smoke.mjs` (HTTP real en 8280). Los actores se crean como ficticios y los tokens se guardan temporalmente en output ignorado; se revocan y el archivo temporal se elimina al terminar. El parámetro se restaura a su valor inicial. Se conservan las solicitudes/evidencias sintéticas como trazabilidad del smoke; no se borran registros existentes ni se importan personas reales.

### 26. Evidencia visual

14 capturas reales en `frontend/react-app/evidence/payment-phase27/`: `01-pago-desactivado.png`, `02-pago-activo-aviso.png`, `03-solicitud-pendiente-pago.png`, `04-cargar-comprobante.png`, `05-pago-en-revision.png`, `06-secretaria-bandeja.png`, `07-secretaria-detalle.png`, `08-confirmar-pago.png`, `09-pago-rechazado-funcionario.png`, `10-reemplazar-comprobante.png`, `11-pago-confirmado.png`, `12-certificado-generado.png`, `13-mobile-pago.png`, `14-mobile-secretaria.png`. La captura 11 muestra la evidencia confirmada después de la expedición síncrona; no simula un estado transitorio. Se inspeccionaron visualmente carga móvil y detalle de Secretaría en escritorio/móvil; pruebas a 360 px verifican ausencia de overflow horizontal. Ninguna evidencia de fases anteriores se sobrescribió.

### 27–28. Performance y catálogo de permisos

La bandeja está paginada a 15 filas (máximo 100), con filtro en revisión por defecto y eager loading de sus relaciones. La expedición conserva el costo del generador PDF actual y ocurre síncronamente; mientras persiste el pago confirmado la solicitud es recuperable. No se añade queue ni proveedor de pagos. Posible mejora futura: expedición asíncrona y políticas de retención/versiones si aumenta el volumen.

Catálogo antes **36**, después **36**. Permisos nuevos: **ninguno**. Admin 36, secretario 6, funcionario 3. Se reutilizan permisos de pago existentes y se ajustan asignaciones; retirar permisos de Secretaría no borra el catálogo ni permisos del admin.

### 29–30. Manual y PDF

Consulta final en CT desarrollo: versión **10 publicada**, **Decreto 1000-24/015 de 2023**, acto `1000-24/015`; **344 fichas / 3.115 funciones / 2.326 conocimientos**. La comprobación fue posterior a migraciones y smoke. Hashes iniciales y finales idénticos:

- MF-0002: `89ec53aab063b9d2ddfab9522f06e16e57c6c48b63107299bdbd268fade5a812`.
- MF-0167: `b9a9a25a1f2bb89172e06e638b0357432afcd10cd2619273e53a3b0b70a1ad76`.

La copia CT tomada antes del upgrade y el CT final coinciden además en los digests SQL agregados: fichas/source+hash **`c1c80cad863ab97452bdb911284536fa`** y textos/orden de funciones **`97c5ff7a3776370e6fc7fe920f94ce16`**. Estos MD5 son solo checksums de comparación de datos, no firmas. MF-0002 generó ambos tipos en el smoke; MF-0167 generó sencillo y conservó el bloqueo individual de funciones.

PDF 3A intacto: sin cambios en `CertificadoPdfService`, `CertificateDocumentData`, `CertificateAssetResolver`, plantilla `laboral.blade.php`, `document.css`, assets institucionales ni tests PDF 3A. El snapshot permanece v3. E2E/regresión comprueban PDF real, integridad, funciones y marca **BORRADOR — SIN VALIDEZ OFICIAL**. No se iniciaron firma, QR ni oficialización. React Bits permanece intacto.

### 31–32. Decisiones institucionales y fases posteriores

Pendiente institucional: tarifa, cuenta/medio e instrucciones oficiales de pago; no se inventaron. La orden existente tiene `monto` nullable como infraestructura futura. Definir cancelación/liberación de reserva de solicitudes abandonadas y procedimiento operativo de recuperación técnica. Para soporte legacy sin versiones, definir migración explícita antes de habilitar reemplazo.

Fase 2.8 queda pendiente: separación de identidades de backoffice/funcionario y desacople de cédula admin. Fase 2.9 queda pendiente: hardening general, MFA, CSP, headers globales, rotación de secretos, pentest y políticas ampliadas de archivos (por ejemplo antivirus/retención). Esta fase implementó únicamente la seguridad necesaria del flujo nuevo. Fase 3B, firma y oficialización siguen pendientes. Tras el commit solicitado se detiene el trabajo; no se hace push.
