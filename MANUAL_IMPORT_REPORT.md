# Informe de importación del Manual de Funciones

Fecha de trabajo: 4 de septiembre de 2026. Proyecto: `labor-certification-system`.

## Resultado y límite documental

Integración técnica implementada y demostrada en desarrollo: el certificado resuelve **funcionario → asignación vigente → ficha explícita → versión → funciones**. La generación ya no utiliza `ResolverFuncionesCargoService` ni busca funciones por código/grado. Se importaron **344 fichas y 3.115 funciones**, con **50 cargos genéricos**, y se generaron **3 certificados temporales** desde el origen real.

**La carga permanece en borrador; no se declara finalizada la conciliación documental ni habilitada la expedición oficial.** Excel/JSON ya están conciliados (344 fichas iguales). Ambos omiten campos presentes en PDF, MF-0167 requiere revisión y no hay fecha de expedición/vigencia confirmada. Resultado actual: **NO PUBLICABLE**; véase MANUAL_RECONCILIATION_REPORT.md. Las demostraciones tienen identidades ficticias y una marca de prueba que únicamente funciona en entornos `local`/`testing`.

## 1–8. Archivos, esquemas, cantidades y diferencias

| Entregable | Resultado |
|---|---|
| 1. Excel | Localizado en `C:/Users/mondr/Downloads/manual_funciones_decreto_015_2023.xlsx`; copia idéntica en `backend/laravel-app/database/data/`. SHA-256 y esquema en MANUAL_SOURCE_INSPECTION.md. |
| 2. JSON original | `C:/Users/mondr/Downloads/manual_funciones_decreto_015_2023.json` |
| JSON en el proyecto | `backend/laravel-app/database/data/manual_funciones_decreto_015_2023.json`, copia idéntica byte a byte del original, sin corregirlo. |
| PDF de referencia | `C:/Users/mondr/Downloads/DECRETO   015 DE 2023 MANUAL DE FUNCIONES.pdf`, 667 páginas, externo al repositorio. |
| 3. Esquema Excel | LEEME 9×2; Perfiles_Cargo 345×14; Funciones 3.116×13; Conocimientos 2.327×8. Inspección completa y tres ejemplos verificados disponibles. |
| 4. Esquema JSON | Array raíz de 344 objetos con las mismas 16 propiedades; dos arrays anidados: `funciones` y `conocimientos`. Sin metadatos de versión raíz. |
| 5. Fichas | 344, con 344 `perfil_id` distintos. |
| 6. Funciones | 3.115, todas en filas normalizadas, sin vacíos, orden consecutivo por ficha. |
| Conocimientos | 2326 elementos conservados en JSONB. |
| 7. Campos | `perfil_id`, `registro_decreto`, `nivel`, `denominacion`, `codigo`, `grado`, `numero_cargos`, `dependencia`, `jefe_inmediato`, `area_funcional`, `proposito_principal`, `pagina_inicio`, `pagina_fin`, `funciones`, `conocimientos`, `fuente`. |
| 8. Diferencias Excel/JSON | **344 fichas COINCIDE; 0 diferencias de campos/funciones, 0 exclusivas, 0 ambiguas**. Contraste PDF puntual en `MANUAL_IMPORT_COMPARISON.md`. |

Tipos: strings en todos los campos escalares salvo `registro_decreto`, `pagina_inicio`, `pagina_fin` (enteros). `funciones[]` contiene `orden` entero, `texto` string, `grupo` string y `numero_fuente` entero o null (solo la función 58 de MF-0001 carece de número de fuente). `conocimientos[]` contiene `numero` entero y `texto` string. Las funciones de MF-0001 tienen grupos y numeraciones de origen que reinician; se conservan ambos sin confundirlos con el orden global.

La inspección completa, la matriz **Campo Manual / Campo JSON / Campo Excel / Obligatorio / Uso aplicación** y **tres ejemplos con campos completos y arrays abreviados** se encuentran en [MANUAL_SOURCE_INSPECTION.md](MANUAL_SOURCE_INSPECTION.md).

Comprobaciones puntuales del PDF: página 14 (MF-0001), página 480 (MF-0229), página 12 (funciones comunes), portada y página 667. No se ejecutó OCR ni reconstrucción masiva. El PDF contiene competencias, formación y experiencia que faltan en el JSON; también contiene funciones comunes por nivel. No se añadieron datos supuestos a la fuente.

## 9–12. Modelo, migración e identidad

