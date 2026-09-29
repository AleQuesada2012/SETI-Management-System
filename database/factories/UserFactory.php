<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            // Set explicitly so a model created in a test has every column the app reads (Model::shouldBeStrict).
            'last_login_at' => null,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ];
    }

    /** Holds the given system role(s): User::factory()->withRole(SystemRole::Administrador). */
    public function withRole(SystemRole ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            $user->assignRole(array_map(fn (SystemRole $role): string => $role->value, $roles));
        });
    }

    /** Created by the Superusuario, invitation not yet accepted (Q21). */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'email_verified_at' => null,
        ]);
    }

    /** Access revoked, history kept (RF_88). */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
