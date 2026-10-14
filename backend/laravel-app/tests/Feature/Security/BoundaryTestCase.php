<?php

namespace Tests\Feature\Security;

use App\Models\Cargo;
use App\Models\ParametroSistema;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ManualFixture;

abstract class BoundaryTestCase extends SecurityTestCase
{
    protected User $owner;
    protected User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $cargo = Cargo::create(['codigo' => 'SEC', 'grado' => '01', 'denominacion' => 'Cargo sintético seguridad', 'nivel' => 'Profesional', 'estado' => true]);
        $this->owner = $this->account('funcionario'); $this->other = $this->account('funcionario');
        foreach ([$this->owner, $this->other] as $user) {
            $user->funcionario->update(['cargo_id' => $cargo->id, 'fecha_ingreso' => '2020-01-01']);
            ManualFixture::vincular($user->funcionario->fresh());
        }
        ParametroSistema::create(['clave' => 'requiere_pago_certificado', 'valor' => 'true', 'tipo' => 'boolean']);
    }

    protected function requestCertificate(): int
    {
        return $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo'])->assertCreated()->json('data.solicitud.id');
    }

    protected function receipt(string $name = 'synthetic.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% Synthetic local receipt\n");
    }
}
