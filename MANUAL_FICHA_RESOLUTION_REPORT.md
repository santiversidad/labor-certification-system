# Resolución de fichas del Manual por página y funciones exactas

Fecha: 12 de octubre de 2026, America/Bogota.

## Resultado y límites del diagnóstico

Se incorporó una referencia documental verificada por ficha, selección explícita por ID interno y vista previa de funciones. La página aprobada es la de la denominación del empleo. MF-0343 queda en 664 y MF-0344 en 665. El inventario completo de 344 páginas está en `MANUAL_FICHA_PAGE_AUDIT.md`.

La causa comprobada de la dificultad de selección era que la API agrupaba fichas por cargo genérico y el formulario enviaba dependencia/cargo, sin presentar candidatas individuales, páginas ni funciones. La base tiene 50 combinaciones dependencia/cargo con más de una ficha aplicable. El backend anterior bloqueaba esas combinaciones: **no se encontró una selección arbitraria de la primera candidata en ese flujo**. La generación ya utilizaba relación normativa explícita, con compatibilidad legada controlada; ahora también comprueba ámbito y evidencia documental.

No se atribuye sin pruebas un certificado incorrecto concreto a esas 50 combinaciones. El cotejo encontró 344 fichas de base iguales al JSON y 3.115 funciones coincidentes con el PDF. No se proporcionó una persona/certificado específico que permita reconstruir el incidente comunicado. El defecto demostrado es la identificación insuficiente y la imposibilidad de escoger candidatas compatibles en casos múltiples; esta solución corrige ese flujo y fortalece los controles de generación.

La auditoría identificó 11 referencias que debían cambiar al aplicar el criterio aprobado, una colisión importada en página 664 y una página final importada 668 fuera del PDF. No se modifica el JSON normativo original ni se usa página final.

## Modelo, API y selección

- Dos columnas documentales en `manual_cargo_versiones`: `pagina_inicio` y `verificacion_documental`. Índice único por versión/página y restricción de página positiva. Sin nuevas tablas de fichas, duplicaciones ni cambio de claves primarias.
- Evidencia versionada en `database/data/manual_paginas_decreto_015_2023_verificadas.json`: checksum PDF, checksum JSON, 344 source_id/hash/página PDF/página original/conteo de funciones y criterio institucional confirmado.
- Comando `manual:verify-pages`: actor activo autorizado para editar Manual, transacción, validación de versión, checksum, hashes originales, arrays de funciones y unicidad; simulación `--dry-run`, aplicación idempotente y auditoría del actor. No se recalcula ni reemplaza el hash normativo.
- La protección SQL de versiones publicadas/inactivas continúa bloqueando escritura normativa, inserción y borrado. Solo permite completar campos documentales y updated_at; también bloquea mover fichas publicadas a versiones borrador.
- `GET /api/v1/manual-funciones/dependencias/cargos?perfiles=1` devuelve fichas individuales resumidas, página, ID, source_id, versión, dependencia/área, verificación, conteo y condición de elegibilidad. Búsqueda backend por denominación/código/grado/página, filtrada por dependencia, cargo activo y vigencia. Paginación de 100 resúmenes, recorrida por el cliente sin perder candidatas. El contrato genérico anterior se conserva para otros consumidores.
- `GET .../dependencias/resolver` acepta `manual_cargo_version_id`; valida que sea una candidata exacta del cargo, unidad y fecha, con evidencia y contenido suficiente. Sin ID, continúa exigiendo cardinalidad única. Ordenar una lista por página no decide una ficha.
- `funciones=1` carga únicamente las funciones de la ficha expresamente elegida, bajo los permisos existentes de administración de funcionarios. El selector inicial no descarga las 3.115 funciones ni abre el PDF.
- `GET .../dependencias/funciones-registradas/{ficha}?funcionario_id=...` permite revisar exclusivamente una ficha ya relacionada con ese funcionario, aun cuando su versión haya cerrado. Protegido por permisos; es lectura histórica, no autorización para nueva asignación.
- Payloads de alta/edición prohíben página y evidencia documental editables. El cliente no puede elegir una página para eludir la validación por ID, unidad y vigencia.

## Frontend y persistencia

El selector muestra denominación, código/grado y página; cada opción corresponde a una ficha interna. Indica perfiles similares y naturaleza transversal. `Ver funciones del cargo` abre una vista de solo lectura con cargo, unidad/área, source_id, página, versión y funciones ordenadas, sin editor, sin reescritura ni consolidación entre fichas.

