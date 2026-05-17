<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        foreach ($this->users() as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                $user,
            );
        }

        $this->call(SupportReferenceDataSeeder::class);
        $this->call(SupportTicketSeeder::class);
        $this->call(SupportAccessControlDemoSeeder::class);
        $this->call(SupportSuperAdminSeeder::class);
    }

    /**
     * @return array<int, array{name: string, email: string, password: string}>
     */
    private function users(): array
    {
        return [
            ['name' => 'Amit', 'email' => 'amit@example.com', 'password' => 'password'],
            ['name' => 'Priya', 'email' => 'priya@example.com', 'password' => 'password'],
            ['name' => 'Mei Lin', 'email' => 'mei.lin@example.com', 'password' => 'password'],
            ['name' => 'Sarah', 'email' => 'sarah@example.com', 'password' => 'password'],
            ['name' => 'Jason Lee', 'email' => 'jason.lee@example.com', 'password' => 'password'],
        ];
    }
}
