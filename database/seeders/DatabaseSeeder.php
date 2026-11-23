<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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
            email: (string) config('demo.admin_email'),
            password: $this->password('demo.admin_password'),
            isAdmin: true,
        );

        $this->upsertUser(
            name: 'Vellum Studio',
            email: (string) config('demo.studio_email'),
            password: $this->password('demo.studio_password'),
            isAdmin: false,
        );
    }

    private function password(string $key): ?string
    {
        $password = config($key);

        return is_string($password) && $password !== '' ? $password : null;
    }

    private function upsertUser(string $name, string $email, ?string $password, bool $isAdmin): void
    {
        if ($password === null) {
            return;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->email_verified_at ??= Carbon::now();
        $user->is_admin = $isAdmin;
        $user->save();
    }
}
