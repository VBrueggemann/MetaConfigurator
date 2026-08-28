<?php

namespace Database\Seeders;

use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Database\Seeder;

class WidgetShowcaseSeeder extends Seeder
{
    /**
     * Scope depth 2 puts every step on a single page.
     * Scope depth 3 turns each step into its own page.
     */
    public function __construct(private readonly int $depth = 3) {}

    public function run(): void
    {
        $configurator = Configurator::factory()->create(['name' => 'Widget Showcase', 'slug' => 'widget-showcase']);

        $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
        $shell = View::factory()->group()->childOf($root)->create(['order' => 0]);
        $allQuestions = View::factory()->group()->childOf($shell)->create(['order' => 0]);

        $step1 = View::factory()->group('Step 1')->childOf($allQuestions)->create(['order' => 0]);
        $step2 = View::factory()->group('Step 2')->childOf($allQuestions)->create(['order' => 1]);
        $step3 = View::factory()->group('Step 3')->childOf($allQuestions)->create(['order' => 2]);
        $step4 = View::factory()->html('Thanks for trying every widget!')->childOf($allQuestions)->create(['order' => 3]);

        $maxChars = View::factory()->number('Max characters allowed in the next field', default: 2)
            ->childOf($step1)->create(['order' => 0]);
        $limitedText = View::factory()->textInput('Type something (limited by the field above)')
            ->childOf($step1)->create(['order' => 1]);

        Rule::factory()->validation(
            "answer(this) == null || strlen(answer(this)) <= answer('{$maxChars->id}')"
        )->create(['view_id' => $limitedText->id, 'meta' => ['message' => 'Text exceeds the allowed character limit.']]);

        $traits = View::factory()->multiselectGroup('Pick your strengths')->childOf($step2)->create(['order' => 0]);
        $courage = View::factory()->checkbox('Courage')->childOf($traits)->create(['order' => 0]);
        $creativity = View::factory()->checkbox('Creativity')->childOf($traits)->create(['order' => 1]);
        $curiosity = View::factory()->checkbox('Curiosity')->childOf($traits)->create(['order' => 2]);

        $courageMessage = View::factory()->html('Courage is not the absence of fear, but the triumph over it.')
            ->childOf($step2)->create(['order' => 1]);
        $creativityMessage = View::factory()->html('Creativity is intelligence having fun.')
            ->childOf($step2)->create(['order' => 2]);
        $curiosityMessage = View::factory()->html('Curiosity is the wick in the candle of learning.')
            ->childOf($step2)->create(['order' => 3]);

        Rule::factory()->visibility("answer('{$courage->id}') == true")->create(['view_id' => $courageMessage->id]);
        Rule::factory()->visibility("answer('{$creativity->id}') == true")->create(['view_id' => $creativityMessage->id]);
        Rule::factory()->visibility("answer('{$curiosity->id}') == true")->create(['view_id' => $curiosityMessage->id]);

        View::factory()->checkbox('Checkbox')->childOf($step3)->create(['order' => 0]);
        View::factory()->color('Colour')->childOf($step3)->create(['order' => 1]);
        View::factory()->date('Date')->childOf($step3)->create(['order' => 2]);
        View::factory()->select(
            [['value' => 'a', 'label' => 'Option A'], ['value' => 'b', 'label' => 'Option B']],
            'Dropdown',
        )->childOf($step3)->create(['order' => 3]);
        View::factory()->email('Email')->childOf($step3)->create(['order' => 4]);
        View::factory()->number('Number')->childOf($step3)->create(['order' => 5]);
        View::factory()->radio(
            [['value' => 'x', 'label' => 'Option X'], ['value' => 'y', 'label' => 'Option Y']],
            'Radio',
        )->childOf($step3)->create(['order' => 6]);
        View::factory()->range('Range', min: 0, max: 10)->childOf($step3)->create(['order' => 7]);
        View::factory()->textInput('Text')->childOf($step3)->create(['order' => 8]);
        View::factory()->datalist(
            [['value' => 'Berlin', 'label' => 'Berlin'], ['value' => 'Munich', 'label' => 'Munich']],
            'Text with datalist',
        )->childOf($step3)->create(['order' => 9]);

        $multiselectDropdown = View::factory()->multiselectGroup('Multi-select (dropdown)')
            ->childOf($step3)->create(['order' => 10]);
        View::factory()->checkbox('Red')->childOf($multiselectDropdown)->create(['order' => 0]);
        View::factory()->checkbox('Green')->childOf($multiselectDropdown)->create(['order' => 1]);
        View::factory()->checkbox('Blue')->childOf($multiselectDropdown)->create(['order' => 2]);

        $multiselectCheckboxes = View::factory()->group('Multi-select (checkboxes)')
            ->childOf($step3)->create(['order' => 11]);
        View::factory()->checkbox('Small')->childOf($multiselectCheckboxes)->create(['order' => 0]);
        View::factory()->checkbox('Medium')->childOf($multiselectCheckboxes)->create(['order' => 1]);
        View::factory()->checkbox('Large')->childOf($multiselectCheckboxes)->create(['order' => 2]);

        Rule::factory()->flow("answer('{$step1->id}') == true", $step2->id)->create(['view_id' => $step2->id]);
        Rule::factory()->flow("answer('{$step2->id}') == true", $step3->id)->create(['view_id' => $step3->id]);
        Rule::factory()->flow("answer('{$step3->id}') == true", $step4->id)->create(['view_id' => $step4->id]);

        $configurator->update(['scope_depth' => $this->depth]);

        $this->command?->info('   Configrator: '.route('configurators.play', ['configurator' => $configurator]));
    }
}
