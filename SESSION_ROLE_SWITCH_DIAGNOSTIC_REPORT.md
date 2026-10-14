# Diagnóstico del incidente de sesión y expedición (2026-09-23)

## Alcance y procedencia

- Trabajo y pruebas funcionales: `C:\Users\mondr\source\labor-certification-recovery`, partiendo exactamente de `c74fcd1670d7107e820861b8d5add196bbc67ff2`.
- Corrección y regresiones: rama aislada `fix/session-role-switch`. Durante el diagnóstico las ramas `recovery/baseline-pre-tipo-certificado` y `feat/certificate-types` permanecieron intactas; tras validar el fix se avanzaron localmente según el procedimiento indicado, sin push.
- Datos de prueba: exclusivamente `scl_recovery_test` y `scl_recovery_e2e`, con identidades ficticias. No se migraron las bases `scl_certificate_types_*`.
- Las consultas al entorno ordinario A fueron de solo lectura (access logs, código y transacciones SQL `BEGIN READ ONLY`). No se usó A para reproducir ni validar el baseline.

## Entorno de la prueba manual

El access log de `scl_nginx` registra el navegador con referente `http://localhost:3000/`: `POST /api/v1/funcionarios` 201 a las 19:17:09 UTC, inicio del funcionario a las 19:18:11, dos `POST /api/v1/solicitudes` 503 a las 19:21:19 y 19:21:35, y nuevos inicios a las 19:22:44 y 19:23:03. No hay secuencia correspondiente en `scl_recovery_nginx`. La prueba manual fue en A, no en recovery.

La auditoría de A confirma: usuario admin 172 creó Funcionario 68 / User 178 a las 19:17:09; el funcionario 178 cambió contraseña y realizó ambas solicitudes fallidas. Tras su logout, los siguientes logins exitosos fueron del User 174, ya `funcionario`, **no** del admin 172. El admin 172 aún conserva rol `admin`, no tiene relación Funcionario; actualmente A le atribuye 36 permisos, otra señal de que A no representa el baseline validado. No se conservó el documento enviado en esos últimos formularios ni un HAR con cuerpos de `/auth/login` y `/auth/me`; por tanto no se puede distinguir entre credenciales ingresadas distintas de las previstas y otros errores de la interacción manual. La auditoría sí descarta que esos logins autenticaran al admin 172.

## Fallo de certificado en A

- Solicitudes fallidas: tipo `funciones`, HTTP 503; la auditoría registró `CERTIFICATE_GENERATION_FAILED` para Funcionario 68.
- Asignación del funcionario: `FuncionarioCargo` 32, `FuncionarioCargoManualFicha` 4, ficha **MF-0087**, versión publicada `Decreto 1000-24/015 de 2023`, 8 funciones, área funcional y propósito no vacíos. No fue MF-0167 ni falta evidente de ficha. La modalidad de A ya era `funciones`; un rango salarial no intervino en esas solicitudes.
- Defecto de código verificable en A: `GenerarCertificadoService::generar()` llama `PdfBasicoService::generarDesdeTexto($texto)`, pero el `PdfBasicoService` allí solo implementa `generarCertificado(...)`. Esa llamada no puede completarse y el controlador captura el error técnico como 503. No hay stack trace del intento; por ello la atribución de esos dos 503 a esta incompatibilidad es una inferencia de alta confianza, no una traza directa de la excepción.
- El cuerpo HTTP de las solicitudes manuales no se conservó. El controlador de A construye para esa ruta: `{"success":false,"message":"No fue posible completar la expedición automática. El cupo no fue consumido; intente nuevamente.","code":"CERTIFICATE_GENERATION_FAILED"}`. No se debe presentar este cuerpo inferido como captura de red.

## Reproducción real en recovery

Flujo Playwright completo, sin mocks: admin login → alta de funcionario ficticio con cargo y ficha compatibles → intento de documento duplicado → logout → primer login funcionario → cambio obligatorio de contraseña → solicitud SIN salario → descarga PDF → logout → admin login → `/auth/me` → `/admin/dashboard`.

| Comprobación | Resultado |
| --- | --- |
| Admin antes y después del alta | User ID 1, rol `admin`, 39 permisos; no relación Funcionario |
| Documento admin duplicado | `POST /funcionarios` 422; error `numero_documento`; admin sin alteración |
| Funcionario nuevo | User ID 6, Funcionario ID 5, `funcionarios.user_id=6`, distinto de admin ID 1 |
| Cargo y norma | `FuncionarioCargo` 5, cargo E2E/01, `FuncionarioCargoManualFicha` 5, ficha E2E-001, versión E2E-1 publicada, área/propósito presentes, 1 función |
| Rango salarial | Existe rango E2E/01 en el fixture, pero la solicitud usó `requiere_salario=false`; no intervino |
| Login y `/auth/me` funcionario | Ambos 200, User ID 6, documento ficticio creado, rol `funcionario`, relación Funcionario ID 5 |
| Logout funcionario | Sesión local ausente; token anterior recibe 401 de `/auth/me` |
| Nueva sesión admin | `/auth/login` 200, `/auth/me` 200, User ID 1, rol `admin`, 39 permisos, dashboard admin |
| Solicitud y PDF | `POST /api/v1/solicitudes` 201; `success=true`, `message="Certificación generada correctamente."`, `data.resultado="generada"`, sin `code` ni `errors`; descarga empieza con `%PDF-` |

Por tanto, el cambio de perfil admin → funcionario **no se reproduce** en el baseline recovery. El fallo de certificado de A tampoco se reproduce en el baseline.

## Defecto confirmado y corrección aislada

Sí se confirmó un defecto distinto, relacionado con el cambio de sesión: `AuthProvider.logout()` eliminaba solo la query `auth/me` del token vigente; consultas de identidad como `disponibilidad-certificacion` permanecían en TanStack Query. Una prueba unitaria primero falló con datos del usuario anterior todavía presentes. En `fix/session-role-switch`, el logout y la respuesta 401 limpian la caché completa del QueryClient, además de limpiar la sesión local. La prueba luego pasó. No se transforman 403 en logout.

Regresiones nuevas: 2 backend (alta preserva admin; documento admin duplicado se rechaza), 1 frontend (caché se limpia), 1 E2E (secuencia completa de identidad, permisos, documento duplicado, certificado y PDF).

Verificación aislada: PHPUnit **142 tests / 761 assertions**; Vitest **26/26**; Playwright **3/3**; ESLint, typecheck y build sin errores. Tras las suites, el admin del E2E sigue con 39 permisos. Los mounts de app/frontend recovery apuntan únicamente al directorio recovery y sus volúmenes propios.

## Límites y siguiente paso

La rama `fix/session-role-switch` contiene la corrección mínima y las pruebas. La rama baseline **local** avanzó por fast-forward a ese fix; el tag y la rama remota originales siguen apuntando a `c74fcd1`. El único commit documental de `feat/certificate-types` se rebasó sobre el baseline local actualizado; no contiene implementación funcional de `sencillo | funciones`. No hubo push. Reparar A no forma parte de este trabajo: su código y datos ordinarios siguen intactos.
