# Modelo de amenazas de oficialización — 3B.1

2026-10-04, Bogotá. Diseño futuro; no controles nuevos implementados. Base: `SECURITY_THREAT_MODEL.md`, `SECURITY_HARDENING_PHASE29_REPORT.md`, `SECURITY_DECISION_REQUIRED.md` y `SECURITY_PRODUCTION_CHECKLIST.md`. **BORRADOR — SIN VALIDEZ OFICIAL** permanece.

## Activos, actores y límites

Activos: contenido laboral v3, input/final PDF, autorización/competencia del firmante, clave bajo custodia externa, credencial de servicio, evidencia TSA/cadena/revocación, estado documental, capability y auditoría. Actores: empleado, backoffice/Secretaría, titular/custodio, worker, proveedor/TSA/CA, tercero validador, insider y atacante externo.

Límites: navegador→TLS/proxy→Sanctum/API; dominio→DB/storage/outbox; worker→secret manager; worker→HTTPS proveedor/gateway HSM; resultado externo→parser/verificador; portal público→consulta minimizada; CI/dependencias→binario. Proveedor y sus callbacks/material de validación son entradas externas. La ECD acreditada no se presume incorruptible ni autoriza automáticamente la identidad de servicio.

Controles 2.9 a conservar: HttpOnly/CSRF/CORS exacto, auth_version, MFA backoffice, Argon2id, rate limits, owner/IDOR, CSP/headers, storage privado y logging sin secretos. No cambiar esos controles para permitir una integración; separar autenticación de máquina del navegador.

## Amenazas y tratamiento candidato

| Amenaza | Abuso / impacto | Control futuro verificable | Residual / responsable propuesto |
|---|---|---|---|
| Robo de clave | Firmas falsificadas fuera del dominio. | Custodia no exportable aprobada, segregación/alertas, suspensión y revocación de credencial. | Compromiso custodio/proveedor; seguridad + custodio + Jurídica. |
| Robo de secreto API | App atacante pide firma masiva. | Secret manager/identidad servicio, scopes, destinos/volumen/reglas limitados, rotación y política remota. | App comprometida puede abusar de su capacidad; exigir controles remotos además del app. |
| Firma no autorizada | Admin firma cualquier PDF o tras cese. | Sin /sign arbitrario; solo dominio elegible/input fijo, acto y vigencias verificados al enviar/publicar. | Autorización errónea institucional; dependencia emisora/Jurídica. |
| Replay / duplicado | Retry, callback o worker genera documentos contradictorios. | Unique/CAS, clave fijada al input/contexto, reconciliación por clave, autenticidad callback. | Proveedor sin dedupe/lookup no satisface gate; TI. |
| Sustitución PDF | Cambiar input, respuesta o final y hash DB. | Input inmutable, verificar contenido cubierto/firmante, final hash/write-once, auditoría segregada. | DB+storage+app comprometidos; confianza externa/evidencia custodiada. |
| Tampering | Modificar byte/rangos/página o añadir revisión posterior. | Verificador real cobertura/CMS y revisiones, descarga exacta/hash; cuarentena. | Parser/render ambiguo; security QA independiente. |
| Proveedor comprometido | Firma incorrecta o salida fabricada. | Verificación independiente con política/raíces autorizadas, material firmado, alertas y kill switch. | Colusión/robo de clave requiere suspensión/análisis retrospectivo. |
| MITM / red | Sustituir destino/respuesta o ocultar revocación. | HTTPS validado sin verify=false, allowlist egress, timeouts, firma de evidencia/callback. | DoS y dependencia DNS/TLS; infraestructura. |
| SSRF del verificador | AIA/OCSP/CRL o URL de resultado accede a red interna. | Egress controlado, validación URL/redirect, rechazo IP privadas, límites y parser aislado. | Material externo complejo; TI/seguridad. |
| QR malicioso/reemplazado | Redirigir phishing o copiar QR válido a falso PDF. | Dominio HTTPS visible; QR dentro de contenido firmado; portal limita lo que valida. | Papel/captura permite reemplazo; capacitación + verificar firma de copia. |
| Enumeración | Adivinar IDs/códigos o filtrar existencia/personas. | Capability CSPRNG256, hash tabla, respuestas de ausencia genéricas, rate limit, no descarga pública. | Capability filtrada otorga acceso al mínimo; privacidad D04. |
| Fuga de token | URL en logs/referrer/analytics/screenshot/backup. | No-referrer específico, no-store, sin terceros; redacción en logs/CDN/APM; proteger snapshot/PDF. | QR/archivo compartido contienen capability por diseño; aprobar TTL/mínimo. |
| Revocación ignorada | Dar válido con certificado revocado o documento anulado. | Ejes separados; OCSP/CRL autenticado/fresco y evidencia temporal; unknown indeterminado; estado DB revalidado. | Servicios caídos/evidencia incompleta; no inventar resultado good. |
| Manipular reloj | Fecha aparente o firma después del cese. | Tiempo operativo monitorizado, TSA/política cuando requerida, chequeo de autorización/vigencias. | TSA/custodio pueden fallar; tiempo local no prueba criptográfica. |
| Insider | Cambia delegación, política, trust anchors o auditoría. | Segregación, aprobación versionada, acceso mínimo, logs protegidos y revisión de cambios sensibles. | Insider autorizado sigue riesgo; controles institucionales. |
| Caída / DoS | Firmas bloqueadas o consumo ilimitado de API. | Cola/timeout/backoff limitados, cuotas ya existentes, circuit breaker/alerta, backlog recuperable. | SLA combinado y presupuesto pendientes; no emitir sin evidencia. |
| Supply chain / parser | SDK/biblioteca maliciosa o PDF explota validador. | Gate licencia/mantenimiento/CVE, versiones fijadas, build aislado, sandbox parser y límites. | D08 sigue abierto; no instalar paquete por apariencia de firma. |
| Pérdida histórica | Expiración/retirada proveedor deja sin prueba. | Exportar evidencia/binarios, backup/restore, TRD, renovación preservación y plan de salida. | Confianza histórica/algoritmos requieren operación continua. |

