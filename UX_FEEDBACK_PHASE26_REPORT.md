# Fase 2.6 — UX feedback y flujo de solicitud

Rama: `feat/ux-feedback-request-flow`. Base: `dbd5ad0` (Fase 3A). Alcance: frontend y configuración E2E de CT. No se hizo push.

## 1. Problema reportado

La disponibilidad, el límite mensual y las fechas aparecían como texto secundario. Tras escoger un tipo en el dashboard, el funcionario debía escogerlo otra vez en el formulario.

## 2. Inventario de mensajes

Se revisaron las pantallas activas del frontend, los usos de `Alert`, `ErrorState`, `text-muted`, `text-sm`, estados deshabilitados, mensajes de dominio y fechas. Los archivos históricos `*-camilo_ortega.tsx` no forman parte del router activo.

| Pantalla | Mensaje | Importancia anterior | Problema | Solución |
| --- | --- | --- | --- | --- |
| Login | Credenciales y validación | Error visible | Ya se distingue como error de formulario | Sin cambio |
| Cambio de contraseña | Cambio obligatorio y reglas | Título más párrafo tenue; reglas en info | La condición para continuar podía perderse | `Alert` warning con título explícito; error en `Alert` error |
| Dashboard funcionario | Cupos independientes | Franja con texto secundario | La regla mensual no destacaba | `Alert` info con título y explicación para usuario |
| Cards funcionario | Disponible / no disponible | Badge pequeño | Estado posible de pasar por alto | Badges “Disponible ahora” / “No disponible” con iconos y acción según estado |
| Cards funcionario | Próxima fecha | Texto de 12 px, fecha ISO | Fecha decisiva poco legible | Bloque ámbar, icono, rótulo y fecha natural en negrita |
| Solicitud | Tipo elegido, límite y pago | Selector repetido; aviso solo en resumen | Paso redundante y consecuencia tardía | Tipo fijo y aviso info antes de generar |
| Solicitud directa | Cupo agotado | Selector podía abrirse | Se podía intentar desde URL | Consulta real, warning y botón deshabilitado |
| Solicitud funciones | Ficha incompleta | Error de negocio tras POST | Debía distinguirse | `Alert` error con texto de dominio; backend conserva 409 |
| Confirmación | Enlace de descarga limitado / pago pendiente | Párrafo tenue | Consecuencia temporal y estado podían pasar inadvertidos | Aviso success o warning |
| Dashboard admin | Manual, pago, indicadores | Métricas y badges | Estados ya distinguibles; son resumen | Sin cambio |
| Funcionarios | Restablecer acceso | Éxito en `Alert` | Requiere anuncio al aparecer | `role=status` solo en este éxito dinámico |
| Manual | Publicación irreversible, errores | Texto secundario de solo lectura; errores no visibles | La acción de publicar requería más contexto | Warning previo y errores en `Alert` error |
| Certificaciones | Modelo histórico | Badge en detalle | Diferencia frente al vigente poco explícita | `Alert` warning en detalle |
| Configuración | Activar pago retrasa PDF | `Alert` info | Consecuencia de cambiar el switch | `Alert` warning; diálogo conservado |
| Auditoría y reportes | Alcance de datos | Reportes ya usa `Alert`; auditoría descriptiva | No hay decisión nueva en texto secundario | Sin cambio |
| Validación pública | Código inválido, anulado o integridad comprometida | Mensaje tenue dentro del resultado | Estado inválido debía destacar | `Alert` error con título y badge conservado |

Ayuda normal como descripciones de cargos, campos, tablas y navegación se mantuvo discreta. Los módulos de pago y solicitudes históricos que no aparecen en el router activo no se alteraron.

## 3. Regla de jerarquía

Una condición que cambia disponibilidad, decisión, fecha, consecuencia o posibilidad de continuar recibe icono, título y superficie o borde distinguible. El texto descriptivo que solo orienta permanece como texto normal. La comunicación no depende del color.

## 4. `Alert`

El componente único admite `variant="info|warning|success|error"`, título, contenido adicional y un icono decorativo. `tone` sigue aceptado para las pantallas existentes. El error usa `role=alert`; los avisos estáticos no se anuncian en cada render. Un éxito que aparece tras una acción puede pedir `announce` para `role=status`.

## 5. Dashboard funcionario

El aviso general ahora explica que sencillo y funciones tienen disponibilidad mensual independiente. Las cards conservan `SpotlightCard`, `GradientText` y `CountUp` donde ya se utilizaban; Spotlight queda deshabilitado en la card no disponible.

## 6. Disponibilidad

Las cards muestran estado junto al icono. “Disponible ahora” acompaña un botón accionable. “No disponible” acompaña un bloque de próxima fecha y botón nativo `disabled`. La segunda card puede seguir disponible aunque se haya consumido la primera.

## 7. Fechas

Se añadió `formatLongDate` al formatter de fechas existente para presentar, por ejemplo, `1 de octubre de 2026`. El valor ISO queda en el contrato API; la presentación es local a la UI.

## 8. Flujo anterior

Dashboard → seleccionar tipo → formulario → volver a seleccionar tipo → continuar → resumen → confirmar → resultado.

## 9. Flujo nuevo

Dashboard → seleccionar tipo → formulario con tipo fijo y observaciones opcionales → generar → resultado.

## 10. Rutas

Rutas finales: `/app/solicitudes/nueva/sencillo` y `/app/solicitudes/nueva/funciones`. Se usó parámetro de ruta porque el tipo identifica la página y el router ya es jerárquico. `/app/solicitudes/nueva` redirige al inicio. Cualquier parámetro distinto de los dos canónicos también redirige antes de montar el formulario.

