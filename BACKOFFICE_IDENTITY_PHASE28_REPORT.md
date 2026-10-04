# Fase 2.8 — Identidades backoffice y ciclo de vida

## Inventario previo a migraciones (2026-10-04)

Precheck: árbol limpio, rama `feat/payment-secretary-validation`, HEAD `6b29b9b`. Se creó `feat/backoffice-identity-lifecycle`. Historia confirmada: UX 2.6 `c0750b2`, React Bits 2.5 `b48a83d`, bootstrap Manual `6d85e46`, PDF 3A `dbd5ad0`.

`users` contiene id, name, documento UNIQUE NOT NULL (20), password hash, telefono, estado boolean, email opcional UNIQUE (150), remember_token, timestamps, must_change_password y password_changed_at. No existen username, funcionario_id, último login ni versión de autenticación. La relación laboral inversa es `funcionarios.user_id` nullable; no se duplicará con otra FK.

| ID | Identificador previo | Funcionario | Roles | Activo | Cambio obligatorio | Email | Última actividad token |
|---|---|---|---|---|---|---|---|
|1|000000001|—|admin|sí|no|—|—|
|2|000000002|—|secretario|sí|no|—|—|
|3|000000003|1|funcionario|sí|no|—|2026-09-24 05:50:29|
|4|919023903716001|2|funcionario|sí|no|—|—|
|5|919023903716002|3|funcionario|sí|no|—|2026-09-24 03:37:45|
|6|919023943187801|4|funcionario|sí|no|—|—|
|7|919023943187802|5|funcionario|sí|no|—|2026-09-24 03:44:17|
|8|1118168191|6|funcionario|sí|no|existente (no copiado)|—|
|9|P27CT-f7b22670-adm|—|admin|sí|no|—|—|
|10|P27CT-f7b22670-sec|—|secretario|sí|no|—|—|
|11|P27CT-f7b22670-off|7|funcionario|sí|no|—|—|
|12|P27CT-f7b22670-on|8|funcionario|sí|no|—|—|
|13|P27CT-f7b22670-inc|9|funcionario|sí|no|—|—|

13 usuarios, 9 funcionarios asociados, 4 privilegiados sin vínculo laboral. Cuentas mixtas: **0**. La tabla polimórfica `model_has_roles` permite múltiples roles actualmente. Catálogo: 36 permisos, admin 36, secretario 6, funcionario 3. Sesiones DB: 0; tokens Sanctum: 3; password_reset_tokens: 0.

Login actual: campo cedula → users.documento, verificación hash y estado, revoca tokens anteriores, emite bearer Sanctum. Frontend almacena sesión bearer. Sanctum también puede resolver cookie web; SESSION_DRIVER=file en CT. El middleware obligatorio existente bloquea APIs de negocio hasta cambiar password. No hay revocación por versión ni control general de usuario inactivo en cada petición. Las 18 FK históricas hacia users usan SET NULL; no hay CASCADE. Se prohibirá borrar User en el modelo; no se agregará endpoint DELETE.

Credenciales conocidas detectadas: UsuariosInicialesSeeder admin/secretario con documento y password fijo; LoginForm prellenado y panel demo; fixtures E2E/test y scripts sintéticos con claves conocidas. Las rutas productivas serán corregidas; los fixtures permanecerán restringidos a entornos aislados.

## Decisión de arquitectura previa

Persona ≠ cuenta ≠ rol. Agregar username normalizado, tipo_cuenta (`funcionario`, `backoffice`, `legacy` transitorio), auth_version, last_login_at y datos de desactivación. Reutilizar estado y los campos de password actuales. Funcionario conserva documento/contraseña inicial institucional y vínculo inverso obligatorio validado en servicio/autorización. Backoffice: documento NULL, sin funcionario asociado, un único rol admin o secretario (se conserva el código real `admin`). Username 3–64 ASCII letras/números/punto/guion/underscore, no enteramente numérico, lowercase e inmutable. Email opcional sin dominio impuesto.

Upgrade: copiar documento a username solo para funcionarios coherentes. Privilegiados y estados inconsistentes quedan legacy, sin acceso hasta provisión explícita con username elegido; no inventar identidad ni modificar auditoría. Comando convierte explícitamente el mismo user_id, desvincula relación laboral si existe, revoca acceso y genera contraseña temporal. El empleado que además administra obtiene una cuenta de funcionario distinta. No borrar actores.

