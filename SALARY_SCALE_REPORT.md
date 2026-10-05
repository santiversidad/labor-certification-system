# Reporte: módulo administrativo de escala salarial

Fecha de trabajo: 2026-10-05. Repositorio canónico `C:\Users\mondr\source\labor-certification-recovery`.

Este reporte conserva la evidencia de implementación del commit `c751327`. La carga posterior autorizada de 2024/2025 está documentada en [SALARY_SCALE_IMPORT_REVIEW.md](SALARY_SCALE_IMPORT_REVIEW.md): 100 ítems en CT dev, ambos años en borrador, 96 relaciones y 4 pendientes institucionales con FK nula. Los estados «pendiente de transcripción / 0 ítems» que aparecen abajo corresponden al cierre original del módulo. 2026 sigue ausente; acto y fecha jurídica sin registrar.

## Baseline y rama

Precheck limpio en `fix/manual-profile-ambiguity-ux`, HEAD `18d9a2764e612accf1b5eae8e68ea5860a62f327` (UX del Manual completada). Se creó `feat/salary-scale-management`. Sin reset, clean, checkout destructivo ni push. Mensaje de commit: `feat(salary-scale): add administrative salary scale management`. El usuario autorizó explícitamente el commit con la excepción de auditoría documentada abajo.

## Investigación legacy

La migración `2026_09_23_000001_replace_salary_modality_with_certificate_types.php` eliminó `rangos_salariales` y retiró campos/modalidades personales. CT dev no tenía esa tabla ni FKs salariales activas; existían 36 permisos antes de esta tarea. Persisten copias históricas `*-camilo_ortega` y documentación obsoleta, que no se restauraron. README backend retiró endpoints/permisos salariales legacy y describe el catálogo nuevo; README frontend corrige rutas y documentación antigua de mocks.

Se reutilizaron Sanctum/middleware existente, roles/permisos Spatie, auditoría, API/axios, TanStack Query, Modal/Input/Select/Button y diseño institucional. No se reutilizó ni reactivó el viejo dominio salarial.

## Resultado funcional

Modelo completo en [SALARY_SCALE_MODEL.md](SALARY_SCALE_MODEL.md). Migración aditiva `2026_10_05_000002_create_salary_scale_management.php`: `escala_salarial_vigencias` y `escala_salarial_items`, restricciones, FKs seguras, revisiones y triggers de inmutabilidad. Aplicada primero en `scl_certificate_types_test`, después en `scl_certificate_types_e2e`; tras validación, incrementalmente en `scl_certificate_types_dev`. No se eliminaron datos ni se alteró el Manual.

- Admin exclusivo, permiso `escala_salarial.gestionar`. CT dev: admin 37, secretario 6, funcionario 3. API exige también rol admin, no basta un permiso asignado erróneamente. E2E comprueba menú, ruta y 403 para ambos roles no administrativos.
- Frontend `/admin/escala-salarial`, feature aislada, tabla dinámica por año, búsqueda y paginación en servidor, formatos COP, ceros iniciales, cantidades por año y scroll interno móvil.
- API: GET tabla; GET/POST vigencias; GET/PATCH vigencia; POST previsualizar/calcular/publicar; PATCH ítem. Todas bajo `/api/v1/escala-salarial` y sesión/CSRF/MFA existentes.
- Crear desde base copia estructura/cantidades sin salarios si falta porcentaje; valores quedan pendientes. Sin base usa cargos activos. Aumento requiere base anterior completa, preview autoritativo del servidor y confirmación; crea o calcula borrador, no valores oficiales. Origen, porcentaje y base quedan registrados.
- Porcentaje decimal string hasta 2 decimales, coma/punto, rango preventivo 0–100 %, sin afirmar límite legal. COP entero positivo `decimal(12,0)`; cálculo entero int64 y half-up al peso. Casos de mitades, subpeso, coma y desbordamiento probados.
- Edición individual de asignación/cantidad solo en borrador; acto/fuente/fecha/observaciones se pueden completar después de publicar. Publicación explícita, valores completos obligatorios, advertencia cuando falta acto, históricos inmutables sin reapertura/borrado.
- Advisory locks, locks de padre, UNIQUE año y revisiones protegen creación, cálculo, publicación y ediciones simultáneas. E2E comprueba dos solicitudes paralelas en cada operación: una tiene éxito y la otra devuelve 409.
- Auditoría segura con actor explícito y metadata administrativa; before/after de edición individual, sin credenciales ni datos ajenos.

La revisión visual detectó y corrigió el total de paginación: se cuentan combinaciones fuente distintas, no las celdas anuales. El E2E detectó una carrera al editar y publicar inmediatamente; la UI ahora espera la recarga de la tabla antes de cerrar el diálogo. Una prueba frontend reproduce y protege ese caso. El primer intento navegador tenía un Vite anterior en memoria; reiniciar exclusivamente `e2e-web` resolvió ese estado de entorno.

## Datos y fuente

La fotografía original no está en los archivos disponibles. Solo se recibió el texto de instrucciones. Se entrega plantilla CSV sin valores, importador Artisan reproducible y validaciones. **No hay cifras institucionales cargadas**:

