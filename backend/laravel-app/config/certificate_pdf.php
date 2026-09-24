<?php

return [
    'evidence_path' => env('CERTIFICATE_EVIDENCE_PATH', base_path('../../output/pdf')),
    // Filename only, after provenance and permitted use have been approved institutionally.
    'approved_mark' => null,
    'institution' => [
        'entity' => 'ALCALDÍA DE VILLAVICENCIO',
        'secretariat' => 'SECRETARÍA DE DESARROLLO INSTITUCIONAL',
        'office' => 'DIRECCIÓN DE PERSONAL',
        'document_title' => 'CERTIFICACIÓN LABORAL',
    ],
    'footer' => [
        'entity' => 'Alcaldía de Villavicencio',
        'office' => 'Dirección de Personal',
        'contact' => null,
        'website' => null,
    ],
];
