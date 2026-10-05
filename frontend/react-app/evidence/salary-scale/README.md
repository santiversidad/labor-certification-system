# Evidencia sintética — escala salarial

Capturas de Playwright contra PostgreSQL/API reales en la base aislada `scl_certificate_types_e2e`. No representan importación ni datos institucionales.

| Archivo | Escenario |
| --- | --- |
| 01-table-desktop.png | Tabla y años dinámicos, roles administrativos sintéticos |
| 02-search.png | Búsqueda por código en servidor |
| 03-create-year.png | Crear año desde la estructura de una base |
| 04-increase-preview.png | Vista previa de aumento 7 %, calculada en backend |
| 05-draft-2026.png | Borrador generado y acciones de revisión |
| 06-edit-value.png | Editar asignación y cantidad del año nuevo |
| 07-mobile.png | 360 px, tabla con overflow horizontal interno |

Cada repetición conserva históricos y usa el siguiente año disponible, desde 2026. El archivo 05 mantiene el nombre solicitado aunque la fecha sintética del escenario repetido avance. Las fuentes dicen “sintético / no oficial”; ningún valor se cargó en CT dev. La suite completa puede regenerar estas capturas.
