# Assets institucionales del frontend

Integración exclusivamente visual sobre `884d28f`, en la rama
`feat/institutional-brand-assets`. Los cuatro archivos estaban incorporados
manualmente y sin seguimiento en `frontend/react-app/public/assets/` al iniciar.
No había otros cambios. No se generaron, convirtieron ni recomprimieron imágenes.

## Inventario y procedencia

Todas las rutas de la tabla son públicas y relativas al origen del frontend.
La procedencia conocida es la entrega manual para esta integración. No se aportó
una URL de origen, licencia, confirmación de generación ni aprobación institucional.
Por eso ninguno se clasifica como `OFICIAL` o `GENERADO` por su apariencia.
`INSTITUCIONAL_PENDIENTE` significa pendiente de confirmar procedencia y aprobación,
no una declaración de oficialidad.

| Archivo | Archivo permanente | Ruta pública | Formato | Dimensiones | Bytes / KiB | Uso | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- |
| escudo-villavicencio.webp | frontend/react-app/public/assets/brand/escudo-villavicencio.webp | /assets/brand/escudo-villavicencio.webp | WebP, RGBA | 768 × 842 | 143002 / 139.65 | Brand: login, sidebar y navegación institucional | INSTITUCIONAL_PENDIENTE |
| govco.png | frontend/react-app/public/assets/brand/govco.png | /assets/brand/govco.png | PNG, RGBA | 1600 × 323 | 61272 / 59.84 | GovBar, enlace gráfico a GOV.CO | INSTITUCIONAL_PENDIENTE |
| villavicencio-panorama.webp | frontend/react-app/public/assets/headers/villavicencio-panorama.webp | /assets/headers/villavicencio-panorama.webp | WebP, RGB | 2172 × 724 | 182348 / 178.07 | Fondo reutilizado por PageHeader institucional | INSTITUCIONAL_PENDIENTE |
| login-villavicencio.webp | frontend/react-app/public/assets/auth/login-villavicencio.webp | /assets/auth/login-villavicencio.webp | WebP, RGB | 1600 × 1001 | 150198 / 146.68 | Panel visual de AuthLayout | INSTITUCIONAL_PENDIENTE |

No hay SVG de escudo o GOV.CO ni favicon nuevo en `assets/`. Se utilizan los rasters
entregados, con sus transparencias y proporciones originales. Se conserva
`public/favicon.svg` y su referencia existente en `index.html`; no se genera otro
favicon y no se emplea ninguna fotografía como icono. No hay manifest existente.
El `public/favicon.svg` conservado es el icono genérico de Vite: SVG vectorial,
ruta pública `/favicon.svg`, procedencia de la plantilla existente y estado
`TEMPORAL`. No representa el escudo institucional.

## Integración

Composición sobria: identidad institucional legible, fotografía contenida y espacio
suficiente para operar el formulario y las páginas. Se conserva la estructura y las
animaciones existentes; no se incorporan slogans ni efectos nuevos.

- **Brand** se reutiliza como equivalente de InstitutionalLogo. Sustituye Landmark
  por el escudo con `object-contain`, 44 × 48 CSS px, sin recortar. El nombre y
  «Sistema de Certificaciones Laborales» permanecen en HTML/React. Su imagen tiene
  `alt=""` porque el enlace ya proporciona un nombre accesible institucional.
- **GovBar** conserva estructura, fondo azul y destino. El raster azul se presenta
  sobre una superficie blanca discreta, a 88 px de ancho y altura automática.
  Conserva transparencia, `aria-label`, nueva pestaña y añade ambos valores de
  `rel="noopener noreferrer"`. La imagen no repite el nombre accesible del enlace.
- **AuthLayout** usa la foto como decoración del panel existente, `object-fit: cover`
  y `object-position: center`, con overlay oscuro para la legibilidad del HTML.
  Sin lazy loading en esta imagen principal; `fetchPriority="high"`.
  Por debajo de 1024 px el panel se oculta y el formulario conserva el espacio.
  El fondo azul sigue permitiendo usar el login si la foto no carga.
- **PageHeader** se reutiliza con `appearance="institutional"` opcional. Acepta
  `title`, `description`, `eyebrow`, `breadcrumbs` y `actions`. La variante simple
  sigue siendo el valor predeterminado. La fotografía es CSS background, sin alt,
  con overlay blanco más intenso a la izquierda. En móvil se reduce el padding,
  se elimina la altura mínima y se atenúa el detalle fotográfico.

Páginas con el header institucional:

| Página | Ruta |
| --- | --- |
| Funcionarios | /admin/funcionarios |
| Certificaciones | /admin/certificaciones |
| Mis solicitudes | /app/solicitudes |
| Manual de Funciones | /admin/manual-funciones |
| Validación de pagos / Secretaría | /admin/pagos |
| Usuarios internos | /admin/usuarios-internos |
| Configuración | /admin/configuracion |

