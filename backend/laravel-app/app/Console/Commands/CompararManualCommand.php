<?php

namespace App\Console\Commands;

use App\Domain\Manual\CompararManualesService;
use App\Domain\Manual\ExcelManualParser;
use App\Domain\Manual\JsonManualParser;
use Illuminate\Console\Command;

class CompararManualCommand extends Command
{
    protected $signature = 'manual:compare {excel} {json}';

    protected $description = 'Compara XLSX/JSON por ficha y contenido, sin escribir fuentes ni base de datos';

    public function handle(ExcelManualParser $excel, JsonManualParser $json, CompararManualesService $comparator): int
    {
        try {
            $xlsxPath = $this->argument('excel');
            $jsonPath = $this->argument('json');
            $jsonBytes = file_get_contents($jsonPath);
            $report = $comparator->comparar($json->parse($jsonBytes), $excel->parse($xlsxPath));
            $report['fuentes'] = ['excel' => ['archivo' => basename($xlsxPath), 'sha256' => hash_file('sha256', $xlsxPath)],
                'json' => ['archivo' => basename($jsonPath), 'sha256' => hash('sha256', $jsonBytes)]];
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return array_sum($report['conteos']) === $report['conteos']['COINCIDE']
                && ! $report['duplicados']['json']['source_ids'] && ! $report['duplicados']['excel']['source_ids'] ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
