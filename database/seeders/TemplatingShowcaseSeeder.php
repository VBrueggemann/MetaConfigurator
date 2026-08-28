<?php

namespace Database\Seeders;

use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Database\Seeder;

class TemplatingShowcaseSeeder extends Seeder
{
    private const int MAX_LENGTH = 8;

    public function run(): void
    {
        $maxLength = self::MAX_LENGTH;

        $configurator = Configurator::factory()->create(['name' => 'Templating Showcase', 'slug' => 'templating-showcase']);

        $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);

        $firstName = View::factory()->textInput('First name')->childOf($root)->create(['order' => 0]);
        $lastName = View::factory()->textInput('Last name')->childOf($root)->create(['order' => 1]);

        $configurator->calculations()->createMany([
            ['name' => 'firstNameLength', 'expression' => "strlen(answer('{$firstName->id}'))"],
            ['name' => 'firstNameRemaining', 'expression' => "{$maxLength} - calculation.firstNameLength"],
            ['name' => 'lastNameLength', 'expression' => "strlen(answer('{$lastName->id}'))"],
            ['name' => 'lastNameRemaining', 'expression' => "{$maxLength} - calculation.lastNameLength"],
        ]);

        Rule::factory()->validation("calculation.firstNameLength <= {$maxLength}")
            ->message("'{{ answer('{$firstName->id}') }}' is {{ calculation.firstNameLength - {$maxLength} }} characters over the limit")
            ->create(['view_id' => $firstName->id]);

        Rule::factory()->validation("calculation.lastNameLength <= {$maxLength}")
            ->message("'{{ answer('{$lastName->id}') }}' is {{ calculation.lastNameLength - {$maxLength} }} characters over the limit")
            ->create(['view_id' => $lastName->id]);

        View::factory()->html(implode('<br>', [
            "Question: {{ label('{$firstName->id}') }}",
            "Answer: {{ answer('{$firstName->id}') }}",
            'Character count: {{ calculation.firstNameLength }}',
            'Characters left before the limit triggers: {{ calculation.firstNameRemaining }}',
            "Validation message preview: {{ validationError('{$firstName->id}') }}",
        ]))->childOf($root)->create(['order' => 2]);

        View::factory()->html(implode('<br>', [
            "Question: {{ label('{$lastName->id}') }}",
            "Answer: {{ answer('{$lastName->id}') }}",
            'Character count: {{ calculation.lastNameLength }}',
            'Characters left before the limit triggers: {{ calculation.lastNameRemaining }}',
            "Validation message preview: {{ validationError('{$lastName->id}') }}",
        ]))->childOf($root)->create(['order' => 3]);

        $this->command?->info('   Configurator: '.route('configurators.play', ['configurator' => $configurator]));
    }
}
