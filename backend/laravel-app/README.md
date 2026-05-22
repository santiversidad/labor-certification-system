# Sistema de Certificaciones Laborales — Backend

**Alcaldía de Villavicencio, Meta**  
Stack: Laravel 13 · PHP 8.5 · PostgreSQL · Laravel Sanctum · Spatie Permission

---

## Requisitos

| Herramienta | Versión mínima |
|-------------|----------------|
| PHP         | 8.3+           |
| Composer    | 2.x            |
| PostgreSQL  | 14+            |
| Laravel CLI | 5.x            |

---

## Instalación

```bash
# 1. Clonar el repositorio
git clone <repo-url>
cd backend/laravel-app

# 2. Instalar dependencias
composer install

# 3. Copiar variables de entorno
cp .env.example .env

# 4. Generar clave de aplicación
php artisan key:generate

# 5. Configurar .env con sus credenciales PostgreSQL
#    DB_DATABASE=scl_db
#    DB_USERNAME=postgres
#    DB_PASSWORD=tu_password

# 6. Crear las bases de datos en PostgreSQL
psql -U postgres -c "CREATE DATABASE scl_db;"
psql -U postgres -c "CREATE DATABASE scl_db_test;"   # para pruebas

# 7. Ejecutar migraciones
php artisan migrate

# 8. Cargar datos iniciales
php artisan db:seed

# 9. Iniciar servidor de desarrollo
php artisan serve
```

---

## Variables de entorno relevantes

```env
APP_NAME="Sistema de Certificaciones Laborales"
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=scl_db
DB_USERNAME=postgres
DB_PASSWORD=tu_password

SANCTUM_STATEFUL_DOMAINS=localhost:3000
FRONTEND_URL=http://localhost:3000
```

---

## Usuarios seed

| Email | Contraseña | Rol |
|-------|-----------|-----|
| admin@villavicencio.gov.co | password | admin |
| secretario@villavicencio.gov.co | password | secretario |
| funcionario@villavicencio.gov.co | password | funcionario |

---

## Arquitectura interna

```
app/
├── Actions/                    # Acciones atómicas de dominio
│   └── RegistrarAuditoriaAction.php
├── Enums/                      # Enumeraciones de estado
│   ├── RoleEnum.php
│   ├── EstadoFuncionarioEnum.php
│   ├── EstadoSolicitudEnum.php
│   ├── EstadoPagoEnum.php
│   ├── TipoCertificadoEnum.php
│   └── EstadoCertificadoEnum.php
├── Http/
│   ├── Controllers/Api/V1/     # Controladores versionados
│   ├── Requests/               # Validaciones Form Request
│   └── Resources/              # API Resources (transformación JSON)
├── Models/                     # Modelos Eloquent
├── Services/                   # Servicios de lógica de negocio (Sprint 2)
├── Policies/                   # Control de acceso por recurso (Sprint 2)
└── Traits/
    └── ApiResponse.php         # Formato de respuesta JSON consistente
```

---

## Endpoints disponibles — Sprint 1

Prefijo base: `/api/v1`

### Autenticación
| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| POST | `/auth/login` | No | Iniciar sesión, retorna token |
| POST | `/auth/logout` | Sí | Cerrar sesión |
| GET  | `/auth/me` | Sí | Usuario y roles actuales |

### Cargos
| Método | Endpoint | Permiso requerido |
|--------|----------|-------------------|
| GET    | `/cargos` | `cargos.ver` |
| POST   | `/cargos` | `cargos.crear` |
| GET    | `/cargos/{id}` | `cargos.ver` |
| PUT    | `/cargos/{id}` | `cargos.editar` |

### Rangos Salariales
| Método | Endpoint | Permiso requerido |
|--------|----------|-------------------|
| GET    | `/rangos-salariales` | `rangos_salariales.ver` |
| POST   | `/rangos-salariales` | `rangos_salariales.crear` |
| GET    | `/rangos-salariales/{id}` | `rangos_salariales.ver` |
| PUT    | `/rangos-salariales/{id}` | `rangos_salariales.editar` |
| GET    | `/rangos-salariales/consultar?codigo=219&grado=02&vigencia=2026` | `rangos_salariales.ver` |

