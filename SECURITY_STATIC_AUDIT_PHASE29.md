# Auditoría estática y ASVS aplicable — 2.9

Fecha 2026-10-04, recovery. Se revisaron rutas, controllers, requests, policies, servicios, modelos, configuración, frontend src, Docker, scripts, documentación y archivos versionados. `rg` excluyó vendor/node_modules/dist para código propio; dependencias se auditaron por lockfiles. Archivos `*-camilo_ortega` son copias históricas versionadas sin import/ruta activa; incluidos en búsqueda de secretos, excluidos del análisis de ejecución. No se borraron silenciosamente.

| Área ASVS 5.0 / Top10:2025 | Evidencia y resultado | Límite / pendiente |
|---|---|---|
| V1 encoding/injection; A05 | Tres selectRaw con expresiones constantes: ReporteController:28, ManualFuncionController:72/75. Dos DB::select con advisory lock constante: BackofficeIdentityService y MfaService. where/whereIn de valores usan binds; orden/dirección allowlist IndexQueryRequest. Sin raw dinámico/statement/unprepared/PDO de entrada HTTP. TEST payloads SQL mantienen DB. JSX y Blade escapan texto. | No asegurar toda dependencia transitiva mediante rg |
| V1.5 deserialización | Sin eval/unserialize/include dinámico runtime. Sesiones serializan JSON cifrado; parser Manual usa validación y JSON. | APP_KEY/locks requieren custodia |
| V2 validación/anti-automation; A06/A10 | FormRequests y servicios allowlist, 64 KiB JSON, listados max100 (backoffice15), límites diferenciados, transacciones/constraints existentes conservados | Estado/resiliencia de infraestructura pendiente |
| V3 frontend; A01/A02 | Cookie HttpOnly/Lax/Secure prod; CSRF oficial explícito; CORS exacto; JSX, CSP script self, sin eval/HTML sinks activos. `localStorage.removeItem` exclusivamente borra credencial legacy; cero set/get bearer runtime. | styles unsafe-inline por UI dinámica, no claim de XSS imposible |
| V4 API; A01/A02 | Matriz de 83 rutas; métodos explícitos, autenticar/contexto/permiso/owner; rutas 410 no mutan. Health mínimo. Sin endpoint de test/debug en routes. | Límites edge según institución |
| V5 archivos; A01/A05 | PDF/JPEG/PNG 5MiB, MIME/extensión/mínimo contenido, UUID, private, SHA256, download attachment, nosniff y no-store; traversal no alcanza ruta | Antivirus/sandbox futuro, PDF header no análisis profundo |
| V6 autenticación; A07 | MFA backoffice obligatorio TOTP librería, recovery hashed único, password12/15–128, dummy hash y respuesta genérica, tasas temporales | HIGH cédula inicial; phishing; diccionario local limitado |
| V7 sesiones; A07 | Login/password/MFA/reauth regeneran sesión; auth_version, estado, logout, pending10/idle30/absolute480 min, locks Laravel. Segunda sesión revoca anterior. | No interfaz de inventario de sesiones; una sesión vigente por epoch, requisito ASVS V7.5.2 no cubierto íntegramente |
| V8 autorización; A01 | Policies/controller/FormRequest, usuarios individuales, último admin, owner compuesto y matriz rol×recurso | Nuevas rutas necesitan revisar matriz |
| V9/V10/V17 | NOT_APPLICABLE: no JWT/SAML, OAuth/OIDC ni WebRTC | No se pretende verificar controles inexistentes |
| V11 criptografía; A04 | Laravel encryption secreto TOTP; Argon2id; random_bytes recovery y capability; SHA256 solo secretos aleatorios/integridad, no password | Key lifecycle y backups institucionales |
| V12 comunicación; A04 | Defaults Secure prod, HSTS solo HTTPS, proxy allowlist | INFRASTRUCTURE_REQUIRED TLS y redes reales |
| V13 configuración; A02 | Checker prod+startup, .env ignorado, fixtures fuera de prod, PHP/Nginx, secret scan de metadatos | Revisar despliegue definitivo; no valores productivos disponibles |
| V14 datos; A01/A04 | Recursos no muestran password/TOTP/recovery/path, private/no-store, validación pública sin documento/email/telefono/snapshot. Hash comprobante interno; actor revisión solo backoffice pagos.ver | Nombre/cargo público y TTL aún D04; retención D03 |
| V15 dependencias/concurrencia; A03/A08 | Composer parches y audit; npm compatible; lock de fila y sesión + constraints | braces dev HIGH D08; pipeline de release institucional |
| V16 eventos/errores; A09/A10 | Security events allowlist, CRLF filtrado, sin mensajes SQL en report(), errores genéricos, logs 0600/30 días | Alertas, SIEM/acceso/retención pendientes |