Revocación: eliminar bearer tokens + incrementar auth_version; cookies deben contener una versión emitida al autenticar y el middleware la compara en cada petición, sin cambiar SESSION_DRIVER. Desactivar/reactivar/reset/cambiar rol invalida sesiones antiguas. Bootstrap mediante comando, sin credenciales productivas conocidas. Contraseñas temporales aleatorias de 24 caracteres, solo respuesta inmediata/modal o terminal operativa, nunca archivos ni auditoría.

La protección de último admin usará lock transaccional global y bloqueo de cuentas para serializar creación/cambios. Se reutilizan usuarios.ver/crear/editar: catálogo final previsto 36. La separación de contexto también se exige por encima de permisos amplios.

## Validación y resultados

Implementación completada; los resultados finales se registran al cierre de este informe.

## 1–3. Estado inicial, users y cuentas mixtas

El inventario anterior se documentó antes de crear migraciones. CT tenía 13 usuarios, 9 funcionarios asociados y 4 privilegiados sin asociación laboral. Cuentas mixtas: 0; privilegiados vinculados a funcionario: 0. Existían múltiples roles posibles en el pivot, aunque no estaban presentes en esos datos. La asociación es funcionarios.user_id, proyectada como funcionario_id en UserResource; no se duplicó con otra FK.

## 4–6. Arquitectura, tipo de cuenta e invariantes

User representa cuenta, Funcionario entidad laboral y Role autorización. Se conserva un solo login y Sanctum. tipo_cuenta distingue funcionario/backoffice; legacy es transición sin acceso. El catálogo conserva admin, presentado como Administrador.

Funcionario exige exactamente una asociación laboral, documento coincidente, username canonizado igual al documento y exclusivamente rol funcionario. Backoffice exige documento NULL, ninguna asociación laboral y exactamente un rol admin o secretario. Modelos, servicio, policies, middleware y constraints/triggers PostgreSQL aplican estas reglas. Los triggers son diferidos hasta commit para crear cuenta, rol y asociación en una transacción. No se cambia automáticamente de contexto ni se deduce el rol del formato del username.

## 7. Campos y migraciones

Se reutilizan estado, must_change_password, password_changed_at, email y password con hash. Se agregan username UNIQUE, tipo_cuenta, auth_version inicial 1, last_login_at, desactivado_at, desactivado_por con RESTRICT y motivo_desactivacion. documento pasa a nullable. username permanece nullable solo para legacy.

| Migración | Resultado |
|---|---|
|2026_10_04_000001_add_backoffice_identity_lifecycle|Campos, clasificación conservadora, CHECK e índice único parcial en funcionarios.user_id.|
|2026_10_04_000002_enforce_identity_topology|Topología usuario/roles/asociación diferida y trigger que prohíbe DELETE users. CREATE OR REPLACE permite fresh repetible.|
|2026_10_04_000003_require_identity_username|CHECK adicional exige username en cuentas listas, evitando que NULL pase por la lógica ternaria de SQL.|

Las garantías de topología y locks requieren PostgreSQL; no se ofrece equivalencia SQLite/MySQL.

## 8. Upgrade, rollback y procedimiento institucional

Se copió CT antes de migrar a scl_phase28_upgrade_test. Upgrade aditivo, rollback de tres migraciones y reupgrade: PASS en esa copia antes de provisión. Fresh y repetición con seeding: PASS solamente en E2E aislado. CT recibió migrate; nunca fresh, wipe o truncate.

Solo funcionarios coherentes se clasifican automáticamente. Privilegiados y ambiguos quedan legacy sin acceso. Antes del despliegue revisar asociaciones duplicadas; el índice puede detener el upgrade hasta resolverlas explícitamente.

Procedimiento productivo:

