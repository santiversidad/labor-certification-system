-- Read-only audit. Run against the canonical CT development database.
BEGIN READ ONLY;
SELECT id, version, estado, vigencia_desde, vigencia_hasta FROM manual_funciones_versiones WHERE id = 10;
SELECT COUNT(*) fichas,
       (SELECT COUNT(*) FROM manual_funciones_esenciales f JOIN manual_cargo_versiones c ON c.id=f.manual_cargo_version_id WHERE c.manual_funciones_version_id=10) funciones,
       SUM(jsonb_array_length(metadata_manual->'conocimientos')) conocimientos,
       md5(string_agg(source_id || ':' || content_hash, ',' ORDER BY source_id)) digest_fichas
FROM manual_cargo_versiones WHERE manual_funciones_version_id=10;
SELECT source_id, dependencia, area_funcional, proposito_principal,
       jsonb_array_length(metadata_manual->'conocimientos') conocimientos
FROM manual_cargo_versiones WHERE manual_funciones_version_id=10 AND source_id='MF-0167';
WITH scopes AS (
    SELECT c.*, LOWER(BTRIM(REGEXP_REPLACE(REGEXP_REPLACE(REPLACE(REPLACE(
        COALESCE(NULLIF(BTRIM(area_funcional), ''), NULLIF(BTRIM(c.dependencia), '')),
        '–', '-'), '—', '-'), '\s*-\s*', ' - ', 'g'), '\s+', ' ', 'g'))) unidad
    FROM manual_cargo_versiones c WHERE manual_funciones_version_id=10
)
SELECT COUNT(DISTINCT unidad) unidades, COUNT(*) fichas_con_ambito_concreto
FROM scopes WHERE unidad NOT IN ('', 'donde se ubique el cargo', 'todas las dependencias');
WITH scopes AS (
    SELECT c.*, LOWER(BTRIM(REGEXP_REPLACE(REGEXP_REPLACE(REPLACE(REPLACE(
        COALESCE(NULLIF(BTRIM(area_funcional), ''), NULLIF(BTRIM(c.dependencia), '')),
        '–', '-'), '—', '-'), '\s*-\s*', ' - ', 'g'), '\s+', ' ', 'g'))) unidad
    FROM manual_cargo_versiones c WHERE manual_funciones_version_id=10
), unidades AS (
    SELECT DISTINCT unidad FROM scopes WHERE unidad NOT IN ('', 'donde se ubique el cargo', 'todas las dependencias')
), aplicables AS (
    SELECT unidad, cargo_id, source_id FROM scopes WHERE unidad NOT IN ('', 'donde se ubique el cargo', 'todas las dependencias')
    UNION ALL
    SELECT unidades.unidad, scopes.cargo_id, scopes.source_id FROM unidades CROSS JOIN scopes
    WHERE scopes.unidad='todas las dependencias'
)
SELECT unidad, cargo.codigo, cargo.grado, cargo.denominacion, COUNT(*) perfiles,
       string_agg(source_id, ', ' ORDER BY source_id) fichas
FROM aplicables JOIN cargos cargo ON cargo.id=aplicables.cargo_id
GROUP BY unidad, cargo.codigo, cargo.grado, cargo.denominacion HAVING COUNT(*)>1
ORDER BY unidad, cargo.codigo, cargo.grado;
SELECT source_id, area_funcional, dependencia FROM manual_cargo_versiones
WHERE manual_funciones_version_id=10 AND (BTRIM(COALESCE(area_funcional, '')) IN ('', 'Todas las dependencias'))
ORDER BY source_id;
COMMIT;
