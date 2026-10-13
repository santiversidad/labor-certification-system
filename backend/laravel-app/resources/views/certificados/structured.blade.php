<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BORRADOR — {{ $config['title'] }}</title>
    <style>
        @page { margin: 165pt 50pt 110pt; }
        body { font-family: '{{ $config['typography']['font'] }}'; font-size: {{ (int) $config['typography']['size'] }}pt; line-height: {{ (float) $config['typography']['line_height'] }}; color: #202a30; }
        h1 { font-size: 14pt; line-height: 1.3; text-align: center; margin: 0 0 5pt; word-wrap: break-word; }
        h2 { font-size: 11pt; margin: 12pt 0 6pt; page-break-after: avoid; }
        p { margin: 8pt 0; orphans: 3; widows: 3; white-space: pre-wrap; word-wrap: break-word; }
        .type { text-align: center; font-size: 9pt; margin-bottom: 12pt; }
        .identity { margin: 10pt 0; padding: 7pt 0; border-bottom: 0.5pt solid #b7bdc1; word-wrap: break-word; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9pt; }
        td { padding: 3pt 5pt; vertical-align: top; word-wrap: break-word; }
        .employment td:first-child { width: 35%; color: #4b5359; }
        .function { font-size: {{ (int) ($config['functions']['size'] ?? 10) }}pt; line-height: {{ (float) ($config['functions']['line_height'] ?? 1.4) }}; margin: 8pt 0 8pt 16pt; text-indent: -16pt; white-space: pre-wrap; word-wrap: break-word; orphans: 3; widows: 3; }
        .group { font-weight: bold; page-break-after: avoid; }
        .signature { margin-top: 22pt; page-break-inside: avoid; text-align: {{ $config['signature']['alignment'] }}; }
        .signature-block { page-break-inside: avoid; }
        .last-closing { page-break-after: avoid; }
        .signature p { margin: 3pt 0; }
        .additional { margin-top: 18pt; page-break-inside: avoid; }
        .additional td { border: 0.5pt solid #b7bdc1; }
        .muted { color: #5a6268; font-size: 8pt; }
    </style>
</head>
<body>
    <h1>{{ $config['title'] }}</h1>
    <div class="type">{{ $snapshot['tipo_certificado'] === 'funciones' ? 'CON FUNCIONES' : 'SIN FUNCIONES' }}</div>
    <div class="identity"><strong>{{ $document['employee_name'] }}</strong> · {{ $document['employee_document'] }}</div>
    @foreach($paragraphs['body'] as $paragraph)
        <p style="text-align: {{ $paragraph['alignment'] }}">{!! $paragraph['html'] !!}</p>
    @endforeach
    <table class="employment">
        @foreach($document['employment'] as $field)
            <tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] }}</td></tr>
        @endforeach
    </table>
    @if($snapshot['tipo_certificado'] === 'funciones')
        <h2>{{ $config['functions']['title'] }}</h2>
        <p class="muted">{{ $document['manual']['reference'] }} · {{ $document['manual']['profile'] }}@if(isset($snapshot['manual_funciones']['pagina_inicio'])) · Página {{ $snapshot['manual_funciones']['pagina_inicio'] }}@endif</p>
        @foreach($functions as $function)
            @if($function['group'])<p class="group">{{ $function['group'] }}</p>@endif
            <p class="function">@if($function['number'] !== null){{ $function['number'] }}. @endif{{ $function['description'] }}</p>
        @endforeach
    @endif
    @foreach($paragraphs['closing'] as $paragraph)
        <p class="{{ $loop->last ? 'last-closing' : '' }}" style="text-align: {{ $paragraph['alignment'] }}">{!! $paragraph['html'] !!}</p>
    @endforeach
    <div class="signature-block"><div class="signature">
        @if($config['signature']['text'])<p>{{ $config['signature']['text'] }}</p>@endif
        <p><strong>{{ $config['signature']['name'] ?: 'Nombre del firmante pendiente de confirmación' }}</strong></p>
        @if($config['signature']['role'])<p>{{ $config['signature']['role'] }}</p>@endif
        @if($config['signature']['office'])<p>{{ $config['signature']['office'] }}</p>@endif
        <p class="muted">Firma institucional pendiente. Este borrador no contiene firma oficial.</p>
    </div>
    @if($config['additional_signatures'])
        <table class="additional">
            @foreach($config['additional_signatures'] as $signature)
                <tr><td style="width: 20%">{{ $signature['label'] }}</td><td>{{ $signature['name'] }}</td><td>{{ $signature['role'] }}</td></tr>
            @endforeach
        </table>
    @endif
    </div>
</body>
</html>
