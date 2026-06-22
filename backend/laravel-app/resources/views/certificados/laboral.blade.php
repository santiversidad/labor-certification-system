{{-- Plantilla temporal. Reemplazar por el formato oficial de la Alcaldia cuando sea entregado. --}}
CERTIFICADO LABORAL TEMPORAL

La Alcaldia certifica que:

Nombre: {{ $funcionario?->nombres }} {{ $funcionario?->apellidos }}
Documento: {{ $funcionario?->tipo_documento }} {{ $funcionario?->numero_documento }}
Cargo: {{ $cargo?->denominacion ?? 'No registrado' }}
Dependencia: {{ $funcionario?->dependencia ?? 'No registrada' }}
Fecha de ingreso: {{ $funcionario?->fecha_ingreso?->toDateString() ?? 'No registrada' }}

@if($solicitud->requiere_salario)
Salario basico: {{ $salario ?? 'No disponible en rangos salariales' }}
@endif

Codigo unico: {{ $codigo }}
Fecha de generacion: {{ $fecha->toDateString() }}

Validacion temporal:
{{ $urlValidacion }}

TODO tecnico: reemplazar esta URL por QR cuando se instale y valide la dependencia simple-qrcode.

Este documento se genera como plantilla tecnica temporal mientras se define el formato oficial y la firma institucional.
