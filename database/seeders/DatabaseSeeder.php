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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(FictionalSurveySeeder::class);
        $this->call(WidgetShowcaseSeeder::class);
        $this->call(TemplatingShowcaseSeeder::class);
        $this->call(WienerbergerWevoltSeeder::class);
        $this->call(RockBandGearConfiguratorSeeder::class);
    }
}
