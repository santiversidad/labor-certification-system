<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BORRADOR - Certificación laboral {{ strtolower($document['type_label']) }}</title>
    <style>
        @page { size: A4 portrait; margin: 42mm 22mm 29mm 22mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #22282d; font-family: "DejaVu Sans", sans-serif; font-size: 10.2pt; line-height: 1.55; }
        main { width: 100%; padding-top: 1px; }
        .page-header { position: fixed; z-index: 1000; top: -34mm; left: 0; right: 0; height: 28mm; border-bottom: .6px solid #757d83; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { height: 24mm; padding: 0; vertical-align: middle; text-align: center; }
        .header-table .visual-cell { width: 22%; text-align: left; }
        .header-table .identity-cell { width: 55%; line-height: 1.55; }
        .header-table .draft-cell { width: 23%; text-align: right; }
        .visual-placeholder { display: inline-block; width: 24mm; height: 18mm; border: .7px dashed #747c82; padding-top: 5mm; color: #5a6167; font-size: 6.2pt; line-height: 1.55; text-align: center; }
        .institution-entity { font-size: 10.2pt; font-weight: bold; }
        .institution-unit { font-size: 7.4pt; }
        .draft-box { display: inline-block; width: 29mm; height: 18mm; border: .9px solid #22282d; padding-top: 4.2mm; font-size: 5.8pt; font-weight: bold; line-height: 1.55; text-align: center; }
        .page-footer { position: fixed; z-index: 1000; bottom: -21mm; left: 0; right: 0; height: 16mm; border-top: .5px solid #757d83; padding-top: 3mm; color: #545b60; font-size: 6.5pt; line-height: 1.45; }
        .footer-entity { color: #22282d; font-size: 7pt; font-weight: bold; }
        .footer-draft { position: absolute; top: 3mm; right: 28mm; }
        .document-kicker { color: #5a6167; font-size: 8pt; letter-spacing: 1.2px; text-align: center; text-transform: uppercase; }
        h1 { margin: 2mm 0 0; font-size: 16pt; line-height: 1.2; letter-spacing: .5px; text-align: center; }
        .type { margin: 1.5mm 0 8mm; font-size: 9pt; font-weight: bold; letter-spacing: 1px; text-align: center; }
        .certifies { margin: 0 0 6mm; font-size: 13pt; letter-spacing: 1.5px; text-align: center; page-break-after: avoid; }
        p { margin: 0 0 4mm; text-align: justify; }
        .employment-table { width: 100%; margin: 5mm 0 7mm; border-collapse: collapse; page-break-inside: avoid; }
        .employment-table td { border-bottom: .5px solid #c5c9cc; padding: 2.2mm 2mm; vertical-align: top; }
        .employment-table td:first-child { width: 41%; color: #555c62; font-size: 8.7pt; }
        .employment-table td:last-child { font-weight: bold; }
        .section-title { margin: 7mm 0 3mm; padding-bottom: 1.6mm; border-bottom: 1px solid #676e73; font-size: 10pt; letter-spacing: .7px; text-transform: uppercase; page-break-after: avoid; }
        .manual-grid { width: 100%; margin-bottom: 4mm; border-collapse: collapse; page-break-inside: avoid; }
        .manual-grid td { padding: 1.4mm 0; vertical-align: top; }
        .manual-grid td:first-child { width: 34%; color: #5a6167; font-size: 8.7pt; }
        .purpose { border-left: 2px solid #777e84; padding-left: 4mm; page-break-inside: avoid; }
        .functions-heading { margin: 6mm 0 3mm; font-weight: bold; page-break-after: avoid; }
        .page-break { height: 0; page-break-after: always; }
        /* A non-zero fragment padding keeps Dompdf's @page margin active after an explicit break. */
        .functions-page { padding-top: 1px; }
        .functions-list { margin: 0; padding-left: 10mm; }
        .function-item { margin: 0 0 3.2mm; padding-left: 2mm; text-align: justify; page-break-inside: avoid; }
        .development-notice { margin: 4mm 0; border: 1px solid #555c62; padding: 3mm; font-size: 8pt; font-weight: bold; text-align: center; page-break-inside: avoid; }
        .issuance { margin-top: 7mm; page-break-inside: avoid; }
        .officialization { margin-top: 6mm; min-height: 22mm; border: 1px solid #92989d; padding: 6mm 5mm; color: #444b50; font-size: 9pt; text-align: center; page-break-inside: avoid; }
        .certificate-sencillo .officialization { margin-top: 4mm; min-height: 18mm; padding: 4mm 5mm; }
    </style>
</head>
<body class="certificate-{{ $document['type'] }}">
<header class="page-header">
    <table class="header-table">
        <tr>
            <td class="visual-cell">
                <span class="visual-placeholder">IDENTIDAD VISUAL<br>OFICIAL PENDIENTE</span>
            </td>
            <td class="identity-cell">
                <div class="institution-entity">{{ $institution['entity'] }}</div>
                <div class="institution-unit">{{ $institution['secretariat'] }}</div>
                <div class="institution-unit">{{ $institution['office'] }}</div>
            </td>
            <td class="draft-cell">
                <span class="draft-box">BORRADOR<br>- SIN VALIDEZ OFICIAL -</span>
            </td>
        </tr>
    </table>
</header>
<footer class="page-footer">
    <div class="footer-entity">{{ $footer['entity'] }}</div>
    <div>{{ $footer['office'] }}</div>
    @if($footer['contact'])<div>{{ $footer['contact'] }}</div>@endif
    @if($footer['website'])<div>{{ $footer['website'] }}</div>@endif
    @if($document['technical_code'])<div>Código técnico: {{ $document['technical_code'] }}</div>@endif
    <div class="footer-draft">BORRADOR</div>
</footer>
<main>
    <div class="document-kicker">Documento administrativo provisional</div>
    <h1>{{ $institution['document_title'] }}</h1>
    <div class="type">{{ $document['type_label'] }}</div>

    @if($document['manual'] && $document['manual']['development_notice'])
        <div class="development-notice">PRUEBA DE DESARROLLO - MANUAL BORRADOR, VIGENCIA PENDIENTE. SIN VALIDEZ OFICIAL.</div>
    @endif

    <h2 class="certifies">CERTIFICA</h2>

    <p>
        La Dirección de Personal de la Alcaldía de Villavicencio certifica que
        @if($document['employee_name'])<strong>{{ $document['employee_name'] }}</strong>@else la persona registrada en el snapshot de emisión @endif
        @if($document['employee_document']), identificada con <strong>{{ $document['employee_document'] }}</strong>@endif,
        registra la siguiente información laboral aprobada al momento de la expedición:
    </p>

    <table class="employment-table">
        @foreach($document['employment'] as $field)
            <tr><td>{{ $field['label'] }}</td><td>{{ $field['value'] }}</td></tr>
        @endforeach
    </table>

    @if($document['manual'])
        <h2 class="section-title">Referencia del Manual aplicable</h2>
        <table class="manual-grid">
            @if($document['manual']['reference'])<tr><td>Manual</td><td>{{ $document['manual']['reference'] }}</td></tr>@endif
            @if($document['manual']['profile'])<tr><td>Ficha / perfil</td><td>{{ $document['manual']['profile'] }}</td></tr>@endif
            @if($document['manual']['functional_area'])<tr><td>Área funcional</td><td>{{ $document['manual']['functional_area'] }}</td></tr>@endif
        </table>

        @foreach($document['manual']['function_groups'] as $groupIndex => $group)
            <div class="page-break"></div>
            <section class="functions-page">
                @if($groupIndex === 0 && $document['manual']['purpose'])
                    <h2 class="section-title">Propósito principal</h2>
                    <p class="purpose">{{ $document['manual']['purpose'] }}</p>
                @endif
                <div class="functions-heading">
                    Funciones esenciales{{ $groupIndex > 0 ? ' - continuación' : '' }}
                </div>
                <ol class="functions-list" start="{{ $group['start'] }}">
                    @foreach($group['items'] as $function)
                        <li class="function-item">{{ $function }}</li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    @endif

    <div class="issuance">
        <p>
            Se expide el presente borrador
            @if($document['issuance_date']) el {{ $document['issuance_date'] }}@endif
            @if($document['filing_code']), bajo el radicado {{ $document['filing_code'] }}@endif.
        </p>
    </div>

    <div class="officialization">Mecanismo de oficialización pendiente de definición institucional</div>
</main>
</body>
</html>
