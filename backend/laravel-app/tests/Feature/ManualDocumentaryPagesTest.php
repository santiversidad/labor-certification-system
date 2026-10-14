<?php

namespace Tests\Feature;

use App\Models\ManualCargoVersion;
use App\Models\ManualFuncionVersion;
use App\Models\User;
use App\Services\ImportarManualFuncionesService;
use App\Services\VerificarPaginasManualService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ManualDocumentaryPagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manifest_aprobado_conserva_344_fichas_funciones_hashes_y_fuente_publicada(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $actor = User::factory()->backoffice()->create(['estado' => true]);
        $actor->assignRole('admin');
        $result = app(ImportarManualFuncionesService::class)->ejecutar(database_path('data/manual_funciones_decreto_015_2023.json'));
        $this->assertSame([], $result['errores']);
        $version = ManualFuncionVersion::findOrFail($result['version_id']);
        $before = $version->cargos()->get()->mapWithKeys(fn ($f) => [$f->id => [$f->metadata_manual, $f->content_hash]])->sortKeys()->all();
        $version->update(['estado' => 'publicado']);
        $service = app(VerificarPaginasManualService::class);
        $this->assertSame(344, $service->ejecutar($version->id, $actor->id, true)['actualizadas']);
        $this->assertSame(0, $version->cargos()->whereNotNull('pagina_inicio')->count());
        $this->assertSame(344, $service->ejecutar($version->id, $actor->id)['actualizadas']);
        $this->assertSame(0, $service->ejecutar($version->id, $actor->id)['actualizadas']);
        $this->assertSame($before, $version->cargos()->get()->mapWithKeys(fn ($f) => [$f->id => [$f->metadata_manual, $f->content_hash]])->sortKeys()->all());
        $this->assertSame(344, $version->cargos()->distinct()->count('pagina_inicio'));
        $this->assertSame(664, $version->cargos()->where('source_id', 'MF-0343')->sole()->pagina_inicio);
        $last = $version->cargos()->where('source_id', 'MF-0344')->sole();
        $this->assertSame(665, $last->pagina_inicio);
        $this->assertSame(664, $last->metadata_manual['pagina_inicio']);
        $this->assertSame(664, $last->verificacion_documental['pagina_fuente_original']);
        $this->assertSame(3115, \App\Models\ManualFuncionEsencial::whereIn('manual_cargo_version_id', array_keys($before))->count());
        $incomplete = $version->cargos()->where('source_id', 'MF-0167')->sole();
        $this->assertTrue($incomplete->paginaVerificada());
        $this->assertSame('', $incomplete->area_funcional);
        $this->assertSame('', $incomplete->proposito_principal);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $last->update(['proposito_principal' => 'No permitido']);
    }

    public function test_manifest_no_se_aplica_a_otra_version_o_actor_no_autorizado(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $actor = User::factory()->backoffice()->create(['estado' => true]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(VerificarPaginasManualService::class)->ejecutar(10, $actor->id);
    }
}