Las páginas que ya usaban PageHeader mantienen un único título. Usuarios internos
reemplaza su cabecera equivalente por el mismo componente. Los dashboards,
formularios y detalles conservan cabeceras simples; las cards y métricas no reciben
fotografías. La marca y GOV.CO se actualizan también en layouts que ya las reutilizan.

## Seguridad, rendimiento y separación web/PDF

Assets servidos desde el propio frontend, sin CDN, base64, rutas Windows en React ni
copias dentro de Docker. Los contenedores existentes leen el directorio mediante su
bind mount habitual. Vite distribuye los archivos públicos en el build normal.
El panorama tiene una sola URL reutilizada y puede aprovechar la caché del navegador.
Las dos fotografías distintas suman 332546 bytes; no se cargan copias por pantalla.
Los rasters entregados se conservan sin modificaciones.

No se modifica CSP, backend, autenticación, Sanctum, CSRF, MFA, cambio obligatorio,
rate limits, mensajes, sesiones ni servicios. Los cambios de páginas se limitan a sus
cabeceras. Pagos, identidad 2.8, Manual, certificados, snapshot y PDF conservan su
lógica. `CertificadoPdfService`, `CertificateDocumentData`, `laboral.blade.php`,
`document.css` y `certificate-assets` quedan intactos.

Los assets de esta tabla son web. No se incorporan al PDF ni se declaran recursos
oficiales de certificados. PDF 3A sigue «BORRADOR — SIN VALIDEZ OFICIAL».
Los documentos 3B.1 no cambian y su aprobación institucional continúa pendiente.
No se inicia 3B.2, firma, QR ni oficialización.

## Verificación y evidencia

La regresión utiliza la base ya existente `scl_certificate_types_e2e`, con autorización
expresa del usuario para escrituras sintéticas exclusivamente allí. No se ejecutan
migraciones ni se modifican bases de desarrollo o producción.

Resultados de verificación:

- `docker exec scl_ct_e2e_web npm run test`: **78 tests / 20 archivos aprobados**,
  con Node 24.20.0. La ejecución inicial en Node 25.8.0 de Windows falló por
  `localStorage.clear is not a function` en el entorno de Vitest/jsdom. Se resolvió
  usando el entorno Node 24 existente, sin modificar autenticación ni tests base.
- `npm run lint`: **aprobado**, incluido el nuevo archivo E2E.
- `npm run typecheck`: **aprobado**.
- `docker exec scl_ct_e2e_web npm run build`: **aprobado**, con API relativa
  `/api/v1` para la verificación bajo CSP de producción. El build de Windows
  también pasó. Los cuatro assets en dist son idénticos byte a byte a public.
- Prueba E2E específica de assets: **aprobada**, en 360, 768 y 1440 px.
- `docker compose -f docker-compose.certificate-types.yml --profile e2e run --rm --no-deps e2e-runner npx playwright test`:
  **31 tests aprobados (12,2 minutos), sin reintentos**. Incluye autoservicio,
  snapshot y PDF real, pagos OFF/ON, carga/rechazo/reemplazo, concurrencia, identidad
  2.8, cookies HttpOnly, CSRF, MFA, revocación de sesiones, rate limits, IDOR y build
  real bajo la CSP de producción, además de la nueva prueba visual. La prueba
  histórica optativa `control-screenshots.spec.ts` no forma parte de la suite normal;
  la evidencia solicitada se obtiene con `brand-assets.spec.ts`.

Se reinició exclusivamente el servidor Vite E2E, que mantenía transformaciones
anteriores en memoria, antes de la ejecución definitiva. Las respuestas de assets
`200` y `304` son válidas; la prueba permite la reutilización de caché.

Revisión visual de las 12 capturas: escudo y GOV.CO sin deformación, fotografías
sin stretch o pixelación notable, títulos y descripciones legibles y sin overflow
horizontal de la página. Las tablas conservan su desplazamiento interno existente.
No se detectaron problemas de contraste o responsive introducidos por esta fase.

La prueba `e2e/brand-assets.spec.ts` cubre el login y las siete cabeceras a 360, 768 y
1440 px, carga de los cuatro assets, título único, ausencia de overflow horizontal,
navegación por menú responsive y uso del formulario con la fotografía bloqueada.
Las capturas ocultan campos de contraseña, MFA y códigos de recuperación.

Evidencia en `frontend/react-app/evidence/brand-assets/`:

- `01-login-desktop.png`
- `02-login-mobile.png`
- `03-header-funcionarios.png`
- `04-header-certificaciones.png`
- `05-header-secretaria.png`
- `06-header-mobile.png`
- `07-login-tablet.png`
- `08-header-tablet.png`
- `09-header-manual.png`
- `10-header-usuarios.png`
- `11-header-configuracion.png`
- `12-header-mis-solicitudes.png`
