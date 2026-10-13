# Editor administrativo de plantillas — modelo y contrato

Fase 3A.2. Se admiten exclusivamente `sencillo` y `funciones`. Todo PDF nuevo sigue siendo **BORRADOR — SIN VALIDEZ OFICIAL**. Publicar una plantilla configura certificados nuevos; no firma ni oficializa documentos.

## Base e investigación del sistema existente

Base integrada: `4e86dcc1e4d9fb3dfd7fb6a12ecc8f1d829e2722`, con la corrección del Manual validada por el usuario y el módulo salarial ya incorporado. El checkout canónico contiene cambios de rendimiento pendientes; se preservan y se prueban como overlay sin incluirlos en el commit del editor.

Antes del editor, `CertificadoPdfService` componía `certificados/laboral` a partir de `CertificateDocumentData` y snapshot v3. Título, textos, encabezado tipográfico provisional y pie eran constantes. El renderer no consultaba datos vivos. Los certificados almacenaban una sola vez PDF privado, snapshot y hash; las descargas recuperaban ese archivo. No existían plantillas/versiones/assets documentales administrables. La validación pública por capacidad existe, pero no hay aprobación de dominio ni de firma oficial para imprimir un QR institucional.

## Modelo persistente

| Tabla | Responsabilidad |
|---|---|
| `plantillas_certificado` | Dos raíces, tipo único y puntero nullable a la versión publicada. |
| `plantilla_versiones` | Número por raíz, estado, revisión optimista, JSONB estructurado, SHA256, autores, fecha de publicación. |
| `plantilla_assets` | PNG normalizado privado, posición, dimensiones, bytes, SHA256, procedencia y autor. |

La migración `2026_10_13_000001_create_certificate_template_editor.php` es aditiva: crea tres tablas y las dos raíces, sin publicar ninguna versión ni migrar certificados históricos. Índices parciales permiten un borrador y una publicada por raíz. Triggers PostgreSQL impiden eliminar versiones o modificar contenido publicado/archivado y prohíben actualizar/eliminar assets. El rollback destructivo exige revisión explícita.

Creación/publicación bloquean la raíz; guardar bloquea la versión y exige su `revision`. La publicación archiva la anterior dentro de la misma transacción. Congelar una plantilla usa bloqueo compartido de raíz para obtener una versión coherente aunque otro administrador publique simultáneamente. Los uploads idénticos se serializan con advisory lock transaccional y reutilizan el recurso inmutable.

## Documento estructurado

JSON con `schema_version: 1`, `title`, `header`, `typography`, `paragraphs`, `functions`, `signature`, `additional_signatures`, `footer`. Se rechazan claves desconocidas incluso dentro de bloques. Máximo 40 KB de configuración, 12 párrafos y 40 fragmentos por párrafo; cada literal tiene hasta 2.000 caracteres. Los fragmentos son texto o una variable registrada, con negrita/cursiva booleanas. No se aceptan HTML, CSS, código, expresiones, rutas dinámicas ni coordenadas.

| Bloque | Edición permitida |
|---|---|
| Encabezado | Reemplazar referencias de las dos imágenes; geometría fija. |
| Título | Texto de hasta 140 caracteres. |
| Párrafos | Texto, variables, orden, alineación, negrita/cursiva; añadir/eliminar opcionales. |
| Identificación y vinculación | Protegidos y derivados del snapshot. |
| Funciones | Solo título, tamaño e interlineado; texto/orden/numeración/grupos exactos del snapshot. |
| Cierre | Párrafos posteriores a funciones; `cierre` obligatorio. |
| Firmante | Nombre/cargo/dependencia/texto/alineación, exclusivamente texto. |
| Firmas adicionales | Hasta tres filas de etiqueta/nombre/cargo, sin imágenes personales. |
| Pie | Siete campos de contacto; estructura y posición protegidas. |

`certificacion` y `cierre` no se eliminan ni cambian de zona. El tipo sencillo exige `functions: null` y no dispone de variable de Manual. Datos normativos ajenos al editor se escapan al renderizar sin alterar sus literales. Se conserva `numero_fuente`, incluido null cuando la fuente no numera, y el grupo lógico.

Fuentes locales Dompdf: DejaVu Sans, DejaVu Serif y DejaVu Sans Mono. Tamaños enteros 9–12 pt, interlineado 1,2–1,6 y alineación izquierda/centro/derecha/justificada. No hay cargas de fuentes ni CSS libre. Los espacios literales alrededor de variables se conservan expresamente frente al middleware TrimStrings.