Se encontró `manual_version_cargo_unique = UNIQUE(manual_funciones_version_id, cargo_id)`. Era incompatible con las fichas reales: el mismo cargo tiene varias áreas y, en ocasiones, varias fichas dentro de la misma área. La migración conserva `manual_cargo_versiones` como ficha específica y elimina esa restricción después de añadir identidades alternativas. No crea otra tabla paralela de fichas.

Migración creada y aplicada en `scl_db_test` y `scl_db`:
`backend/laravel-app/database/migrations/2026_09_04_000001_adapt_manual_employment_profiles.php`.

| Tabla | Cambio |
|---|---|
| `cargos` | Se conserva el catálogo y su `UNIQUE(codigo, grado)`. Código varchar(10), grado varchar(5), ya eran strings. No se cambian a enteros. |
| `manual_funciones_versiones` | `acto_fecha` y `vigencia_desde` permiten null documental; nuevo `metadata_manual JSONB`. Se conservan referencias, estado y unique manual/versión. |
| `manual_cargo_versiones` | Nuevos `source_id varchar(100)`, `import_key/content_hash/natural_key varchar(64)`, denominación de fuente, dependencia, área, número de cargos, jefe inmediato (text), `metadata_manual JSONB`. Propósito y PK existentes conservados. |
| Restricciones de ficha | `UNIQUE(versión, import_key)`, `UNIQUE(versión, source_id)` y `UNIQUE(id, cargo_id)`. `natural_key` indexado, **no único**. Se retira `UNIQUE(versión,cargo)`. |
| `manual_funciones_esenciales` | Conserva FK, `orden`, `descripcion`, unique ficha/orden y check orden positivo. Nuevos `numero_fuente` nullable y `grupo`. |
| `funcionario_cargo` | Nueva FK compuesta `(manual_cargo_version_id, cargo_id) → ficha(id, cargo_id)`, restrict delete, nullable para historial existente. Nuevo `es_prueba_manual=false` para la demostración local; prohibido en peticiones HTTP. |
| `manual_funciones_comunes_nivel` | Nueva tabla vacía preparada para versión/nivel/orden/descripción/metadata, unique versión/nivel/orden. No hay funciones comunes inventadas. |
| `manual_importaciones` | Archivo, SHA-256, versión, fecha, cantidad, importador/usuario, resultado y detalle JSONB; contiene 2 importaciones reales exitosas. |

Identidad interna: PK numérica de ficha, estable e independiente de IDs externos. Identidad de importación: `(version_id, SHA-256(namespace=manual-perfil-v1, perfil_id))`; `source_id=perfil_id` es único dentro de la versión. `natural_key` registra código, grado, denominación literal, nivel, área y dependencia. Hay **45 grupos repetidos aun con esa identidad descriptiva**: no se fusionan.

`perfil_id` es secuencial y derivado, no una identidad oficial garantizada entre futuras extracciones. Se verifica que no cambien los campos de identidad, `registro_decreto` ni `pagina_inicio` bajo el mismo source_id. Una renumeración, retirada de fichas o funciones exige revisar la fuente o usar una nueva versión. El checksum SHA-256 canónico del contenido permite actualizar texto en borrador sin cambiar PKs; las versiones publicadas/inactivas rechazan cambios, aunque admiten una reimportación idéntica. Se compara también el contenido normalizado persistido, no solamente el checksum almacenado.

Las 73 variantes literales de denominación/nivel corresponden a 50 pares código/grado. El DTO retira únicamente el sufijo exacto del propio código/grado observado (`Técnico Administrativo 367-05` → `Técnico Administrativo`, también `Técnico Operativo 314`). **El literal original se conserva íntegro en la ficha y JSONB**. Una incompatibilidad adicional de denominación/nivel en el catálogo se rechaza.

El rollback automático se bloquea para evitar reintroducir una restricción incompatible o perder metadatos. La reversión, si se necesita, requiere otra migración incremental revisada. Las PK existentes reciben una identidad legacy determinista sin cambiar sus relaciones.

## 13–15. Dry-run, carga y ambigüedades

El preflight se mostró antes de modificar `scl_db`. La migración también se inspeccionó con `--pretend` y se probó primero en la base de tests.

| Métrica | Dry-run `scl_db` | Primera carga | Segunda carga |
|---|---:|---:|---:|
| Registros | 344 | 344 | 344 |
| Funciones | 3.115 | 3.115 | 3.115 |
| Cargos existentes encontrados | 1 | 1 | 50 |
| Cargos nuevos | 49 | 49 | 0 |
| Fichas nuevas / sin coincidencia previa | 344 | 344 | 0 |
| Fichas actualizadas | 0 | 0 | 0 |
| Fichas sin cambios | 0 | 0 | 344 |
| IDs duplicados | 0 | 0 | 0 |
| Errores | 0 | 0 | 0 |