1. Respaldar BD/almacenamiento, inventariar actores y programar una ventana de provisión: las cuentas privilegiadas legacy pierden acceso al instalar.
2. Ejecutar php artisan migrate --force. Si falta el catálogo, php artisan db:seed --class=RolesPermisosSeeder --force.
3. Instalación nueva: php artisan backoffice:provision-admin. Solicita nombre y username, acepta --name, --username y --email opcional. No pasar contraseña ni redirigir/grabar su salida.
4. Actor histórico aprobado: php artisan backoffice:provision-admin --legacy-user=ID --username=USUARIO_INDIVIDUAL --role=admin (o secretario). El operador elige username; nunca se deriva del nombre. Conserva user_id, desvincula funcionario si existía, asigna tipo/rol, revoca acceso y genera temporal nueva.
5. Si ese actor tenía entidad laboral: php artisan funcionario:provision-account ID_FUNCIONARIO crea una cuenta de autoservicio distinta vinculada a la misma entidad. Mantiene password inicial=documento y cambio obligatorio; rechaza asociación ya existente.
6. Entregar temporal por canal institucional seguro y exigir cambio. El reemplazo recibe una nueva cuenta; otro admin desactiva al saliente con motivo. No transferir cuenta/credenciales.

No se inventaron usernames reales. Solo se provisionaron explícitamente actores sintéticos CT 1, 2, 9 y 10: admin_test.ct28, secretario_test.ct28, p27ct.admin_test y p27ct.secretario_test. Conservan sus IDs. El usuario laboral existente 8 no fue reconvertido ni sus datos copiados a backoffice.

Después de provisión, el esquema anterior no representa backoffice sin documento. Las migraciones bloquean rollback destructivo con IDENTITY_ROLLBACK_REQUIRES_OPERATOR si existen documentos NULL. Restaurar respaldo/plan aprobado; no fabricar documentos para forzarlo. Reversibilidad segura comprobada antes de provisión.

## 9–10. Login y usernames

UI: Usuario, campos vacíos, helper cédula/usuario institucional, diseño React Bits conservado. Backend busca username exacto tras trim/lowercase; cedula sigue como alias de entrada compatible. Rate limiter usa la misma canonización. last_login_at cambia solo tras autenticación completa exitosa; UI muestra fechas America/Bogota.

Username backoffice: 3–64 caracteres ASCII letras/números/punto/guion/underscore, no totalmente numérico, único e inmutable. Se rechazan admin, administrador, secretaria y secretario como identidades compartidas. Nombre descriptivo editable no crea vínculo laboral. Email opcional, único, sin dominio impuesto.

## 11–12. Password temporal y cambio obligatorio

Str::password(24) genera la temporal en servidor; solo se almacena hash. Crear/reset/reactivar devuelven temporal una vez en respuesta inmediata con Cache-Control private/no-store. GET/listado no permiten recuperarla. CLI muestra una vez en terminal operativa; no crea archivos ni registra la clave en auditoría/logs.

UI conserva secreto únicamente en modal, permite selección/copia mediante navigator.clipboard y descarta al cerrar. No usa URL, localStorage, sessionStorage ni cache de mutaciones React Query. Si Clipboard API no existe/falla, ofrece selección con teclado. Capturas enmascaradas y trace off en E2E de identidad evitan conservar temporales.

Middleware bloquea APIs de negocio mientras must_change_password=true; permite me/logout/change-password. Tras cambiar: obligación false, fecha/evento, epoch incrementado y sesión regenerada. El bearer actual se renueva en su registro y se conserva para el contrato frontend; los demás se revocan. Funcionario mantiene contraseña inicial por cédula y cambio obligatorio, sin introducir política global nueva.

## 13–16. Revocación, ciclo de vida, rol y último admin

Desactivar, reactivar, reset y cambiar rol incrementan auth_version, eliminan tokens y limpian remember_token. Cada petición refresca cuenta/estado/rol/contexto. Cookies/file sessions requieren epoch exacto; sesiones antiguas sin epoch fallan cerradas. Se mantiene SESSION_DRIVER=file. Cambio de password revalida token/epoch después de lock de fila para evitar renovar una cookie revocada entre middleware y controller.

Desactivar exige motivo y registra actor/fecha. Reactivar siempre genera temporal nueva y exige login/cambio; no revive sesiones ni temporal vieja. Reset reemplaza hash. Cambio de rol admite admin/secretario, auditado y requiere nuevo login.

pg_advisory_xact_lock global y lockForUpdate serializan el ciclo, revalidan actor y cuentan administradores activos. LAST_ACTIVE_ADMIN bloquea desactivar/quitar rol al último. CANNOT_DEACTIVATE_SELF y CANNOT_CHANGE_OWN_ROLE bloquean acciones propias. Modelo y trigger prohíben borrar usuarios; no hay DELETE API. El bootstrap crea primer admin sin depender de otro activo.

## 17. Auditoría e historial

