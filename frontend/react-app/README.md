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
VITE_USE_MOCKS=true
VITE_APP_NAME=Certificaciones Laborales Villavicencio
```

Con `VITE_USE_MOCKS=true`, los servicios devuelven datos locales. Con `VITE_USE_MOCKS=false`, los mismos servicios usan `apiClient` y llaman a la API configurada en `VITE_API_BASE_URL`.

## Comandos

```bash
npm install
npm run dev
npm run build
```

## Usuarios mock

La autenticacion real aun no esta implementada. El login guarda un token falso y el usuario en `localStorage`.

- `funcionario@villavicencio.gov.co`
- `secretario@villavicencio.gov.co`
- `admin@villavicencio.gov.co`

Puede usar cualquier contrasena no vacia mientras `VITE_USE_MOCKS=true`.

## Arquitectura

- Las paginas no llaman Axios directamente.
- Cada modulo consume datos desde `features/*/services/*.service.ts`.
- Los servicios usan `apiClient` para backend real y mocks centralizados para desarrollo.
- Los endpoints viven en `src/lib/api/endpoints.ts`.
- La sesion mock se centraliza en `src/lib/auth/authStorage.ts`.
- Las rutas protegidas usan `ProtectedRoute` y `RoleRoute`.
- Los formularios base usan React Hook Form y Zod.

## Rutas principales

- Publicas: `/login`, `/validar-certificado/:token`
- Funcionario/secretario: `/app/dashboard`, `/app/solicitudes`, `/app/certificados`
- Administrador: `/admin/dashboard`, `/admin/funcionarios`, `/admin/cargos`, `/admin/rangos-salariales`, `/admin/auditoria`, `/admin/reportes`