| Año / entorno | Estado |
| --- | --- |
| 2024 institucional | Pendiente de transcripción revisada |
| 2025 institucional | Pendiente de transcripción revisada |
| 2026 institucional | No registrado; sin valores oficiales ni inferencias |
| CT dev | 0 vigencias, 0 ítems, 0 filas mapeadas, 0 sin mapear |
| Test | Datos sintéticos dentro de transacciones con rollback |
| E2E | Dos cargos sintéticos, 4 filas iniciales 2024/2025 mapeadas, 0 sin match; nuevos años exclusivamente sintéticos |

El fixture E2E dice “Tabla sintética E2E; no oficial”. Las pruebas repetidas crean el siguiente año disponible desde 2026 para conservar los años ya publicados sin reset; el nombre `05-draft-2026.png` identifica el escenario solicitado y puede mostrar un año sintético posterior. No significa que ese año esté cargado institucionalmente.

Importador `salary-scale:import`: CSV largo con encabezados exactos, año/código/grado/denominación/cantidad/moneda validados, ceros fuente preservados, coincidencia FK exacta, reporte de desconocidos, resumen y dry-run. Importación exige actor admin activo y fuente; errores estructurales abortan todo; archivo idéntico idempotente; diferencias nunca sobrescriben un año poblado. Instrucciones y plantilla en `backend/laravel-app/database/data/salary-scale/`.

## Validación ejecutada

| Verificación | Resultado |
| --- | --- |
| Backend completo | PASS: 307 tests / 1.824 assertions, regresión final tras corregir total de paginación |
| Frontend completo | 132 tests en 23 archivos; 18 del módulo salarial |
| ESLint | PASS |
| TypeScript | PASS (`tsc -b`, también ejecutado por build) |
| Vite build | PASS |
| Pint | PASS sobre los 14 archivos PHP del alcance |
| E2E módulo real | 4 PASS; sin mocks de red; CRUD, históricos, concurrencia y denegación por rol. Repetición adicional del flujo principal: 1 PASS tras mejorar la espera de la captura de búsqueda |
| E2E completa | PASS: 43 escenarios (11,3 min), incluidos pagos/auth/MFA/PDF/Manual |
| Composer audit | Sin avisos de vulnerabilidad |
| npm audit --omit=dev | 0 vulnerabilidades |
| npm audit completo | FAIL: 5 entradas high de la cadena de desarrollo Tailwind 3 → chokidar/micromatch/fast-glob → braces |
| Manual antes/después de migración CT dev | Digest y conteos idénticos |

Comandos de regresión en `docker-compose.certificate-types.yml`, PHP en servicios test/app, frontend en servicio frontend, Playwright en e2e-runner. Ninguna prueba usó CT dev para escrituras destructivas.

## Auditoría pendiente y condición de commit

`npm audit` reporta GHSA-vfj7-8cjw-p6xm / CVE-2026-93687 sobre `braces` <=3.0.3, sin versión publicada corregida; npm propone migrar a Tailwind 4 (cambio mayor). Referencia verificada: [aviso oficial GitHub](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm). El lockfile y package.json están idénticos al baseline: este módulo no introduce ni modifica dependencias. SHA256 lockfile: `19FCE29D04FA2A555932CCDE518F7BEA5FC165B0AC8DC700CEEEE2375ED746B5`.

No se oculta el aviso, no se ejecutó audit fix --force ni se migra el diseño completo como efecto secundario de la escala salarial. El usuario respondió explícitamente **“Hacer commit con excepción documentada”** el 2026-10-05. Esta autorización permite cerrar el commit del módulo con el hallazgo heredado registrado; no convierte npm audit completo en verde ni declara corregida la vulnerabilidad. Producción conserva 0 vulnerabilidades y las dependencias no se modificaron. No hubo push.

## Integridad y límites

Auditoría read-only `scripts/dependency_manual_integrity.sql` antes y después de la migración en CT dev:

- Versión 10 publicada `Decreto 1000-24/015 de 2023`.
- 344 fichas / 3.115 funciones / 2.326 conocimientos.
- Digest `2ccdb15add1bedbe5c83aa978cf4fa45` intacto.
- 73 ámbitos concretos / 335 fichas en esos ámbitos / 50 combinaciones ambiguas preservadas.
- Ocho perfiles “Todas las dependencias” preservados; no se prefieren sobre/específicos ni se asignan fichas arbitrariamente.
- MF-0167 sin área/propósito inferidos, dependencia original y 8 conocimientos intactos.

Confirmaciones: no existe salario por funcionario; no se reintrodujo certificado salarial; PDF y snapshot sin salario; no se inventó 2026; Secretaría y funcionario no acceden; auth/MFA/CSRF intactos, pagos intactos, cuotas/tipos/firmas/QR documental/oficialización intactos. 3B.2 no iniciada.

## Evidencia y Git

Siete capturas sintéticas, verificadas visualmente en `frontend/react-app/evidence/salary-scale/`: `01-table-desktop.png`, `02-search.png`, `03-create-year.png`, `04-increase-preview.png`, `05-draft-2026.png`, `06-edit-value.png`, `07-mobile.png`. Sin personas reales, credenciales, QR MFA ni fuente privada.

Entrega en `feat/salary-scale-management` con commit autorizado mediante excepción documentada de npm audit. Las capturas de otras fases regeneradas por la suite se restauraron al baseline; solo se conservan las nuevas de escala salarial. El hash final y el estado posterior al commit se reportan desde Git al usuario. No push.
