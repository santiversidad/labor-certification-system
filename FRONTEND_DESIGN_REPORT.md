# Informe de diseño frontend

Fecha de cierre: 20 de septiembre de 2026  
Aplicación: Sistema de Certificaciones Laborales — Alcaldía de Villavicencio

## 1. Alcance y criterio de diseño

La fase consolidó la experiencia web sin alterar las reglas de autoservicio, límite mensual, Manual, ficha explícita, snapshot, onboarding, cambio obligatorio de contraseña, pago o versionado.

La dirección visual toma como referencia el carácter institucional de la Alcaldía de Villavicencio y los patrones de servicios digitales del Estado colombiano, sin copiar una página pública. El resultado utiliza una composición sobria, azul institucional dominante, superficies claras, acentos funcionales y una marca propia para el aplicativo administrativo.

Principios aplicados:

- lectura y acción principal evidentes;
- separación estricta entre experiencia de funcionario y administración;
- jerarquía basada en tipografía, espacio y divisores, con uso moderado de tarjetas;
- estados comprensibles sin depender únicamente del color;
- interfaces móviles prioritarias para el autoservicio;
- formularios administrativos en página, no dentro de modales extensos;
- contenido y métricas respaldados por los contratos actuales.

## 2. Design System

Los tokens están centralizados en `src/styles/theme.css`, `src/styles/index.css` y `tailwind.config.ts`.

### Paleta semántica

| Token | Uso |
|---|---|
| `primary` / `primary-hover` | acción principal y enlaces activos |
| `secondary` | énfasis institucional complementario |
| `success` | operación disponible o completada |
| `warning` | atención, pago o estado pendiente |
| `error` | error, bloqueo o acción destructiva |
| `info` | orientación y mensajes informativos |
| `background` | fondo general del aplicativo |
| `surface` / `surface-muted` | paneles y zonas de lectura |
| `border` | separación y estructura |
| `text` / `muted` | texto principal y secundario |

La combinación fue ajustada para contraste AA en texto, controles, focos y estados. Se eliminaron colores arbitrarios de los flujos rediseñados.

### Fundamentos

- Tipografía: familia sans del sistema con escala compacta para software administrativo.
- Radios: controles y superficies con radios consistentes, sin apariencia excesivamente redondeada.
- Sombras: elevación baja y funcional.
- Espaciado: escala Tailwind; secciones con ritmo vertical uniforme.
- Movimiento: transiciones breves y soporte de `prefers-reduced-motion`.
- Iconografía: `lucide-react`, siempre acompañada por texto o nombre accesible cuando representa una acción.

### Componentes comunes

Se consolidaron o incorporaron:

- `Brand`, `GovBar`, `Header`, `Sidebar`, `Footer`;
- `Button`, `Input`, `Select`, `Textarea`, `Switch`;
- `Card`, `Badge`, `Alert`, `PageHeader`, `Timeline`;
- `Table` con carga, vacío, caption, overflow y paginación server-side;
- `Modal` y `ConfirmDialog` para detalles breves o confirmaciones;
- `Skeleton`, `LoadingState`, `EmptyState`, `ErrorState`;
- `ToastProvider` para feedback uniforme.

## 3. Layouts

### Público y autenticación

`AuthLayout` presenta identidad institucional, propuesta de valor y un área de formulario simple. `PublicLayout` conserva la cabecera GOV.CO y una estructura apropiada para validación pública.

### Funcionario

`AppLayout variant="app"` elimina la navegación administrativa. El header contiene identidad, usuario, rol y cierre de sesión. El contenido se concentra en “Certificaciones laborales” y funciona especialmente bien en móvil.

### Administración

`AppLayout variant="admin"` utiliza sidebar fijo en escritorio, topbar y área de contenido. La navegación contiene exclusivamente Dashboard, Funcionarios, Cargos, Rangos salariales, Manual de Funciones, Certificaciones, Configuración, Auditoría y Reportes.

## 4. Auditoría de páginas y rutas

### Rutas activas

