-- Read-only fingerprints. No personal data or normative function text is emitted.
WITH profiles AS (SELECT * FROM manual_cargo_versiones WHERE manual_funciones_version_id = 10)
SELECT json_build_object(
 'database', current_database(),
 'fichas', (SELECT count(*) FROM profiles),
 'funciones', (SELECT count(*) FROM manual_funciones_esenciales WHERE manual_cargo_version_id IN (SELECT id FROM profiles)),
 'conocimientos', (SELECT coalesce(sum(jsonb_array_length(metadata_manual->'conocimientos')),0) FROM profiles),
 'digest_normativo', (SELECT md5(string_agg(source_id || ':' || content_hash, ',' ORDER BY source_id)) FROM profiles),
 'perfiles_fuente_digest', (SELECT md5(string_agg((to_jsonb(p)-ARRAY['pagina_inicio','verificacion_documental','updated_at'])::text, ',' ORDER BY id)) FROM profiles p),
 'funciones_digest', (SELECT md5(string_agg(to_jsonb(f)::text, ',' ORDER BY id)) FROM manual_funciones_esenciales f WHERE manual_cargo_version_id IN (SELECT id FROM profiles)),
 'vinculaciones', (SELECT count(*) FROM funcionario_cargo),
 'vinculaciones_digest', (SELECT md5(string_agg(to_jsonb(f)::text, ',' ORDER BY id)) FROM funcionario_cargo f),
 'relaciones_digest', (SELECT md5(string_agg(to_jsonb(f)::text, ',' ORDER BY id)) FROM funcionario_cargo_manual_fichas f),
 'certificados', (SELECT count(*) FROM certificados),
 'certificados_digest', (SELECT md5(string_agg(to_jsonb(c)::text, ',' ORDER BY id)) FROM certificados c),
 'escala_items_digest', (SELECT md5(string_agg(to_jsonb(i)::text, ',' ORDER BY id)) FROM escala_salarial_items i),
 'escala_vigencias_digest', (SELECT md5(string_agg(to_jsonb(v)::text, ',' ORDER BY id)) FROM escala_salarial_vigencias v),
 'pagos_digest', (SELECT md5(string_agg(to_jsonb(p)::text, ',' ORDER BY id)) FROM pagos_soportes p),
 'ordenes_pago_digest', (SELECT md5(string_agg(to_jsonb(p)::text, ',' ORDER BY id)) FROM ordenes_pago_certificado p),
 'comprobantes_digest', (SELECT md5(string_agg(to_jsonb(p)::text, ',' ORDER BY id)) FROM pago_comprobantes p),
 'identidades_digest', (SELECT md5(string_agg(to_jsonb(u)::text, ',' ORDER BY id)) FROM users u)
);
