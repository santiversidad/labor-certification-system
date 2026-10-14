<?php

namespace App\Console\Commands;

use App\Models\ManualFuncionVersion;
use App\Services\CompararVersionesManualService;
use Illuminate\Console\Command;

class DiffVersionesManualCommand extends Command
{
    protected $signature = 'manual:diff {--from= : ID de versión publicada} {--to= : ID de versión borrador}';

    protected $description = 'Compara dos versiones del Manual sin confiar en source_id y sin modificar datos';

    public function handle(CompararVersionesManualService $service): int
    {
        $from = ManualFuncionVersion::findOrFail((int) $this->option('from'));
        $to = ManualFuncionVersion::findOrFail((int) $this->option('to'));
        $this->line(json_encode($service->comparar($from, $to),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
