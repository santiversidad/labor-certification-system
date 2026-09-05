# Entorno local con Docker

Este repositorio contiene dos aplicaciones: una API Laravel en `backend/laravel-app` y un cliente React/Vite en `frontend/react-app`. El entorno de desarrollo usa PHP-FPM, Nginx, PostgreSQL y Node.js exclusivamente dentro de contenedores.

## Requisitos

- Docker Desktop con Docker Compose moderno (`docker compose`).
- Git.

No es necesario instalar PHP, Composer, Laravel, Node.js, npm ni PostgreSQL en Windows.

## Servicios y puertos

| Servicio Compose | Función | Puerto interno | Puerto del host |
| --- | --- | ---: | ---: |
| `app` | PHP 8.4 FPM, Composer y Artisan | 9000 | no expuesto |
| `nginx` | servidor HTTP de Laravel | 80 | 8080 |
| `frontend` | Node.js 24 y Vite | 3000 | 3000 |
| `postgres` | PostgreSQL 16 | 5432 | 5433 |

El puerto PostgreSQL del host es 5433 porque 5432 suele estar ocupado por instalaciones locales. Laravel se conecta dentro de la red Docker a `postgres:5432`.

La timezone de negocio está configurada como `America/Bogota`; se usa para determinar `periodo_mes` en la regla mensual de certificaciones.

## Cómo iniciar

Desde la raíz del repositorio:

```powershell
docker compose up -d --build
docker compose ps
```

En el primer arranque, el contenedor `app` crea `backend/laravel-app/.env` a partir de `.env.example` solo si el archivo no existe y genera `APP_KEY` solo si está vacío. Un `.env` existente se conserva. Las variables de conexión entre contenedores se inyectan desde Compose y no se versionan secretos.

Después del primer arranque, cree el esquema y, si desea los datos de desarrollo, ejecute:

```powershell
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

No use `migrate:fresh`, `db:wipe` ni `docker compose down -v` sobre datos que quiera conservar.

## URL de acceso

- Frontend React: <http://localhost:3000>
- Backend Laravel: <http://localhost:8080>
- API: <http://localhost:8080/api/v1>
- Healthcheck Laravel: <http://localhost:8080/up>

Los seeders incluyen cuentas exclusivamente de desarrollo:

| Rol | Cédula | Contraseña |
| --- | --- | --- |
| Administrador | `000000001` | `password` |
| Secretario | `000000002` | `password` |
| Funcionario | `000000003` | `password` |

Estas credenciales no deben existir en producción. Las cuentas creadas desde Administración usan la cédula como contraseña inicial cifrada y el backend obliga a cambiarla antes de permitir cualquier función distinta de consultar sesión, cambiar contraseña o cerrar sesión.

## Flujo de autoservicio de Fase C

- Con `requiere_pago_certificado=false`, un funcionario activo expide y descarga inmediatamente su certificado; validación, snapshot, PDF y persistencia se coordinan en una transacción.
- Con `requiere_pago_certificado=true`, se crea una orden pendiente y no se genera ningún certificado. La continuación exige un proveedor real y un webhook autenticado/idempotente; no existe pasarela simulada ni carga manual de comprobante para el flujo nuevo.
- La cuota es una certificación CON salario y una SIN salario por funcionario y mes calendario.
- Las rutas manuales de aprobación/rechazo de solicitudes nuevas están retiradas. Secretaría no participa en el camino crítico automático.
- El parámetro de pago se modifica desde Administración y el cambio queda auditado.

No deben reutilizarse esas credenciales en otro entorno.

## Cómo detener

```powershell
docker compose down
```

Este comando conserva los volúmenes. Para iniciar nuevamente:

```powershell
docker compose up -d
```

## Ver logs

Todos los servicios:

```powershell
docker compose logs -f
```

Un servicio concreto:

```powershell
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f frontend
docker compose logs -f postgres
```

El log de Laravel también está disponible dentro del contenedor:

```powershell
docker compose exec app sh -lc "tail -f storage/logs/laravel.log"
```

## Ejecutar Artisan

```powershell
docker compose exec app php artisan --version
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate:status
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan permission:cache-reset
```

## Ejecutar Composer

Las dependencias se instalan desde `composer.lock` al construir la imagen. Para ejecutar Composer manualmente:

```powershell
docker compose exec app composer install
docker compose exec app composer validate --strict --no-check-publish
docker compose exec app composer check-platform-reqs
docker compose exec app composer audit
docker compose exec app ./vendor/bin/pint --test
```

No ejecute `composer update` durante la preparación normal del entorno.

## Ejecutar npm

El cliente React usa el `package-lock.json` existente y la imagen instala dependencias con `npm ci`:

```powershell
docker compose exec frontend npm ci
docker compose exec frontend npm run build
docker compose exec frontend npm run lint
docker compose exec frontend npm audit
docker compose exec frontend npm test -- --run
```

El pequeño frontend Blade de Laravel posee `package-lock.json`; sus recursos se construyen reproduciblemente en la etapa Node de la imagen PHP con `npm ci` y `npm run build`. La imagen PHP final no contiene Node. Para comprobar el build y auditar ese árbol:

```powershell
docker compose build app
docker run --rm -v "${PWD}/backend/laravel-app/package.json:/app/package.json:ro" -v "${PWD}/backend/laravel-app/package-lock.json:/app/package-lock.json:ro" -w /app node:24-alpine npm audit
```

## Base de datos

- Motor: PostgreSQL 16.
- Host desde Laravel: `postgres`.
- Puerto interno: `5432`.
- Host desde Windows: `localhost`.
- Puerto desde Windows: `5433`.
- Base principal: `scl_db`.
- Base de pruebas: `scl_db_test`.
- Usuario local: `postgres`.
- Persistencia: volumen Docker `postgres_data`.

Estado y migraciones:

```powershell
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Abrir `psql`:

```powershell
docker compose exec postgres psql -U postgres -d scl_db
```

`database/init.sql` únicamente crea `scl_db_test`. PostgreSQL lo ejecuta al crear un volumen nuevo; no contiene ni importa datos de negocio.

Para ejecutar las pruebas contra la base de pruebas sin contaminar la caché local de permisos:

```powershell
docker compose exec -e APP_ENV=testing -e CACHE_STORE=array -e SESSION_DRIVER=array -e DB_HOST=postgres -e DB_DATABASE=scl_db_test app php artisan test
```

## Variables y almacenamiento

Compose configura localmente `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL`, PostgreSQL, sesiones y caché en archivos, cola síncrona, correo a log y disco `local`. Redis no se usa. Tampoco se requieren contenedores separados para workers, scheduler o WebSockets según el código actual.

Los archivos de soportes y certificados se guardan en el disco privado local de Laravel. La aplicación no consume el disco público en los flujos revisados, por lo que `php artisan storage:link` no es requisito actual.

En Docker Desktop para Windows los bind mounts pueden mostrarse con modo `777` dentro del contenedor aunque el entrypoint no aplique ese permiso. El arranque crea solo las carpetas requeridas y asigna su propiedad a `www-data`; no ejecuta `chmod -R 777`.

## Cambiar puertos

Los puertos se pueden sobrescribir sin editar Compose:

```powershell
$env:FRONTEND_PORT=3001
$env:BACKEND_PORT=8081
$env:POSTGRES_HOST_PORT=5434
docker compose up -d
```

Al cambiar los puertos, reinicie los servicios para que Vite, CORS y `APP_URL` reciban los nuevos valores.

## Estado conocido de esta revisión

- El entorno local y la conexión React/Vite → Laravel → PostgreSQL funcionan. Los cuatro servicios tienen healthcheck.
- El build de los recursos Blade del backend termina correctamente.
- `npm run build` y `npm run lint` terminan correctamente. Vite en desarrollo arranca y renderiza.
- La suite backend termina con 82 pruebas correctas y 0 fallidas (398 aserciones) usando `scl_db_test`; Vitest termina con 11 pruebas React correctas. Incluye autoservicio de Fase C, regla mensual, cambio obligatorio, IDOR, soportes privados/lifecycle, Manual, snapshot, hash, contratos, rate limit y errores API.
- `composer audit` y ambos árboles npm reportan 0 advisories/vulnerabilidades al corte de Fase C.
- La auditoría integral posterior detectó brechas funcionales y de seguridad que impiden considerar el sistema listo para producción; están documentadas en `AUDIT_REPORT.md`.
- El detalle funcional, el antes/después y los límites de producción están documentados en `PHASE_C_REPORT.md`.