## Catálogo de variables

La API publica identificador, etiqueta, grupo, descripción, tipo, origen, formato, disponibilidad y política de ausencia. El backend resuelve mediante `match` explícito, sin `eval`, consultas ni acceso dinámico por rutas. Las rutas de esta tabla documentan el origen, no son instrucciones ejecutables.

| Identificador | Origen en snapshot v3 | Ausencia |
|---|---|---|
| `nombre_completo` | funcionario.nombres + apellidos | Bloquea render |
| `tipo_documento` | funcionario.tipo_documento | Bloquea render |
| `numero_documento` | funcionario.numero_documento | Bloquea render |
| `fecha_vinculacion` | asignacion.fecha_inicio | Bloquea render |
| `fecha_ingreso` | funcionario.fecha_ingreso | Omite párrafo |
| `fecha_retiro` | funcionario.fecha_retiro | Omite párrafo |
| `dependencia` | cargo.dependencia | Omite párrafo |
| `denominacion_cargo` | cargo.denominacion | Bloquea render |
| `codigo_cargo` | cargo.codigo | Omite párrafo |
| `grado_cargo` | cargo.grado | Omite párrafo |
| `tipo_vinculacion` | asignacion.tipo_vinculacion | Omite párrafo |
| `naturaleza_cargo` | asignacion.naturaleza_cargo | Omite párrafo |
| `fecha_expedicion` | expedicion.fecha_expedicion | Bloquea render |
| `numero_certificado` | expedicion.codigo_tecnico | Omite párrafo |
| `radicado` | expedicion.radicado | Omite párrafo |
| `pagina_manual` | manual_funciones.pagina_inicio; solo funciones | Omite párrafo |

Fechas en español `D de mes de YYYY`; etiquetas de vinculación/naturaleza presentan los códigos capturados. Códigos, grados y documentos siguen siendo cadenas. No hay variables salariales ni variable editable de funciones. Si falta un dato opcional, se omite el párrafo entero para evitar frases incompletas.

## Assets y referencia aportada

Referencia del usuario: `doc09536920260520143639.pdf`, dos páginas escaneadas, SHA256 `5a2b78f6fbc36515f6cd10f90519e8b9409f82ecc2924d64ecf346ff5b43c338`. Se extrajeron únicamente los dos elementos del encabezado a `resources/certificate-assets/reference/`; su README registra procedencia y recorte. No se incorporan al repositorio los nombres, documentos, salario o firmas manuscritas de la muestra. El original usa tamaño carta; el sistema conserva A4 según el alcance autorizado. La muestra también superpone encabezado y funciones en su continuación; la implementación reserva una banda independiente.

Cada upload exige procedencia y valida MIME real, decodificación, tamaño ≤2 MB, lados 40–4096 px y ≤6 megapíxeles. Admite PNG/JPEG/WebP estáticos y rechaza SVG, APNG y WebP animado. GD + EXIF normalizan orientación JPEG y reencodifican como PNG opaco sin metadatos. Proporción izquierda 1:2–2:1; derecha 3:2–8:1. Se reduce proporcionalmente a máximo 400×400 o 800×300 px, sin estirar ni recortar. El runtime Docker incluye GD/EXIF/WebP comprobados.

Ruta privada por contenido: `certificate-template-assets/<sha256>-<left|right>.png`. La API expone ID y endpoint autenticado, nunca la ruta. Renderer verifica SHA256 antes de usar bytes locales y no descarga recursos remotos. Reemplazar crea/reutiliza otro asset; las versiones anteriores conservan sus referencias.

Los PNG escaneados sirven como referencia de borrador; permanecen pendientes originales de mayor calidad y aprobación institucional para uso final. Los siete datos de pie se transcribieron de la muestra, sin inferir titular actual de Dirección de Personal.

## Layout A4 y preview

Renderer único `CertificateTemplateRenderer` + Blade `certificados/structured`. A4, 50 pt laterales, 165 pt superiores y 110 pt inferiores. Canvas Dompdf repite en **todas** las páginas: escudo izquierdo, identidad derecha, reserva QR bajo el bloque derecho, marca BORRADOR, pie y numeración. Los logos usan ajuste proporcional; el pie se mide/parte en hasta seis líneas. No se permite desplazar estos elementos desde el editor.

