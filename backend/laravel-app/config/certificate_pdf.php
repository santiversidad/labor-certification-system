<?php

return [
    'evidence_path' => env('CERTIFICATE_EVIDENCE_PATH', base_path('../../output/pdf')),
    'institution' => [
        'entity' => 'ALCALDÍA DE VILLAVICENCIO',
        'secretariat' => 'SECRETARÍA DE DESARROLLO INSTITUCIONAL',
        'office' => 'DIRECCIÓN DE PERSONAL',
        'document_title' => 'CERTIFICACIÓN LABORAL',
        'identity_placeholder' => 'IDENTIDAD VISUAL OFICIAL PENDIENTE',
    ],
    'footer' => [
        'entity' => 'Alcaldía de Villavicencio',
        'office' => 'Dirección de Personal',
        'contact' => null,
        'website' => null,
    ],
];
