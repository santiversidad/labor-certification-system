<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SalaryScaleImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportSalaryScale extends Command
{
    protected $signature = 'salary-scale:import {--file=} {--dry-run} {--actor-id=} {--fuente=Tabla salarial entregada por Dirección de Personal}';

    protected $description = 'Validar e importar transcripciones revisadas de escala salarial (CSV, sin OCR)';

    public function handle(SalaryScaleImportService $service): int
    {
        $file = $this->option('file');
        if (! is_string($file) || ! is_file($file) || ! is_readable($file) || filesize($file) > 5000000) {
            $this->error('Indique --file con un CSV legible de hasta 5 MB.');

            return self::FAILURE;
        }
        $summary = $service->inspect($file);
        $rows = $summary['filas'];
        unset($summary['filas']);
        $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($summary['errores']) {
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se escribió ningún registro.');

            return self::SUCCESS;
        }
        $actor = ctype_digit((string) $this->option('actor-id')) ? User::find($this->option('actor-id')) : null;
        if (! $actor || ! $actor->estado || ! $actor->hasRole('admin') || ! $actor->can('escala_salarial.gestionar')) {
            $this->error('Se requiere --actor-id de un administrador activo con permiso de escala salarial.');

            return self::FAILURE;
        }
        $source = $this->option('fuente');
        if (! is_string($source) || trim($source) === '' || mb_strlen($source) > 250) {
            $this->error('Fuente inválida.');

            return self::FAILURE;
        }
        try {
            $result = $service->import($summary + ['filas' => $rows], $actor->id, $source);
        } catch (ValidationException $error) {
            foreach ($error->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