Cambiar dependencia, cargo, búsqueda o fecha en un alta limpia ficha y vista previa. Las claves de consulta incluyen unidad/cargo/ficha/fecha y evitan que respuestas tardías restauren una selección anterior. Errores documentales o de compatibilidad bloquean el guardado, conservan los datos personales y permiten escoger otro perfil; fallos técnicos permiten reintentar.

El alta guarda `manual_cargo_version_id` exacto en `funcionario_cargo` y su relación en `funcionario_cargo_manual_fichas`; los metadatos de relación incluyen versión, página y source_id. Se auditan actor, ficha, versión, página y asignación, sin copiar funciones completas al log. Las pruebas comprueban que dos fichas del mismo código/grado guardan la elegida explícitamente.

En edición se precargan la dependencia y ficha registradas. Se puede revisar su contenido histórico. Cambiar solo teléfono u otro dato personal conserva la asignación y la relación normativa; un cambio laboral utiliza el flujo preexistente que preserva la vinculación anterior.

## Generación, snapshots e historial

`ResolverFuncionesFuncionarioService` usa la relación normativa vigente exacta y, solo si no existieron relaciones normativas, la referencia legada explícita. No infiere una ficha al emitir. Verifica cargo/lineage autorizado, vigencia de versión, compatibilidad de unidad cuando está registrada, completitud y página documental verificada. Una asignación sin unidad registrada no se reasigna silenciosamente: requiere revisión administrativa si se cuestiona su ámbito.

El snapshot conserva ficha_id, source_id, hash normativo, versión, acto, relación normativa, asignación, página, evidencia documental y funciones ordenadas de esa ficha. El PDF sigue usando el snapshot existente; no vuelve a resolver contra el Manual actual. Las pruebas leen texto del PDF y descartan funciones de la candidata no elegida. El certificado sencillo mantiene su flujo independiente y permite relaciones incompletas existentes cuando sus reglas actuales lo permiten.

No se regeneran snapshots ni PDF históricos. Los tipos siguen siendo `sencillo` y `funciones`; el PDF mantiene **BORRADOR — SIN VALIDEZ OFICIAL**. No se incorporan salarios, firma digital, oficialización ni Fase 3B.2.

## Las 50 combinaciones ambiguas

Hay 73 ámbitos documentales, 335 fichas específicas y ocho transversales en la versión 10; MF-0167 no crea un ámbito elegible. Las 50 combinaciones incluyen 48 multiplicidades específicas y dos coincidencias con transversal. Cada ficha de la tabla tiene una página distinta verificada. Se habilita la selección explícita compatible; **las 50 multiplicidades documentales no desaparecen ni se fusionan**. Sin selección explícita, siguen bloqueadas. La elección institucional de una ficha para una persona sigue correspondiendo al administrador autorizado.

