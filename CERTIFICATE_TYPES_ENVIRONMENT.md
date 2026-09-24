# Entorno aislado para Certificate Types

## Estado

El entorno CT de `feat/certificate-types` está activo. La base de desarrollo usa el Manual publicado ID 10 y los tipos canónicos `sencillo | funciones`. La preparación inicial de este entorno precedió al bootstrap del Manual descrito abajo.

El archivo de Compose es `docker-compose.certificate-types.yml`. No debe combinarse con `docker-compose.yml` ni utilizar el entorno A.

## Puertos y contenedores

| Componente | Nombre | Acceso |
| --- | --- | --- |
| Frontend CT | `scl_ct_frontend` | `http://localhost:3200` |
| API CT | `scl_ct_nginx` / `scl_ct_app` | `http://localhost:8280` |
| Pruebas backend CT | `scl_ct_test` | perfil interno `test` |
| E2E CT | `scl_ct_e2e_*` | perfil interno `e2e` |

Los puertos no colisionan con A (`3000` / `8080`) ni con recovery (`3100` / `8180`).

## Aislamiento de datos y runtime

Se reutiliza únicamente el servidor PostgreSQL de recovery, accesible como `scl_recovery_postgres` en la red externa `labor-certification-recovery_default`. Cada proceso CT selecciona de forma explícita una base lógica reservada:

- desarrollo: `scl_certificate_types_dev`;
- pruebas backend: `scl_certificate_types_test`;
- E2E: `scl_certificate_types_e2e`.

No se apunta a `scl_recovery_db`, `scl_recovery_test`, `scl_recovery_e2e`, `scl_db`, `scl_db_test` ni `scl_e2e_test`.

Los volúmenes de vendor, build, `node_modules` y storage tienen nombres `scl_ct_*`. Desarrollo, pruebas backend y E2E no comparten storage de aplicación con baseline/recovery; las sesiones y caches basadas en archivos quedan dentro del storage CT correspondiente.

## Configuración de Compose

Antes de iniciar CT, el contenedor `scl_recovery_postgres` y su red deben existir. La configuración puede validarse sin levantar servicios:

```powershell
docker compose -f docker-compose.certificate-types.yml config
```

Los perfiles `test` y `e2e` usan sus bases aisladas; sus fixtures del Manual son sintéticos y mínimos.

El entorno A en `localhost:3000` / `localhost:8080` está congelado como evidencia histórica. El conjunto validado de recovery sirvió como referencia de lectura para comprobar el contenido CT; el bootstrap de CT usa el JSON versionado.

## Instalación actual de CT: esquema y datos de desarrollo

Para un **schema fresh install**, `migrate` crea tablas y restricciones, pero deja vacío el Manual institucional. `DatabaseSeeder` carga permisos, cuentas de desarrollo, un cargo y un funcionario sintético; no importa las 344 fichas.

En una base `scl_certificate_types_dev` nueva, con el contenedor CT apuntando expresamente a esa base:

```powershell
docker compose -f docker-compose.certificate-types.yml exec app php artisan migrate --force
docker compose -f docker-compose.certificate-types.yml exec app php artisan db:seed --force
docker compose -f docker-compose.certificate-types.yml exec app php artisan manual:bootstrap-development --actor-id=1
```

El último comando exige entorno `local`, base `scl_certificate_types_dev`, administrador CT existente, tablas del Manual vacías y el SHA-256 conocido del JSON versionado. Ejecuta primero el preflight del importador, importa dentro de una transacción y publica mediante el servicio de dominio. Conserva el ID de versión 10 y rechaza una segunda carga. Si la base ya tiene datos, inspección y resolución de conflictos preceden cualquier intento; no usar `migrate:fresh` en CT desarrollo.

Los test backend y E2E siguen usando `scl_certificate_types_test` y `scl_certificate_types_e2e` con fixtures sintéticos mínimos. El Manual completo corresponde a CT desarrollo y su prueba manual. Véase [CT_MANUAL_BOOTSTRAP_REPORT.md](CT_MANUAL_BOOTSTRAP_REPORT.md) para conteos, trazabilidad y resultados.
