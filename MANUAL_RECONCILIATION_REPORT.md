# Resultado de conciliación del Manual de Funciones

**Recomendación: NO PUBLICABLE.** La comparación Excel/JSON está concluida: 344 fichas, 3.115 funciones y 2.326 conocimientos coinciden. El contraste documental puntual identifica el área omitida de MF-0167 y un propósito que requiere revisión; la vigencia permanece sin confirmar. La versión interna 10 continúa en **borrador**.

No se aplicó la propuesta a `scl_db`, no se publicó el Manual y no se habilitaron certificados oficiales. Los originales Excel, JSON y PDF permanecen intactos.

| Entrega solicitada | Resultado |
|---|---|
| 1. Excel localizado | Original `C:/Users/mondr/Downloads/manual_funciones_decreto_015_2023.xlsx`; 461.321 bytes. Copia idéntica en `backend/laravel-app/database/data/manual_funciones_decreto_015_2023.xlsx`. No había otro candidato relacionado en la búsqueda recursiva inicial del repositorio. |
| 2. SHA-256 | `bb3184d3e49027c58a4829c8c9a689d8873c36c4ecfa06390c8339e4580a3c7c`. Hashes JSON y PDF en la inspección y metadata del derivado. |
| 3. Hojas | LEEME, Perfiles_Cargo, Funciones, Conocimientos. |
| 4. Esquema | LEEME: 9×2, título combinado A1:B1; Perfiles_Cargo: 345×14; Funciones: 3.116×13; Conocimientos: 2.327×8. Las tres tablas incluyen una fila de encabezados. Encabezados, tipos, vacíos, fórmulas y tres ejemplos: MANUAL_SOURCE_INSPECTION.md. Cero fórmulas, cero combinaciones en hojas de datos. |
| 5. Registros | 344 fichas; 3.115 funciones; 2.326 conocimientos; 50 cargos genéricos ya importados. |
| 6. Excel/JSON | 344 COINCIDE; 0 SOLO_JSON, SOLO_EXCEL, DIFERENCIA_CAMPO, DIFERENCIA_FUNCIONES y AMBIGUA. Comprobación estricta, sin borrar diferencias mediante normalización textual. |
| 7. PDF puntual | MF-0167 pp.362–364; repetidos MF-0001 pp.14–15, MF-0047 p.117 y MF-0154 p.336; fichas técnicas pp.478/480 y acto/vigencia pp.1,10,666–667. No se hizo OCR ni consulta externa ni comparación exhaustiva de 667 páginas. |
| 8. Discrepancias | Cero entre Excel/JSON. Omisiones compartidas frente al PDF: área y propósito de MF-0167, secciones complementarias y funciones comunes. Tres pares de funciones con texto repetido ya presentes documentalmente; se conservan. |
| 9. MF-0167 | Excel J168/K168 vacíos. Área corroborada en PDF p.362; solo se propone esa corrección. El párrafo bajo el área no tiene encabezado III y se conserva como evidencia pendiente; propósito sigue vacío. |
| 10. Funciones comunes | Ausentes en Excel/JSON; presentes en artículo 2 del PDF. Cero filas nuevas; modelo, resolver y snapshot conservan la separación. Impresión automática sigue pendiente. |
| 11. Campos adicionales | Competencias, formación, experiencia y equivalencias no están en Excel ni JSON. Conocimientos íntegros en DTO/JSONB; metadata de conciliación, hashes, filas Excel y LEEME se preservan en trazabilidad. No se inventaron campos documentales. |
| 12. Vigencia | VIGENCIA_NO_CONFIRMADA: art.10 rige desde expedición y el espacio de expedición está vacío. Portada 13/01/2023 no se adopta como vigencia. acto_fecha/vigencia_desde/vigencia_hasta siguen NULL. |
| 13. Publicación | NO PUBLICABLE. Sin transición de borrador a publicado. |
| 14. Derivado | [manual_funciones_decreto_015_2023_reconciliado.json](backend/laravel-app/database/data/manual_funciones_decreto_015_2023_reconciliado.json), envoltura metadata + fichas, esquema manual-reconciliado-v1, estado propuesta_no_aplicada. Una corrección de área, cero cambios de funciones. Prepararlo dos veces mantiene bytes/hash y fecha. |
| 15. Dry-run | 0 nuevas; 1 actualización propuesta (MF-0167); 343 sin cambios; 0 cargos nuevos; 0 functions diff; 1 error de protección de identidad, MANUAL_SOURCE_ID_REASIGNADO. Resultado rechazado, cero escrituras. |
| 16. Backend | Antes: 103 tests / 533 assertions. Después: **117 tests completos pasan**, incluidas 14 pruebas de conciliación nuevas. Evidencia detallada tmp/reconciliation-backend-debug.txt; salida PHPUnit 0. |
| 17. Frontend | **15 tests pasan, 9 archivos**. No se modificó UI en esta conciliación. |
| 18. Build | **Correcto**, TypeScript y Vite. Advertencia de bundle JS de 531,02 kB (>500 kB); no bloquea el build. |
| 19. Docker | docker version responde; app, nginx, frontend y postgres **healthy** antes y después. Se conservó la infraestructura existente, sin reconstrucciones ni eliminación de volúmenes. |
| 20. No pérdida de datos | 344 fichas/3.115 funciones siguen iguales al JSON original; 3 asignaciones y 3 snapshots iguales por PK; los 3 PDF conservan SHA-256; auditorías de importación permanecen 2→2; todas las fuentes originales intactas. Ver MANUAL_RECONCILIATION_VERIFICATION.json. |

