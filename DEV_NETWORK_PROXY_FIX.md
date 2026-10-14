# Incidente del proxy de desarrollo CT

Fecha local: 2026-10-04 (America/Bogota). Alcance: entorno/configuración; sin cambios funcionales ni inicio de Fase 3B.2.

## Síntoma y causa demostrada

El navegador mostraba `Network Error`; las rutas `/api/*` y `/sanctum/*` se atendían como HTML de Vite en lugar de Laravel.

El diff manual de Compose, además de cambiar la URL de la API e incorporar el target del proxy, había eliminado `ports` y los dos mounts de `frontend`. Al recrearlo, Docker utilizó los archivos antiguos de `scl-ct-frontend:prepared`: `/app/vite.config.ts` tenía solo plugins/tests, sin `server.proxy`. Tampoco publicó el puerto 3200; al comenzar este diagnóstico la conexión desde el host ya fallaba.

`docker compose config`, `.Config.Env`, `env`, Node y `/proc/19/environ` coincidían: `/api/v1` y `http://nginx`. Vite ejecutaba `node /app/node_modules/.bin/vite --host 0.0.0.0 --port 3000`, con cwd `/app`. `resolveConfig` identificó `/app/vite.config.ts` y `proxy: undefined`. El problema era el código cargado, no variables perdidas ni un defecto de Vite 8.2.2. Las etiquetas de Docker confirmaron el proyecto `labor-certification-certificate-types` y el archivo Compose del repositorio canónico; no hubo override, `env_file`, profile o interpolación del host que cambiara las variables de frontend.

E2E funcionaba porque `e2e-web` conservaba el bind mount del repositorio y su target `http://e2e-api:8280`; cargaba la configuración actual con proxy. Existe `vite.config-camilo_ortega.ts` como archivo alternativo, pero el comando activo no lo selecciona. No hay `vite.config.js`, `.mjs` o `.cjs` compitiendo.

## Corrección mínima

Se conservó el cambio manual de variables y se restauraron los puertos y mounts originales:

```yaml
frontend:
  environment:
    VITE_API_BASE_URL: /api/v1
    VITE_API_PROXY_TARGET: http://nginx
    VITE_APP_NAME: Sistema de Certificaciones Laborales Villavicencio
    CHOKIDAR_USEPOLLING: "true"
  ports:
    - "127.0.0.1:3200:3000"
  volumes:
    - ./frontend/react-app:/app
    - ct_frontend_node_modules:/app/node_modules
```

Solo se recreó `frontend`, con `--no-deps`; no se reconstruyó la imagen ni se modificó `vite.config.ts`. El SHA-256 del archivo en el contenedor y el repositorio coincidió: `d4c7996df5adf614ab9234cb593f6c591e0e53339c652002b7fa6dc4686954bc`. La configuración efectiva ahora contiene `/api` y `/sanctum` con target `http://nginx`, preservando `changeOrigin: false` y los headers existentes. El cliente usa el mismo origen `http://localhost:3200`.

## Arranque y entorno canónico

Repositorio: `C:\Users\mondr\source\labor-certification-recovery`. PostgreSQL y la red `labor-certification-recovery_default` deben existir previamente.

```powershell
Set-Location 'C:\Users\mondr\source\labor-certification-recovery'
docker compose -f docker-compose.certificate-types.yml up -d app nginx frontend
docker compose -f docker-compose.certificate-types.yml ps
# Tras cambiar variables de frontend:
docker compose -f docker-compose.certificate-types.yml up -d --no-deps --force-recreate frontend
```

| Componente diario | Contenedor | Puerto del host |
| --- | --- | --- |
| React/Vite | `scl_ct_frontend` | `http://localhost:3200` |
| API/Nginx | `scl_ct_nginx` | `http://localhost:8280` |
| Laravel/PHP-FPM | `scl_ct_app` | interno 9000 |
| PostgreSQL existente | `scl_recovery_postgres` | `localhost:5434` |

No levantar `scl_app`, `scl_frontend`, `scl_nginx`, `scl_postgres`, `scl_recovery_app`, `scl_recovery_frontend` o `scl_recovery_nginx`. No ejecutar Compose sin `-f docker-compose.certificate-types.yml`. Los servicios E2E son temporales; al terminar se retiran exclusivamente esos servicios, sin volúmenes:

```powershell
docker compose -f docker-compose.certificate-types.yml --profile e2e rm -s -f e2e-api e2e-web e2e-production
```

No usar `down -v`, `volume prune`, `system prune --volumes` ni migraciones destructivas. La preparación utilizada fue `e2e:prepare-autoservice`, protegida por entorno testing y base `scl_certificate_types_e2e`; no eliminó registros ni ejecutó migraciones.

## Diagnóstico reproducible

