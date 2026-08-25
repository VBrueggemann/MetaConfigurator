<?php

use App\Actions\DTO\PendingAnswer;
use App\Actions\Rules\CommitConfigurationAnswers;
use App\Actions\Rules\FetchConfigurationState;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\WidgetShowcaseSeeder;

beforeEach(function () {
    app(WidgetShowcaseSeeder::class)->run();

    $configurator = Configurator::where('name', 'Widget Showcase')->firstOrFail();
    $this->configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $root = $configurator->rootView;
    $shell = View::where('parent_id', $root->id)->firstOrFail();
    $allQuestions = View::where('parent_id', $shell->id)->firstOrFail();

    $steps = View::where('parent_id', $allQuestions->id)->orderBy('order')->get();
    $this->step1 = $steps[0];
    $this->step2 = $steps[1];
    $this->step3 = $steps[2];
    $this->step4 = $steps[3];

    $step1Fields = View::where('parent_id', $this->step1->id)->orderBy('order')->get();
    $this->maxChars = $step1Fields[0];
    $this->limitedText = $step1Fields[1];

    $step2Children = View::where('parent_id', $this->step2->id)->orderBy('order')->get();
    $this->traits = $step2Children[0];
    $this->courageMessage = $step2Children[1];
    $this->creativityMessage = $step2Children[2];
    $this->curiosityMessage = $step2Children[3];

    $traitOptions = View::where('parent_id', $this->traits->id)->orderBy('order')->get();
    $this->courage = $traitOptions[0];
    $this->creativity = $traitOptions[1];
    $this->curiosity = $traitOptions[2];
});

it('rejects text longer than the configured max characters', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->step1->id, true),
        new PendingAnswer($this->maxChars->id, 2),
        new PendingAnswer($this->limitedText->id, 'abc'),
    ]);

    expect($evaluation->componentContexts['validation'])->toHaveKey($this->limitedText->id);
});

it('accepts text within the configured max characters', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->step1->id, true),
        new PendingAnswer($this->maxChars->id, 2),
        new PendingAnswer($this->limitedText->id, 'ab'),
    ]);

    expect($evaluation->componentContexts['validation'])->not->toHaveKey($this->limitedText->id);
});

it('raises the character limit when the number field is increased', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->step1->id, true),
        new PendingAnswer($this->maxChars->id, 5),
        new PendingAnswer($this->limitedText->id, 'abcde'),
    ]);

    expect($evaluation->componentContexts['validation'])->not->toHaveKey($this->limitedText->id);
});

it('shows only the inspiring messages matching the selected strengths', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->courage->id, true),
        new PendingAnswer($this->creativity->id, false),
        new PendingAnswer($this->curiosity->id, true),
    ]);

    expect($evaluation->componentContexts['visibility'][$this->courageMessage->id])->toBeTrue();
    expect($evaluation->componentContexts['visibility'][$this->creativityMessage->id])->toBeFalse();
    expect($evaluation->componentContexts['visibility'][$this->curiosityMessage->id])->toBeTrue();
});

it('walks through every step in order via flow rules', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->step1->id, true),
        new PendingAnswer($this->maxChars->id, 2),
        new PendingAnswer($this->limitedText->id, 'ab'),
    ]);
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->step2->id)
        ->not->toContain($this->step3->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->step2->id, true),
        new PendingAnswer($this->courage->id, true),
        new PendingAnswer($this->creativity->id, false),
        new PendingAnswer($this->curiosity->id, false),
    ]);
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->step3->id)
        ->not->toContain($this->step4->id);

    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->step3->id, true),
    ]);
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->step4->id);
});
