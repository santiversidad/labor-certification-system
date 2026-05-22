<?php

namespace Database\Factories;

use App\Models\RangoSalarial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RangoSalarial>
 */
class RangoSalarialFactory extends Factory
{
    protected $model = RangoSalarial::class;

    public function definition(): array
    {
        return [
            'codigo'         => fake()->numerify('###'),
            'grado'          => fake()->numerify('##'),
            'vigencia_anio'  => fake()->year(),
            'salario_basico' => fake()->randomFloat(2, 1000000, 10000000),
            'moneda'         => 'COP',
            'observaciones'  => null,
            'estado'         => true,
        ];
    }
}
