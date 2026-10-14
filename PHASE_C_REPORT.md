# Informe de implementación — Fase C: autoservicio 24/7

**Fecha de corte:** 2026-08-31  
**Proyecto:** `labor-certification-system`  
**Resultado:** implementación funcional terminada y verificada localmente; no se declara lista para producción por las decisiones externas pendientes descritas al final.

## 1. Resultado ejecutivo

Fase C reemplaza el circuito humano de aprobación para nuevas expediciones por un flujo de autoservicio. Un funcionario activo, con cuenta habilitada y contraseña inicial ya cambiada, puede solicitar una certificación CON salario y otra SIN salario por mes calendario. El backend deriva siempre la identidad del usuario autenticado y ejecuta validación, radicación, snapshot, PDF, certificado y token como una única operación cuando el pago está desactivado.

Cuando el pago está activado, la misma solicitud crea una orden pendiente y se detiene antes de producir certificado o PDF. El código deja un contrato explícito para integrar un proveedor real; no simula una pasarela, no acepta comprobantes manuales del funcionario y no introduce una aprobación de Secretaría.

La verificación final concluyó con:

- Laravel: **82 pruebas, 398 aserciones, 0 fallas**.
- React/Vitest: **11 pruebas, 0 fallas**.
- Build TypeScript/Vite, ESLint y build de recursos Laravel: correctos.
- Pint: **156 archivos PHP canónicos sin problemas de estilo**; las copias de conflicto están excluidas.
- Composer, npm React y npm Laravel: **0 vulnerabilidades reportadas**.
- Cuatro servicios Docker saludables.
- Migraciones incrementales aplicadas; `migrate --pretend` no propone cambios pendientes.

## 2. Flujo institucional confirmado

### Pago desactivado

1. El funcionario inicia sesión con una cuenta activa.
2. Si usa la contraseña inicial o una restablecida, el middleware limita la sesión a consultar identidad, cambiar contraseña o cerrar sesión.
3. Al solicitar el certificado, el servidor obtiene al funcionario desde la sesión; no acepta que el cliente elija `funcionario_id`.
4. Se verifican estado activo, vinculación, cargo, datos institucionales, modalidad y cupo mensual.
5. Dentro de una transacción se crea la solicitud en estado de generación y se bloquea la combinación mensual para evitar carreras.
6. Se resuelve el Manual de Funciones vigente. Para la modalidad CON salario también se exige una única asignación y rango salarial vigentes.
7. Se congela un snapshot estructurado, se renderiza el PDF privado, se calcula SHA-256 y se persiste certificado, token público de validación y token temporal de descarga del titular.
8. La solicitud queda generada y la respuesta ofrece descarga inmediata al propio funcionario.
9. Si falla cualquier paso técnico, la transacción se revierte, se compensa el archivo y no queda un cupo consumido por una expedición incompleta.

### Pago activado

1. Se ejecutan las mismas validaciones de identidad, actividad, datos y cupo.
2. Se crea la solicitud en espera de pago y una `orden_pago_certificado` con referencia única y estado pendiente.
3. No se crea certificado, snapshot final ni PDF, y no se entrega enlace de descarga.
4. Una integración futura deberá implementar `PaymentGateway`, autenticar el webhook del proveedor, procesarlo de forma idempotente y disparar automáticamente la misma generación después de confirmar el pago.
5. No existe pasarela falsa, botón de “pago aprobado”, carga de recibo por el funcionario ni intervención obligatoria de Secretaría.

La activación se controla con el parámetro `requiere_pago_certificado`. Solo Administración puede consultarlo y modificarlo; cada cambio registra valor anterior, valor nuevo y actor en auditoría.

## 3. Regla mensual

La regla oficial es exactamente:

- máximo una certificación CON salario por funcionario y mes calendario;
- máximo una certificación SIN salario por funcionario y mes calendario.

Son cupos independientes. PostgreSQL conserva una restricción única por funcionario, `periodo_mes` y modalidad; el servicio añade bloqueo transaccional para concurrencia y la API de disponibilidad publica únicamente ambos cupos. Las solicitudes históricas rechazadas, canceladas o anuladas continúan contando según la regla definida en Fase A. Una generación automática fallida se revierte y no deja una solicitud huérfana consumiendo cupo.