Advertencias: 45 grupos con identidad descriptiva repetida, dos campos vacíos en MF-0167 y ausencia de secciones del Manual. La comparación Excel posterior ya confirmó igualdad de ambas fuentes estructuradas. **MF-0167** conserva área y propósito vacíos; el resolver la bloquea como `MANUAL_FICHA_INCOMPLETA`. MF-0001 conserva conocimientos como array vacío. Las coincidencias descriptivas no son duplicados de importación ni autorizan elegir una ficha por proximidad.

SHA-256 exacto del JSON: `261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e`.

Importador desacoplado: `JsonManualParser → FichaManualData → ImportarManualFuncionesService → BD`. Comando:

```sh
php artisan manual:import database/data/manual_funciones_decreto_015_2023.json --dry-run
php artisan manual:import database/data/manual_funciones_decreto_015_2023.json
php artisan manual:import database/data/manual_funciones_decreto_015_2023.json --version-id=10 --dry-run
```

Existe `--user-id` opcional. El dry-run no crea auditorías ni consume secuencias. La escritura real se realiza en transacción, con bloqueo de importación y de versión; las operaciones administrativas de edición/publicación bloquean esa misma versión. Los intentos rechazados de archivos legibles quedan auditados. No se eliminan fichas ni funciones durante una reimportación.

Versión interna ID **10**, identificador **Decreto 1000-24/015 de 2023**, estado **borrador**, acto **Decreto 1000-24/015**. Es un identificador técnico basado en el acto, no una numeración documental añadida. La portada muestra **2023-01-13**, guardada como `metadata_manual.fecha_portada`. La página final dice que rige desde expedición, pero su fecha está en blanco; `acto_fecha`, `vigencia_desde` y `vigencia_hasta` permanecen null. No se publica automáticamente.

## 16–17. Funcionarios de desarrollo y fichas distintas

`php artisan manual:demo --generar` crea/reutiliza únicamente cuentas ficticias de desarrollo, con contraseñas aleatorias y marca explícita de prueba. No usa cédulas reales ni salarios inventados. Fechas de asignación de hoy corresponden a datos de prueba, no a la vigencia documental.

| Documento ficticio | Funcionario ID | Cargo ID | Ficha interna / source_id | Área | Funciones | Certificado ID |
|---|---:|---:|---|---|---:|---:|
| TEST-MANUAL-0001 | 65 | 65 | 9 / MF-0001 | Despacho del Alcalde | 58 | 31 |
| TEST-MANUAL-0228 | 66 | 91 | 236 / MF-0228 | Oficina Asesora Jurídica | 10 | 32 |
| TEST-MANUAL-0229 | 67 | 91 | 237 / MF-0229 | Oficina de Gestión del Riesgo | 6 | 33 |

MF-0001 tiene el código/grado único 005/03. MF-0228 y MF-0229 comparten **el mismo cargo interno 91, Técnico Administrativo 367/05**, pero tienen fichas internas 236 y 237.

- MF-0228, primera función: Apoyar la implementación del sistema de gestión documental de la dependencia, teniendo en cuenta las normas vigentes y procedimientos establecidos.
- MF-0229, primera función: Administrar la correspondencia interna y externa de la dependencia, siguiendo el sistema de gestión documental de la entidad.

El administrador puede seleccionar la ficha al crear/editar funcionario. Se muestran denominación, código/grado, dependencia, área, source_id, propósito y estado. Solo una ficha vigente compatible puede preseleccionarse; varias exigen selección explícita. Cambiar a otra ficha del mismo cargo cierra la asignación anterior y conserva su ficha. Un cambio conflictivo el mismo día devuelve un error para revisión del historial. La FK compuesta impide asociar la ficha de otro cargo incluso fuera de la API.

## 18–19. Pruebas backend y frontend

| Verificación | Antes | Después |
|---|---|---|
| Backend PostgreSQL | 82 tests, 398 assertions | **103 tests, 533 assertions; todos pasan** |
| Frontend Vitest | 11 tests, 7 archivos | **15 tests, 9 archivos; todos pasan** |
| Frontend TypeScript + Vite | — | Build exitoso |

