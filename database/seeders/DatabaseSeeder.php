<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Create the two accounts the assessment needs: an operator and a studio
     * demo account. No generations, charges or statistics are seeded — those
     * must come from real Pixelforge usage.
     */
    public function run(): void
    {
        $this->upsertUser(
            name: 'Studio Operator',
            email: env('ADMIN_EMAIL', 'admin@vellum.studio'),
            password: env('ADMIN_PASSWORD', app()->isProduction() ? null : 'VellumAdmin!2026'),
            isAdmin: true,
        );

        $this->upsertUser(
            name: 'Vellum Studio',
            email: env('DEMO_EMAIL', 'studio@vellum.studio'),
            password: env('DEMO_PASSWORD', app()->isProduction() ? null : 'VellumStudio!2026'),
            isAdmin: false,
        );
    }

    private function upsertUser(string $name, string $email, ?string $password, bool $isAdmin): void
    {
        if ($password === null || $password === '') {
            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->is_admin = $isAdmin;
        $user->save();
    }
}