## 4. Ciclo de vida de la cuenta

La creación administrativa de un funcionario ahora es atómica:

- crea usuario, rol funcionario, ficha laboral y asignación de cargo;
- usa la cédula como nombre de usuario;
- establece inicialmente la cédula como contraseña, almacenada mediante hash;
- marca `must_change_password=true`;
- impide usar funciones del sistema hasta completar el cambio obligatorio.

La nueva contraseña no puede coincidir con la cédula. El restablecimiento administrativo vuelve a la contraseña inicial, activa nuevamente el cambio obligatorio y revoca todos los tokens existentes. La desactivación impide iniciar sesión y expedir certificados.

Administración dispone de búsqueda, filtro por estado, paginación, creación, edición, activación/desactivación y restablecimiento. El frontend ya no solicita un `user_id` interno.

## 5. Automatización de los datos certificados

El contenido no depende de transcripción humana:

- identidad y vinculación: ficha institucional del funcionario;
- cargo y dependencia: asignación laboral vigente;
- funciones: versión publicada del Manual Específico aplicable por cargo y fecha;
- salario: rango vigente, únicamente para modalidad CON salario;
- trazabilidad: radicado, snapshot inmutable, hash, token de validación y auditoría.

El snapshot impide que cambios posteriores alteren lo ya emitido. La validación pública recalcula el hash del archivo y no expone salario. Los archivos se mantienen en almacenamiento privado y las descargas autenticadas aplican autorización y cabeceras seguras.

## 6. Cambios técnicos principales

### Backend

- `ExpedirCertificacionService` coordina el autoservicio en una transacción.
- `MiCertificacionController` expone disponibilidad, expedición y descarga propia temporal.
- `EnsurePasswordChanged` impone el cambio de contraseña en backend, no solo en interfaz.
- `ParametroCertificacionController` administra el interruptor de pago con permisos y auditoría.
- `OrdenPagoCertificado`, `EstadoOrdenPagoEnum` y `PaymentGateway` preparan el borde de integración real.
- `FuncionarioController` administra cuenta, ficha y asignación de manera coordinada.
- Las rutas manuales de aprobación/rechazo de solicitudes nuevas responden `410 Gone`.
- Los servicios históricos de certificados y soportes se conservan para expedientes anteriores, sin formar parte del camino crítico nuevo.

### Frontend

- Dashboard del funcionario centrado en los dos cupos y expedición inmediata.
- Confirmación diferenciada entre descarga disponible y pago pendiente.
- Pantalla obligatoria de cambio de contraseña y guard de navegación.
- Administración completa de funcionarios y del parámetro de pago.
- Retiro de colas de aprobación del flujo nuevo y explicación del rol histórico para Secretaría.
- Restauración de contratos y controles de Fase B para descargas, validación, cargos, rangos y reportes.

### Base de datos

La migración incremental `2026_08_31_000005_add_phase_c_self_service_foundation.php`:

- agrega `must_change_password` y `password_changed_at` a usuarios;
- agrega token temporal de descarga e índice único a certificados;
- crea `ordenes_pago_certificado` con referencias, estados y campos de proveedor;
- agrega índices de búsqueda administrativa.

No se usaron `migrate:fresh`, `db:wipe`, borrado de volumen, `DROP DATABASE` ni reinicialización destructiva. En la base principal local no había registros de negocio al verificar la migración, por lo que no existían huérfanos ni duplicados que remediar.

## 7. Contrato API relevante

- `PUT /api/v1/auth/change-password`: cambia la contraseña obligatoria.
- `GET /api/v1/mi-certificacion/disponibilidad`: consulta los dos cupos del mes.
- `POST /api/v1/solicitudes`: expide o crea orden pendiente según configuración.
- `GET /api/v1/mi-certificacion/descargar/{token}`: descarga temporal, autenticada y exclusiva del titular.
- `GET|PATCH /api/v1/configuracion/certificaciones`: consulta o modifica pago requerido.
- Recursos administrativos de funcionarios: listado filtrado/paginado, alta, edición, activación/desactivación y reset de contraseña.