| Ruta | Página | Clasificación |
|---|---|---|
| `/login` | Inicio de sesión | DEFINITIVA |
| `/cambiar-contrasena` | Cambio obligatorio | DEFINITIVA |
| `/validar-certificado/:token?` | Validación pública | DEFINITIVA |
| `/app/inicio` | Inicio funcionario | DEFINITIVA |
| `/app/dashboard` | Alias a inicio | DUPLICADA CONTROLADA |
| `/app/solicitudes/nueva` | Solicitud corta | DEFINITIVA |
| `/app/solicitudes/confirmacion` | Resultado y descarga | DEFINITIVA |
| `/admin/dashboard` | Dashboard por rol | DEFINITIVA |
| `/admin/funcionarios` | Listado y filtros | DEFINITIVA |
| `/admin/funcionarios/nuevo` | Alta seccionada | DEFINITIVA |
| `/admin/funcionarios/:id` | Detalle | DEFINITIVA |
| `/admin/funcionarios/:id/editar` | Edición | DEFINITIVA |
| `/admin/cargos` | Cargos | DEFINITIVA, SISTEMA COMÚN |
| `/admin/rangos-salariales` | Rangos | DEFINITIVA, SISTEMA COMÚN |
| `/admin/manual-funciones` | Manual y versionado | DEFINITIVA |
| `/admin/certificaciones` | Consulta histórica | DEFINITIVA, SISTEMA COMÚN |
| `/admin/certificaciones/:id` | Detalle histórico | DEFINITIVA, SISTEMA COMÚN |
| `/admin/configuracion` | Pago de certificaciones | DEFINITIVA |
| `/admin/configuracion-certificaciones` | Alias a configuración | DUPLICADA CONTROLADA |
| `/admin/auditoria` | Auditoría | DEFINITIVA |
| `/admin/reportes` | Reportes API | DEFINITIVA |

Todas las páginas principales se cargan con `React.lazy` y `Suspense`.

### Legacy, duplicados y no utilizados

- Los archivos con sufijo `-camilo_ortega` son copias históricas o duplicadas. No están importados por el router activo y se conservaron para revisión, porque no existía autorización suficiente para su eliminación definitiva.
- `SecretarioDashboardPage-camilo_ortega.tsx`, `SolicitudesPage-camilo_ortega.tsx` y `SolicitudDetailPage-camilo_ortega.tsx` representan el flujo anterior de revisión/aprobación manual. Se clasifican como LEGACY y NO UTILIZADOS.
- La navegación activa no expone aprobación, validación manual de comprobantes ni generación administrativa ordinaria.
- Los alias de rutas indicados en la tabla se conservan como compatibilidad de enlaces existentes, sin duplicar implementación.

## 5. Flujos terminados

### Login y onboarding

- Login institucional con cédula, contraseña, visibilidad controlada y feedback no técnico.
- Helpers de usuarios únicamente bajo modo desarrollo.
- Cambio obligatorio de contraseña antes de acceder a otras rutas.
- Labels vinculados con controles y errores asociados.

### Funcionario y solicitud

- Tres opciones: sin salario, con salario e información laboral con funciones.
- Estados visibles: disponible, usado en el mes, requiere pago o inconsistencia institucional.
- Interacción corta: selección, resumen/confirmación y resultado; la barra comunica los cuatro momentos sin convertir el proceso en un wizard extenso.
- Resultado con radicado, fecha, modalidad y descarga inmediata cuando aplica.

### Funcionarios

- Tabla responsive con cédula, nombre, cargo, dependencia, ficha, estado de acceso y acciones.
- Búsqueda y filtros respaldados por API, con paginación server-side.
- Alta por Datos personales, Vinculación, Cargo, Ficha del Manual y Acceso.
- Fichas compatibles filtradas por cargo y selección explícita con source ID y contexto institucional.
- Detalle legible y acciones confirmadas de edición, activación/desactivación y restablecimiento.

### Manual

- Manual vigente destacado con acto, versión, fichas y funciones.
- Versiones publicadas marcadas como solo lectura.
- Flujo preparado y conectado para borrador, importación, dry-run, comparación, mapeo y publicación.

### Configuración, auditoría y reportes

- Switch de pago con explicación de impacto y confirmación explícita.
- Auditoría en tabla; metadata disponible en detalle, no como JSON crudo por defecto.
- Reportes limitados a datos suministrados por la API, con jerarquía y barras simples.

## 6. Errores funcionales

`src/lib/utils/errors.ts` traduce los códigos de dominio sin mostrarlos como texto técnico:

- `MONTHLY_CERTIFICATE_LIMIT`;
- `MANUAL_FICHA_INCOMPLETA`;
- `MANUAL_FICHA_NO_ASIGNADA`;
- `SALARIO_NO_RESOLUBLE`;
- `PAGO_NO_CONFIRMADO`;
- `PASSWORD_CHANGE_REQUIRED`.

También preserva errores de validación útiles y evita mostrar trazas SQL/Laravel al usuario.

## 7. Responsive y accesibilidad

Se revisaron 1366×768 y móvil 390×844 mediante capturas automatizadas. Los layouts usan breakpoints fluidos y permanecen utilizables en tablet y 1920×1080.

