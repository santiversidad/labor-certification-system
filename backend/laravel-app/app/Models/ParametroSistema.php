<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametroSistema extends Model
{
    protected $table = 'parametros_sistema';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'updated_by',
    ];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Devuelve el valor de un parámetro casteado a su tipo declarado.
     * Si la clave no existe, retorna $default.
     */
    public static function valor(string $clave, mixed $default = null): mixed
    {
        $parametro = static::where('clave', $clave)->first();

        if (! $parametro) {
            return $default;
        }

        return match ($parametro->tipo) {
            'boolean' => filter_var($parametro->valor, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $parametro->valor,
            default   => $parametro->valor,
        };
    }
}
