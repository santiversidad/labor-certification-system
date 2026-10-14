# Plan condicionado Fase 3B.2

2026-10-04, Bogotá. **Plan, no inicio de implementación.** 3B.1 termina con commit documental y sin push. La ejecución de este plan requiere nueva instrucción y decisiones institucionales; no quitar BORRADOR por tener este documento.

## Gates y orden de trabajo

| Gate / etapa | Entregable / responsable propuesto | Condición para continuar |
|---|---|---|
| G0 Aprobación de alcance | Nueva autorización de fase, precheck limpio, revisión informe 3B.1. | No iniciar automáticamente después del commit 3B.1. |
| G1 Decisiones jurídicas/institucionales | Confianza/modalidad/SCD; competencia/cargo/delegación/encargo; autorización desatendida; privacidad/retención/anulación. Jurídica + autoridad competente + dependencia. | Fichas de confianza/firmante aprobadas; no sustituidas por decisión de desarrollo. |
| G2 Insumos e infraestructura | Muestra/identidad documental/dominio, D01–D05/D08 y operación 24/7. | Assets verificables; custodia/secret manager/storage/queue y responsables definidos. Diseño sintético local puede preceder despliegue, integración real no omite gates. |
| G3 Selección técnica | API/DPC/alcance/credencial/TSA/revocación/SLA/exportación y autorización automática. | Compatibilidad jurídica y técnica; sin contacto/compra implícitos por este plan. Contrato e identidad reales requieren proceso institucional. |
| G4 Evaluación dependencias | Informe licencia/mantenimiento/CVEs/runtime/perfiles/custodia, decisión de motor/verificador. | Antes de instalar paquete; evidencia real de PAdES y firma remota, no imagen. |
| G5 Arquitectura final | ADR y delta RF/RNF/UML aprobados; modelo mínimo/compatibilidad legacy. | Resolver publicación, idempotencia/outbox/cola, resultado ambiguo, vigencia firmante, política validación y preservar v3. |
| G6 Implementación local/sandbox autorizada | Migraciones estrictamente necesarias, dominio/cola/provider/verificador/almacenamiento y estado/QR. | Fixtures sintéticos aislados; secretos nunca repo; servicios sandbox solo con autorización/contexto institucional. |
| G7 Validación y seguridad | Pruebas abajo, QA PDF, auditorías, runbooks/restore y riesgos residuales. | Evidencia verificable, sin resultados fake aceptados; regressions pasan. |
| G8 Habilitación institucional | Revisión Jurídica/seguridad/archivo/operaciones; credencial/acto/config final. | Solo nuevos documentos desde fecha de habilitación; producción/oficialización no autorizadas por 3B.1. |

## Implementación candidata al superar gates

1. Crear oficialización separada y constraints/claims/version mínimos; mantener solicitud lógica, pago, cuota y radicado. Decidir relación con `hash_pdf` y path de borrador sin reinterpretar historia.
2. Preparar documento nuevo con plantilla/assets/QR/dominio aprobados antes de firma; input durable/hash fijo. Contexto de firma fuera de snapshot laboral; ninguno histórico se muta.
3. Despacho fiable postcommit/outbox, worker persistente supervisado, retry y reconciliación por clave; adaptar queue elegida con timeouts/leases coherentes.
4. Adaptador único al proveedor seleccionado/servicio institucional, acceso desde secret manager y egress controlado; sin `/sign` arbitrario. Callback si necesario con autenticidad/replay/deduplicación.
5. Verificador real independiente, política de confianza/identidad/chain/TSA/OCSP/CRL/cobertura/revisiones; no aceptar `completed` por sí mismo. Fallos indeterminados bloquean publicación.
6. Final privado inmutable/hash/evidencia y publicación atómica; entrega del binario exacto con controles 2.9. Estado owner y público minimizado; legacy siempre explícito como borrador técnico.
7. Reusar anulación actual con política oficial aprobada; no implementar sistema adicional sin necesidad. Vigencias/encargos y suspensión de autorización versionados. Nuevos permisos solo para excepciones.
8. Runbooks/observabilidad y cierre de evidencias. Preservación B-LTA solo si aprobada, con representaciones enlazadas sin sobrescritura.

## Gate previo a instalar bibliotecas/SDK

| Criterio | Evidencia exigida |
|---|---|
| Mantenimiento | Releases/commits y soporte del autor consultados, issues críticos y política de seguridad; no antigüedad inferida por fama. |
| Licencia | SPDX/licencia real y compatibilidad de uso/distribución municipal; revisión de dependencia/transitivas. |
| CVEs/advisories | Consulta oficial/vendor/GHSA/OSV y composer/npm audit en fecha de instalación; vulnerabilidades transitivas/dev y D08. |
| PHP 8.4 / Laravel 13 | Restricciones composer, ejecución en contenedor real y pruebas; librería agnóstica no implica integración sin verificar. |
| PAdES | Perfiles reales, cobertura/revisiones, ensamblado PDF y comprobación por verificador independiente. PKCS#7 simple no prueba B-LT. |
| TSA / revocación | RFC3161, cadena/política, material OCSP/CRL histórico y manejo de unknown. |
| Claves remotas | API/HSM/PKCS#11 o firma de digest con ensamblado/custodia aprobados; ningún requisito de exportar clave real a app. |
| Operación | Límites/parser/egress/timeouts/dedupe/exportación y actualizaciones; pruebas de mantenimiento/renovación. |