Los endpoints internos y de expediente siguen protegidos por autenticación, permisos y, cuando corresponde, autorización por objeto.

## 8. Evidencia de verificación

Comandos reproducidos dentro del entorno Docker:

```powershell
docker compose exec -T app php artisan test
# 82 passed (398 assertions)

docker compose exec -T frontend npm test -- --run
# 7 archivos, 11 pruebas correctas

docker compose exec -T frontend npm run build
docker compose exec -T frontend npm run lint
docker compose exec -T app ./vendor/bin/pint --test
docker compose build app
docker compose exec -T app composer audit
docker compose exec -T frontend npm audit
docker run --rm -v "${PWD}/backend/laravel-app/package.json:/app/package.json:ro" -v "${PWD}/backend/laravel-app/package-lock.json:/app/package-lock.json:ro" -w /app node:24-alpine npm audit
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan migrate --pretend
docker compose ps
```

Además se verificaron en pruebas automatizadas: concurrencia y unicidad mensual, titularidad, funcionario inactivo, cambio obligatorio, reset con revocación, generación inmediata sin pago, orden sin certificado con pago, snapshot, hash, archivo alterado/ausente/anulado, soportes privados, compensación de archivos y envelopes de error.

## 9. Comparativo antes/después

| Aspecto | Estado observado al iniciar esta continuación | Fase C terminada |
| --- | --- | --- |
| Suite backend real | 71 pruebas / 328 aserciones; copias de conflicto conservaban cambios no integrados | 82 / 398, todas verdes |
| Frontend real | Sin script de pruebas reproducible y con fallos de build/lint por regresiones de archivos | 11 pruebas, build y lint correctos |
| Expedición | Radicación y caminos manuales heredados | Autoservicio transaccional; aprobación manual retirada |
| Pago apagado | No entregaba el resultado completo automáticamente | PDF y descarga propia inmediatos |
| Pago encendido | Soporte manual heredado | Orden pendiente; cero certificado hasta proveedor real |
| Cuenta nueva | Usuario y ficha podían quedar desacoplados | Alta atómica, contraseña inicial cifrada y cambio obligatorio |
| Administración | CRUD incompleto para este flujo | Búsqueda, filtros, paginación, edición, estado y reset |
| Integridad | Controles de Fases A/B parcialmente sobrescritos en el árbol local | Controles restaurados y ampliados con pruebas de regresión |
| Dependencias | Estado real por volver a comprobar | Tres auditorías con 0 vulnerabilidades |

El informe previo de Fase B documentaba 74 pruebas/341 aserciones y 8 pruebas React. La diferencia con el punto de partida observado se debió a copias de conflicto `*-camilo_ortega` creadas por OneDrive: algunas contenían la versión avanzada mientras los archivos canónicos habían retrocedido. Se restauró el contenido aplicable, pero las copias se conservaron deliberadamente para no destruir material del usuario y se excluyeron del compilador TypeScript.

## 10. Límites y decisiones pendientes para producción

La automatización funcional no resuelve por sí sola la oficialidad documental. Antes de producción se requiere:

1. Definir e integrar un proveedor de pago real, incluyendo firma/autenticación de webhook, idempotencia, conciliación, reintentos y tratamiento de reversos.
2. Aprobar el formato oficial, firmante competente, QR y política de conservación.
3. Elegir una firma automática jurídicamente válida. Si se exige firma digital, debe operar mediante servicio de firma/HSM o proveedor remoto y no mediante un clic humano; la decisión legal y de infraestructura corresponde a la Alcaldía.
4. Sustituir o endurecer el Bearer token persistido en `localStorage`, definir expiración/rotación y políticas de sesión.
5. Ejecutar pruebas E2E, carga/concurrencia sostenida, recuperación de backups y observabilidad productiva.
6. Dividir el bundle principal del frontend, que compila correctamente pero supera levemente la recomendación de 500 kB.
7. Resolver fuera de esta entrega las copias de conflicto de OneDrive. Se recomienda mover el repositorio fuera de una carpeta sincronizada antes de decidir cuáles eliminar.

Por estos puntos el software queda **funcionalmente preparado para la Fase C local**, pero **no certificado como listo para producción**.
