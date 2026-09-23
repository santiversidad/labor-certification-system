# Entorno aislado para Certificate Types

## Estado

La configuración de `feat/certificate-types` está preparada, pero no se levantó ningún servicio ni se ejecutó ninguna migración durante esta fase. Tampoco se inició la implementación funcional de `sencillo | funciones`.

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

## Uso futuro

Antes de iniciar CT, el contenedor `scl_recovery_postgres` y su red deben existir. La configuración puede validarse sin levantar servicios:

```powershell
docker compose -f docker-compose.certificate-types.yml config
```

En una fase posterior, y solo después de autorizar las migraciones de CT, se podrán iniciar los servicios o perfiles correspondientes. Ejecutar el perfil `test` o `e2e` puede activar migraciones desde la suite; no debe hacerse durante la preparación.

El entorno A en `localhost:3000` / `localhost:8080` está congelado como evidencia del desarrollo incompleto. No es válido para nuevas pruebas, no debe repararse y no debe utilizarse. El entorno recovery validado es la autoridad para el siguiente desarrollo.