Sin candidato adecuado documentar limitación y no incorporar biblioteca solo para aparentar firma. Una POC sintética opcional genera clave efímera en memoria durante test, identidad ficticia, ningún output con private key, destrucción al terminar y rótulo **PRUEBA TÉCNICA — SIN VALIDEZ OFICIAL**. Su éxito técnico nunca demuestra aprobación jurídica. 3B.1 no hizo POC ni seleccionó biblioteca.

## Plan de pruebas significativo

| Nivel | Casos mínimos / oráculo |
|---|---|
| Unit/dominio | Input fijo/hashes separados; QR solo URL/capability antes de firma; owner/acto vigente/pago; retry no recrea solicitud/cupo/radicado; clave duplicada/input distinto rechazado; CAS y fallos permanentes/transitorios/ambiguos. |
| Integration local | Worker/outbox después de commit; caída entre commit/enqueue; duplicados simultáneos; crash antes/después de firma/storage/publicación; reconciliar sin transaction ID; anulación/cambio de firmante durante operación. |
| Sandbox proveedor | Identidad sintética, sign/status por clave, replay/callback duplicado, API caída/timeouts, PDF distinto, cadena/perfil/TSA/revocación/unknown. Sin certificado personal real de prueba. |
| Criptografía real de test | Validación independiente de fixture firmado sintético; **modificar byte cubierto/contenido → invalid**, añadir revisión de contenido → rechazo por política; revisar ByteRange y cobertura total esperada. Un parser que solo detecta `/Sig` no es oráculo. |
| Largo plazo | Credencial expirada con evidencia histórica; revocación antes/después del momento pertinente; TSA ausente/incorrecta; material OCSP/CRL viejo; indeterminate conservado; renovación archivística no sobrescribe binario. |
| E2E | Generar→oficializar→descargar mismos bytes→validar registro; pago OFF/ON con una cuota/orden/radicado; pending/failure/no falso oficial; anulado y legacy; tercero verifica copia alterada aunque QR copiado apunte a registro válido. |
| Security | CSRF/CORS/cookie/MFA/auth_version/IDOR/permisos preservados; enumeración capability/no PII; logs/referrer/APM sin tokens; SSRF AIA/OCSP/URL; secrets ausentes del bundle/build; endpoint arbitrario inexistente. |
| Operación/rendimiento | p95 con volumen aprobado, backlog/proveedor caído, timeout<retry_after, workers multiinstancia, secret/cert rollover, clock drift, restore y plan de salida. |
| PDF/Manual | QA todas páginas sencilla/funciones, Unicode/paginación/contenido snapshot íntegro; QR/dominio legibles; Manual 344/3.115/2.326 preservado, ningún salario o función inventada. |

Regresión completa obligatoria en 3B.2 o cualquier POC/cambio runtime: backend completo en DB *_test aislada; frontend; E2E en DB E2E aislada; lint; typecheck; build; Pint; composer audit; npm audit completo y runtime. Baseline heredado 2.9: **268 tests/1.535 assertions; frontend 78; E2E 30**. No se afirma que esas suites fueron corridas en 3B.1.

No resetear CT desarrollo, ni modificar Manual o IDs históricos para obtener pruebas verdes. Traces/evidencias no pueden exponer claves/tokens. Tests fake de coordinación y test criptográfico independiente se reportan separados.

## Runbooks y SLA por aprobar

Negociar timeout por operación, reintentos máximos/ventanas, disponibilidad conjunta, latencia objetivo, backlog, RPO/RTO, soporte 24/7 y ventanas de mantenimiento. Database/Redis/servicio institucional según infraestructura, sin instalar Redis por defecto. Fuente técnica consultada 2026-10-04: [Laravel 13 queues](https://laravel.com/docs/13.x/queues).

Procedimientos: renovar credencial antes de expiración; cambio/encargo de titular con operaciones en vuelo; recuperar caída de proveedor/worker/storage; suspender firma ante compromiso; validar/revocar sin borrar historia; restaurar evidencia/estado/outbox; retirar proveedor exportando bytes/evidencias; renovar preservación archivística si aplica. Custodios de factores y secretos y recuperación no quedan a cargo de una sola cuenta admin.

Archivo/protección de datos deben decidir retención PDF/evidencia/logs/TRD, tratamiento al retiro, acceso y validación después de expiry, anulación y rotación capability. **No hay política resuelta por desarrollo.**

## Cierre futuro

Criterio: nuevos documentos oficiales únicamente con autoridad/firmante vigentes, autorización automática, mecanismo/evidencia aprobados, verificación y storage final, privacidad y riesgos tratados. Histórico intacto; fallos recuperables sin nuevo cobro/cupo/radicado; PAdES/perfil y custodia demostrados si aplican; ausencia de claves en app. No realizar compras, contratación, despliegue ni emitir certificados oficiales sin el proceso/autorización institucional correspondiente.