| # | Ámbito de aplicabilidad | Cargo interno | Fichas y páginas verificadas |
|---:|---|---:|---|
| 1 | despacho del alcalde | 48 | MF-0340 (p. 658); MF-0341 (p. 660) |
| 2 | despacho del alcalde | 49 | MF-0342 (p. 662); MF-0343 (p. 664) |
| 3 | oficina asesora jurídica | 14 | MF-0080 (p. 186); MF-0081 (p. 188) |
| 4 | oficina de contratación | 16 | MF-0114 (p. 254); MF-0115 (p. 256) |
| 5 | oficina de la alta consejería para la seguridad ciudadana | 16 | MF-0119 (p. 264); MF-0120 (p. 266); MF-0121 (p. 268) |
| 6 | secretaría de catastro y espacio público | 16 | MF-0123 (p. 273); MF-0124 (p. 274); MF-0125 (p. 276) |
| 7 | secretaría de catastro y espacio público - dirección de conservación y actualización catastral | 13 | MF-0069 (p. 164); MF-0070 (p. 166) |
| 8 | secretaría de catastro y espacio público - dirección de conservación y actualización catastral | 16 | MF-0126 (p. 279); MF-0127 (p. 281); MF-0128 (p. 283) |
| 9 | secretaría de catastro y espacio público - dirección de difusión catastral | 13 | MF-0071 (p. 168); MF-0072 (p. 170) |
| 10 | secretaría de catastro y espacio público - dirección de difusión catastral | 16 | MF-0129 (p. 285); MF-0130 (p. 287) |
| 11 | secretaría de catastro y espacio público - dirección de gestión del espacio público | 14 | MF-0084 (p. 194); MF-0085 (p. 195) |
| 12 | secretaría de competitividad y desarrollo | 36 | MF-0273 (p. 552); MF-0274 (p. 553) |
| 13 | secretaría de desarrollo institucional - dirección de personal | 14 | MF-0086 (p. 197); MF-0087 (p. 200) |
| 14 | secretaría de desarrollo institucional - dirección de personal | 16 | MF-0133 (p. 293); MF-0134 (p. 295); MF-0135 (p. 297); MF-0136 (p. 299); MF-0137 (p. 301) |
| 15 | secretaría de desarrollo institucional - dirección de personal | 28 | MF-0232 (p. 484); MF-0233 (p. 486); MF-0234 (p. 488) |
| 16 | secretaría de desarrollo institucional - dirección de personal | 36 | MF-0277 (p. 557); MF-0278 (p. 558); MF-0279 (p. 560) |
| 17 | secretaría de educación - dirección administrativa | 16 | MF-0138 (p. 304); MF-0139 (p. 306) |
| 18 | secretaría de educación - dirección administrativa | 36 | MF-0283 (p. 566); MF-0284 (p. 567) |
| 19 | secretaría de gestión social y participación ciudadana | 16 | MF-0143 (p. 314); MF-0144 (p. 316) |
| 20 | secretaría de gestión social y participación ciudadana - dirección de grupos de especial protección | 16 | MF-0141 (p. 310); MF-0142 (p. 312) |
| 21 | secretaría de gestión social y participación ciudadana - dirección de grupos de especial protección | 36 | MF-0287 (p. 571); MF-0288 (p. 572) |
| 22 | secretaría de gestión social y participación ciudadana - dirección de participación ciudadana | 28 | MF-0238 (p. 496); MF-0239 (p. 498) |
| 23 | secretaría de gobierno y post conflicto | 14 | MF-0088 (p. 202); MF-0091 (p. 208) |
| 24 | secretaría de gobierno y post conflicto | 16 | MF-0145 (p. 318); MF-0147 (p. 322) |
| 25 | secretaría de gobierno y post conflicto - dirección de justicia | 14 | MF-0089 (p. 204); MF-0090 (p. 206); MF-0092 (p. 210) |
| 26 | secretaría de gobierno y post conflicto - dirección de justicia | 16 | MF-0148 (p. 324); MF-0149 (p. 326); MF-0150 (p. 328); MF-0151 (p. 330); MF-0152 (p. 332); MF-0153 (p. 334) |
| 27 | secretaría de gobierno y post conflicto - dirección de justicia | 36 | MF-0289 (p. 573); MF-0290 (p. 575); MF-0291 (p. 577) |
| 28 | secretaría de gobierno y post conflicto - dirección de justicia | 37 | MF-0321 (p. 625); MF-0322 (p. 627); MF-0323 (p. 629) |
| 29 | secretaría de hacienda - dirección de impuestos | 13 | MF-0076 (p. 178); MF-0077 (p. 180) |
| 30 | secretaría de hacienda - dirección de impuestos | 14 | MF-0093 (p. 212); MF-0095 (p. 216) |
| 31 | secretaría de hacienda - dirección de impuestos | 16 | MF-0154 (p. 336); MF-0155 (p. 338) |
| 32 | secretaría de hacienda - dirección de impuestos | 28 | MF-0241 (p. 501); MF-0242 (p. 503) |
| 33 | secretaría de hacienda - tesorería | 16 | MF-0158 (p. 344); MF-0159 (p. 346) |
| 34 | secretaría de infraestructura - dirección de obras civiles | 16 | MF-0160 (p. 348); MF-0161 (p. 350) |
| 35 | secretaría de movilidad - dirección de servicios de movilidad | 16 | MF-0170 (p. 368); MF-0171 (p. 370) |
| 36 | secretaría de planeación | 16 | MF-0174 (p. 376); MF-0184 (p. 396); MF-0185 (p. 398) |
| 37 | secretaría de planeación - dirección de ordenamiento territorial | 14 | MF-0103 (p. 232); MF-0104 (p. 234) |
| 38 | secretaría de planeación - dirección de ordenamiento territorial | 16 | MF-0177 (p. 382); MF-0178 (p. 384); MF-0179 (p. 386); MF-0180 (p. 388); MF-0181 (p. 390); MF-0182 (p. 392) |
| 39 | secretaría de planeación - dirección de ordenamiento territorial | 36 | MF-0306 (p. 602); MF-0307 (p. 603) |
| 40 | secretaría de planeación - dirección de planeación socioeconómica | 14 | MF-0094 (p. 214); MF-0102 (p. 230) |
| 41 | secretaría de planeación - dirección de planeación socioeconómica | 16 | MF-0175 (p. 378); MF-0176 (p. 380) |
| 42 | secretaría de planeación - dirección de planeación socioeconómica | 36 | MF-0309 (p. 607); MF-0310 (p. 609) |
| 43 | secretaría de salud - dirección de aseguramiento | 16 | MF-0192 (p. 412); MF-0193 (p. 414) |
| 44 | secretaría de salud - dirección de salud ambiental | 36 | MF-0312 (p. 611); MF-0313 (p. 612) |
| 45 | secretaría de salud - dirección de salud pública | 14 | MF-0107 (p. 240); MF-0108 (p. 242) |
| 46 | secretaría de salud - dirección de salud pública | 16 | MF-0188 (p. 404); MF-0189 (p. 406); MF-0190 (p. 408); MF-0191 (p. 410) |
| 47 | secretaría de salud - dirección de salud pública | 36 | MF-0314 (p. 614); MF-0315 (p. 615) |
| 48 | secretaría de salud - dirección de salud pública | 37 | MF-0326 (p. 633); MF-0328 (p. 636) |
| 49 | secretaría del medio ambiente | 14 | MF-0109 (p. 244); MF-0110 (p. 246) |
| 50 | secretaría del medio ambiente | 16 | MF-0194 (p. 416); MF-0195 (p. 418); MF-0196 (p. 419) |