Implementado:

- landmarks, encabezados y navegación semántica;
- labels reales con `htmlFor/id` generado incluso cuando el consumidor no define `name`;
- nombres accesibles en botones de icono;
- foco visible global y navegación por teclado;
- `aria-invalid`, `aria-describedby`, roles de alerta/estado y captions de tabla;
- estado expresado con icono/texto además de color;
- modales con título accesible y cierre por teclado;
- blancos de carga sustituidos por skeleton o estado de carga;
- preferencia de movimiento reducido.

## 8. Pruebas y verificación

Resultados finales:

| Verificación | Resultado |
|---|---|
| ESLint | aprobado |
| TypeScript + build Vite | aprobado |
| Vitest | 25/25 tests en 13 archivos |
| Backend PHPUnit | 140/140 tests, 739 aserciones |
| Playwright real | 2/2 aprobados |
| Generador Playwright de capturas | 1/1 aprobado, 12 vistas |
| Auditoría dependencias de producción | 0 vulnerabilidades |
| Docker | `scl_frontend`, `scl_app`, `scl_nginx`, `scl_postgres` healthy |

Los selectores E2E usan roles, labels y nombres accesibles. El generador de capturas está excluido de la suite funcional normal y se activa de forma explícita con `E2E_SCREENSHOTS=1`.

## 9. Bundle y code splitting

| Métrica | Antes | Después |
|---|---:|---:|
| Chunk principal JS | 543,71 kB | 324,74 kB |
| Reducción | — | 218,97 kB (40,3 %) |
| Warning de chunk >500 kB | presente | resuelto |

Además del chunk base, Vite produce chunks independientes por página y por dependencias compartidas. El chunk principal final comprime a 102,32 kB gzip.

## 10. Capturas de control

Directorio: `frontend/react-app/docs/screenshots/`

1. `01-login.png`
2. `02-cambio-contrasena.png`
3. `03-dashboard-funcionario.png`
4. `04-solicitud.png`
5. `05-resultado.png`
6. `06-dashboard-admin.png`
7. `07-funcionarios.png`
8. `08-alta-funcionario.png`
9. `09-detalle-funcionario.png`
10. `10-manual-funciones.png`
11. `11-configuracion.png`
12. `12-dashboard-funcionario-mobile.png`

Las capturas usan datos locales/sintéticos y no contienen información personal real.

## 11. Docker

El servicio `frontend` fue reconstruido mediante Docker Compose. La instancia actual está disponible en `http://localhost:3000` y fue confirmada con respuesta HTTP 200 y estado healthy. No se reconstruyeron ni modificaron las reglas de negocio del backend durante esta fase.

## 12. Pendientes deliberados

- Reportes no muestra filtros de fecha porque el endpoint vigente entrega datos acumulados y no acepta rango. Agregar el control sin contrato habría sido engañoso; queda pendiente de una ampliación explícita de API.
- Los archivos históricos `-camilo_ortega` permanecen fuera de rutas e imports activos. Pueden eliminarse en una limpieza posterior, después de aprobar su descarte documental.
- El rediseño del PDF oficial está expresamente fuera de alcance; se mantiene el PDF TEMPORAL.
- Si Comunicaciones entrega un paquete oficial de marca para aplicaciones internas, el símbolo abstracto actual puede sustituirse sin cambiar layouts ni tokens.

## 13. Corrección de regresión de autorización administrativa

El frontend dejó de considerar `localStorage.user` como autoridad. `AuthProvider` usa el token para consultar `/auth/me` antes de renderizar una ruta privada; durante esa verificación muestra `Validando sesión...`. Solo después entrega el usuario confirmado a `ProtectedRoute`, `RoleRoute`, `HomeRedirect`, layouts y páginas con TanStack Query.

Si el perfil cacheado indica `admin` y `/auth/me` devuelve `funcionario`, se reemplaza el perfil local, se redirige a `/app/inicio` y la página administrativa no llega a montarse ni dispara consultas. Si `/auth/me` devuelve 401 se limpia la sesión. Un 403 conserva el token y muestra un error de validación, sin convertir autorización denegada en logout.

Se añadieron dos pruebas de identidad: discrepancia admin/funcionario sin requests administrativos, y admin confirmado que renderiza `/admin/funcionarios` y consulta el backend. Playwright añadió un recorrido sin mocks por dashboard, funcionarios, Manual, configuración y logout. Resultado final: 25 pruebas Vitest, lint/typecheck/build aprobados y 2 E2E reales aprobados.
