# Configuración de autenticación en dos pasos: UX y QR local

Fecha: 2026-10-04 (America/Bogota). Repositorio canónico: `C:\Users\mondr\source\labor-certification-recovery`.
Rama: `feat/institutional-brand-assets`. Base: `e9bfcfc`; precheck `git status` limpio, rama y últimos diez commits inspeccionados antes de editar.

## Alcance y causa

La pantalla anterior pedía añadir una cuenta «TOTP» mediante una clave, sin orientar sobre la aplicación, el escaneo ni el código de confirmación. Ahora explica el propósito y presenta tres pasos con QR como método principal y clave manual accesible, inicialmente colapsada.

No se cambia la arquitectura de autenticación ni la lógica de certificados, pagos, Secretaría, funcionarios, Manual, cuotas, snapshots, PDF, firma o QR documental. Fase 3B.2 no iniciada. Sanctum, CSRF, cookies HttpOnly, auth_version, MFA obligatorio, cifrado, ventana ±1, replay protection, límites, step-up, invalidación y reset administrado conservan su implementación.

## Textos finales

- Encabezado: **Seguridad de la cuenta** / **Configurar autenticación en dos pasos**.
- Explicación: «Agregue una segunda verificación para proteger su cuenta. Además de su contraseña, utilizará un código temporal generado en su celular.»
- Paso 1: **Abra una aplicación de autenticación**. «Puede utilizar Google Authenticator, Microsoft Authenticator, 2FAS u otra aplicación compatible.»
- Paso 2: **Escanee este código QR**. «En su aplicación autenticadora, seleccione agregar una cuenta y escanee el código.»
- Alternativa: **No puedo escanear el código**. «Seleccione la opción para introducir una clave manual en su aplicación y copie la siguiente clave.» **Clave de configuración**, **Copiar clave**, feedback **Clave copiada**. Si falla el portapapeles: «Seleccione y copie la clave con el teclado.»
- Paso 3: **Ingrese el código de 6 dígitos**. «Después de agregar la cuenta, su aplicación mostrará un código temporal. Escríbalo aquí para finalizar la configuración.»
- Campo único: **Código de 6 dígitos**, placeholder `000000`, inputMode numeric, autocomplete one-time-code. Acepta pegado, sin bloquear gestores de contraseñas.
- Acción principal: **Activar autenticación en dos pasos**. En progreso: **Verificando…**.
- Acción secundaria: **Cancelar y cerrar sesión**. En progreso: **Cerrando sesión…**.
- Vacío/formato incompleto: «Ingrese el código de 6 dígitos que aparece en su aplicación.»
- Prueba rechazada (422): «El código no es válido o ya cambió. Revise el código actual de su aplicación e inténtelo nuevamente.»
- Recuperación rechazada: «El código de recuperación no es válido o ya fue utilizado. Revise otro de sus códigos guardados.» Los errores de sesión y límites mantienen sus mensajes genéricos existentes.
- Logout fallido: «No fue posible cerrar la sesión. Inténtelo nuevamente para cancelar la configuración de forma segura.»
- Recovery: «Estos códigos le permiten acceder a su cuenta si pierde el celular o no puede usar su aplicación autenticadora.» Se conserva: «Guarde estos códigos en un lugar seguro. Cada código sirve una sola vez y solo se muestra ahora. No los comparta.» Copia/descarga son acciones explícitas; no hay guardado automático ni correo.

## Dependencia y arquitectura

Se inspeccionaron package.json y package-lock: no había un encoder QR apropiado. Se añade una sola dependencia runtime fijada: **qrcode.react 4.2.0**; ninguna nueva dependencia dev ni transitiva. Renderer `QRCodeSVG`, totalmente local en navegador; sin fetch, imágenes remotas, QR APIs ni servicios de pago.