Eventos: backoffice_created, backoffice_updated, backoffice_deactivated, backoffice_reactivated, backoffice_role_changed, backoffice_password_reset, backoffice_username_provisioned, forced_password_changed y crear_usuario_funcionario, además de login/logout existentes. Actor, target/modelo_id, fecha y motivo/roles previo-nuevo según corresponda. CLI registra actor NULL y origen operativo; no inventa actor humano.

No se registran temporal, hash, token ni cookie. Errores de identidad tienen códigos estables sin stacks; creación traduce conflictos únicos para evitar logging de consultas con hash. Los actores e IDs anteriores conservan auditoría, pagos, certificados y Manual. Ningún CASCADE destructivo hacia users.

## 18. UI y accesibilidad

Usuarios internos reutiliza Alert, Modal, Table, Badge, Input y Button. Nombre/username/rol/estado/último acceso/creación/acciones; filtros y paginación en servidor. Crear, editar nombre/email, desactivar con motivo, reactivar, reset y rol. Solo Administrador accede, también mediante policy servidor.

No se añadieron React Bits. Modal mantiene Tab/Shift+Tab, Escape, retorno de foco, labels, aria y scroll interno. Referencia estable a onClose evita reiniciar foco al escribir. Se verificaron login, tabla y modales crear/temporal/desactivar/rol a 360/768/1440 sin overflow del documento; la tabla puede desplazarse internamente en móvil.

## 19–20. Persona dual y contextos

María Test tiene dos User IDs/hashes/tokens independientes. E2E usa dos contextos de navegador simultáneos. Cuenta laboral genera certificado propio; cuenta administrativa no consulta disponibilidad ni solicita ni consume cupo ni carga pago propio. Contexto se comprueba más allá de RBAC incluso con permisos amplios. Funcionario y Secretaría no administran internos.

No se añadió un registro de personas ni sincronización automática de datos. Desactivar cuenta interna no desactiva automáticamente entidad/cuenta laboral.

## 21–23. Regresiones pagos, Manual y PDF

Pagos 2.7: backend y cuatro E2E verdes. OFF automático; ON comprobante/Secretaría/confirmación/generación; rechazo y reemplazo con mismo radicado; concurrencia e IDOR. Admin conserva configuración ON/OFF y no se vuelve revisor. No cambia máquina de estados/storage/cuotas/idempotencia.

Manual CT: versión 10 publicada, 344 fichas, 3.115 funciones, 2.326 conocimientos. Digests iguales a copia anterior: fichas c0c1179b2c5547f179f3e1fe1859d3fb; funciones 7bc62fb3fae7a72814aabcded1b0708d. Son comparación, no firmas. Solo se adapta identidad de fixtures/demo, sin editar contenido/servicios Manual.

PDF 3A: CertificadoPdfService, CertificateDocumentData, CertificateAssetResolver, laboral.blade.php, document.css y construcción de snapshot sin cambios. Fixtures de pruebas adaptados a tipo explícito. Backend/E2E verifican descarga real y BORRADOR — SIN VALIDEZ OFICIAL.

## 24–25. Tests, E2E y smoke CT

219 backend (1.147 assertions): 176 previos y 43 casos nuevos de identidad, incluyendo constraints SQL diferidos, bearer, cookies, carrera de renovación, primer admin y seeders productivos. Frontend 65 en 18 archivos. Playwright completo 18: 11 previos + A–G (8,3 min); siete A–G reejecutados con capturas finales (1,8 min). Sin sleeps arbitrarios.

Smoke HTTP nginx CT: PASS con Admin A/B, Secretaría, Funcionario Independiente y Persona Dual ficticios. User IDs nuevos 14–19, funcionarios 10–11, certificados 14–15. Verificó temporal/cambio, contexto, generación, desactivación/revocación, reactivar/reset y rol/autodesactivación. Restauró pago y revocó todos los tokens nuevos en finally. CT final 19 cuentas: 11 funcionario, 8 backoffice, 0 legacy/mixtas; permanecen tres tokens previos de funcionarios. Script exige local y BD CT exacta, no guarda secretos.

## 26. Capturas

22 PNG en frontend/react-app/evidence/identity-phase28/. Temporales/campos password enmascarados; captura espera heading, fin de validación de sesión/skeletons y desactiva animaciones para fotografías estables. Regresiones 2.7 producen capturas en test-results/regression-phase27 para preservar evidencia histórica.