Un hash editable con el PDF no impide adulteración por administrador DB. Firma tampoco demuestra verdad de hechos laborales o facultad del cargo por sí sola. Snapshot y aprobación institucional mantienen sus responsabilidades.

## Riesgos abiertos de 2.9 y gates

**A** = investigación documental 3B.1. **B** = implementación 3B.2: distinguir desarrollo local sintético de integración real/diseño definitivo. **C** = producción/oficialización. Ninguna fila se considera resuelta por este informe. "No bloquea A" no es aceptación institucional.

| Riesgo | A | B | C / acción y responsable propuesto |
|---|---|---|---|
| D01 HIGH password inicial = cédula | No. No usar identidades reales. | Permite tests sintéticos; diseño de activación pendiente condiciona flujo seguro real. | Bloquea lanzamiento oficial sin solución de activación verificada o evaluación/aceptación formal jurídicamente admisible. Seguridad/Talento Humano. Cambio obligatorio no elimina apropiación inicial. |
| D02 reset/custodia MFA | No. | Local posible; prueba operativa de recuperación requiere procedimiento aprobado. Custodia de firma es decisión adicional, no cubierta por reset MFA. | Bloquea operación segura sin verificación de identidad, segundo admin y recuperación/custodia. Seguridad/mesa de ayuda. |
| D03 retención/logs | No. | Se diseñan eventos; retención, acceso y archivo real dependen de política. | Aprobar TRD/retención, acceso, centralización/alertas; protección de datos/archivo/operaciones. |
| D04 nombre/cargo/TTL público | No. Documentar exposición existente, incluido token en snapshot URL. | **Bloquea decisión final y habilitación real de QR/validación** sin política; mock sin PII puede avanzar. | Bloquea publicación/QR oficial hasta aprobación de mínimo/TTL/rotación/retención y base de tratamiento. Jurídica/protección de datos. |
| D05 HIGH infraestructura | No. | Tests locales no la requieren; diseño multiinstancia/proveedor real depende de TLS, DB/locks/storage/secret manager. | Bloquea despliegue sin infraestructura institucional y pruebas de restore/seguridad. Infraestructura. |
| D06 recuperación funcionario | No. | Fixtures posibles; canal real pendiente y enlazado a D01. | Debe resolverse recuperación verificada sin reintroducir apropiación por cédula. Talento Humano. |
| D07 tokens no browser | No. | Mantener deshabilitados; autenticación proveedor no implica abrir emisión Sanctum HTTP. | Cualquier capacidad nueva requiere decisión propia; no habilitar por esta fase. Seguridad/integraciones. |
| D08 HIGH supply chain dev | No: no se instala/ejecuta tooling adicional. | Builds aislados confiables; evaluar cada SDK antes de incorporar. No asumir cinco avisos históricos igual al estado futuro. | Resolver o aceptar formalmente riesgo y medidas de build antes de deploy; repetir audit en 3B.2. Desarrollo/seguridad. |

Gates propios 3B: confianza/modalidad (Jurídica/autoridad); competencia/delegación y autorización desatendida (dependencia); certificado/custodia/API y evidencia (custodio/TI); assets/muestra/dominio (comunicaciones/TI); revocación/retención/SLA (archivo/operaciones). Bloquean la decisión/integración correspondiente en B y oficialización en C; permiten investigación y mock documental A. No promover `ACCEPTED_PENDING_DECISION` a aceptación formal.

## Verificación y respuesta a incidentes propuestas

Probar tamper/replay/concurrencia/anulación/rangos/SSRF y no entrega con evidencia indeterminada. Simular proveedor caído y respuesta perdida sin segunda cuota/cobro. Rotar/suspender identidad de servicio y detener nuevas firmas ante compromiso; conservar operaciones/evidencia, evaluar periodo afectado con Jurídica, notificar por proceso institucional y reflejar estado documental aprobado. No re-firmar historia para ocultar incidente. Audit logs no contienen PII innecesaria, PIN, claves, secrets o capabilities.
