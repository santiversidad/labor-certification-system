<?php

namespace App\Console\Commands;

use App\Models\Cargo;
use App\Models\EscalaSalarialVigencia;
use App\Models\User;
use App\Services\SalaryScaleImportService;
use App\Services\SalaryScaleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareSalaryScaleE2E extends Command
{
    protected $signature = 'e2e:prepare-salary-scale';

    protected $description = 'Prepare synthetic salary rows exclusively in the isolated E2E database';

    public function handle(): int
    {
        if (! app()->environment('testing') || ! in_array(DB::connection()->getDatabaseName(), ['scl_certificate_types_e2e', 'scl_manual_pages_e2e', 'scl_template_editor_e2e'], true)) {
            $this->error('E2E_ISOLATION_REQUIRED');

            return self::FAILURE;
        }
        $actor = User::where('username', 'e2e-admin')->firstOrFail();
        foreach ([['909', '07', 'Profesional salarial sintético E2E'], ['910', '02', 'Técnico salarial sintético E2E']] as [$code, $grade, $name]) {
            Cargo::firstOrCreate(['codigo' => $code, 'grado' => $grade], ['denominacion' => $name, 'nivel' => 'Profesional', 'estado' => true]);
        }
        $file = tempnam(sys_get_temp_dir(), 'e2e-salary-');
        try {
            file_put_contents($file, implode(',', SalaryScaleImportService::HEADERS)."\n2024,909,07,Profesional salarial sintético E2E,2,900001\n2024,910,02,Técnico salarial sintético E2E,4,800003\n2025,909,07,Profesional salarial sintético E2E,3,1000001\n2025,910,02,Técnico salarial sintético E2E,5,900003\n");
            $import = app(SalaryScaleImportService::class);
            $import->import($import->inspect($file), $actor->id, 'Tabla sintética E2E; no oficial');
            foreach ([2024, 2025] as $number) {
                $year = EscalaSalarialVigencia::where('anio', $number)->firstOrFail();
                if ($year->estado === 'borrador') {
                    app(SalaryScaleService::class)->publish($year->id, $year->revision, $actor->id);
                }
            }
        } finally {
            unlink($file);
        }
        $this->info('Synthetic salary fixtures ready; no institutional values loaded.');

        return self::SUCCESS;
    }
}