## 11. Formulario

El tipo se ve en un bloque de contexto y no hay radio, select ni selector de ficha o funciones. Se conservan observaciones opcionales con máximo 500 caracteres. El botón nombra el certificado generado.

## 12. Payload

El POST se construye con `tipo_certificado` del parámetro validado y `observaciones`. No contiene `requiere_salario`, `funcionario_id`, `manual_ficha_id` ni `incluye_funciones`.

## 13. Enlaces directos

Cada montaje consulta `disponibilidad-certificacion` y fuerza actualización aun si había caché. Durante la consulta, el botón está deshabilitado. Con cupo agotado aparece warning, fecha y enlace al inicio. Tras un fallo de POST se vuelve a consultar disponibilidad. El backend conserva la autoridad y sus respuestas 409.

## 14. Accesibilidad

Los iconos decorativos se ocultan al lector; el título y el texto expresan el estado. Se mantienen `disabled` nativo, foco visible del botón y enlace, navegación por teclado y mensajes de error anunciados al aparecer. Los avisos informativos persistentes no usan live region.

## 15. Responsive

El layout mantiene cards en una columna móvil y dos desde tablet. Avisos, fecha, formulario y acciones usan ancho flexible; Playwright comprobó ausencia de overflow horizontal a 360 y 768 px, y las capturas de escritorio se tomaron a 1440 px.

## 16. Tests

Vitest cubre aviso general, títulos, variantes, badges, fecha destacada, disabled, rutas de ambas cards, ausencia de selector, tipo fijo, payloads, parámetro inválido, enlace directo bloqueado y error 409. La suite completa pasó **41/41** en el contenedor frontend CT. TypeScript, ESLint y build Vite pasaron. La suite backend completa pasó **157/157, 826 aserciones** en `scl_certificate_types_test`. En el host Node 25 se usa `NODE_OPTIONS=--no-experimental-webstorage` para evitar que su implementación experimental de `localStorage` sustituya la de jsdom; la corrida concluyente se hizo en el contenedor CT.

## 17. E2E

Los **7/7** escenarios Playwright de CT cubren admin y 36 permisos, expedición real sencillo y funciones, PDF descargable, cuotas independientes, cambio de identidad, ficha incompleta y pantallas de 360/768 px. Se añadió navegación y POST desde la UI para ambos tipos, y acceso directo consumido. El E2E usa exclusivamente `scl_certificate_types_e2e` y los puertos internos 3200/8280.

## 18. Screenshots

Las 12 capturas reales de CT están en `frontend/react-app/evidence/ux-feedback-phase26/`: aviso general, sencillo disponible y no disponible, fecha destacada, formularios de ambos tipos, enlace directo bloqueado, error de ficha incompleta, cambio de contraseña, validación inválida y móvil. La captura `08-mf0167-warning.png` usa la ficha sintética incompleta de E2E que reproduce el error `MANUAL_FICHA_INCOMPLETA`; la ficha real MF-0167 permanece en CT desarrollo y no se resembró para tomar la imagen.

## 19. Comparación visual

Referencia preservada: `frontend/react-app/evidence/reactbits-phase25/`. Antes, el cupo se veía en una franja de texto secundario y la fecha como `2026-10-01` en 12 px. Después, el aviso tiene encabezado y superficie azul; la próxima fecha está en un bloque ámbar con jerarquía de 16 px. Antes, la solicitud repetía dos cards de selección y un paso de resumen. Después, el tipo está fijo, visible y no editable, y se genera desde el mismo formulario.

## 20. Archivos y límites

Se modificaron componentes y páginas frontend de avisos, dashboard, solicitud, confirmación, contraseña, Manual, configuración, detalle histórico y validación; pruebas Vitest y Playwright; `playwright.config.ts`; y los puertos internos E2E de `docker-compose.certificate-types.yml`. No se modificó código backend ni el PDF institucional: `CertificateDocumentData`, `CertificadoPdfService`, `CertificateAssetResolver`, `document.css`, `laboral.blade.php` y PDF tests permanecen intactos. No se iniciaron firma, QR ni oficialización. La verificación SQL de solo lectura en CT desarrollo confirmó versión 10 publicada (`1000-24/015`), 344 fichas, 3.115 funciones, 2.326 conocimientos y 36 permisos. La búsqueda de referencias salariales en frontend activo, excluyendo pruebas y archivos históricos, devolvió cero resultados.

Archivos de implementación: `src/app/router.tsx`, `src/components/ui/Alert.tsx`, `src/lib/formatters/dates.ts`, `src/features/dashboard/pages/FuncionarioDashboardPage.tsx`, `src/features/solicitudes/components/SolicitudForm.tsx`, `src/features/solicitudes/pages/SolicitudCertificadoPage.tsx`, `SolicitudConfirmacionPage.tsx`, `src/features/auth/pages/ChangePasswordPage.tsx`, `src/features/manuales/pages/ManualesPage.tsx`, `src/features/configuracion/pages/ConfiguracionCertificacionesPage.tsx`, `src/features/funcionarios/pages/FuncionariosPage.tsx`, `src/features/certificados/pages/CertificadoDetailPage.tsx` y `src/features/validacion-publica/components/ValidationResultCard.tsx` (todos bajo `frontend/react-app`). Pruebas y entorno: `src/components/ui/Alert.test.tsx`, `FuncionarioDashboardPage.test.tsx`, `SolicitudForm.test.tsx`, `e2e/autoservice.spec.ts`, `e2e/control-screenshots.spec.ts`, `playwright.config.ts` y `docker-compose.certificate-types.yml`. Evidencia nueva: `frontend/react-app/evidence/ux-feedback-phase26/`.