```powershell
docker compose -f docker-compose.certificate-types.yml config
docker inspect scl_ct_frontend --format '{{range .Config.Env}}{{println .}}{{end}}'
docker exec scl_ct_frontend sh -lc 'env | sort'
docker exec scl_ct_frontend node -p 'JSON.stringify({api:process.env.VITE_API_BASE_URL,proxy:process.env.VITE_API_PROXY_TARGET,app:process.env.VITE_APP_NAME,cwd:process.cwd()})'
docker exec scl_ct_frontend pwd
docker exec scl_ct_frontend sh -lc 'ls -la /app && sed -n "1,120p" /app/vite.config.ts'
docker exec scl_ct_frontend ps -ef
docker inspect scl_ct_frontend --format '{{range .Mounts}}{{println .Source .Destination}}{{end}}'
docker exec scl_ct_frontend getent hosts nginx
docker exec scl_ct_frontend node --input-type=module -e 'const {resolveConfig}=await import("vite");const c=await resolveConfig({},"serve");console.log({file:c.configFile,proxy:c.server.proxy})'
```

Las salidas relevantes, limitadas a frontend y sin valores de cookies o credenciales, están en `output/dev-network-proxy-fix/` (ignorado por Git). No compartir un volcado general de variables/configuración que incluya secretos de otros servicios.

## Comprobaciones HTTP y navegador

```powershell
curl.exe -sS -o NUL -w 'root: %{http_code} %{content_type}\n' http://localhost:3200/
curl.exe -sS -i http://localhost:3200/api/v1/auth/me
# Redactar valores de cookies antes de guardar o compartir:
curl.exe -sS -i http://localhost:3200/sanctum/csrf-cookie | ForEach-Object {
  if ($_ -match '^Set-Cookie:\s*([^=]+)=') { 'Set-Cookie: ' + $Matches[1] + '=[REDACTED]' } else { $_ }
}
```

Resultados esperados y comprobados: `/` → 200 HTML React; `/api/v1/auth/me` sin sesión → 401 `application/json`, `success: false`, `code: UNAUTHENTICATED`; `/sanctum/csrf-cookie` → 204 con `XSRF-TOKEN` y `sistema-de-certificaciones-laborales-session`. La cookie de sesión sigue siendo HttpOnly y SameSite=Lax. Desde frontend, HTTP real a `http://nginx` devuelve los mismos 401/204. Para inspeccionar estas respuestas desde Node se utilizó `fetch`, disponible en la imagen, sin instalar herramientas.

La comprobación real en Edge/Playwright de `http://localhost:3200` pasó: CSRF 204, POST login 200 (`AUTHENTICATED`), cookie de sesión HttpOnly/SameSite=Lax, GET `/auth/me` desde el navegador 200 JSON, dashboard `/app/inicio` y logout 200. No hubo requests fallidos ni `Network Error`. Se usaron las credenciales existentes de una cuenta sintética, mantenidas solo en memoria, sin crear cuentas ni modificar contraseñas. No se omite cambio obligatorio ni MFA cuando la cuenta los requiere. Un `APIRequestContext` separado sin Origin/Referer no reproduce el contexto Sanctum del navegador; para esta comprobación se utilizó `fetch` dentro de la página.

## Regresión y límites

Frontend: 78 tests / 20 archivos aprobados (`docker exec scl_ct_frontend npm test`). ESLint aprobado (`npm run lint`); TypeScript y Vite build aprobados (`npm run build`, que ejecuta `npm run typecheck`) en Node 24 del contenedor CT. Login real aprobado. E2E completa: **31 pruebas aprobadas en 8,5 minutos, sin reintentos**, ejecutando `docker compose -f docker-compose.certificate-types.yml --profile e2e run --rm --no-deps e2e-runner`. Incluyó autenticación, MFA, cambio obligatorio, CSRF, revocación, CSP de producción, pagos, identidades, autoservicio, PDF y assets.

Los servicios temporales `e2e-api`, `e2e-web` y `e2e-production` se retiraron al finalizar; el runner se eliminó mediante `--rm`. Se conservaron bases y volúmenes E2E. Las capturas regeneradas se copiaron a `output/dev-network-proxy-fix/generated-evidence/` y se restauraron únicamente sus archivos versionados al estado previo; no forman parte de este commit. Los únicos archivos modificados son Compose y documentación. Una consulta SQL de solo lectura confirmó las 344 fichas y 3.115 funciones del Manual de desarrollo.

Se mantienen CSRF, Sanctum stateful, CORS exacto, cookies HttpOnly, MFA, `auth_version`, cambio obligatorio, rate limits, CSP, headers y revocación. DB de desarrollo, pagos, Manual, PDF, snapshot, firma, QR y oficialización no recibieron cambios funcionales. PDF continúa `BORRADOR — SIN VALIDEZ OFICIAL`; Fase 3B.2 no iniciada.
