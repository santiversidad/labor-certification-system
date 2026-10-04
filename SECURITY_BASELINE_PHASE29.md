# Baseline de seguridad — fase 2.9

Fecha: 2026-10-04. Levantado **antes de modificar código** en `7670f3f`, árbol limpio, rama creada `feat/security-hardening`. Exclusivamente recovery y contenedores CT/TEST/E2E. No constituye certificación ASVS.

Referencias primarias: [ASVS 5.0](https://owasp.org/projects/asvs), [Top 10:2025](https://top10.owasp.org/2025/), [Cheat Sheets](https://cheatsheetseries.owasp.org/), [Laravel 13 Sanctum](https://laravel.com/docs/13.x/sanctum), [Fortify](https://laravel.com/docs/13.x/fortify), [Hashing](https://laravel.com/docs/13.x/hashing). Objetivo técnico L2 para controles aplicables; requiere verificación e infraestructura antes de producción.

| ID / Control | Estado actual | Riesgo | Severidad | Evidencia | Cambio recomendado | 2.9 / pendiente |
|---|---|---|---|---|---|---|
| S01 Credencial navegador | Sanctum bearer en localStorage | Robo por XSS, persistencia | HIGH | authStorage.ts, apiClient.ts, AuthController::login | Cookie HttpOnly, no bearer browser | CODE 2.9 |
| S02 CSRF | api/* excluido; login no usa guard web | Mutaciones cookie desprotegidas al migrar | HIGH | bootstrap/app.php | Middleware oficial sin excepción, tests reales | CODE 2.9 |
| S03 MFA | No implementado | Cuenta privilegiada comprometida con un factor | HIGH | AuthController, User | TOTP obligatorio, cifrado, recuperación hash, step-up | CODE 2.9 |
| S04 Password inicial funcionario | Documento predecible, cambio obligatorio | Suplantación antes de activación legítima | HIGH | FuncionarioController, ProvisionFuncionarioAccount | Preservar requisito, mitigar límites y acceso restringido | CODE + INSTITUTIONAL |
| S05 Enumeración | Inactivos 403 detallado; inexistente sin hash | Revela estado y existencia | MEDIUM | AuthController::login | Mensaje/status uniforme, dummy hash | CODE 2.9 |
| S06 Rate limits | Solo 5/min username+IP | Spraying, MFA/generación/uploads sin límites | HIGH | AppServiceProvider, routes/api.php | Límites IP, identidad y por contexto | CODE 2.9 |
| S07 Sesiones/revocación | auth_version y borrado tokens existentes | Conservar garantías 2.8 al migrar | HIGH | EnsureCurrentIdentity, User::revokeAuthentication | Epoch, idle/absolute/pending TTL, regeneración | CODE 2.9 |
| S08 CORS | Varios localhost implícitos, headers/métodos * | Allowlist excesiva, config productiva ambigua | MEDIUM | config/cors.php | Orígenes exactos env, métodos y headers explícitos | CONFIG 2.9 |
| S09 Cookies | HttpOnly true, Lax; Secure sin default prod | Robo en transporte sin TLS | HIGH | config/session.php | Secure prod, dominio configurable, check-config | CONFIG + INFRASTRUCTURE |
| S10 Hash/password policy | Bcrypt por defecto; 12 + composición | Bcrypt longitud, reglas arbitrarias | MEDIUM | ChangePasswordRequest, hashing framework | Medir Argon2id, rehash gradual, 12/15–128, lista local | CODE 2.9 |
| S11 Autorización/IDOR | Policies, permisos, ownership, contextos | Verificar todos recursos y roles | HIGH | Policies, Controllers, tests existentes | Matriz endpoint × rol × propio/ajeno | CODE 2.9 |
| S12 Mass assignment | FormRequests validated; backoffice all() valida claves | Campos privilegiados manipulados | MEDIUM | Controllers, BackofficeIdentityService | Preservar allowlists, payloads extra y tests | CODE 2.9 |
| S13 SQL | Tres selectRaw agregados constantes, advisory SQL constante, Eloquent binds | Inyección en filtros/sort | MEDIUM | ReporteController:28, ManualFuncionController:72,75; IndexQueryRequest | Clasificar SQL, comprobar allowlists y TEST sintético | CODE 2.9 |
| S14 XSS | JSX escapa; sin sinks HTML runtime en búsqueda inicial | Payloads en texto/nombre/observación | MEDIUM | src, Blade PDF | Tests JSX, CSP sin eval | CODE 2.9 |
| S15 Archivos | Local privado, UUID, MIME, 5MB, SHA256, attachment | Archivos inválidos/nombres largos, traversal | MEDIUM | PagoSolicitudService, PagoSolicitudController, FileSystem | Validación defensiva y tests; AV futuro | CODE + INFRASTRUCTURE |
| S16 Headers/CSP/cache | Sin política uniforme | Clickjacking, caching datos, XSS defense | MEDIUM | bootstrap, Nginx, Vite | Middleware API y plantilla servidor SPA prod/dev | CODE + CONFIG |
| S17 Errors/debug | API 500 genérico; APP_DEBUG local true | Misconfiguración prod, logging SQL sensible | HIGH | bootstrap/app.php, Compose | Fail closed prod, check-config, logging filtrado | CODE + CONFIG |
| S18 Logging/auditoría | Audit negocio, logs técnicos | Secretos, log injection, retención indefinida | MEDIUM | RegistrarAuditoriaAction, logging.php | Canal security con campos allowlist | CODE + INSTITUTIONAL |
| S19 Secretos/seeders | .env ignorado; fixtures/dev conocidos | Credenciales demo promovidas a prod | HIGH | .gitignore, seeders, Compose, scripts | Guardas prod, placeholders, scan con metadatos | CODE + CONFIG |
| S20 Validación pública | Nombre/cargo, sin documento/salario; dos alias sin throttle | Scraping/flood, metadatos laborales | MEDIUM | ValidacionPublicaController, Resource | Throttle y TTL, minimización decisión institucional | CODE + INSTITUTIONAL |
| S21 Proxy/DB/TLS | DB CT postgres, HTTP loopback | Privilegio excesivo fuera de dev | HIGH | docker-compose.certificate-types.yml | Proxy allowlist, least privilege, TLS | INFRASTRUCTURE |
| S22 Dependencias | composer.lock, npm lock presentes | Supply chain/advisories | MEDIUM | manifests | Auditorías antes/final, evaluar lib MFA | CODE 2.9 |
| S23 Command/SSRF/serialization/redirect | Sin sinks inseguros en app runtime en búsqueda inicial | Superficie potencial | INFO | rg app/src; Dompdf remote disabled | Documentar alcance y revalidar | CODE 2.9 |
| S24 Límites HTTP | Nginx 10m, php locations amplios | Payloads/TRACE/archivos PHP auxiliares | MEDIUM | docker/nginx/default.conf | 6m uploads, JSON 64KB, index.php únicamente, métodos | CODE + CONFIG |
| S25 Manual/Pagos/PDF | Fases 2.7/2.8/3A congeladas | Regresión al incorporar seguridad | HIGH | historial y suites | Full regression + conteos CT 344/3115/2326 | CODE 2.9 |

No hay CRITICAL probado en este baseline. Los riesgos HIGH de producción de infraestructura son recomendaciones, no evidencia de un despliegue institucional existente. El inventario por ruta previo se conserva en `tmp/security-phase29/routes-before.json`; matriz detallada será versionada junto al informe. `/up` y `/` son públicos; `/api/v1/auth/login` y dos validaciones públicas también. Todas las rutas de negocio tienen auth:sanctum, identity.current, password.changed; autoservicio añade context.funcionario. Policies/permisos en controllers y FormRequests hacen la autorización final. No Route::any/match/debug/test HTTP activo encontrado. Rutas históricas responden 410 sin mutación; deben mantener auth y límites.

Decisión MFA previa a instalación: Fortify incluye rutas/pipelines de autenticación que reemplazarían parte del flujo estable. Usar su dependencia TOTP `pragmarx/google2fa` directamente, biblioteca agnóstica RFC 6238, rama 9.x activa, MIT, PHP 8.3 compatible. Validar resolución Composer, transitivas y audit antes de implementarla. Mostrar secreto manual de setup en memoria; no se necesita imagen QR persistente ni servicio externo. No escribir TOTP casero.