Las funciones fluyen entre páginas sin consultas al Manual; un literal de varias páginas se conserva completo. Cierre y firma evitan separaciones innecesarias; la firma principal y filas adicionales permanecen agrupadas. No se incorporan firma manuscrita, certificado digital, sello de aprobación ni QR inventado. Placeholder seguro cuando falta nombre confirmado.

Preview requiere acción explícita, mismo renderer y datos sintéticos deterministas, escenarios breve/medio/amplio/extenso. No crea solicitudes, certificados ni tokens y responde PDF `private, no-store`. React dibuja el PDF real con PDF.js cargado bajo demanda, worker del mismo origen y canvases navegables. Se desactiva WebAssembly en el visor para mantener CSP sin permisos de evaluación; Nginx sirve `.mjs` como JavaScript. No cambia CSP, no usa iframe ni carga funciones/funcionarios/históricos en el editor.

## Snapshot y compatibilidad histórica

Se mantiene snapshot laboral v3. Solo cuando existe una versión publicada se añade `plantilla_certificado`, envelope v1 con ID, número, tipo, SHA256, configuración completa y referencias inmutables de assets. El hash de configuración ordena recursivamente claves de objetos y conserva orden de listas. El renderer usa exclusivamente este envelope y el snapshot laboral; no vuelve a leer plantilla viva, funcionarios ni Manual.

Sin `plantilla_certificado`, se conserva exactamente la ruta legacy `CertificadoPdfService` → `certificados/laboral`. Ninguna versión se inventa retrospectivamente. Los archivos PDF históricos se sirven desde almacenamiento como antes; no se regeneran ni cambian hashes. Publicar afecta exclusivamente nuevas generaciones.

## Autorización, rutas y auditoría

Se exige cuenta activa con rol `admin` **y** permiso existente `parametros.editar`; conceder ese permiso a otro rol no habilita editor. Se conservan los 37 permisos del administrador y los permisos de Secretaría/funcionario. Todas las rutas están bajo Sanctum, identidad, cambio de contraseña, MFA y limitadores existentes. Preview y upload añaden límites propios de 10/minuto. React usa RoleRoute y navegación exclusiva de admin.

Prefijo `/api/v1/admin/plantillas-certificados`:

| Método | Ruta | Acción |
|---|---|---|
| GET | `/` | Dos raíces y resúmenes activos. |
| GET | `/catalogo?tipo=...` | Variables/fuentes permitidas. |
| POST | `/{plantilla}/versiones` | Crear borrador. |
| GET | `/{plantilla}/versiones?page=...` | Historial de 25 versiones por página. |
| GET | `/versiones/{version}` | Configuración y metadatos de assets. |
| PUT | `/versiones/{version}` | Guardar con revisión. |
| POST | `/versiones/{version}/preview` | PDF sintético de configuración enviada/guardada. |
| POST | `/versiones/{version}/publicar` | Confirmación, revisión, validación, preflight y publicación atómica. |
| POST | `/versiones/{version}/assets` | Subir imagen de borrador. |
| GET | `/assets/{asset}` | Bytes privados autenticados. |

UI: `/admin/plantillas-certificados` y `/admin/plantillas-certificados/versiones/:versionId`. La publicación exige borrador guardado y preview vigente en la interfaz, confirmación explícita y preflight sintético obligatorio en backend. Un cliente API no puede eludir validación ni integridad.

Eventos: `plantilla_creada`, `plantilla_actualizada`, `plantilla_publicada`, `plantilla_asset_reemplazado`. Incluyen actor, IDs, revisión/hash y campos/asset IDs pertinentes; no copian texto del certificado ni datos personales a logs. La creación inicial de assets de referencia queda enlazada por el evento de creación de versión.

## Operación y límites

La primera migración deja ambas raíces en contrato legacy; un administrador crea, revisa y publica su primera versión. No hay publicación automática ni cambio de certificados existentes. Los assets referenciados deben respaldarse junto con DB y PDFs privados; no hay borrado/GC de historia en esta fase.

QR/dominio de validación, facultad del titular, proveedor de firma y aprobación de fuentes gráficas permanecen pendientes institucionales. No se inició Fase 3B.2. No se alteran cuotas, pagos, identidad, MFA, escala salarial ni resolución por página/ficha del Manual.