## Alcance retrospectivo y pendientes institucionales

CT dev contiene 14 vinculaciones y 18 certificados al inicio de esta revisión. Ninguna vinculación o certificado referencia MF-0343/MF-0344. Ocho snapshots con source_id/funciones comparables coinciden con el JSON original; esa comprobación no clasifica los otros diez como correctos o incorrectos. No hay evidencia suficiente para cuantificar personas con funciones erróneas por decisiones administrativas anteriores. No se reasignan empleados ni se modifican certificados existentes.

MF-0167 conserva área y propósito vacíos, aunque su página es verificable. Sigue requiriendo revisión institucional para completar información; esta tarea no la inventa ni la convierte en elegible. También permanecen bloqueadas fichas sin evidencia, incompletas, incompatibles o selecciones sin ID con varias candidatas. Nuevas versiones del Manual requieren su propia comprobación documental; no heredan automáticamente las páginas del Decreto 015.

Para investigar un certificado reportado como incorrecto, la entidad deberá identificar su código/ID y la ficha que considera correspondiente. Eso permitirá revisar la decisión de asociación sin alterar el certificado histórico.

## Validación ejecutada

- Backend completo: **318 pruebas, 1.988 aserciones, aprobadas** en `scl_manual_pages_test`.
- Auditor documental real: **344 fichas, 344 páginas únicas, 3.115 funciones**, aprobado contra los checksums originales.
- Prueba de aplicación del manifiesto final: **2 pruebas, 17 aserciones**, aprobadas; simulación, aplicación 344, idempotencia, fuente intacta y protección SQL.
- Frontend completo: **138 pruebas en 24 archivos**, aprobadas. ESLint, TypeScript y Vite build aprobados.
- E2E completa: **45 casos distintos verificados**. La corrida completa obtuvo 44 aprobados y un fallo del entorno: build con URL localhost incompatible con CSP. Se reconstruyó con `VITE_API_BASE_URL=/api/v1`; el caso CSP y el escenario documental se repitieron y aprobaron (2/2). No quedan fallos pendientes. Se verificaron autoservicio, snapshot/PDF, selección, edición, IDOR, pagos/concurrencia, escala, identidades, MFA, CSRF, sesiones y CSP.
- QA: vista previa revisada visualmente en 360, 768, 1024 y 1440 px; sin desbordamiento horizontal en navegador y navegación por teclado comprobada. En pantallas pequeñas, la ficha/página también aparece debajo del selector y en la vista previa, para evitar depender del ancho de la opción nativa.
- Los fallos iniciales de fixtures/pruebas se corrigieron y se repitieron los controles: evidencia documental sintética para pruebas existentes, actualización de etiquetas/valores, configuración CORS del entorno aislado, conservación de la credencial del fixture incompleto y liberación del PDF mediante su loading task. No se cambió auth para hacer pasar E2E.

