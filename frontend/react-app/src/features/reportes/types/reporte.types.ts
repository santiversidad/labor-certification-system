export type ReporteResumen = {
  total_funcionarios: number;
  total_solicitudes: number;
  solicitudes_pendientes: number;
  solicitudes_aprobadas: number;
  solicitudes_rechazadas: number;
  certificados_generados: number;
  pagos_pendientes: number;
  tiempo_promedio_respuesta: number | null;
};
