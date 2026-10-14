<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Exceptions\CertificateTemplateException;
use App\Models\PlantillaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CertificateTemplateAssets
{
    public function upload(UploadedFile $file, string $slot, User $actor, string $source, array $context = []): PlantillaAsset
    {
        $bytes = $file->get();
        $info = @getimagesizefromstring($bytes);
        if (strlen($bytes) > 2 * 1024 * 1024 || ! $info || ! in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)
            || min($info[0], $info[1]) < 40 || max($info[0], $info[1]) > 4096 || $info[0] * $info[1] > 6000000
            || str_contains($bytes, 'acTL') || ($info['mime'] === 'image/webp' && str_contains($bytes, 'ANIM'))) {
            $this->invalid('Use una imagen estática PNG, JPEG o WebP válida, de hasta 2 MB y 6 megapíxeles, con lados entre 40 y 4096 px.');
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new CertificateTemplateException('TEMPLATE_IMAGE_RUNTIME_MISSING', 'El servidor necesita la extensión GD para validar las imágenes.', 503);
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            $this->invalid('No se pudo decodificar la imagen.');
        }
        if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
            }
            $angle = match ($orientation) {
                3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
            };
            if ($angle) {
                $image = imagerotate($image, $angle, 0);
            }
        }
        $ratio = imagesx($image) / imagesy($image);
        if (($slot === 'left' && ($ratio < 0.5 || $ratio > 2)) || ($slot === 'right' && ($ratio < 1.5 || $ratio > 8))) {
            $this->invalid('La proporción no corresponde al contenedor: escudo de 1:2 a 2:1; bloque derecho de 3:2 a 8:1.');
        }
        $scale = min(1, ($slot === 'left' ? 400 : 800) / imagesx($image), ($slot === 'left' ? 400 : 300) / imagesy($image));
        $width = max(1, (int) round(imagesx($image) * $scale));
        $height = max(1, (int) round(imagesy($image) * $scale));
        $normalized = imagecreatetruecolor($width, $height);
        imagefill($normalized, 0, 0, imagecolorallocate($normalized, 255, 255, 255));
        imagecopyresampled($normalized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        ob_start();
        imagepng($normalized);
        $png = ob_get_clean();
        imagedestroy($image);
        imagedestroy($normalized);

        return $this->store($png, $slot, $actor, $source, $context);
    }

    public function reference(User $actor): array
    {
        $assets = [];
        foreach (['left' => 'escudo', 'right' => 'secretaria'] as $slot => $name) {
            $assets[$slot.'_asset_id'] = $this->store(file_get_contents(resource_path('certificate-assets/reference/'.$name.'.png')), $slot, $actor,
                'Encabezado de doc09536920260520143639.pdf; SHA256 5a2b78f6fbc36515f6cd10f90519e8b9409f82ecc2924d64ecf346ff5b43c338; uso en borrador.')->id;
        }

        return $assets;
    }

    public function references(array $content): array
    {
        $ids = array_values(array_filter($content['header']));
        $found = PlantillaAsset::whereIn('id', $ids)->get()->keyBy('id');
        $result = [];
        foreach (['left', 'right'] as $slot) {
            $id = $content['header'][$slot.'_asset_id'];
            if (! $id) {
                continue;
            }
            $asset = $found->get($id);
            if (! $asset || $asset->posicion !== $slot) {
                $this->invalid('La imagen seleccionada no existe o pertenece a otra posición.');
            }
            $data = $asset->getAttributes();
            $this->bytes($data);
            $result[$slot] = array_intersect_key($data, array_flip(['id', 'ruta', 'sha256', 'mime', 'ancho', 'alto']));
        }

        return $result;
    }

    public function bytes(array $asset): string
    {
        if (! preg_match('~^certificate-template-assets/[a-f0-9]{64}-(left|right)\.png$~', $asset['ruta'] ?? '')
            || ! Storage::disk('local')->exists($asset['ruta'])) {
            throw new CertificateTemplateException('TEMPLATE_ASSET_MISSING', 'Falta un recurso versionado de la plantilla.');
        }
        $bytes = Storage::disk('local')->get($asset['ruta']);
        if (! hash_equals((string) $asset['sha256'], hash('sha256', $bytes))) {
            throw new CertificateTemplateException('TEMPLATE_ASSET_INTEGRITY', 'La integridad de una imagen no coincide con la versión registrada.');
        }

        return $bytes;
    }

    private function store(string $png, string $slot, User $actor, string $source, array $context = []): PlantillaAsset
    {
        $hash = hash('sha256', $png);
        $path = 'certificate-template-assets/'.$hash.'-'.$slot.'.png';

        return DB::transaction(function () use ($png, $slot, $actor, $source, $hash, $path, $context) {
            // Serialize identical content-addressed uploads without touching immutable rows.
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$path]);
            $existing = PlantillaAsset::where('ruta', $path)->first();
            if ($existing) {
                $this->bytes($existing->getAttributes());

                return $existing;
            }
            if (! Storage::disk('local')->put($path, $png)) {
                throw new \RuntimeException('TEMPLATE_ASSET_STORAGE_FAILED');
            }
            $dimensions = getimagesizefromstring($png);
            $asset = PlantillaAsset::create(['posicion' => $slot, 'sha256' => $hash, 'ruta' => $path, 'mime' => 'image/png',
                'ancho' => $dimensions[0], 'alto' => $dimensions[1], 'bytes' => strlen($png), 'procedencia' => $source, 'creado_por' => $actor->id]);
            app(RegistrarAuditoriaAction::class)->execute('plantilla_asset_reemplazado', 'PlantillaAsset', $asset->id,
                'Imagen institucional versionada.', ['posicion' => $slot, 'asset_id' => $asset->id, 'sha256' => $hash] + $context, $actor->id);

            return $asset;
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['archivo' => $message]);
    }
}