### Funcionarios
| Método | Endpoint | Permiso requerido |
|--------|----------|-------------------|
| GET    | `/funcionarios` | `funcionarios.ver` |
| POST   | `/funcionarios` | `funcionarios.crear` |
| GET    | `/funcionarios/{id}` | `funcionarios.ver` |
| PUT    | `/funcionarios/{id}` | `funcionarios.editar` |
| DELETE | `/funcionarios/{id}` | `funcionarios.eliminar` |

---

## Formato de respuesta JSON

**Éxito simple:**
```json
{ "success": true, "message": "...", "data": {} }
```

**Paginado:**
```json
{
  "success": true,
  "message": "...",
  "data": [],
  "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8 }
}
```

**Error:**
```json
{ "success": false, "message": "...", "errors": {} }
```

---

## Comandos útiles

```bash
# Migraciones
php artisan migrate               # Ejecutar migraciones pendientes
php artisan migrate:fresh --seed  # Reiniciar BD completa con datos seed
php artisan migrate:rollback      # Revertir última migración

# Seeders individuales
php artisan db:seed --class=RolesPermisosSeeder
php artisan db:seed --class=UsuariosInicialesSeeder
php artisan db:seed --class=DatosEjemploSeeder

# Pruebas
php artisan test                          # Todas las pruebas
php artisan test --filter LoginTest       # Solo autenticación
php artisan test --filter RangoSalarial   # Solo rangos salariales

# Inspección de rutas
php artisan route:list --path=api

# Limpiar caché
php artisan optimize:clear
```

---

## Pruebas automatizadas

Las pruebas usan PostgreSQL con base `scl_db_test` (configurada en `phpunit.xml`).

```bash
# Crear la BD de pruebas (solo una vez)
psql -U postgres -c "CREATE DATABASE scl_db_test;"

# Ejecutar suite completa
php artisan test
```

Cobertura Sprint 1:
- `LoginTest` — login válido, credenciales incorrectas, usuario inactivo, logout, sin auth
- `RangoSalarialTest` — consultar existente, 404, crear, duplicado, sin auth

---

## Roles y permisos

| Permiso | admin | secretario | funcionario |
|---------|:-----:|:----------:|:-----------:|
| usuarios.ver/crear/editar | ✅ | — | — |
| funcionarios.ver | ✅ | ✅ | — |
| funcionarios.crear/editar/eliminar | ✅ | — | — |
| cargos.ver | ✅ | ✅ | — |
| cargos.crear/editar | ✅ | — | — |
| rangos_salariales.ver | ✅ | ✅ | — |
| rangos_salariales.crear/editar | ✅ | — | — |
| solicitudes.ver | ✅ | ✅ | ✅ |
| solicitudes.crear | ✅ | ✅ | ✅ |
| solicitudes.cambiar_estado | ✅ | ✅ | — |
| pagos.validar/rechazar | ✅ | ✅ | — |
| certificados.generar | ✅ | ✅ | — |
| certificados.descargar | ✅ | ✅ | ✅ |
| auditoria.ver | ✅ | — | — |

---

## Pendientes — Sprint 2

- [ ] `SolicitudCertificacionController` + flujo completo de estados
- [ ] `PagoSoporteController` + carga segura de archivos (PDF/JPG/PNG)
- [ ] `CertificadoController` + generación PDF con DomPDF
- [ ] Código QR en certificados (Simple QrCode)
- [ ] `TokenValidacionService` + endpoint público `/validar-certificado/{token}`
- [ ] `ActuacionAdministrativaController`
- [ ] `AuditLogController`
- [ ] Políticas formales (`FuncionarioPolicy`, `CertificadoPolicy`, etc.)
- [ ] Pruebas de funcionarios, cargos, solicitudes y certificados
- [ ] Documentación OpenAPI / Swagger (L5-Swagger o Scribe)
