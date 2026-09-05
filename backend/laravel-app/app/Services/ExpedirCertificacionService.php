<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoOrdenPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\OrdenPagoCertificado;
use App\Models\ParametroSistema;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExpedirCertificacionService
{
    public function __construct(
        private readonly DisponibilidadCertificacionService $disponibilidad,
        private readonly GenerarCertificadoService $generarCertificado,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function expedir(User $actor, bool $requiereSalario, string $tipoCertificado, ?string $observaciones): array
    {
        if ($actor->must_change_password) {
            throw new \DomainException('PASSWORD_CHANGE_REQUIRED');
        }
        $funcionario = $actor->funcionario;
        if (! $funcionario || $funcionario->estado !== EstadoFuncionarioEnum::Activo || ! $actor->estado) {
            throw new RuntimeException('INACTIVE_EMPLOYEE');
        }
        if (! $funcionario->cargo_id || ! $funcionario->cargo) {
            throw new RuntimeException('EMPLOYEE_WITHOUT_POSITION');
        }
        $fecha = CarbonImmutable::now(config('app.timezone'));
        app(ResolverFuncionesFuncionarioService::class)->resolver($funcionario, $fecha, $tipoCertificado === 'funciones');
        if ($requiereSalario) {
            app(ResolverSalarioFuncionarioService::class)->resolver($funcionario, $fecha);
        }

        $periodo = $this->disponibilidad->periodo();
        $requierePago = (bool) ParametroSistema::valor('requiere_pago_certificado', false);
        $resultadoGeneracion = null;

        try {
            $solicitud = DB::transaction(function () use (
                $actor, $funcionario, $requiereSalario, $tipoCertificado, $observaciones,
                $periodo, $requierePago, &$resultadoGeneracion
            ) {
                DB::select('SELECT pg_advisory_xact_lock(?)', [$periodo->year]);

                if (SolicitudCertificacion::query()
                    ->where('funcionario_id', $funcionario->id)
                    ->whereDate('periodo_mes', $periodo->toDateString())
                    ->where('requiere_salario', $requiereSalario)
                    ->exists()) {
                    throw new RuntimeException('MONTHLY_CERTIFICATE_LIMIT');
                }

                $solicitud = SolicitudCertificacion::create([
                    'funcionario_id' => $funcionario->id,
                    'tipo_certificado' => $tipoCertificado,
                    'estado' => $requierePago ? EstadoSolicitudEnum::PendientePago : EstadoSolicitudEnum::Generando,
                    'requiere_pago' => $requierePago,
                    'requiere_salario' => $requiereSalario,
                    'periodo_mes' => $periodo->toDateString(),
                    'observaciones' => $observaciones,
                    'created_by' => $actor->id,
                ]);

                $this->registrarAuditoria->execute(
                    accion: 'solicitar_certificacion_autoservicio',
                    modelo: 'SolicitudCertificacion',
                    modeloId: $solicitud->id,
                    descripcion: "El funcionario radicó su propia certificación {$solicitud->radicado}.",
                    metadata: ['requiere_salario' => $requiereSalario, 'requiere_pago' => $requierePago, 'periodo_mes' => $periodo->toDateString()],
                );

                if ($requierePago) {
                    OrdenPagoCertificado::create([
                        'solicitud_certificacion_id' => $solicitud->id,
                        'referencia' => 'OPC-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(5))),
                        'estado' => EstadoOrdenPagoEnum::Pendiente,
                        'moneda' => 'COP',
                        'metadata' => ['integracion' => 'pendiente_proveedor'],
                    ]);
                } else {
                    $resultadoGeneracion = $this->generarCertificado->generar($solicitud, $actor);
                }

                return $solicitud;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505'
                && str_contains((string) ($exception->errorInfo[2] ?? ''), 'solicitudes_funcionario_periodo_modalidad_unique')) {
                throw new RuntimeException('MONTHLY_CERTIFICATE_LIMIT', previous: $exception);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if (isset($resultadoGeneracion['certificado'])) {
                Storage::disk('local')->delete($resultadoGeneracion['certificado']->archivo_pdf_path);
            }
            if (! in_array($exception->getMessage(), ['MONTHLY_CERTIFICATE_LIMIT', 'INACTIVE_EMPLOYEE', 'EMPLOYEE_WITHOUT_POSITION'], true)) {
                $this->registrarAuditoria->execute(
                    accion: 'generacion_certificado_fallida',
                    modelo: 'Funcionario',
                    modeloId: $funcionario->id,
                    descripcion: 'La expedición automática no se constituyó por un error técnico o de fuentes institucionales.',
                    metadata: ['requiere_salario' => $requiereSalario, 'error' => $exception->getMessage()],
                );
            }
            throw $exception;
        }

        $solicitud->load(['funcionario.cargo', 'certificado', 'ordenPago']);
        if ($requierePago) {
            return ['estado' => 'pendiente_pago', 'solicitud' => $solicitud, 'orden_pago' => $solicitud->ordenPago];
        }

        $certificado = $resultadoGeneracion['certificado'];
        $this->registrarAuditoria->execute(
            accion: 'generar_certificado_autoservicio',
            modelo: 'Certificado',
            modeloId: $certificado->id,
            descripcion: "Certificado {$certificado->codigo_unico} generado automáticamente para su titular.",
            metadata: ['solicitud_id' => $solicitud->id, 'actor_funcionario_id' => $funcionario->id, 'cupo_consumido' => true],
        );

        return ['estado' => 'generada', 'solicitud' => $solicitud->fresh(['funcionario.cargo', 'certificado']), ...$resultadoGeneracion];
    }
}
