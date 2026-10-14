<?php

namespace Tests\Feature\Security;

use App\Models\PagoComprobante;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileSecurityTest extends BoundaryTestCase
{
    public function test_empty_double_extension_long_filename_and_invalid_image_rejected(): void
    {
        $id = $this->requestCertificate();
        foreach ([$this->receipt('proof.php.pdf'), $this->receipt(str_repeat('x', 190).'.pdf'),
            UploadedFile::fake()->createWithContent('empty.pdf', ''), UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;')] as $file) {
            $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $file])->assertUnprocessable();
        }
        $this->assertDatabaseCount('pago_comprobantes', 0); $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_traversal_filename_cannot_choose_path_and_integrity_checked(): void
    {
        $id = $this->requestCertificate();
        $this->postJson("/api/v1/solicitudes/$id/pago/comprobantes", ['archivo' => $this->receipt('../../synthetic.pdf')])->assertCreated();
        $receipt = PagoComprobante::firstOrFail();
        $this->assertMatchesRegularExpression('~^pago-comprobantes/'.$id.'/[a-f0-9-]{36}\.pdf$~', $receipt->archivo_path);
        $this->getJson("/api/v1/solicitudes/$id/pago/comprobantes/$receipt->id")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        Storage::disk('local')->put($receipt->archivo_path, 'tampered');
        $this->getJson("/api/v1/solicitudes/$id/pago/comprobantes/$receipt->id")->assertConflict();
        $this->getJson('/api/v1/mi-certificacion/descargar/%2e%2e%2f.env')->assertNotFound();
    }
}
