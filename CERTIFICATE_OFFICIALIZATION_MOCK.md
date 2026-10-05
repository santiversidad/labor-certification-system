# Mock técnico documental — PRUEBA TÉCNICA — SIN VALIDEZ OFICIAL

2026-10-04, Bogotá. **No ejecutable; sin firma, certificado, PDF o QR generado.** IDs y valores siguientes son sintéticos, no capacidades reales. Este mock permite revisar contrato y UX sin simular validación criptográfica.

## Escenario y entrada sintética

```text
solicitud: MOCK-REQUEST-001 (ya pagada cuando ON; no repetir pago/cupo)
certificado lógico: MOCK-CERT-001
radicado: MOCK-RADICADO-001 (etiqueta, no radicado institucional emitido)
oficialización: MOCK-OP-001 / versión 1
idempotency_key: officialize:v1:MOCK-OP-001:1
firmante: CARGO FICTICIO DE PRUEBA / autorización FICTICIA
input_path: REFERENCIA_PRIVADA_SINTETICA
input_sha256: NO_CALCULADO_EN_MOCK
QR conceptual: https://validacion.example.invalid/validar/{capability_sintetica}
provider: NINGUNO
final_pdf / final_sha256 / signature / TSA: null
```

`.invalid` es dominio ficticio: no contactar, imprimir QR ni usar identidad/dominio institucional. No se asigna grado de confianza. Un resultado que contenga `NO_CALCULADO_EN_MOCK` no puede ser aceptado por un servicio real; este archivo no es una fixture runtime.

## Traza de coordinación propuesta

| Paso | Estado simulado | Efecto esperado en diseño |
|---|---|---|
| 1 | `pendiente` | Input reservado, token no activo. No hay oficialidad. |
| 2 | `en_proceso` | Worker envía una clave/bytes/contexto fijados. |
| 3 | `unknown` por timeout | No se sabe si se aceptó; estado permanece en proceso. Consultar clave. |
| 4a | Consulta pendiente | Esperar/reconciliar; no generar otra solicitud ni firmar con otra clave. |
| 4b | Consulta sin resultado definitivo | Operación sigue indeterminada; alerta al superar ventana aprobada. |
| 5 | Resultado disponible SIN evidencia | `VerificationReport = indeterminate`; publicación denegada. |
| 6 | Fallo conocido reintentable | Reusar misma clave/contexto/input; cero pago/cupo/radicado adicional. |
| 7 | Callback repetido | Deduplicar; no cambia estados terminales ni acepta binario diferente. |

**El mock termina sin oficialización aceptada.** En una integración real solo verificación satisfactoria y storage/hash final durable permitirían `oficializada`; no hay demostración de éxito criptográfico en este documento.

## Wireframes textuales para revisar requisitos

```text
AUTOSERVICIO — MOCK SIN VALIDEZ OFICIAL
Solicitud registrada. Oficialización técnica pendiente.
Conservamos su solicitud; consulte su estado más tarde.
No se genera un nuevo cobro, cupo o radicado por reintentar la firma.
Descarga oficial: no disponible.
```

```text
VALIDACIÓN PÚBLICA — MOCK SIN VALIDEZ OFICIAL
Documento: no oficializado.
Tipo: sencillo (dato sintético).
Dependencia emisora: pendiente de aprobación.
Firma: no verificada. Integridad: no evaluada.
Esta consulta se refiere al archivo registrado; no comprueba una copia recibida.
Nombre/cédula/funciones/documento completo: ausentes.
```

```text
ERROR TÉCNICO — MOCK SIN VALIDEZ OFICIAL
La oficialización no pudo completarse.
La solicitud permanece registrada y su recuperación es automática cuando sea posible.
Referencia de seguimiento: MOCK-OP-001.
Sin credenciales, transaction IDs externos o excepciones técnicas.
```

Casos adicionales requeridos en 3B.2: documento anulado; capability expirada (no inferir firma inválida); firma inválida por alteración; verificación indeterminada; nuevo oficializado verificado por motor real; legado BORRADOR. Este mock no rediseña ni cambia la UI productiva.
