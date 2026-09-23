# Baseline de desarrollo

## Baseline funcional actual

- Commit validado: `79720315bb36f8a00ed9d42718173d642e75bd1a`.
- Rama publicada: `recovery/baseline-pre-tipo-certificado`.
- Tag anotado: `baseline-pre-tipo-certificado-20260923`.
- Backend: **142 tests / 761 aserciones**.
- Frontend: **26 tests**; typecheck, build y ESLint verdes.
- E2E: **3/3**.
- Admin: **39 permisos**.
- Documento duplicado: respuesta **422**.
- Datos del Manual publicado: **344 fichas, 3.115 funciones y 2.326 conocimientos**.

El fix validado aísla la cache de identidad al cambiar o cerrar sesión: logout y respuestas 401 limpian la identidad cacheada; una respuesta 403 no provoca logout.

## Referencia histórica

El baseline original `c74fcd1670d7107e820861b8d5add196bbc67ff2` se conserva en el tag histórico `baseline-pre-tipo-certificado-20260921`. Ese tag no se mueve ni se reutiliza. Su verificación histórica fue backend **140 tests / 739 aserciones**, frontend **25 tests** y E2E **2/2**.

Los certificados históricos IDs **31, 32 y 33** permanecen como `REGISTRO_RECUPERADO_PDF_BINARIO_AUSENTE`. No regenerar, eliminar ni modificar sus hashes.

## Siguiente desarrollo

El desarrollo de `sencillo | funciones` se hará únicamente en `feat/certificate-types`, que parte de `79720315bb36f8a00ed9d42718173d642e75bd1a`. Todavía no se ha iniciado el cambio funcional. No aplicar automáticamente `tipo-certificado-incompleto`, no hacer cherry-pick de ese change set y no copiar su migración ni su generador PDF.

Las bases `scl_recovery_db`, `scl_recovery_test` y `scl_recovery_e2e` quedan como baseline y no deben recibir migraciones ni datos de la nueva fase. Las bases CT `scl_certificate_types_dev`, `scl_certificate_types_test` y `scl_certificate_types_e2e` permanecen vacías y sin migrar. La configuración aislada prevista se documenta en `CERTIFICATE_TYPES_ENVIRONMENT.md` y se define en `docker-compose.certificate-types.yml`.

## Incidente A

El entorno A en `localhost:3000` / `localhost:8080` no es válido para nuevas pruebas. Queda congelado como evidencia del desarrollo incompleto: no repararlo ni utilizarlo. El entorno recovery validado es la autoridad para el siguiente desarrollo.
