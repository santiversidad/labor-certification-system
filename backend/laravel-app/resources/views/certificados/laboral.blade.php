<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BORRADOR - Certificación laboral {{ strtolower($document['type_label']) }}</title>
    <style>{!! $stylesheet !!}</style>
</head>
<body class="certificate-{{ $document['type'] }}">
    <div class="document-kicker">Documento administrativo provisional</div>
    <h1>{{ $institution['document_title'] }}</h1>
    <div class="type">{{ $document['type_label'] }}</div>

    @if($document['manual'] && $document['manual']['development_notice'])
        <div class="development-notice">PRUEBA DE DESARROLLO - MANUAL BORRADOR, VIGENCIA PENDIENTE. SIN VALIDEZ OFICIAL.</div>
    @endif

    <h2 class="certifies">CERTIFICA:</h2>
    <p>{{ $document['body'] }}</p>

    @if($document['employment'])
        <table class="employment-table">
            @foreach($document['employment'] as $field)
                <tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] }}</td></tr>
            @endforeach
        </table>
    @endif

    @if($document['manual'])
        <h2 class="section-title">Referencia del Manual aplicable</h2>
        <table class="manual-grid">
            @if($document['manual']['reference'])<tr><td>Manual</td><td>{{ $document['manual']['reference'] }}</td></tr>@endif
            @if($document['manual']['profile'])<tr><td>Ficha / perfil</td><td>{{ $document['manual']['profile'] }}</td></tr>@endif
            @if($document['manual']['functional_area'])<tr><td>Área funcional</td><td>{{ $document['manual']['functional_area'] }}</td></tr>@endif
        </table>

        @foreach($document['manual']['function_pages'] ?: [[]] as $pageIndex => $functions)
            <div class="functions-page {{ $document['manual']['controlled_pages'] ? 'controlled-page' : '' }}">
                @if($pageIndex === 0 && $document['manual']['purpose'])
                    <h2 class="section-title">Propósito principal</h2>
                    <p class="purpose">{{ $document['manual']['purpose'] }}</p>
                @endif

                @if($functions)
                    @if($pageIndex === 0)<h2 class="section-title">FUNCIONES ESENCIALES</h2>@endif
                    <ol class="functions-list">
                        @foreach($functions as $function)
                            <li class="function-item"><span class="function-number">{{ $function['number'] }}.</span>{{ $function['description'] }}</li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endforeach
    @endif

    <div class="issuance"><p>{{ $document['closing'] }}</p></div>
    <div class="officialization">Mecanismo de oficialización pendiente de definición institucional.</div>
</body>
</html>
