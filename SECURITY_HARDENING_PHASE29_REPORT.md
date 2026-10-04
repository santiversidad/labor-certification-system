# Security hardening — fase 2.9

Fecha: 2026-10-04. Rama `feat/security-hardening`, base `7670f3f`. Trabajo únicamente en `C:\Users\mondr\source\labor-certification-recovery`, CT/TEST/E2E locales. Sin push. Informe técnico, **no certificación ASVS ni autorización de producción**.

## 1. Alcance

Autenticación, sesión, MFA, límites, autorización, límites de aplicación, errores/logs, archivos/configuración y dependencias. Ningún cambio de tarifa, cuota, tipo/contenido de certificado, Manual, máquina de pagos, firma, QR u oficialización. Fase 3A congelada. Sin pruebas ofensivas externas ni escaneo activo.

## 2. Referencias OWASP/Laravel

[ASVS5.0](https://owasp.org/projects/asvs), [capítulos ASVS](https://cheatsheetseries.owasp.org/IndexASVS.html), [Top10:2025](https://top10.owasp.org/2025/), [Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html), [Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html), [CSRF](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html), [MFA](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html), [Laravel13 Sanctum](https://laravel.com/docs/13.x/sanctum), [Fortify](https://laravel.com/docs/13.x/fortify), [Hashing](https://laravel.com/docs/13.x/hashing), [Session](https://laravel.com/docs/13.x/session). Checklist aplicable en SECURITY_STATIC_AUDIT_PHASE29.md; no se afirma cumplir todos los requisitos L2.

## 3. Baseline

SECURITY_BASELINE_PHASE29.md se creó antes de editar runtime. Precheck confirmó árbol limpio, rama anterior feat/backoffice-identity-lifecycle y commit7670f3f, con historial Certificate Types, UX2.6, pagos2.7, identidad2.8, Manual bootstrap, React Bits y PDF3A. La referencia anterior es219 tests/1147 assertions. El primer intento de suite coincidió con una actualización de vendor y **no es baseline válido**; los resultados de cierre son suites posteriores completas. Inventario final83 rutas en SECURITY_ROUTE_MATRIX_PHASE29.md.

## 4. Hallazgos Critical/High/Medium/Low

Baseline: 0 CRITICAL confirmado, 12 categorías HIGH, 12 MEDIUM y 1 INFO. Son categorías de riesgo o control por revisar; no representan 12 exploits demostrados. La matriz final incorpora los hallazgos posteriores de dependencias y minimización de pagos: 14 categorías HIGH, de las cuales 7 están FIXED, 3 MITIGATED, 2 ACCEPTED_PENDING_DECISION y 2 INFRASTRUCTURE_REQUIRED. No hay CRITICAL confirmado. HIGH pendientes: contraseña inicial igual a cédula, operación TLS/DB institucional y la cadena de dependencias de desarrollo de braces. Las 5 entradas HIGH de npm corresponden a esa misma cadena. IDOR y regresión eran riesgos de verificación, mitigados con pruebas. La matriz final incluye evidencia y distingue esos estados.

## 5. Autenticación

SPA first-party con cookie de sesión oficial Laravel/Sanctum. Username canonizado; cédula continúa como username funcionario. Credenciales erróneas, desconocidas e inactivas devuelven422 y el mismo cuerpo genérico; ruta de hash para desconocidos evita rechazo gratuito. Revalidación bajo lock impide que login concurrente revierta desactivación/reset. Backoffice recibe principal pendiente, no guard administrativo, hasta segundo factor. /me representa identidad del servidor.

## 6. Migración bearer → cookie

GET /sanctum/csrf-cookie → POST /api/v1/auth/login (alias POST /login en API origin) → cookie HttpOnly → credentials y X-XSRF-TOKEN → logout servidor. SPA usa endpoint API para conservar GET /login de router en mismo origen. No token en respuesta normal; no Authorization interceptor. BrowserBoundary rechaza bearer browser aun con capability CLI activada. API tokens deshabilitados por defecto, conservados solo para CLI/tests opt-in, sin emisión HTTP.

## 7. Sesiones

Idle30 min, absoluto480 min, pendiente MFA10 min, step-up5 min. Login/password/MFA/reauth regeneran ID/CSRF; logout invalida. auth_version/estado/identidad se comprueban en cada acceso; reset/rol/desactivación revocan cookies viejas y borran tokens. Login nuevo revoca epoch anterior. Locks oficiales Laravel serializan solicitudes con igual cookie para que setup/lecturas concurrentes no sobrescriban estado; cache compartida requerida en réplicas. Cambio de password mantiene actual guard/HMAC Sanctum y revoca otras sesiones. /me anónimo no destruye CSRF de inicialización. Frontend espera bootstrap antes de login.

## 8. CSRF

Se eliminó excepción api/*. Middleware oficial Laravel13 PreventRequestForgery/EnforceCsrfToken con validación estándar; se desactiva solo su atajo hasValidOrigin para exigir token incluso same-origin. No token casero. POST/PUT/PATCH/DELETE sin token419, con token válido pasan, origen ajeno403. Respuesta controlada y evento csrf_rejected. PHPUnit normally bypasses CSRF: pruebas de seguridad fuerzan middleware real y E2E confirma en navegador.

## 9. CORS

Orígenes exactos CORS_ALLOWED_ORIGINS/FRONTEND_URL, credentials=true, sin patrones/wildcard; métodos GET/HEAD/POST/PUT/PATCH/DELETE/OPTIONS y headers mínimos Accept/Content-Type/X-XSRF-TOKEN/X-Requested-With. Retry-After expuesto. SANCTUM_STATEFUL_DOMAINS explícito. Defaults CT localhost3200/8280; dominio institucional pendiente, sin hardcode. Referrer strict-origin-when-cross-origin conserva reconocimiento Sanctum same-origin, no filtra paths en requests cross-origin.

## 10. MFA

Backoffice admin/secretario obligatorio, funcionario no obligatorio. Cambio password antes de enrollment. Setup manual32 caracteres Base32 en memoria, sin QR persistido/servicio externo. Confirmación TOTP6 dígitos, periodo estándar30 s, ventana±1 paso, verifyKeyNewer y mfa_last_step impiden replay. Secreto cifrado Laravel en DB y setup cifrado en sesión. Ocho recovery codes CSPRNG128 bits, SHA256 en reposo, consumo único bajo lock, regeneración revoca anteriores. Modal permite copiar/guardar y descarta al cerrar. Reset por otro admin activo con permiso/reautenticación reciente, auditado, revoca target y obliga enrollment; self reset/último admin bloqueados. No SMS/email/preguntas ni backdoor HTTP.

## 11. Passwords

Backoffice15 caracteres mientras no está confirmado MFA;12 con MFA y funcionario después de cambio; máximo128, espacios/managers/paste sin composición arbitraria. Lista local común pequeña y mantenible; no consulta plaintext externa. Password inicial funcionario=cédula se conserva por requisito, marcadoHIGH D01. Cambio obligatorio restringe APIs, límites/mensajes genéricos/auditoría ayudan pero no eliminan apropiación antes del primer cambio. Temporales backoffice aleatorias de fase2.8 preservadas.

## 12. Hashing

Argon2id para nuevas contraseñas,19456 KiB/t2/p1, costos prod acotados. Bcrypt existente se verifica y rehash solo tras login válido needsRehash; no conversión irreversible sin password. SHA256 se usa para recovery/capabilities aleatorias e integridad, nunca passwords. PHPUnit usa bcrypt4 exclusivamente para fixtures y una prueba explícita verifica transición Argon. Benchmark nativo5 hashes en contenedor: promedio22.77 ms; no representa carga institucional.

## 13. Rate limits

Login30/IP/min,5/pareja username canonizado+IP/min,10/identidad/min. MFA/setup/password/reauth/security-actions5/actor/min, validación pública30, generación5, upload10, revisión pago30, provisión60, auth general120; IP por contexto3×límite. Temporal, no bloqueo permanente de cuenta. Reset MFA/password además security-action, cambio rol/desactivar/config step-up. 429 con Retry-After y eventos sin credenciales. Ataque distribuido requiere observación institucional.

## 14. Autorización

Denegar sin identidad/contexto/MFA/password vigente. Policies, permisos explícitos y FormRequests/servicios; no inferir acceso desde UI. Matriz83 rutas documenta rol/contexto/owner/inputs/files/returns/middleware/tasas. Secretaría6 permisos, admin36, funcionario3. Confirmar/rechazar pago requiere secretario explícito aunque admin tenga permisos. Usuarios internos solo backoffice admin, último administrador protegido bajo advisory lock existente. Rutas410 preservan autenticación/límites y no mutan.

## 15. IDOR

TEST y E2E: A consulta A; A no consulta solicitud/pago/comprobante/certificado de B. Binding comprobante↔solicitud antes de archivo; owner funcionario; secretaria/admin con permiso según recurso. No rutas elegidas por paths/nombre de archivo cliente. Sin cookie/pending/epoch obsoleto desactivado/rol cambiado fallan cerrados.

## 16. Mass assignment

FormRequests validated y valores internos/DTO. Backoffice all() valida claves allowlist explícita antes de create/fill, rechaza extras; User.fillable interno no constituye autorización HTTP. Payloads role/tipo_cuenta/owner/estado/revisado_por/require_pago/snapshot/auth_version/password flags no modifican privilegios/flujo desde endpoint funcionario. Pruebas dedicadas.

## 17. SQL injection

Tres selectRaw agregados y dos advisory DB::select constantes auditados; ningún SQL raw recibe interpolación cliente. Eloquent binds, sort/dirección allowlist, límites filtros. Payloads controlados SQL se probaron únicamente TEST transaccional; DB permanece intacta. Command injection, SSRF, eval/unserialize y open redirect no encontraron sinks runtime aplicables; Dompdf remoto/PHP off/chroot local. Referencia audit detallado.

## 18. XSS

JSX/Blade escaping preservado, sin HTML sinks activos. Inputs sintéticos en observación/búsqueda y modal verifican texto/escape; no sanitización que altere dominio. URL externa en texto PDF no habilita fetch remoto. React Bits existente intacto. Runtime storage cero bearer; HttpOnly reduce robo pero no evita acciones ejecutadas por XSS mismo origen.

## 19. Uploads

PDF/JPEG/PNG MIME y extensión,≤5MiB, vacío/nombre>180/doble extensión executable/MIME falso/imagen no decodificable/PDF sin header mínimo bloqueados. UUID interno, root private, SHA256 antes de descarga, attachment/nosniff/no-store. Tampering409 con evento. Hash de integridad permanece interno y no se devuelve en API; revisor/validado_por se limita al backoffice con pagos.ver, conservando al funcionario estado, fecha y motivo de revisión. UI usa su fallback existente sin rediseño ni cambio de state machine. AuthorizationSecurityTest verifica ambos contextos y el hash interno. Validación PDF mínima no certifica documento benigno; antivirus/sandbox es defensa futura sin infraestructura instalada.

## 20. CSP

Build Vite real bajo Nginx plantilla: default/self, scripts self, estilos self+unsafe-inline por estilos dinámicos React Bits/progreso, img self/data, fonts self, connect self, objects none, base self, frame ancestors none, form self. Sin unsafe-eval/script wildcard/script inline. API puede añadir connect-origins HTTPS exactos env. Dev HMR usa política propia; no aplicar CSP prod a Vite dev a ciegas.

## 21. Headers

Middleware uniforme APIs/errores/files: nosniff, DENY/frame-ancestors none, Referrer-Policy strict-origin-when-cross-origin, Permissions-Policy camera/microphone/geolocation desactivadas, private/no-store sensible. HSTS31536000 solo prod HTTPS; local HTTP intencionalmente sin HSTS. Plantilla SPA misma política; no HPKP/X-XSS-Protection como mecanismo principal.

## 22. Errores

500/DB/Domain/404/403/422/401/419/429 responden códigos/mensajes controlados, sin SQL/bindings/class/path/stack. report almacena clase y código, suprime mensaje sensible. Frontend filtra SQLSTATE/stack/classes/paths. APP_DEBUG prod o configuración crítica devuelve503 cerrado y startup falla. Sin correlation ID nuevo; no se afirma implementado.

## 23. Logging

Auditoría negocio/administración existente preservada; security daily separado30 días/0600, campos allowlist scalar128 caracteres con CRLF normalizado. Login/MFA/reset/lifecycle/CSRF/forbidden/integridad/revisión pago/config. Ningún password/TOTP/recovery/cookie/session ID/bearer/secreto en eventos. URI capabilities no se guardan en access log Nginx. Centralización/alertas/acceso/retención institucional pendientes D03.

## 24. Secretos

Scan solo metadatos, fixtures diferenciados, .env reales ignorados y ejemplos sin key/password institucional. Sin clave privada/APP_KEY productiva/VITE secreto confirmado; no se afirma scanner exhaustivo de binaries/historial. Seeders local/testing, E2E exige DB exacta, checker prod cuentas fixture/DB default. APP_KEY actual no rotada: recifrado/restauración MFA requiere plan. Outputs/traces ignorados; traces off, snapshots de fallo off y capturas con máscaras.

## 25. Nginx

CT root public, server_tokens off, 6m, TRACE/métodos ajenos405, solo index.php fastcgi, otrosPHP404, dotfiles403/storage404, access log off. Template producción root dist + API internal same-origin; GET /login sigue SPA. No roots vendor/tests/database/output/composer/reports expuestos, source maps no generados. nginx -t CT y estático correctos.

## 26. DB

Consulta read-only confirmó postgres CT superuser/createdb/createrole; aceptable solo dev y HIGH producción. No se alteró DB local compartida. Usar rol aplicación DML/secuencias necesarias, migrador DDL separado, red privada/backup seguro; checker rechaza usuario/password por defecto. Constraints identidad/quota/payment/unique certificado conservados; MFA agrega solo campos cifrados/hash/estado a users.

## 27. Dependencias

Composer audit final: 0 advisories y 0 paquetes abandonados tras actualizar framework/commonmark a versiones compatibles. Se aplicaron correcciones npm compatibles, incluida brace-expansion 5.0.12. `npm audit --omit=dev`: 0 vulnerabilidades; audit completo: 5 entradas HIGH de desarrollo relacionadas con [braces GHSA-vfj7-8cjw-p6xm](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm), a través de tailwindcss/chokidar/micromatch/fast-glob. El audit npm del tooling Laravel también devuelve 0. Las auditorías finales se repitieron sobre los lockfiles instalados mediante `npm ci`; `npm ls` terminó sin dependencias inválidas. El advisory no publica una versión parchada de braces y npm propone una migración incompatible a Tailwind 4. D08 registra la decisión pendiente: builds aislados con fuentes confiables y sin servir Vite en producción.

MFA pragmarx/google2fa9.1.0 MIT, agnóstica PHP8.3+ compatible PHP8.4/Laravel13; transitiva paragonie/constant_time_encoding3.1.3 MIT. [Repo mantenido](https://github.com/antonioribeiro/google2fa), [Packagist](https://packagist.org/packages/pragmarx/google2fa). Fortify reemplazaría pipelines/rutas, por eso dependencia TOTP directa evaluada antes de instalar. `otpauth` solo dev/E2E MIT, oracle independiente para probar TOTP estándar; no bundle runtime. No se instaló ZAP/herramienta global; ZAP no disponible, scan pasivo no ejecutado.

## 28. Validación pública

Capability random32 bytes/256 bits, SHA256 persistido, sin enumeración por ID. Dos aliases throttle30, no-store. Respuesta nombre/cargo/estado/fecha/código/tipo, sin documento/email/teléfono/historia/snapshot/funciones/path. expires_at respetado si existe; TTL obligatorio de tokens validación anteriores no se introdujo por contrato. D04: aprobar minimización/retención/TTL. Sin descarga PDF pública nueva ni QR.

## 29. Threat model

SECURITY_THREAT_MODEL.md incluye actores/activos/límites browser→proxy→API→DB/files/logs, abusos de contraseña predecible/MFA/replay/CSRF/IDOR/injection/upload/insider/config/supplychain, controles y riesgos residuales con dueños.

## 30. Tests backend/frontend

Backend: **268 pruebas aprobadas, 1.535 aserciones, 340,17 s**, mediante `php artisan test --compact` en el contenedor local, exclusivamente contra `scl_certificate_types_test`. Frontend: **78 pruebas aprobadas en 20 archivos, 51,96 s**, mediante `npm run test -- --run`. `npm run lint` y `npm run build` finalizaron con código 0; el build incluye la comprobación TypeScript. Son corridas completas posteriores al último cambio de runtime: la minimización de datos de comprobantes. No se usan los intentos iniciales incompletos como evidencia de cierre.

Familias tests/Feature/Security separadas: Authentication, Authorization, Session, CSRF, CORS, Input, SQL, File, Headers, MFA, InformationDisclosure y RateLimit. Fixtures actingAs simulan MFA ya completa únicamente para regresiones de dominio; las pruebas nuevas ejecutan login/pendiente/MFA real. CLI bearer se crea en tests directamente, no vía login. Tests/bootstrap rechaza DB no *_test y conserva esquema migrado para no resetear bases compartidas.

## 31. E2E

**30 escenarios aprobados en 9,4 min**, mediante `docker compose -f docker-compose.certificate-types.yml --profile e2e run --rm e2e-runner sh -c 'npx playwright test'`. Se conservan los 18 escenarios anteriores: 7 de autoservicio, 4 de pagos y 7 de identidades. Se agregan los 12 escenarios A–L: cookie y autoservicio; enrollment/TOTP admin; MFA Secretaría; rechazo de bearer legacy; CSRF obligatorio; revocación al desactivar; revocación por rol; recovery de uso único; reset MFA y nuevo enrollment; límites de login; IDOR; build real con CSP y archivos privados bloqueados. La corrida final no tuvo fallos ni reintentos. Los fallos de corridas anteriores se corrigieron y no se cuentan como pruebas aprobadas.

Sin sleeps arbitrarios: waits por URL/elemento/respuesta. E2E helpers usan cookies/contextos separados; `token` en helper es solo identificador interno del runner para adaptar regresiones previas, nunca header bearer/credencial browser. Fixture con recovery pool sintético mayor que8 exclusivamente CLI testing+DB exacta evita esperar ventanaTOTP en regresiones; A–L prueba generación real8/TOTP/replay/revocación. Siempre refrescar fixtures/cache E2E antes de suite, nunca CT.

## 32. Evidence

Diez PNG requeridos en `frontend/react-app/evidence/security-phase29`, generados con Playwright y máscaras de secretos, códigos y passwords, en escritorio y a 360 px. Se revisaron visualmente las diez imágenes: login, enrollment, verificación MFA, recovery codes, sesión expirada, límite de intentos, seguridad admin/Secretaría y las dos vistas móviles. No se observaron desbordamientos ni credenciales legibles. Las áreas magenta son las máscaras intencionales. Cookies, tokens, secretos y códigos sintéticos tampoco se capturan. Los screenshots de la fase 2.8 regenerados incidentalmente por sus regresiones se excluyen del cambio de esta fase.

## 33. Performance

Argon promedio22.77ms/5 hashes en PHP8.4 CT,19456KiB/t2/p1. HTTP medido secuencialmente contra E2E aislado, con identidades sintéticas nuevas, cookies/CSRF reales y Argon2id. La medición coincidió con suites locales y usa filesystem Docker sobre Windows; son tiempos absolutos, no una diferencia demostrada frente al baseline.

| Operación | Muestras | Promedio ms | Rango ms |
|---|---:|---:|---:|
| Login funcionario | 3 | 773.62 | 629.38–868.39 |
| Password backoffice, aún pendiente | 3 | 1082.09 | 864.31–1242.56 |
| Segundo factor (1 TOTP, 2 recovery) | 3 | 1364.86 | 1155.54–1494.50 |
| Password + segundo factor, sin setup CSRF/tiempo humano | 3 | 2446.94 | 2294.93–2737.06 |
| /me autenticado | 5 | 915.00 | 489.54–1273.66 |
| Rechazo 429 con Retry-After | 1 | 777.58 | 777.58 |
| Login inexistente / incorrecto / inactivo | 2 por caso | 893.62 / 765.45 / 790.86 | Rangos se solapan; 422 y cuerpos idénticos |

La política de hash no hace inviable login local; los tiempos HTTP incluyen bootstrap Laravel, DB, storage y locks. No benchmark de producción, carga institucional ni promesa de SLA. El muestreo de enumeración es limitado y no demuestra resistencia estadística a timing. Los fixtures de rendimiento se desactivaron/revocaron y se eliminó el archivo temporal de credenciales sin imprimirlas. Dimensionar caché/locks compartidos y medir p95 bajo carga en el entorno real. Una sesión vigente por epoch puede afectar sesiones simultáneas legítimas. Migración bcrypt/Argon puede tener tiempos distintos; no se afirma constancia criptográfica.

## 34. Manual regression

CT consulta read-only: ID10 publicado (Decreto1000-24/015 de2023),344 fichas,3115 funciones,2326 conocimientos en metadata_manual.conocimientos. Sin import/mutación Manual en CT; Git no cambios a fuentes/contenido del Manual. TEST conserva reconciliación Excel/JSON y flujo de versiones. E2E usa Manual sintético aislado.

## 35. Payments regression

OFF automático; ON solicitud→comprobante→Secretaría→confirmación→generación; rechazo/reemplazo e IDOR; concurrencia real creación/upload/confirmar/confirmar-rechazar. State machine y tarifas congeladas. Cambios limitados a límites, auth/MFA, comprobaciones defensivas de archivo/logs y minimización de hash/actor en las respuestas API, descrita en la sección 19.

## 36. PDF regression

Servicio/Blade/assets/layout/firmas intactos. BORRADOR — SIN VALIDEZ OFICIAL, sin QR ni firma. Regresión PDF tipo sencillo/funciones/multipágina/snapshot/hash/descarga. No iniciar3B tras commit.

## 37. Findings still open

HIGH cédula inicialD01, infraestructura TLS/DB/secret manager/backups, braces devD08; MEDIUM minimización/TTL público y retención/operación. styles inline, diccionario local limitado, inventario de sesiones ASVS parcial y AV/sandbox futuro son limitaciones explícitas, no controles certificados.

## 38. Institutional decisions required

SECURITY_DECISION_REQUIRED.md D01–D08: activación funcionario, reset verificado/custodia factores, retención/eventos, validación pública, infraestrutura, recuperación funcionario, integraciones API tokens y supplychain dev. No decisión institucional inventada.

## 39. Infrastructure requirements

SECURITY_PRODUCTION_CHECKLIST.md separa CODE/CONFIG/INFRASTRUCTURE/INSTITUTIONAL. TLS/reverse proxy autorizado, DB leastprivilege, caché/locks compartidos, storage/backups cifrados, centralización de eventos y permisos, dominio real/keymanager, scanner pipeline. `security:check-config --accounts` después de migrar DB y antes de PHP/tráfico; startup falla seguro si críticos. CT sigue local HTTP/postgres.

## 40. Recommendation before Phase 3B

Revisar y aprobar D01/D02/D04/D05/D08 antes de despliegue/oficialización; demostrar TLS/DB/backup/operación en entorno institucional. Esta fase prepara aplicación, no produce certificados oficiales. Commit solo tras validación de lo implementado; no push y detener sin firma/QR/3B.

## Matriz final de hallazgos

| ID | Hallazgo | Severidad | OWASP/ASVS | Antes | Después | Estado | Evidencia |
|---|---|---|---|---|---|---|---|
| S01 | Bearer navegador persistido | HIGH | A07/V3,V7 | localStorage/header | Cookie HttpOnly, storage memoria | FIXED | AuthenticationSecurityTest, frontend security, E2ED |
| S02 | CSRF excluido | HIGH | A01/V3 | api/* except | Token oficial obligatorio | FIXED | CsrfSecurityTest, E2EE |
| S03 | MFA ausente | HIGH | A07/V6 | Password único BO | Pendiente→TOTP/recovery; cifrado/replay/reset | FIXED | MfaSecurityTest, E2EB/C/H/I |
| S04 | Password inicial cédula | HIGH | A07/V6 | Predecible | Restricción/límites/cambio; sigue predecible | ACCEPTED_PENDING_DECISION | D01, Auth/Login/forced tests |
| S05 | Enumeración login | MEDIUM | A07/V6 | Status/shortcut distinto | Body/status uniforme y hash dummy | MITIGATED | AuthenticationSecurityTest, timing limitado |
| S06 | Límites insuficientes | HIGH | A06/V2,V6 | Solo pareja5 | IP/identidad/contextos + temporal | FIXED | RateLimitSecurityTest, E2EJ |
| S07 | Revocación al migrar | HIGH | A07/V7 | Solo bearer epoch | Cookie epoch/estado/TTL/locks | FIXED | SessionSecurityTest, identidad2.8, E2EF/G/I |
| S08 | CORS amplio/implícito | MEDIUM | A02/V3 | headers/methods* | Exact env, credenciales | FIXED | CorsSecurityTest |
| S09 | Secure/TLS prod | HIGH | A04/V3,V12 | Default ambiguo | Secure prod/checker; TLS real pendiente | INFRASTRUCTURE_REQUIRED | HeadersSecurityTest, checklist |
| S10 | Password/hash | MEDIUM | A07/A04/V6,V11 | Bcrypt/composición | Argon gradual/passphrase12/15–128 | MITIGATED | AuthenticationSecurityTest, benchmark; diccionario limitado |
| S11 | Owner/permisos a verificar | HIGH | A01/V8 | Policies existentes | Matriz y pruebas negativas ampliadas | MITIGATED | AuthorizationSecurityTest, E2EK/regresión |
| S12 | Campos privilegiados | MEDIUM | A01/V2,V8 | Validated/allowlist | Payloads extras comprobados | MITIGATED | InputSecurityTest |
| S13 | SQL a verificar | MEDIUM | A05/V1 | Binds/raw constantes | Audit+payloadTEST/sort allowlist | MITIGATED | SqlInjectionSecurityTest, static audit |
| S14 | XSS a verificar | MEDIUM | A05/V1,V3 | JSX/Blade | Escape probado + CSP | MITIGATED | InputSecurityTest, Security.test.tsx |
| S15 | Upload defensivo | MEDIUM | A05/V5 | Privado/hash/MIME | Firmas mínimas/imagen/name/empty + traversal | MITIGATED | FileSecurityTest; AV futuro |
| S16 | Headers/CSP | MEDIUM | A02/V3 | Sin uniformidad | Middleware+build bajo Nginx | MITIGATED | HeadersSecurityTest, E2EL; styles inline |
| S17 | Debug/error productivo | HIGH | A02/A10/V13,V16 | Config riesgosa | Check/startup/failclosed/generic | FIXED | HeadersSecurityTest, InformationDisclosureTest |
| S18 | Eventos/retención | MEDIUM | A09/V16 | Audit sin seguridad | Security allowlist/CRLF/rotación | MITIGATED | InformationDisclosureTest, D03 operación |
| S19 | Fixtures/defaults prod | HIGH | A02/V13 | Fixtures conocidas | Guardas y checker prod/revisión | MITIGATED | check-config, seeders guards, static audit |
| S20 | Public validation | MEDIUM | A01/V2,V14 | Sin throttle |30/min/no-store; mínimo actual | ACCEPTED_PENDING_DECISION | RateLimitSecurityTest, D04 TTL/nombre |
| S21 | Proxy/DB/HTTPS | HIGH | A02/A04/V12,V13 | HTTP/postgres dev | Allowlist/checker, despliegue pendiente | INFRASTRUCTURE_REQUIRED | Consulta roles read-only, checklist |
| S22 | Dependencias | MEDIUM | A03/V15 | Advisories presentes | Composer/runtime npm0; dev5HIGH | MITIGATED | Audits, S27 |
| S23 | Shell/SSRF/serialization | INFO | A05/V1,V15 | Sin sinks runtime | Audit remoto off/rutas fijas | NOT_APPLICABLE | Static audit, CertificatePdfTest URL/HTML escapa, Dompdf rechaza remoto sin fetch |
| S24 | Métodos/tamaño/root | MEDIUM | A02/V4,V5 |10m/PHP amplio |6m/JSON64KiB/métodos/index único | FIXED | InformationDisclosureTest, nginx -t, local probe |
| S25 | Regresión negocio | HIGH | A06/V2,V15 | Fases congeladas | Suites y Manual counts preservados | MITIGATED | Full tests/E2E, consulta344/3115/2326 |
| S26 | Framework/commonmark advisory | HIGH | A03/V15 | Framework LOW, commonmark HIGH/Medium | Updates compatibles, Composer0 | FIXED | composer audit final/lock |
| S27 | braces dev tooling | HIGH | A03/V15 | npm8HIGH + otros |5HIGH dev de una cadena, runtime0 | ACCEPTED_PENDING_DECISION | npm audits, GHSA-vfj7-8cjw-p6xm, D08 |
| S28 | Datos técnicos de comprobante al funcionario | MEDIUM | A01/V14 | API devolvía sha256/revisor | Hash interno; actor solo backoffice con permiso | FIXED | AuthorizationSecurityTest minimización + integridad y regresión pagos |

Los FIXED se refieren al control probado en alcance local. MITIGATED preserva limitaciones y no significa explotación previa demostrada. INFRASTRUCTURE_REQUIRED no se cierra con cambiar código. ACCEPTED_PENDING_DECISION registra pendiente para aprobación institucional, no aceptación formal de riesgo por la Alcaldía.
