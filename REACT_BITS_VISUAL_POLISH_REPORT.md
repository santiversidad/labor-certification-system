# Fase 2.5 — React Bits visual polish

## Evaluación previa (24 de septiembre de 2026)

La tesis visual es **papel institucional, tinta azul y superficies con profundidad suave**. Las dos elecciones de certificado son la acción principal; las métricas solo muestran datos existentes. La interacción se limita a un foco discreto al pasar el puntero, un conteo único y una breve entrada de superficies.

| Componente FREE | Uso propuesto | Dependencias upstream | Peso fuente aproximado | Movimiento reducido | Valor visual | Decisión |
| --- | --- | --- | --- | --- | --- | --- |
| GradientText | Acento breve en login | `motion` | 4,6 KB | Requiere adaptación | Medio | Usar adaptación CSS sin bucle continuo |
| CountUp | Métricas admin existentes | `motion` | 2,9 KB | Requiere adaptación | Medio | Usar adaptación sin dependencia, una vez |
| SpotlightCard | Dos opciones de funcionario | Ninguna | 1,7 KB | Requiere apagar tracking | Alto | Usar intensidad baja |
| GlareHover | Opciones de funcionario | Ninguna | 2,9 KB | Requiere apagar transición | Bajo | Descartar: compite con Spotlight |
| FadeContent | Confirmación | `gsap` | 2,7 KB | Requiere adaptación | Bajo | Descartar: peso desproporcionado |
| AnimatedContent | Confirmación | `gsap` | 3,1 KB | Requiere adaptación | Bajo | Descartar: peso desproporcionado |

