$ErrorActionPreference = 'Stop'
Push-Location (Join-Path $PSScriptRoot '..')
try {
    # Fixed synthetic database; never migrate or seed scl_db.
    $databaseExists = docker compose exec -T postgres psql -U postgres -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='scl_e2e_test'"
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar PostgreSQL.' }
    if ($databaseExists -ne '1') {
        docker compose exec -T postgres createdb -U postgres scl_e2e_test
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la base E2E.' }
    }
    docker compose -f docker-compose.yml -f docker-compose.e2e.yml --profile e2e up -d --wait e2e-api e2e-web
    if ($LASTEXITCODE -ne 0) { throw 'Servicios E2E no saludables.' }
    docker compose -f docker-compose.yml -f docker-compose.e2e.yml exec -T e2e-api php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Falló la migración incremental E2E.' }
    docker compose -f docker-compose.yml -f docker-compose.e2e.yml exec -T e2e-api php artisan e2e:prepare-autoservice
    if ($LASTEXITCODE -ne 0) { throw 'Falló la preparación E2E.' }
    docker compose -f docker-compose.yml -f docker-compose.e2e.yml --profile e2e run --rm e2e-runner
    if ($LASTEXITCODE -ne 0) { throw 'Playwright detectó un fallo.' }
} finally {
    # Keep synthetic records and volumes for diagnosis; stop only these temporary servers.
    docker compose -f docker-compose.yml -f docker-compose.e2e.yml stop e2e-api e2e-web
    Pop-Location
}
