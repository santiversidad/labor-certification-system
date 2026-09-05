{{-- Plantilla temporal. Reemplazar por el formato oficial de la Alcaldia cuando sea entregado. --}}
CERTIFICADO LABORAL TEMPORAL

@if(!empty($snapshot['manual']['prueba_desarrollo']))
PRUEBA DE DESARROLLO - MANUAL BORRADOR, VIGENCIA PENDIENTE. SIN VALIDEZ OFICIAL.
@endif

La Alcaldia certifica que:

Nombre: {{ $snapshot['funcionario']['nombres'] }} {{ $snapshot['funcionario']['apellidos'] }}
Documento: {{ $snapshot['funcionario']['tipo_documento'] }} {{ $snapshot['funcionario']['numero_documento'] }}
Cargo: {{ $snapshot['cargo']['denominacion'] ?? 'No registrado' }}
Codigo y grado: {{ $snapshot['cargo']['codigo'] ?? 'No registrado' }} / {{ $snapshot['cargo']['grado'] ?? 'No registrado' }}
Dependencia: {{ $snapshot['cargo']['dependencia'] ?? 'No registrada' }}
Fecha de ingreso: {{ $snapshot['funcionario']['fecha_ingreso'] ?? 'No registrada' }}
Modalidad: {{ $snapshot['modalidad']['descripcion'] }}

@if($snapshot['modalidad']['requiere_salario'])
Salario basico: {{ $snapshot['salario']['valor'] ?? 'No disponible en la fuente salarial institucional' }} {{ $snapshot['salario']['moneda'] ?? '' }}
@endif

@if(!empty($snapshot['manual_funciones']))
Manual de funciones: {{ $snapshot['manual_funciones']['manual_nombre'] }} — version {{ $snapshot['manual_funciones']['version'] }}
Ficha: {{ $snapshot['manual_funciones']['source_id'] ?? $snapshot['manual_funciones']['ficha_id'] }}
Area funcional: {{ $snapshot['manual_funciones']['area_funcional'] }}
Proposito principal: {{ $snapshot['manual_funciones']['proposito_principal'] }}
Funciones esenciales:
@foreach($snapshot['manual_funciones']['funciones'] as $funcion)
{{ $funcion['orden'] }}. {{ $funcion['descripcion'] }}
@endforeach
@endif

Codigo unico: {{ $codigo }}
Fecha de generacion: {{ substr($snapshot['fecha_generacion'], 0, 10) }}

Validacion temporal:
{{ $urlValidacion }}

TODO tecnico: reemplazar esta URL por QR cuando se instale y valide la dependencia simple-qrcode.

Este documento se genera como plantilla tecnica temporal mientras se define el formato oficial y la firma institucional.