Fuentes oficiales: [registro FREE](https://github.com/DavidHDev/react-bits/tree/main/public/r), [sitio](https://reactbits.dev/get-started/index), [licencia](https://github.com/DavidHDev/react-bits/blob/main/LICENSE.md). HEAD consultado: `b6666e9f3a03a062143ce409f3aac53e27fdfaa8`. Los pesos representan solo el texto de las variantes TypeScript/CSS, sin dependencias. Se revisaron implementación, eventos de puntero, ausencia de soporte nativo de movimiento reducido y comportamiento responsivo de las variantes oficiales.

## Estado inicial

- Rama inicial: `feat/certificate-types`; HEAD `6d85e46`.
- Árbol limpio. El bootstrap del Manual ya está en `6d85e46` (mensaje histórico: `Version del manual reproducible en el recobery`); no se creó un commit duplicado.
- Rama de trabajo: `feat/reactbits-visual-polish`.
- Build inicial Vite: CSS 36,20 KB (gzip 7,05 KB); JS principal 334,89 KB (gzip 105,24 KB). Las rutas se cargan en chunks separados.

## Integración y decisiones

- `GradientText`: solo la última frase del título de login, con blanco y azul claro provenientes de los tokens institucionales. La animación CSS ocurre una vez, durante 700 ms. No se alteraron GovBar, escudo ni marca.
- `SpotlightCard`: solo las dos opciones de certificado en el dashboard funcionario. Su halo usa el azul existente a opacidad baja. El estado disponible conserva texto e icono, y el estado consumido muestra la próxima fecha, un botón deshabilitado y ninguna luz. Los botones navegan directamente sin anidar controles interactivos.
- `CountUp`: seis métricas ya obtenidas de las respuestas actuales del backend en el dashboard admin. Se anima una sola vez en 520 ms y luego conserva el número final. No se agregaron cifras ni tendencias.
- Confirmación: conserva tipo, radicado, fecha, estado y descarga. Recibe solo una entrada CSS de 220 ms; se descartó `FadeContent` para no incorporar GSAP. El resto de páginas comparte los tokens existentes sin añadir efectos.

Las adaptaciones están aisladas en `frontend/react-app/src/components/react-bits/`. Conservan la procedencia en comentarios y la licencia completa en `THIRD_PARTY_NOTICES.md`. **Dependencias runtime y dev agregadas: 0**; `package.json` y lockfile permanecieron intactos.

## Accesibilidad, responsive y QA visual

- `prefers-reduced-motion`: el gradiente deja de animarse, el contador presenta directamente el valor final y el spotlight no sigue al puntero. La hoja global reduce además transiciones; la confirmación aparece sin desplazamiento. Verificado con prueba unitaria y navegador Edge con emulación de movimiento reducido.
- El foco visible persiste, `SpotlightCard` no captura clics y los botones de modalidad funcionan con teclado. La opción consumida conserva `disabled`. La confirmación mantiene datos reales de respuesta y la descarga siguió funcionando en E2E.
- Capturas y comprobación de ancho horizontal en **360, 768 y 1440 px**. Se corrigió `min-w-0` en la sección de expediciones recientes del admin para evitar que la tabla ensanchara el layout móvil. El contenido de la tabla puede desplazarse dentro de su región, sin desbordar la página.
- Revisados visualmente login, dashboards, confirmación, funcionarios, alta y detalle, cargos, Manual, certificaciones y detalle, configuración, auditoría, reportes, solicitud sencilla y con funciones, validación pública, drawer móvil y modal. El velo del modal se corrigió con un portal al `body` para cubrir también el sidebar y el header; se verificó cierre con Escape. El estado inválido de validación pública y la modalidad consumida cubren estados de error y deshabilitado. El resto conserva su estructura de Fase 2.
- Comparado con `frontend/react-app/evidence/certificate-types-phase2/`: login gana una superficie de formulario más definida; las modalidades tienen una jerarquía y disponibilidad más claras; la cuadrícula admin tiene divisiones coherentes. Tablas, navegación y validación pública permanecen sobrias. Las capturas Fase 2 no se modificaron.

## Rendimiento y dependencias

| Asset Vite | Antes | Después | Diferencia |
| --- | ---: | ---: | ---: |
| JS principal | 334,89 KB / 105,24 gzip | 335,16 KB / 105,32 gzip | +0,27 KB / +0,08 gzip |
| CSS total de app y chunk de funcionario | 36,20 KB / 7,05 gzip | 38,56 KB / 7,73 gzip | +2,36 KB / +0,68 gzip |
| Chunk admin | 7,06 KB / 2,47 gzip | 7,88 KB / 2,85 gzip | +0,82 KB / +0,38 gzip |
| Chunk funcionario | 3,97 KB / 1,63 gzip | 4,66 KB / 1,97 gzip | +0,69 KB / +0,34 gzip |
| Chunk confirmación | 3,84 KB / 1,70 gzip | 3,92 KB / 1,74 gzip | +0,08 KB / +0,04 gzip |

`npm ls` termina correctamente. `npm audit` informa **3 hallazgos transitivos de desarrollo** (1 bajo en `postcss-selector-parser`, 1 moderado en `baseline-browser-mapping`, 1 alto en `browserslist`); `npm audit --omit=dev` informa **0** en runtime. No se actualizaron paquetes ajenos a esta fase.

## Evidencia y verificaciones

- `frontend/react-app/evidence/reactbits-phase25/`: capturas `01` a `22`, incluidas las 11 obligatorias. La `07-confirmacion.png` proviene de una expedición real sobre la base **E2E aislada**; las demás se capturaron en CT desarrollo (`localhost:3200` y API `localhost:8280`) sin crear registros. El script de captura es `frontend/react-app/scripts/capture-reactbits-phase25.mjs`.
- Frontend: **33/33** pruebas en contenedor CT; también pasan localmente con `NODE_OPTIONS=--no-experimental-webstorage` por una incompatibilidad de Node 25 con el `localStorage` de jsdom. ESLint, TypeScript y build: **pasan**.
- Backend: **153/153**, 777 aserciones. E2E: **7/7** en `scl_certificate_types_e2e`, regenerada antes de la corrida final para evitar cupos consumidos por una ejecución anterior. Las bases CT desarrollo y recovery no se restablecieron.
- CT desarrollo (consulta solo lectura): Manual versión **10 publicado**, **344** fichas, **3.115** funciones, **2.326** conocimientos; admin **36** permisos. La UI activa tiene **0** referencias salariales, excluyendo archivos históricos y tests negativos.
- Búsqueda de marcadores de React Bits Pro en `src`, scripts y manifests: **0**. No hay registry privado, llave o token React Bits. No se tocó backend funcional, migraciones, datos de desarrollo ni el PDF.

## Archivos y observación para revisión

Archivos editados: `AuthLayout.tsx`, `Modal.tsx`, `FuncionarioDashboardPage.tsx` y su prueba, `AdminDashboardPage.tsx`, `SolicitudConfirmacionPage.tsx`, `theme.css`, `index.css`, `tailwind.config.ts`, `e2e/autoservice.spec.ts`. Archivos nuevos: tres carpetas de componentes adaptados bajo `src/components/react-bits/`, prueba `CountUp.test.tsx`, script de captura, capturas, `THIRD_PARTY_NOTICES.md` y este informe.

La revisión visual pendiente del usuario puede concentrarse en el brillo tenue de la opción disponible, el acento de texto del login y la densidad de métricas admin en móvil. Para la futura Fase 3A, conviene conservar el azul y el tratamiento tipográfico institucional como referencia; esta fase no cambia ni diseña el PDF.
