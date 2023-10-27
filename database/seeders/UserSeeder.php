<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'DevDojo',
            'email' => 'support@devdojo.com',
            'password' => bcrypt('m7Ej8lVK0Fu7EPLrUG')
        ]);
    }
}