Revisión de fuentes primarias: [repositorio](https://github.com/zpao/qrcode.react), [release 4.2.0](https://github.com/zpao/qrcode.react/discussions/384) y metadata `npm view`. Última versión publicada revisada: 4.2.0, diciembre de 2024; sin asumir una frecuencia de mantenimiento más reciente. Seleccionada por soporte explícito React 19, TypeScript/ESM, cero dependencias transitivas y encoder Nayuki integrado. Licencia ISC, encoder incluido MIT; licencia distribuida comprobada. Paquete descomprimido: 114.980 bytes. El chunk lazy completo MfaPage del build final mide 22,87 kB / 8,44 kB gzip; esto incluye la pantalla y el encoder, no representa el peso aislado de la librería. Vite 8.2.2 y TypeScript 6 verificados con build.

Backend agrega `otpauth_uri` exclusivamente a POST `/auth/mfa/setup` mediante `Google2FA::getQRCodeUrl` de la misma instancia que verifica. URI y clave manual usan el mismo secreto pendiente: SHA1, seis dígitos, 30 segundos. No se crea un segundo secreto ni se altera la ventana o validación. La respuesta mantiene `private, no-store`.

Audit anterior y posterior: runtime **0 vulnerabilidades** (`npm audit --omit=dev`, exit 0); completo **5 HIGH de desarrollo, 0 CRITICAL** (`npm audit`, exit 1), sin advisories nuevos ni advisory de qrcode.react. Se conserva la cadena braces/chokidar/micromatch/fast-glob/tailwindcss y la decisión abierta D08; no se ejecutó audit fix --force ni migración a Tailwind 4.

## Lifecycle del secreto

Se auditó setup, confirm, recuperación, logout, expiración, cambio de contraseña y reset administrado antes de modificar. El lifecycle seguro ya existía y se conserva:

1. Después de verificar contraseña, el backoffice sigue sin acceso privilegiado y tiene `pending_auth` con usuario, auth_version y hora de inicio.
2. Setup crea una única semilla cifrada bajo `mfa_setup_secret` en sesión backend; todavía no hay secreto activo en el usuario. Repetir setup/recargar devuelve esa misma semilla dentro del plazo vigente.
3. La autenticación pendiente vence a los 10 minutos desde su inicio, sin renovar el plazo al recargar. Una petición con contexto vencido invalida la sesión y descarta la semilla. Si se cierra la pestaña sin logout, la semilla no es confirmable indefinidamente: rigen ese plazo, auth_version y la expiración de sesión. Un login nuevo invalida la sesión anterior y requiere nueva semilla.
4. Cancelar llama al logout existente, que invalida la sesión completa y regenera CSRF. Nuevo login/setup crea una semilla distinta. Si la petición falla, frontend no simula la cancelación: mantiene la pantalla y permite reintentar.
5. Confirmar exige prueba válida. Guarda secreto activo con cast cifrado, registra paso antirreplay, genera ocho recovery codes con hashes y elimina `mfa_setup_secret` al completar la autenticación.
6. Cancelar un login con MFA ya activo solo cierra la sesión; no rota secreto activo ni recovery codes. Reset administrado mantiene autorización, step-up, protección del último admin y revocación de sesiones.

Frontend mantiene clave/URI solo en estado y una promesa del componente montado. React StrictMode reutiliza la petición setup para evitar concurrencia por replay de efectos. Confirmación/logout exitosos descartan ambas referencias; desmontar libera el material. No hay localStorage/sessionStorage, URLs de navegador, query parameters, console, analytics o telemetry con material de enrollment. Copiar al portapapeles requiere una acción explícita. Colapsar el fallback evita exposición visual; no se añadió un enmascarado adicional que dificulte copiar una clave íntegra.

## Accesibilidad y evidencia

Tres pasos con encabezados, labels reales, un solo input, errores asociados y foco al campo inválido. QR con nombre accesible y alternativa manual; SVG decorativo dentro del contexto accesible. `details/summary` admite teclado y foco visible. QR cuadrado de hasta 220 px, quiet zone de cuatro módulos, negro sobre blanco, sin desbordamiento horizontal en 360/768/1440.

`e2e/mfa-enrollment-visual.spec.ts` intercepta me/setup sin login ni enrollment real y utiliza una semilla deliberadamente inválida en Base32, exclusiva para evidencia visual. Capturas `evidence/mfa-enrollment/setup-{360,768,1440}-synthetic.png`: QR no funcional, sin cookies, contraseñas ni recovery codes. Estas tres imágenes se inspeccionan visualmente antes del commit. Las capturas de flujo con cuentas sintéticas reales enmascaran QR, clave, recovery, contraseña y códigos de acceso; trace permanece off. No se conserva evidencia nueva con una semilla utilizable. Las capturas de regresiones ajenas a MFA se restauran, sin incorporarlas a este cambio.

## Validación ejecutada

- Backend MFA: **11 passed, 110 assertions** (11,32 s), incluidos cinco nuevos casos para coherencia URI/semilla, refresh, cancelación, TTL, secreto activo y ausencia en respuestas/logs.
- Backend Feature Security: **53 passed, 424 assertions** (49,98 s), incluyendo replay, recuperación de un uso, reset, CSRF, cookies, auth_version, autorización, límites y disclosure.
- Frontend: **85 passed en 20 archivos**, baseline 78; siete nuevos escenarios. Tests de pantalla también bajo StrictMode. Node local 25 expone un localStorage nativo incompatible con el setup jsdom existente: se ejecuta con `NODE_OPTIONS=--no-experimental-webstorage`, sin cambiar configuración de aplicación ni tests globales.
- ESLint, TypeScript y Vite build: exit 0.
- E2E visual aislada: **3 passed** (4,6 s), responsive, teclado, QR local, ausencia de solicitudes externas y storage vacío.
- E2E completa final: **34 passed (9,2 min), 0 fallos, 0 omitidos, sin retries**. Baseline de esta rama: 31; se agregan tres visuales. B amplía enrollment con reload/cancel/nuevo secreto/código inválido/activación/recovery/dashboard; H/I conservan single-use y reset. Se aprobaron también autoservicio, pagos, identidades, límites y CSP del build real.

Comandos: `npm run test` con la variable Node indicada; `npm run lint`; `npm run build` (incluye typecheck; para el servidor E2E production, `VITE_API_BASE_URL=/api/v1`); `npm audit --omit=dev`; `npm audit`; `docker compose -f docker-compose.certificate-types.yml --profile test run --rm test php artisan test --compact --filter=MfaSecurityTest`; suite Security con `--testsuite=Feature --filter=Security`; E2E con `--profile e2e run --rm e2e-runner sh -c 'npm ci --no-audit --no-fund && npx playwright test'`. La corrida final reutiliza esas dependencias instaladas y ejecuta `npx playwright test`.

Las corridas E2E iniciales con fallos no se cuentan como validación aprobada: Vite dentro de Docker conservaba el transform anterior a la deduplicación StrictMode (se comprobó el código servido y se reinició el frontend de pruebas); la nueva prueba inválida consumía un intento del límite existente y el caso H ahora respeta el Retry-After de una respuesta 429, sin modificar límites; el build local por defecto utilizaba una API absoluta de desarrollo y se recompiló con la variable same-origin del entorno E2E production para conservar CSP. Se reinstalaron las dependencias del frontend Docker de desarrollo y se reinició su servidor para que el entorno local también use el código actualizado.

Backend tests exclusivamente en `scl_certificate_types_test`; E2E en `scl_certificate_types_e2e` confirmada antes de preparar fixtures idempotentes. No hubo borrado de bases, migrate:fresh ni cambios en CT desarrollo. No push.

## Archivos de implementación y pruebas

- `backend/laravel-app/app/Services/MfaService.php`
- `backend/laravel-app/tests/Feature/Security/MfaSecurityTest.php`
- `frontend/react-app/package.json`, `package-lock.json`
- `frontend/react-app/src/features/auth/services/auth.service.ts`
- `frontend/react-app/src/features/auth/pages/MfaPage.tsx`, `Security.test.tsx`
- `frontend/react-app/src/features/auth/components/RecoveryCodesModal.tsx`
- `frontend/react-app/e2e/security-session.ts`, `security-hardening.spec.ts`, `brand-assets.spec.ts` (máscaras)
- `frontend/react-app/e2e/mfa-enrollment-visual.spec.ts`
- Tres PNG sintéticos en `frontend/react-app/evidence/mfa-enrollment/`
- Este informe.

Todas las verificaciones requeridas aprobadas, con el audit de desarrollo D08 explícitamente pendiente y sin empeorar. Commit: `feat(auth): improve MFA enrollment with local QR setup`. Detener tras commit; no push.