Se agregaron 21 pruebas backend para importación válida/inválida, tipos de código, orden, dry-run sin escrituras, idempotencia y estabilidad de IDs, actualización en borrador, inmutabilidad de versión publicada, renumeración/duplicados, identidad por ficha, resolución independiente, cero/dos/una coincidencia sin asignación, FK, historial, snapshot/PDF, fuentes incompletas, funciones comunes separadas, prechecks y autoservicio HTTP. Las preparaciones antiguas de tests ahora incluyen una ficha sintética explícita; los tests de ausencia/ambigüedad siguen verificando esos errores, sin crearles una asignación accidentalmente.

Frontend: selección explícita de fichas homónimas, cero fichas, conservación de la selección y aviso de borrador; selección de certificación con funciones y envío por autoservicio sin `funcionario_id` ni ficha aportados por el solicitante. El formulario de autoservicio permite elegir **Información laboral y funciones del Manual** además de la modalidad salarial.

Las pruebas no usan migraciones destructivas. `tests/bootstrap.php` exige una BD `*_test` y desactiva el reinicio destructivo de `RefreshDatabase`; se prepara con migraciones incrementales y cada test usa transacciones. Comandos reproducibles:

```sh
docker compose exec -e DB_DATABASE=scl_db_test app php artisan migrate --force
docker compose exec -e APP_ENV=testing -e DB_HOST=postgres -e DB_DATABASE=scl_db_test -e CACHE_STORE=array -e SESSION_DRIVER=array app php vendor/bin/phpunit
docker compose exec frontend npm test
docker compose exec frontend npm run build
```

## 20–21. PDF automático, precheck y snapshot

Flujo: identidad autenticada → funcionario → asignación por fecha → ficha FK explícita → versión → funciones específicas/comunes separadas → salario cuando se requiere → snapshot v2 → PDF. No requiere administrador ni secretario durante autoservicio.

Prechecks: usuario/funcionario activos, contraseña cambiada, asignación única vigente, cargo activo, ficha asignada/coherente/completa, versión vigente publicada (salvo prueba local explícita), funciones cuando se solicitan, cupo mensual, pago no requerido o confirmado y salario resoluble. Errores concretos: `PASSWORD_CHANGE_REQUIRED`, `INACTIVE_EMPLOYEE`, `ASIGNACION_NO_VIGENTE`, `ASIGNACION_AMBIGUA`, `CARGO_INVALIDO`, `MANUAL_FICHA_NO_ENCONTRADA`, `MANUAL_FICHA_AMBIGUA`, `MANUAL_FICHA_NO_ASIGNADA`, `MANUAL_FICHA_INCOMPATIBLE`, `MANUAL_FICHA_INCOMPLETA`, `MANUAL_VERSION_NO_VIGENTE`, `MANUAL_FUNCIONES_NO_DISPONIBLES`, `MONTHLY_CERTIFICATE_LIMIT`, `PAGO_NO_CONFIRMADO`, `SALARIO_NO_RESOLUBLE`, `SALARIO_AMBIGUO`. Si falla una fuente no se emite PDF ni se consume cupo.

La prueba HTTP real en localhost con las cuentas ficticias MF-0228/MF-0229 confirmó login y disponibilidad 200; un segundo pedido SIN salario devuelve `MONTHLY_CERTIFICATE_LIMIT`; CON salario sin fuente salarial devuelve `SALARIO_NO_RESOLUBLE`. Permanecen solo tres solicitudes SIN salario. Las cuentas se cerraron al finalizar la prueba HTTP.

Snapshot v2 guarda `manual` (manual_id, version_id, ficha_id, acto, área, dependencia, propósito, source_id/checksum y vigencias), `funciones_especificas`, `funciones_comunes`, `asignacion_id`, cargo y funcionario, modalidad, fecha, generador y salario cuando corresponda. Conserva `manual_funciones` para compatibilidad de la plantilla con certificados de funciones. Los certificados anteriores no se recalculan y las pruebas confirman que editar una función o el catálogo después no altera snapshot ni bytes del PDF emitido.

Funciones comunes: tabla preparada pero **0 filas**, porque el JSON no las proporciona. El resolver entrega ambos grupos y un estado `pendientes_de_fuente`; `imprimir_funciones_comunes=false`. La decisión de imprimirlas automáticamente sigue pendiente del formato institucional, explícitamente documentada.

PDF temporal ahora pagina el contenido y usa WinAnsi para preservar tildes del origen. Se renderizaron y revisaron las 10 páginas generadas. Una comprobación independiente con pypdf encontró **todas las funciones completas** en cada PDF; se revisaron composición, márgenes y ausencia de recortes. Mantienen `CERTIFICADO LABORAL TEMPORAL` y `PRUEBA DE DESARROLLO - MANUAL BORRADOR, VIGENCIA PENDIENTE. SIN VALIDEZ OFICIAL`.

