# Importacion Stitch

## Estado de importacion

No se pudo consultar Google Stitch desde esta sesion porque no hay herramienta MCP de Stitch disponible mediante `tool_search`.

Proyecto solicitado:

- Title: Certificaciones Laborales Villavicencio
- Project ID: `8534803900640218404`

Pantallas solicitadas:

- Dashboard Funcionario V2: `db271a97f1274fdebf79f221d828ea1c`
- Solicitud de Certificado V2: `d9a0184404db482dbaa0a60d7e9dd8d7`
- Panel Administrativo V2: `3283015ce6074ede81d22c0c7b93e227`

## Archivos creados

- `src/assets/stitch/dashboard-funcionario-v2/reference.png`
- `src/assets/stitch/dashboard-funcionario-v2/stitch-source.html`
- `src/assets/stitch/solicitud-certificado-v2/reference.png`
- `src/assets/stitch/solicitud-certificado-v2/stitch-source.html`
- `src/assets/stitch/panel-administrativo-v2/reference.png`
- `src/assets/stitch/panel-administrativo-v2/stitch-source.html`

Los `reference.png` y `stitch-source.html` actuales son placeholders documentados. Deben reemplazarse por los recursos reales si el MCP de Stitch queda habilitado.

## Adaptacion realizada

Aunque no fue posible descargar las pantallas reales, se integro una version visual institucional en las pantallas existentes sin romper la arquitectura:

- `src/features/dashboard/pages/FuncionarioDashboardPage.tsx`
- `src/features/solicitudes/pages/SolicitudCertificadoPage.tsx`
- `src/features/solicitudes/components/SolicitudForm.tsx`
- `src/features/dashboard/pages/AdminDashboardPage.tsx`
- Componentes nuevos en `src/features/dashboard/components/`
- Ajustes responsive en layout base

## Decisiones

- Se mantuvo TanStack Query para consultas existentes.
- Se mantuvo React Hook Form + Zod en solicitud de certificado.
- Se agrego creacion mock mediante `solicitudesService.create`.
- No se agregaron llamadas directas a Axios en componentes o paginas.
- Se reutilizaron `Button`, `Card`, `Badge`, `Table`, `Input`, `LoadingState` y `ErrorState`.
- La paleta institucional existente se conserva en Tailwind.