## Integridad de CT dev y despliegue local

Aplicada exclusivamente la migración documental aditiva y el manifiesto a **CT dev, versión 10**, con actor administrativo 1. Simulación previa: 344 actualizaciones; aplicación: 344; repetición: 0. Resultado SQL: 344 fichas, 344 páginas distintas, 344 evidencias verificadas. MF-0343=664; MF-0344=665, conservando su referencia de extracción original 664. Todos los fingerprints antes/después son idénticos: fuente normativa, funciones, 14 vinculaciones, relaciones, 18 certificados completos, escala, pagos e identidades. El digest normativo continúa siendo `2ccdb15add1bedbe5c83aa978cf4fa45`.

La comprobación de dominio sobre CT dev recorrió las 50 combinaciones, aceptó sus **123 candidatas explícitas compatibles** y rechazó los 50 intentos sin ID. La resolución de las 14 vinculaciones existentes produce 10 resoluciones exactas, tres bloqueos de ficha incompleta MF-0167 y un bloqueo ambiguo de asignación sin ficha explícita; no hay bloqueo global de certificados. Dos vinculaciones tienen unidad estructurada y doce conservan su condición legada sin unidad. No se deduce su dependencia ni se cambia su ficha automáticamente.

`scripts/audit_manual_database_integrity.sql` emite únicamente conteos y fingerprints, sin datos personales ni textos de funciones. Compara fuente, funciones, vinculaciones, relaciones, certificados completos, escala, pagos e identidades. Digest normativo esperado: `2ccdb15add1bedbe5c83aa978cf4fa45`.

Las pruebas se ejecutan en bases aisladas `scl_manual_pages_test` y `scl_manual_pages_e2e`, con contenedores propios. Solo se reinicializó el esquema sintético E2E; **no se ejecutó fresh, truncate, reset ni rebootstrap en CT dev**. La migración salarial preexistente deja funciones PostgreSQL al hacer fresh; se recreó únicamente el esquema de la base E2E aislada y se ejecutaron las migraciones completas, sin modificar lógica salarial.

## Evidencia y Git

Rama solicitada: `fix/manual-ficha-page-resolution`. Partida: `47131067071ca1725063e276354823aaae15f81e` del repositorio canónico. Se trabajó en un worktree aislado para preservar los cambios locales de rendimiento/configuración del usuario. Commit final: consultar `git log -1 --format=%H`; el identificador se entrega en el reporte final del chat. **No push**.

Evidencias locales: `output/manual-ficha-page-audit/` (fingerprints antes/después y diagnóstico) y `frontend/react-app/evidence/dependency-position-selection/` (capturas y resultados de regresión). Capturas específicas nuevas: `07-first-profile-preview.png`, `08-preview-360.png`, `08-preview-768.png`, `08-preview-1024.png`, `08-preview-1440.png`, `09-exact-profile-certificate.png`. Todas muestran datos sintéticos.

Archivos de esta entrega (sin los cambios ajenos del checkout canónico):

