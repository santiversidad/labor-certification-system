# Preparación de producción — fase 2.9

Esta checklist requiere ejecución por infraestructura y seguridad institucional. No se ha desplegado ni probado TLS institucional. No contiene secretos o dominios inventados.

| Tipo | Requisito / comprobación | Estado local |
|---|---|---|
| CODE | Cookies, MFA, CSRF, policies, revocación, límites, errores y upload | Implementado; evidencia en informe 2.9 |
| CONFIG | APP_ENV=production, APP_DEBUG=false, APP_URL HTTPS real | Checker bloquea valores inseguros |
| CONFIG | APP_KEY custodiada, no autogenerada ni impresa en producción | Entry point no genera key en prod; no se rotó key actual |
| CONFIG | SESSION_DRIVER=file/database/redis, encrypt=true, lifetime≤30, HttpOnly=true, Secure=true, SameSite=Lax | Defaults + checker; nunca cookie/array en prod |
| CONFIG | SESSION_DOMAIN según arquitectura first-party; preferir host-only y mismo origen | Definir dominio real, nunca `.localhost` en prod |
| CONFIG | SANCTUM_STATEFUL_DOMAINS host[:port] exactos, CORS_ALLOWED_ORIGINS URLs HTTPS exactas, supports_credentials=true | Checker rechaza wildcard, localhost y desacople CORS/stateful |
| CONFIG | TRUSTED_PROXIES lista de IP/CIDR del proxy real | Lista vacía por defecto, sin confiar en `*` |
| CONFIG | Argon2id m=19456 KiB, t=2, p=1; costos acotados | Benchmark local; medir capacidad institucional |
| CONFIG | SECURITY_API_TOKENS_ENABLED=false | Checker exige false; integraciones nuevas requieren decisión |
| CONFIG | Caché y locks compartidos en varias réplicas | Redis/DB común; bloqueo Laravel sesión activo, ventana lock120/wait15 s |
| INFRASTRUCTURE | TLS válido, redirect HTTP→HTTPS, HSTS en terminador HTTPS | HSTS middleware solo prod HTTPS; sin HSTS local HTTP |
| INFRASTRUCTURE | Nginx sirve solo dist SPA y public Laravel, nunca raíz repo | Plantilla SPA same-origin y CT root public verificados |
| INFRASTRUCTURE | SPA CSP y API con headers; no unsafe-eval ni script inline; Vite no expuesto en prod | Build real probado, style inline por estilos dinámicos existentes |
| INFRASTRUCTURE | PHP display_errors/expose_php off, upload5M/post6M, un único index.php | ini y config Nginx en repo; image reconstruida |
| INFRASTRUCTURE | DB rol aplicación sin superuser/createdb/createrole; migrador separado | CT postgres amplio solo local; no alterado |
| INFRASTRUCTURE | DB red privada, TLS según topología, permisos DML mínimos de tablas/secuencias necesarias | No configurar IPs/credenciales desconocidas |
| INFRASTRUCTURE | Storage privado, sin public/storage symlink; cuotas, permisos, backups cifrados/restaurados | Checker verifica raíz/link; backup/restore institucional pendiente |
| INFRASTRUCTURE | Logs 0600, acceso por rol, centralización sin URI/cookies/secrets, alertas de eventos | Security daily 30 días; Nginx access log off evita capability URLs |
| INSTITUTIONAL | D01 activación funcionario y canal de entrega | HIGH pendiente; cédula=password continúa predecible |
| INSTITUTIONAL | Dos admins operativos, identidad verificada antes de MFA reset, recovery custodiado | D02 pendiente; no recuperación SMS/email inventada |
| INSTITUTIONAL | Retención laboral/recibos/logs y mínimo de validación pública | D03/D04 pendientes |
| CODE / SUPPLY CHAIN | Resolver o aceptar explícitamente braces dev y compilar solo fuentes confiables | D08 abierto; runtime npm sin avisos |

Orden operativo: definir secretos/config; aplicar migraciones con rol migrador; revisar cuentas reales y deshabilitar fixtures sin borrar historia; ejecutar `php artisan security:check-config --accounts --no-interaction` con configuración final; preparar caches de configuración; iniciar PHP (entrypoint repite checker); publicar únicamente build validado detrás de TLS y proxy autorizado. El checker no concede permisos DB, no crea TLS y no constituye una prueba de infraestructura. En despliegue virgen la base debe migrarse antes del startup `--accounts`.

Antes de abrir tráfico: probar cookies/CSRF/MFA con origen definitivo, GET /login de SPA y API /api/v1/auth/login, CORS preflight permitido/rechazado, session locks compartidos, expiración, revocación, headers en errores/archivos, acceso denegado a .env/.git/vendor/tests/database/output/Markdown/composer/source maps. No enviar secretos en VITE_* ni logs CI. No montar directorio completo del proyecto como root web. El rol migrador puede tener DDL; el rol de aplicación no necesita crear roles/bases.

Rotación APP_KEY: preparar copia custodiada y plan de recifrado TOTP/session/backups; probar restauración antes de retirar una key. Nunca rotar automáticamente para cerrar un hallazgo. Firma/QR/oficialización siguen fuera de esta fase.
