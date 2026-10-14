<?php

namespace App\Console\Commands;

use App\Services\ImportarManualFuncionesService;
use Illuminate\Console\Command;

class ImportarManualCommand extends Command
{
    protected $signature = 'manual:import {archivo} {--dry-run} {--version-id=} {--user-id=}';

    protected $description = 'Valida e importa las fichas JSON del Decreto 1000-24/015 de 2023 a una versión borrador';

    public function handle(ImportarManualFuncionesService $service): int
    {
        try {
            $summary = $service->ejecutar($this->argument('archivo'), (bool) $this->option('dry-run'),
                $this->option('version-id') ? (int) $this->option('version-id') : null,
                $this->option('user-id') ? (int) $this->option('user-id') : null);
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $summary['errores'] ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
