# Contratos mínimos propuestos — sin implementación

2026-10-04, Bogotá. **Diseño documental exclusivamente.** No se crean clases PHP, contenedor/adapters, endpoints ni proveedor ficticio instalado. Estos contratos corresponden a la alternativa criptográfica; si se aprueba otra modalidad se adapta evidencia/validación mediante decisión arquitectónica.

## Servicios y responsabilidades

`OfficializationService`: servicio de dominio futuro. Recibe **ID de oficialización**, lee solicitud/certificado/input autorizado, verifica habilitación del firmante, reclama operación, coordina proveedor/verificador/storage y transición por CAS. No acepta PDF arbitrario del cliente ni ejecuta pagos/cupos/radicados otra vez. Idempotencia y gate de publicación pertenecen al dominio, no únicamente al SDK.

Dos interfaces documentales son suficientes:

```text
CertificateSigningProvider
  sign(preparedDocument, authorizedContext) -> SigningOutcome
  status(operationReference) -> SigningOutcome

CertificateSignatureVerifier
  verify(finalDocument, expectedContext, validationPolicy) -> VerificationReport
```

El verificador es independiente de la afirmación de éxito del proveedor. No duplicar `verify` en proveedor como fuente autoritativa; una verificación ofrecida por este puede ser evidencia adicional. No crear veinte adapters. Ninguna implementación devuelve `verified` sin validar realmente.

## Entradas, resultados e invariantes

| Contrato | Campos mínimos conceptuales |
|---|---|
| `PreparedDocument` | Referencia a bytes privados/stream inmutable, `input_sha256`, ID interno/version, tamaño y formato PDF. Path local solo interno; adaptador no transmite ruta del host. |
| `AuthorizedContext` | Clave idempotente opaca, referencia de autorización/política versionadas, referencia de firmante y credencial/proveedor, perfil solicitado. Sin claves/PIN/password/token de proveedor; infraestructura resuelve acceso aparte. |
| `OperationReference` | Proveedor fijado, transaction ID si conocido y **clave idempotente obligatoria** para consultar tras perder respuesta. |
| `SigningOutcome` | `accepted/completed/rejected/unknown`, transaction ID nullable, bytes/evidencia si completed, código de fallo allowlist y capacidad de retry. `completed` significa resultado disponible, nunca firma validada. |
| `ExpectedContext` | Input/hashes/referencia de documento, firmante/credencial permitidos, contenido autorizado, cobertura/revisiones y política de validación. |
| `VerificationReport` | `verified/invalid/indeterminate/unsupported`, componentes independientes, tiempo de análisis, versión motor/política y evidencia privada. |

Componentes del reporte: integridad matemática/cobertura del contenido esperado, revisiones posteriores, identidad/huella/issuer/serial de credencial, cadena/confianza, propósito/key usage, tiempo declarado, TSA/cadena TSA si requerido, revocación y frescura/evidencia histórica. `unknown` OCSP, red caída, formato no soportado o material ausente no se convierten en verified. La competencia y autorización del firmante se comprueban contra política institucional, no pueden deducirse de criptografía únicamente.

Precondiciones `sign`: input hash coincide; operación única reclamada; mismo input/contexto/proveedor bajo misma clave; autorización vigente; TLS/destino aprobado; pago/cupo resueltos previamente. El proveedor no puede modificar contenido laboral; cambios de estructura de firma/evidencia deben ser permitidos y comprobables. Postcondición: guardar resultado y verificar antes de publicación.

Errores candidatos: `PROVIDER_UNAVAILABLE`, `PROVIDER_RESULT_UNKNOWN`, `AUTHORIZATION_EXPIRED`, `INPUT_HASH_MISMATCH`, `IDEMPOTENCY_CONFLICT`, `SIGNATURE_INVALID`, `VALIDATION_INDETERMINATE`, `FINAL_STORAGE_FAILED`. Clasificar sin almacenar mensajes externos con PII/credenciales. Resultado desconocido conserva operación reconciliable; no nueva solicitud.

## API futura, no habilitada

| Contrato HTTP candidato | Acceso / propósito |
|---|---|
| GET estado de oficialización del certificado existente | Cookie Sanctum + owner o permiso vigente; devuelve progreso mínimo, no secretos/paths/payload de proveedor. Extender consulta existente si sirve. |
| GET validación por capability | Reutilizar contrato público existente con semántica oficialidad/legacy aprobada y D04; 30/min como baseline, no-store y controles específicos. |
| POST retry interno por ID oficialización | Operación excepcional bajo permiso futuro, MFA/step-up/CSRF, auditoría y política; reanuda misma operación, no firma cualquier archivo. Preferir worker automático. |
| Revocación documental | Evaluar reutilizar PATCH anular actual; aprobación institucional para alcance oficial, motivo/actor y efecto público. |

No se crea `POST /sign` abierto a admin, permiso `certificados.firmar` o carga arbitraria de PDFs. Paths y verbos candidatos no constituyen nuevas rutas implementadas. No se habilita descarga pública ni upload de copia del tercero; cualquier futuro verificador por upload exigiría nuevo análisis de privacidad/parser/DoS.

## Mock/Fake permitido y gate

El [mock documental](CERTIFICATE_OFFICIALIZATION_MOCK.md) no implementa estas interfaces. Si 3B.2 necesita doble de prueba, nombre **`FakeCertificateSigningProvider`**, binding exclusivo local/testing y denegación en producción. Un Fake prueba coordinación, no validez criptográfica. Un falso verificador exitoso jamás entra en producción ni habilita publicación oficial; pruebas criptográficas usan validador real y fixtures sintéticos separados.
