<?php

namespace Database\Seeders;

use App\Models\Component;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Database\Seeder;

class FictionalSurveySeeder extends Seeder
{
    public function run(): void
    {
        $configurator = Configurator::factory()->create(['name' => 'Dinner Party Survey']);

        $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);

        $aboutYou = View::factory()->group()->childOf($root)->create(['order' => 0]);
        $general = View::factory()->group()->childOf($root)->create(['order' => 1]);
        $veganExtras = View::factory()->group()->childOf($root)->create(['order' => 2]);
        $thankYou = View::factory()->group()->childOf($root)->create(['order' => 3]);

        $name = View::factory()->textInput(label: 'What is your name?')->childOf($aboutYou)->create(['order' => 0]);
        $diet = View::factory()->select([
            ['value' => 'omnivore', 'label' => 'Omnivore'],
            ['value' => 'vegetarian', 'label' => 'Vegetarian'],
            ['value' => 'vegan', 'label' => 'Vegan'],
        ], label: 'What is your diet?')->childOf($aboutYou)->create(['order' => 1]);
        $activities = View::factory()->group()->childOf($aboutYou)->create(['order' => 2]);
        View::factory()->html('Which activities are you interested in? (1-3)')->childOf($activities)->create(['order' => 0]);
        $hiking = View::factory()->checkbox(label: 'Hiking')->childOf($activities)->create(['order' => 1]);
        $boardGames = View::factory()->checkbox(label: 'Board games')->childOf($activities)->create(['order' => 2]);
        $karaoke = View::factory()->checkbox(label: 'Karaoke')->childOf($activities)->create(['order' => 3]);
        $cooking = View::factory()->checkbox(label: 'Cooking')->childOf($activities)->create(['order' => 4]);
        $allergy = View::factory()->textInput(label: 'Please describe your allergy')->childOf($aboutYou)->create(['order' => 3]);

        View::factory()->html('Thanks! We\'ll use your preferences to plan the party.')
            ->childOf($general)->create(['order' => 0]);
        View::factory()->html('As a vegan, you\'ll get a specially curated menu!')
            ->childOf($veganExtras)->create(['order' => 0]);
        View::factory()->html('Thank you for completing the survey!')
            ->childOf($thankYou)->create(['order' => 0]);

        Rule::factory()->visibility("answers['{$diet->id}'] == 'vegan'")
            ->create(['view_id' => $allergy->id]);

        Rule::factory()->validation("answers['{$name->id}'] != null && strlen(answers['{$name->id}']) > 0")
            ->create(['view_id' => $name->id, 'meta' => ['message' => 'Please tell us your name.']]);
        Rule::factory()->validation("answers['{$name->id}'] == null || strlen(answers['{$name->id}']) <= 50")
            ->create(['view_id' => $name->id, 'meta' => ['message' => 'Name must be 50 characters or fewer.']]);

        Rule::factory()->validation("answers['{$diet->id}'] != null")
            ->create(['view_id' => $diet->id, 'meta' => ['message' => 'Please choose a diet.']]);

        $selectedActivityCount = "(answers['{$hiking->id}'] == true ? 1 : 0) + (answers['{$boardGames->id}'] == true ? 1 : 0) + (answers['{$karaoke->id}'] == true ? 1 : 0) + (answers['{$cooking->id}'] == true ? 1 : 0)";

        Rule::factory()->validation("{$selectedActivityCount} >= 1")
            ->create(['view_id' => $activities->id, 'meta' => ['message' => 'Choose at least one activity.']]);
        Rule::factory()->validation("{$selectedActivityCount} <= 3")
            ->create(['view_id' => $activities->id, 'meta' => ['message' => 'Choose at most three activities.']]);

        Rule::factory()->validation("answers['{$allergy->id}'] != null && strlen(answers['{$allergy->id}']) > 0")
            ->create(['view_id' => $allergy->id, 'meta' => ['message' => 'Please tell us about your allergy.']]);

        Rule::factory()->validation("answers['{$general->id}'] != null")
            ->create(['view_id' => $general->id, 'meta' => ['message' => 'Please continue.']]);
        Rule::factory()->validation("answers['{$veganExtras->id}'] != null")
            ->create(['view_id' => $veganExtras->id, 'meta' => ['message' => 'Please continue.']]);

        Rule::factory()->visibility("answers['{$diet->id}'] != 'vegan'")
            ->create(['view_id' => $general->id]);
        Rule::factory()->visibility("answers['{$diet->id}'] == 'vegan'")
            ->create(['view_id' => $veganExtras->id]);

        Component::factory()->scope('just_next', depth: 1)
            ->create(['configurator_id' => $configurator->id]);

        $this->command?->info('   Configrator: '.route('configurators.play', ['configurator' => $configurator->id]));
    }
}
