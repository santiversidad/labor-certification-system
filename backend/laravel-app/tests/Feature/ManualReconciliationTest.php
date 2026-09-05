<?php

namespace Tests\Feature;

use App\Domain\Manual\CompararManualesService;
use App\Domain\Manual\ExcelManualParser;
use App\Domain\Manual\FichaManualData;
use App\Domain\Manual\JsonManualParser;
use App\Models\ManualCargoVersion;
use App\Services\ImportarManualFuncionesService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PharData;
use Tests\TestCase;

class ManualReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private array $temporary = [];

    private function path(string $extension = 'json'): string
    {
        return database_path('data/manual_funciones_decreto_015_2023.'.$extension);
    }

    private function records(): array
    {
        return app(JsonManualParser::class)->parse(file_get_contents($this->path()));
    }

    private function proposal(): string
    {
        return database_path('data/manual_funciones_decreto_015_2023_reconciliado.json');
    }

    private function compare(array $a, array $b): array
    {
        return app(CompararManualesService::class)->comparar($a, $b);
    }

    private function change(FichaManualData $dto, array $fields): FichaManualData
    {
        return new FichaManualData(array_replace($dto->origen, $fields));
    }

    private function mutatedWorkbook(string $entry, callable $modify): string
    {
        $path = sys_get_temp_dir().'/manual-excel-'.bin2hex(random_bytes(8)).'.xlsx';
        $this->temporary[] = $path;
        copy($this->path('xlsx'), $path);
        $zip = new PharData($path);
        $zip[$entry] = $modify($zip[$entry]->getContent());
        unset($zip);

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_actual_excel_matches_all_json_records_without_losing_source_representation(): void
    {
        $hash = hash_file('sha256', $this->path('xlsx'));
        $excel = app(ExcelManualParser::class)->parse($this->path('xlsx'));
        $json = $this->records();
        $this->assertCount(344, $excel);
        $this->assertSame(array_map(fn ($d) => FichaManualData::hash($d->origen), $json), array_map(fn ($d) => FichaManualData::hash($d->origen), $excel));
        $this->assertSame(3115, array_sum(array_map(fn ($d) => count($d->funciones()), $excel)));
        $this->assertSame(2326, array_sum(array_map(fn ($d) => count($d->origen['conocimientos']), $excel)));
        $this->assertSame('005', $excel[0]->origen['codigo']);
        $this->assertSame('03', $excel[0]->origen['grado']);
        $this->assertNull($excel[0]->origen['funciones'][57]['numero_fuente']);
        $this->assertSame('', $excel[166]->origen['area_funcional']);
        $this->assertSame('', $excel[166]->origen['proposito_principal']);
        $this->assertSame($hash, $excel[166]->trazabilidad['sha256']);
        $this->assertSame(168, $excel[166]->trazabilidad['ubicacion']['perfil_fila']);
        $this->assertCount(8, $excel[166]->trazabilidad['ubicacion']['funciones_filas']);
        $this->assertNotEmpty($excel[0]->trazabilidad['leeme']);
        $this->assertSame($hash, hash_file('sha256', $this->path('xlsx')));
        $result = $this->compare($json, $excel);
        $this->assertSame(344, $result['conteos']['COINCIDE']);
        $this->assertSame([], $result['diferencias']);
        $this->assertSame([], $result['duplicados']['excel']['source_ids']);
        $this->assertCount(3, $result['duplicados']['excel']['funciones']);
        $this->assertSame($result, $this->compare($json, $excel));
    }

    public function test_excel_inspection_retains_empty_cell_types_and_rejects_discarding_new_fields(): void
    {
        $inspection = app(ExcelManualParser::class)->inspect($this->path('xlsx'));
        $this->assertSame('str', $inspection['hojas']['Perfiles_Cargo']['celdas_origen']['J168']['tipo_ooxml']);
        $this->assertSame(['A1:B1'], $inspection['hojas']['LEEME']['combinadas']);
        $this->assertSame([], $inspection['hojas']['Funciones']['formulas']);
        unset($inspection);
        $path = $this->mutatedWorkbook('xl/worksheets/sheet2.xml', fn ($x) => str_replace('Perfil ID', 'Campo inesperado', $x));
        $this->expectExceptionMessage('MANUAL_EXCEL_ENCABEZADOS_INVALIDOS');
        app(ExcelManualParser::class)->parse($path);
    }

    public function test_excel_uses_column_headers_even_when_columns_are_reordered(): void
    {
        $path = $this->mutatedWorkbook('xl/worksheets/sheet2.xml', fn ($x) => preg_replace_callback('/r="([AB])(\d+)"/', fn ($m) => 'r="'.($m[1] === 'A' ? 'B' : 'A').$m[2].'"', $x));
        $actual = app(ExcelManualParser::class)->parse($path);
        $this->assertSame(array_map(fn ($d) => FichaManualData::hash($d->origen), $this->records()), array_map(fn ($d) => FichaManualData::hash($d->origen), $actual));
    }

    public function test_excel_rejects_formulas_instead_of_using_cached_results(): void
    {
        $path = $this->mutatedWorkbook('xl/worksheets/sheet2.xml', fn ($x) => preg_replace('/(<x:c\b[^>]*r="B2"[^>]*>)/', '$1<x:f>1+1</x:f>', $x));
        $this->expectExceptionMessage('MANUAL_EXCEL_FORMULAS_O_COMBINADAS');
        app(ExcelManualParser::class)->parse($path);
    }

    public function test_excel_cross_checks_redundant_fields_in_child_sheets(): void
    {
        $path = $this->mutatedWorkbook('xl/worksheets/sheet3.xml', fn ($x) => str_replace('MF-0001', 'MF-0002', $x));
        $this->expectExceptionMessage('MANUAL_EXCEL_REFERENCIA_INCONSISTENTE');
        app(ExcelManualParser::class)->parse($path);
    }

    public function test_excel_rejects_orphan_functions(): void
    {
        $path = $this->mutatedWorkbook('xl/worksheets/sheet3.xml', fn ($x) => str_replace('MF-0001', 'MF-9999', $x));
        $this->expectExceptionMessage('MANUAL_EXCEL_HUERFANA');
        app(ExcelManualParser::class)->parse($path);
    }

    public function test_excel_rejects_duplicate_profile_ids(): void
    {
        $path = $this->mutatedWorkbook('xl/worksheets/sheet2.xml', fn ($x) => str_replace('MF-0001', 'MF-0002', $x));
        $this->expectExceptionMessage('MANUAL_EXCEL_PERFIL_DUPLICADO');
        app(ExcelManualParser::class)->parse($path);
    }

    public function test_comparison_does_not_match_using_source_id_alone(): void
    {
        $data = $this->records();
        $other = $this->change($data[228], ['perfil_id' => $data[227]->origen['perfil_id']]);
        $r = $this->compare([$data[227]], [$other]);
        $this->assertSame(1, $r['conteos']['SOLO_JSON']);
        $this->assertSame(1, $r['conteos']['SOLO_EXCEL']);
        $changedId = $this->change($data[227], ['perfil_id' => 'OTRO-ID']);
        $r = $this->compare([$data[227]], [$changedId]);
        $this->assertSame(1, $r['conteos']['DIFERENCIA_CAMPO']);
        $this->assertSame('perfil_id', $r['diferencias'][0]['campo']);
    }

    public function test_comparison_keeps_whitespace_differences_and_does_not_choose_first_ambiguous_match(): void
    {
        $a = $this->records()[227];
        $b = $this->change($a, ['area_funcional' => $a->origen['area_funcional'].' ']);
        $r = $this->compare([$a], [$b]);
        $this->assertSame(1, $r['conteos']['DIFERENCIA_CAMPO']);
        $this->assertSame($b->origen['area_funcional'], $r['diferencias'][0]['excel']);
        $r = $this->compare([$a], [$a, $this->change($a, ['perfil_id' => 'DUP'])]);
        $this->assertSame(1, $r['conteos']['AMBIGUA']);
        $this->assertSame(0, $r['conteos']['COINCIDE']);
        $r = $this->compare([$a, $a], [$a]);
        $this->assertSame(2, $r['conteos']['AMBIGUA']);
    }

    public function test_function_diff_keeps_text_source_number_group_order_and_missing_functions(): void
    {
        $a = $this->records()[227];
        foreach (['texto' => 'Texto diferente ', 'numero_fuente' => 90, 'grupo' => 'Otro grupo', 'orden' => 99] as $field => $value) {
            $functions = $a->origen['funciones'];
            $functions[0][$field] = $value;
            $r = $this->compare([$a], [$this->change($a, ['funciones' => $functions])]);
            $this->assertSame(1, $r['conteos']['DIFERENCIA_FUNCIONES']);
            $this->assertSame('funciones.0.'.$field, $r['diferencias'][0]['campo']);
        }
        $functions = $a->origen['funciones'];
        array_pop($functions);
        $r = $this->compare([$a], [$this->change($a, ['funciones' => $functions])]);
        $this->assertSame('Excel', $r['diferencias'][0]['ausente_en']);
        $r = $this->compare([$this->change($a, ['funciones' => $functions])], [$a]);
        $this->assertSame('JSON', $r['diferencias'][0]['ausente_en']);
    }

    public function test_reconciled_envelope_preserves_metadata_and_only_reviewed_area_changes(): void
    {
        $original = $this->records();
        $bytes = file_get_contents($this->proposal());
        $new = app(JsonManualParser::class)->parse($bytes);
        $this->assertCount(344, $new);
        $this->assertSame(hash('sha256', $bytes), $new[166]->trazabilidad['sha256']);
        $metadata = json_decode($bytes, true)['metadata'];
        $this->assertSame($metadata, $new[166]->trazabilidad['conciliacion']);
        $this->assertCount(3, $metadata['fuentes']);
        foreach (['json', 'xlsx'] as $format) {
            $src = array_values(array_filter($metadata['fuentes'], fn ($s) => $s['formato'] === $format))[0];
            $this->assertSame(hash_file('sha256', $this->path($format)), $src['sha256']);
        }
        $r = $this->compare($original, $new);
        $this->assertSame(343, $r['conteos']['COINCIDE']);
        $this->assertSame(1, $r['conteos']['DIFERENCIA_CAMPO']);
        $this->assertSame(0, $r['conteos']['DIFERENCIA_FUNCIONES']);
        $this->assertSame('MF-0167', $r['diferencias'][0]['ficha']);
        $this->assertSame('area_funcional', $r['diferencias'][0]['campo']);
        $this->assertSame('', $new[166]->origen['proposito_principal']);
        $this->assertSame($r, $this->compare($original, app(JsonManualParser::class)->parse($bytes)));
        $this->assertNotSame(FichaManualData::hash($original[166]->origen), FichaManualData::hash($new[166]->origen));
        $this->assertSame(FichaManualData::hash($original[227]->origen), FichaManualData::hash($new[227]->origen));
    }

    public function test_reconciled_metadata_invalid_count_or_hash_is_rejected(): void
    {
        $envelope = json_decode(file_get_contents($this->proposal()), true);
        foreach (['numero_fichas', 'numero_funciones', 'hash'] as $field) {
            $broken = $envelope;
            if ($field === 'hash') {
                $broken['metadata']['fuentes'][0]['sha256'] = 'bad';
            } else {
                $broken['metadata'][$field]++;
            }
            try {
                app(JsonManualParser::class)->parse(json_encode($broken));
                $this->fail('Metadata inválida no rechazada: '.$field);
            } catch (DomainException $e) {
                $this->assertStringContainsString('MANUAL_METADATA_', $e->getMessage());
            }
        }
    }

    public function test_proposal_dry_run_is_idempotent_and_never_changes_imported_profiles_or_audits(): void
    {
        $service = app(ImportarManualFuncionesService::class);
        $import = $service->ejecutar($this->path());
        $this->assertSame([], $import['errores']);
        $before = ManualCargoVersion::orderBy('id')->get()->toArray();
        $audits = DB::table('manual_importaciones')->count();
        $version = DB::table('manual_funciones_versiones')->where('id', $import['version_id'])->first();
        $a = $service->ejecutar($this->proposal(), true, $import['version_id']);
        $b = $service->ejecutar($this->proposal(), true, $import['version_id']);
        $this->assertSame($a, $b);
        $this->assertSame(0, $a['fichas_nuevas']);
        $this->assertSame(1, $a['fichas_actualizadas']);
        $this->assertSame(['MF-0167'], $a['source_ids']['actualizadas']);
        $this->assertSame(343, $a['fichas_sin_cambios']);
        $this->assertSame([], $a['functions_diff']);
        $this->assertStringContainsString('MANUAL_SOURCE_ID_REASIGNADO', implode(' ', $a['errores']));
        $this->assertSame($before, ManualCargoVersion::orderBy('id')->get()->toArray());
        $this->assertSame($audits, DB::table('manual_importaciones')->count());
        $this->assertEquals($version, DB::table('manual_funciones_versiones')->where('id', $import['version_id'])->first());
    }

    public function test_proposal_cannot_be_imported_even_into_an_empty_database(): void
    {
        $before = ManualCargoVersion::count();
        $r = app(ImportarManualFuncionesService::class)->ejecutar($this->proposal());
        $this->assertContains('MANUAL_RECONCILIACION_SOLO_DRY_RUN', $r['errores']);
        $this->assertSame('rechazado', $r['resultado']);
        $this->assertSame($before, ManualCargoVersion::count());
        $this->assertNull($r['version_id']);
    }
}
