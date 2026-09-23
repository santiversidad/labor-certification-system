<?php

namespace App\Console\Commands;

use App\Models\ManualFuncionVersion;
use App\Models\User;
use App\Services\PublicarVersionManualService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PublicarVersionManualCommand extends Command
{
    protected $signature = 'manual:publish {--version-id=} {--user-id=} {--vigencia-desde=} {--adopt-current} {--preflight}';

    protected $description = 'Publica una versión mediante el flujo de dominio, con auditoría y vigencias';

    public function handle(PublicarVersionManualService $service): int
    {
        $version = ManualFuncionVersion::findOrFail((int) $this->option('version-id'));
        $preflight = $service->preflight($version);
        $this->line(json_encode(['version_id' => $version->id, 'preflight' => $preflight],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($this->option('preflight')) {
            return self::SUCCESS;
        }
        $actorId = (int) $this->option('user-id');
        if (! $actorId || ! User::whereKey($actorId)->exists()) {
            $this->error('MANUAL_PUBLICADOR_REQUERIDO: indique --user-id de un usuario existente.');

            return self::FAILURE;
        }
        $fecha = $this->option('vigencia-desde') ? CarbonImmutable::parse($this->option('vigencia-desde')) : null;
        $result = $service->publicar($version, $fecha, $actorId, (bool) $this->option('adopt-current'));
        $this->info("Versión {$result['version']->id} publicada correctamente.");

        return self::SUCCESS;
    }
}
