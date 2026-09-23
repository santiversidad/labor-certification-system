# Baseline de desarrollo

- Baseline histórico validado: `c74fcd1670d7107e820861b8d5add196bbc67ff2`.
- Baseline local actualizado tras diagnóstico de sesiones: `79720315bb36f8a00ed9d42718173d642e75bd1a`.
- Rama de resguardo: `recovery/baseline-pre-tipo-certificado`.
- Tag: `baseline-pre-tipo-certificado-20260921`.
- Verificación histórica: backend **140 tests / 739 aserciones**; frontend **25 tests**; E2E **2/2**.
- Verificación del fix: backend **142 tests / 761 aserciones**; frontend **26 tests**, typecheck, build y ESLint verdes; E2E **3/3**.
- Datos del Manual publicado: **344 fichas, 3.115 funciones, 2.326 conocimientos**. Admin: **39 permisos**.
- Certificados históricos IDs **31, 32 y 33**: `REGISTRO_RECUPERADO_PDF_BINARIO_AUSENTE`. Sus registros y hashes se conservan; no regenerar ni eliminar los binarios o registros para aparentar coincidencia.

**Regla:** el siguiente desarrollo de `sencillo | funciones` se hará únicamente en `feat/certificate-types`, que parte del baseline local actualizado `7972031`. No aplicar automáticamente los parches de `tipo-certificado-incompleto` ni su migración. El tag y la rama remota del baseline histórico permanecen en `c74fcd1`; no se ha hecho push del fix.

Las bases `scl_recovery_db`, `scl_recovery_test` y `scl_recovery_e2e` quedan como baseline y no deben recibir migraciones ni datos de la nueva fase. Para el futuro trabajo se crearon, vacías y sin migrar, `scl_certificate_types_dev`, `scl_certificate_types_test` y `scl_certificate_types_e2e`. Configurar explícitamente cada proceso y prueba para apuntar a ellas antes de iniciar cambios funcionales; el Compose recovery actual aún apunta a las bases baseline.
