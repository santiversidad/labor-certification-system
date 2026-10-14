<?php

namespace App\Services;

use RuntimeException;

final class CertificateAssetResolver
{
    public function stylesheet(): string
    {
        $path = resource_path('certificate-assets/document.css');
        $css = file_get_contents($path);
        if ($css === false) {
            throw new RuntimeException('CERTIFICATE_STYLESHEET_UNAVAILABLE');
        }

        return $css;
    }

    /** Only an explicitly configured, local and institutionally approved mark can be embedded. */
    public function approvedMark(): ?string
    {
        $filename = config('certificate_pdf.approved_mark');
        if (! is_string($filename) || ! preg_match('/^[a-z0-9_-]+\.(png|jpg)$/i', $filename)) {
            return null;
        }

        $path = resource_path('certificate-assets/'.$filename);
        if (! is_file($path)) {
            return null;
        }

        return $path;
    }
}
