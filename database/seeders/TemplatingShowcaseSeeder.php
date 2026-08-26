<?php

namespace Database\Seeders;

use App\Models\Component;
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

        Component::factory()->calculation([
            'firstNameLength' => "strlen(answers['{$firstName->id}'])",
            'firstNameRemaining' => "{$maxLength} - calculation.firstNameLength",
            'lastNameLength' => "strlen(answers['{$lastName->id}'])",
            'lastNameRemaining' => "{$maxLength} - calculation.lastNameLength",
        ])->create(['configurator_id' => $configurator->id]);

        $firstNameRule = Rule::factory()->validation("calculation.firstNameLength <= {$maxLength}")->create([
            'view_id' => $firstName->id,
            'meta' => ['message' => "'{{ answers['{$firstName->id}'] }}' is {{ calculation.firstNameLength - {$maxLength} }} characters over the limit"],
        ]);

        $lastNameRule = Rule::factory()->validation("calculation.lastNameLength <= {$maxLength}")->create([
            'view_id' => $lastName->id,
            'meta' => ['message' => "'{{ answers['{$lastName->id}'] }}' is {{ calculation.lastNameLength - {$maxLength} }} characters over the limit"],
        ]);

        $firstNameInfo = View::factory()->html('placeholder')->childOf($root)->create(['order' => 2]);
        $firstNameInfo->update(['config' => ['content' => implode('<br>', [
            "Question: {{ label('{$firstName->id}') }}",
            "Answer: {{ answers['{$firstName->id}'] }}",
            'Character count: {{ calculation.firstNameLength }}',
            'Characters left before the limit triggers: {{ calculation.firstNameRemaining }}',
            "Validation message preview: {{ message('{$firstNameRule->id}') }}",
        ])]]);

        $lastNameInfo = View::factory()->html('placeholder')->childOf($root)->create(['order' => 3]);
        $lastNameInfo->update(['config' => ['content' => implode('<br>', [
            "Question: {{ label('{$lastName->id}') }}",
            "Answer: {{ answers['{$lastName->id}'] }}",
            'Character count: {{ calculation.lastNameLength }}',
            'Characters left before the limit triggers: {{ calculation.lastNameRemaining }}',
            "Validation message preview: {{ message('{$lastNameRule->id}') }}",
        ])]]);

        $this->command?->info('   Configurator: '.route('configurators.play', ['configurator' => $configurator]));
    }
}
