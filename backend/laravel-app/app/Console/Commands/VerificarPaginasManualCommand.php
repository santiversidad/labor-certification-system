<?php

namespace App\Console\Commands;

use App\Services\VerificarPaginasManualService;
use Illuminate\Console\Command;

class VerificarPaginasManualCommand extends Command
{
    protected $signature = 'manual:verify-pages {--version-id=10} {--user-id=} {--dry-run}';

    protected $description = 'Aplica evidencia documental cotejada, conservando fuente y hashes normativos';

    public function handle(VerificarPaginasManualService $service): int
    {
        try {
            if (! $this->option('user-id')) {
                throw new \DomainException('Se requiere un actor autorizado con --user-id.');
            }
            $this->line(json_encode($service->ejecutar((int) $this->option('version-id'), (int) $this->option('user-id'), (bool) $this->option('dry-run')), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
