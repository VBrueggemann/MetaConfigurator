<?php

namespace Database\Seeders;

use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Database\Seeder;

class FictionalSurveySeeder extends Seeder
{
    public function run(): void
    {
        $configurator = Configurator::factory()->create(['name' => 'Dinner Party Survey', 'slug' => 'dinner-party-survey']);

        $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);

        $aboutYou = View::factory()->group()->childOf($root)->create(['order' => 0]);
        $name = View::factory()->textInput(label: 'What is your name?')->childOf($aboutYou)->create(['order' => 0]);
        $diet = View::factory()->textInput(label: 'What is your diet?')->childOf($aboutYou)->create(['order' => 1]);
        $allergy = View::factory()->textInput(label: 'Please describe your allergy')->childOf($aboutYou)->create(['order' => 2]);

        $normalEnd = View::factory()->html('Thanks! We\'ll use your preferences to plan the party.')
            ->childOf($root)->create(['order' => 1]);

        $allergyEnd = View::factory()->html('Thanks! We\'ll take care of you allergy.')
            ->childOf($root)->create(['order' => 2]);

        Rule::factory()->visibility("answer('{$diet->id}') == 'vegan'")
            ->create(['view_id' => $allergy->id]);

        Rule::factory()->validation('answer(this) != null && strlen(answer(this)) > 0')
            ->create(['view_id' => $name->id, 'meta' => ['message' => 'Please tell us your name.']]);
        Rule::factory()->validation('answer(this) == null || strlen(answer(this)) <= 50')
            ->create(['view_id' => $name->id, 'meta' => ['message' => 'Name must be 50 characters or fewer.']]);

        Rule::factory()->validation('answer(this) != null && strlen(answer(this)) > 0')
            ->create(['view_id' => $diet->id, 'meta' => ['message' => 'Please tell us your diet.']]);

        Rule::factory()->validation('answer(this) != null && strlen(answer(this)) > 0')
            ->create(['view_id' => $allergy->id, 'meta' => ['message' => 'Please tell us about your allergy.']]);

        Rule::factory()->flow("answer('{$aboutYou->id}') != null && answer('{$allergy->id}') == null", $normalEnd->id)
            ->create(['view_id' => $normalEnd->id]);
        Rule::factory()->flow("answer('{$aboutYou->id}') != null && answer('{$allergy->id}') != null", $allergyEnd->id)
            ->create(['view_id' => $allergyEnd->id]);

        $configurator->update(['scope_depth' => 1]);

        $this->command?->info('   Configrator: '.route('configurators.play', ['configurator' => $configurator]));
    }
}
