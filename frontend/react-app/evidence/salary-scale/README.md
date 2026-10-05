# Evidencia de escala salarial

Las capturas 01–07 son de Playwright contra PostgreSQL/API reales en la base aislada `scl_certificate_types_e2e`. No representan importación ni datos institucionales.

| Archivo | Escenario |
| --- | --- |
| 01-table-desktop.png | Tabla y años dinámicos, roles administrativos sintéticos |
| 02-search.png | Búsqueda por código en servidor |
| 03-create-year.png | Crear año desde la estructura de una base |
| 04-increase-preview.png | Vista previa de aumento 7 %, calculada en backend |
| 05-draft-2026.png | Borrador generado y acciones de revisión |
| 06-edit-value.png | Editar asignación y cantidad del año nuevo |
| 07-mobile.png | 360 px, tabla con overflow horizontal interno |

Cada repetición conserva históricos y usa el siguiente año disponible, desde 2026. El archivo 05 mantiene el nombre solicitado aunque la fecha sintética del escenario repetido avance. Las fuentes de esos escenarios dicen “sintético / no oficial”; no se cargan en CT dev. La suite completa puede regenerar las capturas 01–07.

## Importación revisada en CT dev, 2026-10-05

Las capturas 08–10 proceden del smoke real después de importar la fuente institucional 2024/2025. El recorte nativo del navegador incluye únicamente la tabla, sin cabecera de sesión, nombres de usuarios, credenciales ni datos personales. El ancho del navegador exige desplazamiento horizontal: 08 conserva la identidad de Comisario; 09 muestra las dos columnas de salarios; 10 conserva la identidad de Inspector. Los tres archivos se revisaron visualmente.

| Archivo | Escenario |
| --- | --- |
| 08-reviewed-import.jpg | Comisario de Familia 202/13, fuente intacta, FK nula y salario 2024 consultable |
| 09-comisario-2025.jpg | Misma fila pendiente; columnas 2024/2025 accesibles por teclado, ambas en borrador |
| 10-inspector-pending.jpg | Inspector de Tránsito 312/08, denominación intacta, FK nula y salario consultable |

Son salarios por cargo, sin relación con funcionarios. Fuente «Tabla salarial entregada por Dirección de Personal»; acto y fecha jurídica sin registrar. 2026 no fue creado. Detalle en `SALARY_SCALE_IMPORT_REVIEW.md`.