## Adaptación realizada

`ExcelManualParser` lee el XLSX observado con PharData/SimpleXML, sin guardar el archivo ni agregar dependencias. Usa nombres de encabezados, valida cruces entre hojas y rechaza fórmulas, huérfanos, IDs duplicados, hojas o campos desconocidos; no descarta contenido silenciosamente. Código/grado conservan strings y ceros; distingue cadenas OOXML vacías de número fuente null. La inspección detallada de celdas es opcional para limitar memoria al comparar.

`JsonManualParser` mantiene la lista original y admite la envoltura de propuesta con validación de metadata, hashes declarados y conteos. Los metadatos permanecen en la trazabilidad del DTO; no alteran el contenido original de las fichas. El comprobador independiente verifica esos hashes contra los archivos locales.

`CompararManualesService` realiza matching por campos descriptivos y anclas documentales, con verificación de exclusividad. La comparación recursiva informa texto, posición, número, grupo y ausencia. `manual:compare` expone el resultado sin acceso de escritura a BD. Las funciones repetidas se reportan, no se eliminan.

`ImportarManualFuncionesService` añade listas de source IDs y functions_diff al preflight. Una propuesta reconciliada no puede importarse, incluso en una BD vacía: devuelve MANUAL_RECONCILIACION_SOLO_DRY_RUN. No se relajó la protección de identidad ni la inmutabilidad de versiones publicadas. El cambio de área de MF-0167 altera su natural_key y por eso se rechaza en el dry-run de la versión existente. **Fichas actualizadas=1 indica diferencia propuesta, no una escritura realizada.**

No se crearon tablas, columnas, constraints ni migraciones en esta conciliación. La integración anterior mantiene PK interna de ficha, source_id por versión, funciones normalizadas y FK explícita `funcionario_cargo.manual_cargo_version_id`; no vuelve a resolver funciones por código/grado.

## Evidencia y comandos

- [Inspección completa y matriz](MANUAL_SOURCE_INSPECTION.md).
- [Comparación con tabla JSON / Excel / PDF](MANUAL_IMPORT_COMPARISON.md).
- [Detalle de las 344 fichas](MANUAL_RECONCILIATION_DETAILS.json).
- [Dry-run íntegro](MANUAL_RECONCILIATION_DRY_RUN.json), incluidas 343 source IDs sin cambios, MF-0167 actualizada, funciones y advertencias.
- [Verificación de fuentes, BD y PDF](MANUAL_RECONCILIATION_VERIFICATION.json).

```sh
docker exec scl_app php artisan manual:compare database/data/manual_funciones_decreto_015_2023.xlsx database/data/manual_funciones_decreto_015_2023.json
docker exec scl_app php artisan manual:import database/data/manual_funciones_decreto_015_2023_reconciliado.json --version-id=10 --dry-run
docker exec -e APP_ENV=testing -e DB_HOST=postgres -e DB_DATABASE=scl_db_test -e CACHE_STORE=array -e SESSION_DRIVER=array scl_app php vendor/bin/phpunit --colors=never
docker exec scl_frontend npm test -- --run
docker exec scl_frontend npm run build
```

El segundo comando devuelve error de dominio esperado; no debe interpretarse como autorización para omitir las protecciones. Las pruebas usan exclusivamente `scl_db_test` con transacciones y sin reinicio destructivo de migraciones. Se usó `--debug` para registrar el resultado individual de los 117 tests. Pint pasa en los siete archivos PHP incorporados/modificados en esta conciliación.

Los scripts Python `reconcile_manual_sources.py` (primero informes, luego `--prepare-proposal`) y `verify_manual_reconciliation.py` permiten repetir la preparación y verificación. Los generadores históricos ahora se detienen si ya existe una conciliación, para no reemplazar los informes nuevos por el estado antiguo «Excel no localizado».

## Certificados anteriores y decisión pendiente

Se verificaron los PDF existentes sin regenerarlos: MF-0001, 58 funciones/6 páginas; MF-0228 Jurídica, 10 funciones/2 páginas; MF-0229 Riesgo, 6 funciones/2 páginas. Las dos últimas fichas comparten 367-05 y mantienen funciones distintas. Los tres conservan **CERTIFICADO LABORAL TEMPORAL**, aviso SIN VALIDEZ OFICIAL y snapshot v2 con ficha/versión y grupos de funciones separados.

Para una futura publicación hace falta confirmar documentalmente la expedición/vigencia y resolver el propósito de MF-0167. La corrección propuesta del área requiere un flujo explícito posterior que preserve la identidad y el historial. La preparación de este archivo no constituye aprobación ni publicación.
