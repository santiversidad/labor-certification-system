# Certificaciones Laborales Villavicencio

Frontend institucional para solicitar, revisar, generar, descargar y validar certificaciones laborales de la Alcaldia de Villavicencio.

## Stack

- React + Vite + TypeScript
- React Router DOM
- TanStack React Query
- Axios
- React Hook Form + Zod
- Tailwind CSS
- Lucide React

## Configuracion

Cree un archivo `.env` a partir de `.env.example`:

```bash
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_APP_NAME=Certificaciones Laborales Villavicencio
```

Los servicios llaman a la API real configurada en `VITE_API_BASE_URL`. Las pruebas usan datos sintéticos y bases aisladas.

## Comandos

```bash
npm install
npm run dev
npm run build
```

## Sesión

Autenticación real Sanctum con cookies y CSRF. Administración y Secretaría requieren MFA; funcionarios usan su portal personal. No se persisten tokens bearer en localStorage.

## Arquitectura

- Las paginas no llaman Axios directamente.
- Cada modulo consume datos desde `features/*/services/*.service.ts`.
- Los servicios usan `apiClient` para el backend real.
- Los endpoints viven en `src/lib/api/endpoints.ts`.
- La identidad y el estado de sesión se centralizan en AuthProvider.
- Las rutas protegidas usan `ProtectedRoute` y `RoleRoute`.
- Los formularios base usan React Hook Form y Zod.

## Rutas principales

- Publicas: `/login`, `/validar-certificado/:token`
- Funcionario: `/app/inicio`, `/app/solicitudes`
- Secretaría: `/admin/dashboard`, `/admin/pagos`, `/admin/certificaciones`
- Administrador: `/admin/dashboard`, `/admin/funcionarios`, `/admin/usuarios-internos`, `/admin/cargos`, `/admin/escala-salarial`, `/admin/manual-funciones`, `/admin/certificaciones`, `/admin/configuracion`, `/admin/auditoria`, `/admin/reportes`

## Escala salarial

Feature aislada en `src/features/escala-salarial/`, exclusivamente admin. Tabla dinámica por vigencias, búsqueda en servidor, edición de borradores, vista previa calculada en backend y publicación operativa confirmada. Las cifras fuente 2024/2025 están pendientes de transcripción revisada; no se infiere 2026. No afecta certificados ni salarios personales. [Modelo](../../SALARY_SCALE_MODEL.md) y [reporte](../../SALARY_SCALE_REPORT.md).


## Selección por dependencia

El formulario administrativo selecciona dependencia y cargo; el servidor resuelve la ficha exacta por ámbito y fecha. Modelo, API, compatibilidad histórica y límites de los datos: [DEPENDENCY_POSITION_SELECTION_MODEL.md](../../DEPENDENCY_POSITION_SELECTION_MODEL.md). La migración es aditiva, sin backfill ni modificaciones del Manual.