| Archivo | Evidencia |
|---|---|
|01-login-usuario.png|Login vacío.|
|02-usuarios-internos.png|Listado.|
|03-crear-backoffice.png|Alta sin vínculo laboral.|
|04-password-temporal.png|Temporal enmascarada.|
|05-cambio-password-obligatorio.png|Primer ingreso restringido.|
|06-admin-dashboard.png|Administrador.|
|07-secretaria-dashboard.png|Secretaría.|
|08-desactivar-usuario.png|Motivo/confirmación.|
|09-usuario-desactivado.png|Login rechazado.|
|10-reactivar.png|Reactivación.|
|11-reset-password.png|Reset.|
|12-ultimo-admin-warning.png|Rechazo último admin.|
|13-persona-cuenta-funcionario.png|Autoservicio dual.|
|14-persona-cuenta-backoffice.png|Administración dual.|
|15-mobile-usuarios.png|Listado 360.|
|16-cambio-rol.png|Cambio de rol.|
|17-mobile-login.png|Login 360.|
|18-mobile-password-temporal.png|Temporal 360/foco.|
|19-mobile-crear.png|Crear 360.|
|20-mobile-desactivar.png|Desactivar 360.|
|21-mobile-cambio-rol.png|Rol 360.|
|22-tablet-usuarios.png|Listado 768.|

## 27–28. Permisos y credenciales conocidas

Catálogo 36 → 36; admin 36, secretario 6, funcionario 3. Se reutilizan usuarios.ver/crear/editar junto con BackofficeUserPolicy; no se añadieron permisos.

UsuariosInicialesSeeder dejó de producir privilegiados por documento/claves conocidas en producción. Ese seeder y DatosEjemploSeeder solo crean sintéticos en local/testing. LoginForm eliminó claves prellenadas/panel demo. Fixtures backend/E2E y scripts CT mantienen claves sintéticas restringidas; no se borraron fixtures legítimos ni se copiaron secretos a este informe. No cambiaron manifests/lockfiles composer/npm.

## 29. Riesgos y deuda 2.9

Entrega de temporal sigue operativa, sin correo/recovery nuevo: verificar destinatario y no grabar salida CLI en tickets/logs. APP_ENV production y ventana de provisión legacy son necesarios en despliegue. Rollback posterior a provisión requiere respaldo/plan explícito. PostgreSQL es requisito. Cambios manuales privilegiados de BD pueden desactivar admins fuera del servicio; requieren control operativo.

Se mantienen password inicial funcionario por cédula, política/rate limits existentes y bearer en almacenamiento frontend. Evaluar en 2.9 políticas globales, recovery, sesiones/CSRF/tokens, account lockout y MFA con alcance aprobado. No se implementaron MFA, CSP/HSTS, WAF, pentest, antivirus, rotación general, QR, firma ni oficialización.

## 30. UML y requerimientos

User–Funcionario es 0..1 en ambos sentidos, pero tipo funcionario exige uno y backoffice cero. User–Role exige uno compatible en identidades listas. Persona puede usar dos cuentas independientes; no implica relación laboral de la cuenta administrativa.

Agregar casos de uso: provisionar primer admin, migrar actor legacy, crear cuenta laboral separada y administrar accesos/temporales. Secuencias incluyen tipo/contexto, mandatory password, epoch, locks y auditoría. Reemplazo crea actor nuevo y conserva saliente desactivado.

## Cierre de verificación

| Puerta | Resultado |
|---|---|
|Backend completo|219 passed / 1.147 assertions, 332,38 s.|
|Frontend completo|65 passed / 18 archivos.|
|Playwright completo|18 passed; A–G final adicional 7 passed.|
|ESLint / TypeScript / Vite|PASS; build 2.034 módulos.|
|Pint|PASS: 46 PHP backend y 2 scripts.|
|Upgrade / rollback / reupgrade|PASS copia aislada preprovisión.|
|Fresh repetible|PASS E2E aislado con seeding.|
|Smoke HTTP CT|PASS.|
|Manual / PDF / pagos|Regresiones verdes.|
|Evidencia|22 capturas revisadas; responsive y teclado.|
|Diff / UTF-8|PASS.|

Rama feat/backoffice-identity-lifecycle. Commit de cierre: feat(auth): separate backoffice identities from employee accounts. Sin push y sin iniciar Fase 2.9. No correspondió audit de dependencias: no se modificaron.