| Archivo entregable | Páginas | Funciones completas verificadas |
|---|---:|---:|
| [PDF MF-0001](output/pdf/manual-demo/MF-0001.pdf) | 6 | 58 |
| [PDF MF-0228](output/pdf/manual-demo/MF-0228.pdf) | 2 | 10 |
| [PDF MF-0229](output/pdf/manual-demo/MF-0229.pdf) | 2 | 6 |

Los PDF y snapshots originales persistidos se conservan en `backend/laravel-app/storage/app/private/manual-demo/`. Las credenciales de desarrollo están en `acceso-MF-xxxx.json` dentro de esa carpeta privada, excluida de Git; no aparecen en este informe.

## 22. Conservación de datos

Antes: 1 cargo, 1 funcionario, 0 asignaciones, 0 manuales/versiones/fichas/funciones/certificados. Después: 50 cargos, 4 funcionarios (tres de prueba), 3 asignaciones de prueba, 1 manual, 1 versión borrador, 344 fichas, 3.115 funciones, 0 funciones comunes, 3 certificados y 2 importaciones.

Se verificó igualdad completa antes/después de todos los registros preexistentes de cargos y funcionarios; las otras tablas indicadas estaban vacías. El archivo JSON sigue idéntico byte a byte al original de Descargas. Una comparación independiente de las 344 fichas guardadas confirmó igualdad del JSONB completo y de cada una de las 3.115 funciones normalizadas (texto, orden, grupo, número fuente). **No se descartó información que existiera en el JSON**; no se afirma que el JSON contenga todo el PDF.

Respaldo previo de PostgreSQL: `tmp/scl_manual_before.dump` (privado/ignorado por Git). No se ejecutaron `migrate:fresh`, `db:wipe`, `TRUNCATE`, `docker compose down -v` ni eliminaciones masivas. Tampoco se reemplazaron los cambios anteriores del usuario en el árbol de trabajo. Se mantuvieron los archivos de conflicto `-camilo_ortega` sin usarlos como código activo.

## 23. Informe final y pendientes

Informe: `MANUAL_IMPORT_REPORT.md` en la raíz del proyecto. Inspección: `MANUAL_SOURCE_INSPECTION.md`. Comparación: `MANUAL_IMPORT_COMPARISON.md`. Evidencia de conteos/importaciones/tests/HTTP/PDF en `tmp/manual-*`, `tmp/backend-final.txt` y `tmp/frontend-final-*`; son artefactos locales ignorados.

Pendientes documentales antes del uso ordinario:

1. Excel localizado y comparado: 344 fichas iguales. Resolver la revisión documental de MF-0167 y la vigencia; propuesta de área preparada sin aplicar.
2. Resolver MF-0167 y aportar las secciones estructuradas omitidas (competencias, formación, experiencia, equivalencias cuando existan, funciones comunes).
3. Confirmar documentalmente expedición/vigencia y conciliar el origen antes de publicar una versión. La portada no se usó como vigencia por suposición.
4. Definir si el formato final imprime funciones comunes y completar firma/oficialización.

El único resolver por cargo conservado es una consulta administrativa legada marcada deprecated, cubierta por tests antiguos. **Ningún flujo de generación lo utiliza**: la aplicación exige la ficha explícita guardada en la asignación laboral.

**Incidencia histórica, resuelta en la conciliación posterior (los cuatro servicios están healthy):** después de completar importaciones, PDF, pruebas y smoke HTTP, Docker Desktop dejó de exponer `dockerDesktopLinuxEngine`. Se intentó iniciar Docker Desktop sin tocar volúmenes, pero la conexión al motor no se recuperó en las comprobaciones realizadas. Los resultados anteriores están guardados; para volver a probar la aplicación en vivo debe estar disponible el motor Docker y los servicios del proyecto. Esta incidencia no indica una reversión de la importación ni una pérdida de datos.


## Actualización: conciliación Excel / JSON / PDF

Resultado completo: [MANUAL_RECONCILIATION_REPORT.md](MANUAL_RECONCILIATION_REPORT.md). 344 coincidencias; propuesta MF-0167 sin aplicar; VIGENCIA_NO_CONFIRMADA; **NO PUBLICABLE**. Backend actual: 117 tests pasan; frontend: 15; build correcto; Docker healthy. Sin escrituras en scl_db ni cambios de fuentes/certificados. Los conteos de importación y las 103 pruebas descritas arriba corresponden a la fase inicial; la conciliación añade 14 pruebas.
