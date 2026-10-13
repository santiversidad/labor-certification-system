# Evidencia del editor institucional

Capturas producidas por Playwright con cuentas, funcionarios y documentos sintéticos en `scl_template_editor_e2e`. No contienen los datos personales, salario o firmas manuscritas del certificado municipal de referencia. El logo derecho procede únicamente del encabezado recortado; el escudo azul de las pruebas se rotula SINTÉTICO.

| Captura | Validación |
|---|---|
| `01-listado.png` | Dos tipos canónicos, estado y acciones. |
| `02-editor-pdf.png` | Bloques, variables, fuente, imágenes y PDF real. |
| `03-confirmacion.png` | Publicación requiere confirmación. |
| `04-publicada.png` | Versión inmutable y creación de otra versión. |
| `05-certificado-generado.png` | Expedición sintética por autoservicio. |
| `06-historial.png` | Versión anterior archivada y publicada actual. |
| `07-editor-*.png` | 360, 768, 1024 y 1440 px, sin desbordamiento horizontal. |
| `08-pdf-extenso.png` | Navegación de un PDF multipágina. |
| `09-pdf-movil.png` | Pestaña de preview y controles en 360 px. |
| `10-produccion-csp.png` | Fuentes y logos dibujados con worker local bajo CSP. |

`performance.json` registra tiempos HTTP del flujo explícito de edición/preview/publicación en Windows + Docker con bind mounts; no constituye un SLA productivo. `production-csp.json` distingue la detección de capacidades preexistente de Zod durante login (eval bloqueado y capturado por CSP) de la apertura del editor/PDF, que no introduce violaciones. No se habilita `unsafe-eval`, `wasm-unsafe-eval` ni iframe.

Los PDFs sintéticos y sus hashes están en `output/template-editor/pdf/`, fuera de Git según la política de artifacts generados del repositorio. El verificador `scripts/verify_template_pdf_evidence.py` revisa las 21 páginas de ocho casos, incluyendo 1/2/3/5 páginas, firma textual, logo reemplazado y una sola función extensa. Logs y comparaciones en `output/template-editor/checks/`.

Reproducción E2E: `npx playwright test e2e/zz-certificate-template-editor.spec.ts` en el entorno sintético aislado preparado por los comandos `e2e:prepare-autoservice` y `e2e:prepare-salary-scale`. El segundo administrador de concurrencia se crea exclusivamente en la base aislada del editor. Reproducción PDF: `php artisan certificates:template-evidence`; el comando exige entorno testing y nombre de base de pruebas, no funciona en CT-dev.
