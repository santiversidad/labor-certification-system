<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_cuenta' => 'funcionario',
            'name' => fake('es_CO')->name(),
            // La cédula es el identificador único de autenticación
            'documento' => fake()->unique()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'telefono' => fake()->numerify('3##-###-####'),
            'estado' => true,
            'remember_token' => Str::random(10),
            // email es opcional; se omite por defecto en tests
        ];
    }

    public function backoffice(): static
    {
        return $this->state(fn () => [
            'tipo_cuenta' => 'backoffice', 'documento' => null,
            'username' => 'persona.test.'.Str::lower(Str::random(12)),
        ]);
    }

    /** Estado para usuario inactivo. */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => false,
        ]);
    }
}