- `MANUAL_FICHA_PAGE_AUDIT.md`
- `MANUAL_FICHA_RESOLUTION_REPORT.md`
- `backend/laravel-app/app/Console/Commands/PrepareAutoserviceE2E.php`
- `backend/laravel-app/app/Console/Commands/PrepareSalaryScaleE2E.php`
- `backend/laravel-app/app/Console/Commands/VerificarPaginasManualCommand.php`
- `backend/laravel-app/app/Http/Controllers/Api/V1/DependenciaManualController.php`
- `backend/laravel-app/app/Http/Controllers/Api/V1/FuncionarioController.php`
- `backend/laravel-app/app/Http/Requests/StoreFuncionarioRequest.php`
- `backend/laravel-app/app/Http/Requests/UpdateFuncionarioRequest.php`
- `backend/laravel-app/app/Http/Resources/FuncionarioResource.php`
- `backend/laravel-app/app/Models/ManualCargoVersion.php`
- `backend/laravel-app/app/Services/ResolverFuncionesFuncionarioService.php`
- `backend/laravel-app/app/Services/SeleccionarFichaManualService.php`
- `backend/laravel-app/app/Services/VerificarPaginasManualService.php`
- `backend/laravel-app/database/data/manual_paginas_decreto_015_2023_verificadas.json`
- `backend/laravel-app/database/migrations/2026_10_12_000001_add_manual_documentary_pages.php`
- `backend/laravel-app/routes/api.php`
- `backend/laravel-app/tests/Feature/DependencyPositionSelectionTest.php`
- `backend/laravel-app/tests/Feature/ManualDocumentaryPagesTest.php`
- `backend/laravel-app/tests/Feature/ManualImportTest.php`
- `backend/laravel-app/tests/Feature/ManualVersioningTest.php`
- `backend/laravel-app/tests/Support/ManualFixture.php`
- `frontend/react-app/e2e/autoservice.spec.ts`
- `frontend/react-app/e2e/dependency-position-selection.spec.ts`
- `frontend/react-app/e2e/dependency-selection.ts`
- `frontend/react-app/evidence/dependency-position-selection/01-create-employee-dependency.png`
- `frontend/react-app/evidence/dependency-position-selection/02-position-filtered.png`
- `frontend/react-app/evidence/dependency-position-selection/03-position-search.png`
- `frontend/react-app/evidence/dependency-position-selection/04-edit-employee.png`
- `frontend/react-app/evidence/dependency-position-selection/05-mobile.png`
- `frontend/react-app/evidence/dependency-position-selection/06-tablet.png`
- `frontend/react-app/evidence/dependency-position-selection/07-first-profile-preview.png`
- `frontend/react-app/evidence/dependency-position-selection/08-preview-1024.png`
- `frontend/react-app/evidence/dependency-position-selection/08-preview-1440.png`
- `frontend/react-app/evidence/dependency-position-selection/08-preview-360.png`
- `frontend/react-app/evidence/dependency-position-selection/08-preview-768.png`
- `frontend/react-app/evidence/dependency-position-selection/09-exact-profile-certificate.png`
- `frontend/react-app/evidence/dependency-position-selection/backend-tests.txt`
- `frontend/react-app/evidence/dependency-position-selection/e2e-tests.txt`
- `frontend/react-app/evidence/dependency-position-selection/frontend-build.txt`
- `frontend/react-app/evidence/dependency-position-selection/frontend-tests.txt`
- `frontend/react-app/evidence/dependency-position-selection/manual-integrity.txt`
- `frontend/react-app/evidence/manual-ambiguity-ux/01-ambiguous-position.png`
- `frontend/react-app/evidence/manual-ambiguity-ux/02-choose-another-position.png`
- `frontend/react-app/evidence/manual-ambiguity-ux/03-resolved-position.png`
- `frontend/react-app/evidence/manual-ambiguity-ux/04-network-retry.png`
- `frontend/react-app/evidence/manual-ambiguity-ux/05-mobile.png`
- `frontend/react-app/src/features/funcionarios/components/DependenciaSelect.tsx`
- `frontend/react-app/src/features/funcionarios/components/FuncionarioForm.test.tsx`
- `frontend/react-app/src/features/funcionarios/components/FuncionarioForm.tsx`
- `frontend/react-app/src/features/funcionarios/schemas/funcionario.schema.ts`
- `frontend/react-app/src/features/funcionarios/services/dependencias.service.test.ts`
- `frontend/react-app/src/features/funcionarios/services/dependencias.service.ts`
- `frontend/react-app/src/features/funcionarios/services/funcionarios.service.test.ts`
- `frontend/react-app/src/features/funcionarios/services/funcionarios.service.ts`
- `frontend/react-app/src/features/funcionarios/services/manualSelectionError.ts`
- `frontend/react-app/src/features/funcionarios/types/funcionario.types.ts`
- `frontend/react-app/src/lib/utils/errors.ts`
- `scripts/audit_manual_database_integrity.sql`
- `scripts/audit_manual_source_pages.py`

Confirmaciones: no se inventaron páginas ni funciones; no se modificó contenido normativo del Manual; no se seleccionan candidatas arbitrariamente; no se alteraron certificados históricos; no se modificaron escala salarial institucional, pagos ni autorizaciones, auth ni MFA; no se inició 3B.2; no push. El único cambio en `PrepareSalaryScaleE2E` amplía la lista exacta de bases sintéticas permitidas para ejecutar la regresión aislada y no modifica valores ni reglas salariales.