Este inventario es una revisión dirigida a controles aplicables de L2, **no cumplimiento completo ni certificación ASVS**. Aún faltan decisiones de activación, recuperación, diccionario de passwords más completo, protección de phishing, inventario de sesiones, infraestructura, operación/retención y supply chain dev. No se asignan números finos de requisito sin verificación; capítulos oficiales provienen del [índice ASVS](https://cheatsheetseries.owasp.org/IndexASVS.html).

## Otros sinks y datos

No shell_exec/exec/system/passthru/proc_open/Process dinámico en runtime HTTP. No Http::/Guzzle/curl con URL suministrada; Dompdf isRemoteEnabled=false, isPhpEnabled=false, chroot local, PDFs desde snapshot/Blade. CertificatePdfTest inyecta URL/HTML sintético en nombres, verifica escape, rechazo de recurso remoto por Dompdf y PDF válido; no emite tráfico externo. Links externos frontend son constantes con noreferrer; redirects usan rutas internas fijas. No dangerouslySetInnerHTML/innerHTML/DOMParser activo. Nombres, observaciones, rechazos, Manual y filename se renderizan como texto. Los paths de descarga derivan de modelos/rutas internas, nombres almacenados son UUID. El servicio backoffice recibe all() pero rechaza claves no permitidas antes de fill/create; otros writes usan validated/DTO/valores internos. User.fillable contiene campos privilegiados por provisión interna, **no se conecta all() a fillable directamente**.

Validación pública: capability de 32 bytes CSPRNG/64 hex (256 bits), persistida como SHA256; no ID secuencial como secreto. `expires_at` se respeta si existe; por contrato anterior los tokens de validación creados sin expiration no tienen TTL obligatorio. D04 requiere decidir TTL/minimización sin romper el contrato. Downloads autenticados sí comprueban contexto/owner/policy e integridad. Secretaria consulta pagos por permisos y confirmación/rechazo exige explícitamente rol secretario, incluso admin tiene permiso pero no contexto.

## Secretos (solo metadatos)

Scan final del árbol versionado y archivos nuevos: 724 archivos inventariados y 13 coincidencias revisadas, sin imprimir valores. Son fixtures sintéticos o falsos positivos de nombres de endpoint; no se confirmó ningún secreto institucional. No se encontraron claves privadas, APP_KEY literal ni VITE_SECRET/API_KEY/TOKEN en ese alcance. `.env` reales ignorados; ejemplos con key/DB password vacíos. Compose usa credenciales PostgreSQL **locales** y bind loopback, no configuración productiva. UsuariosInicialesSeeder/DatosEjemploSeeder solo local/testing; PrepareAutoserviceE2E exige testing + DB exacta. Checker prod rechaza fixtures activos conocidos y DB por defecto. Ninguna key real fue rotada.

| Tipo | Archivo/línea representativa | Clasificación |
|---|---|---|
| Password sintético | tests/Feature/BackofficeIdentityLifecycleTest.php:57 | TEST aislado |
| Password sintético | e2e/autoservice.spec.ts:7; identity-lifecycle.spec.ts:8; payment-workflow.spec.ts:19 | E2E guardado; no producción |
| Credenciales sintéticas | database/seeders/UsuariosInicialesSeeder.php:24; app/Console/Commands/PrepareAutoserviceE2E.php | Guardas local/testing / DB exacta |
| DB password conocido | docker-compose.certificate-types.yml:17 | Dev CT local; checker prod rechaza |
| Match de nombre de endpoint | src/lib/api/endpoints.ts:6; LoginForm-camilo_ortega.tsx:20 | Falso positivo / copia histórica sin import |
| Fixture de prueba | Onboarding.test.tsx:53; auth.service.test.ts:14/15 | Unit tests, sin secreto real |
| Fixtures de seguridad | AuthenticationSecurityTest.php:49; SecurityTestCase.php:38; security-hardening.spec.ts:9/100 | Passwords y bearer inválido sintéticos, solo pruebas |

Traces Playwright off. Failure snapshots deshabilitados con flag comprobado en Playwright1.58.2; capturas nuevas enmascaran passwords/secreto/códigos. Los outputs de diagnóstico y backups se mantienen ignorados. Se verifican metadatos, no se garantiza ausencia de secreto en cada binary histórico sin OCR exhaustivo; evidencia nueva inspeccionada visualmente. Antes de publicación institucional, scanner de CI/secret manager y revisión de historial/binaries siguen siendo operación requerida.
