<?php

namespace App\Domain\SalaryScale;

use Illuminate\Validation\ValidationException;

class SalaryScaleAmount
{
    public const MAX = 999999999999;

    public static function percentage(string $value): int
    {
        if (! preg_match('/^\d{1,3}(?:[.,]\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['porcentaje' => 'Ingrese un porcentaje entre 0 y 100, con hasta dos decimales.']);
        }
        $parts = explode('.', str_replace(',', '.', $value));
        $basisPoints = ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if ($basisPoints > 10000) {
            throw ValidationException::withMessages(['porcentaje' => 'El límite preventivo de esta herramienta es 100 %. No representa un límite jurídico.']);
        }

        return $basisPoints;
    }

    public static function increase(string $base, string $percentage): string
    {
        $basisPoints = self::percentage($percentage);
        if (! preg_match('/^[1-9]\d{0,11}$/D', $base)) {
            throw ValidationException::withMessages(['porcentaje' => 'La base contiene un valor monetario inválido.']);
        }
        // Integer arithmetic on PHP 64-bit: no floating point, half-up to one COP.
        $result = intdiv(((int) $base) * (10000 + $basisPoints) + 5000, 10000);
        if ($result > self::MAX) {
            throw ValidationException::withMessages(['porcentaje' => 'El resultado supera la capacidad monetaria de la tabla.']);
        }

        return (string) $result;
    }
}
