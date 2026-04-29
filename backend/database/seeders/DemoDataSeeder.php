<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $parent = User::updateOrCreate(
            ['email' => 'parent@kidzoo.test'],
            [
                'name' => 'Abdelaty Ahmed',
                'password' => 'password123',
                'email_verified_at' => now(),
                'phone' => '+201000000000',
                'language' => 'en',
            ],
        );

        Child::updateOrCreate(
            ['username' => 'leo'],
            [
                'parent_id' => $parent->id,
                'name' => 'Leo',
                'password' => 'kids1234',
                'age' => 6,
                'gender' => 'boy',
                'is_active' => true,
            ],
        );

        $this->command->info('Demo parent: parent@kidzoo.test / password123');
        $this->command->info('Demo child login: leo / kids1234');
    }
}
