<?php

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\ConfigurationEvaluation;
use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Enums\RuleComponent;
use App\Enums\ViewType;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\RockBandGearConfiguratorSeeder;
use Illuminate\Support\Collection;

beforeEach(function () {
    app(RockBandGearConfiguratorSeeder::class)->run();

    $configurator = Configurator::where('slug', 'rock-band-gear-configurator')->firstOrFail();
    $this->configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $root = $configurator->rootView;
    $shell = View::where('parent_id', $root->id)->first();
    $allQuestions = View::where('parent_id', $shell->id)->first();

    $this->steps = View::where('parent_id', $allQuestions->id)->orderBy('order')->get();
    $this->intro = $this->steps[0];

    $picker = View::where('parent_id', $this->intro->id)->orderBy('order')->get()[1];
    $toggles = View::where('parent_id', $picker->id)->orderBy('order')->get();
    [$this->needGuitar, $this->needBass, $this->needVocals, $this->needDrums] = $toggles->all();
});

/**
 * @return Collection<int, View>
 */
function rbgInputs(ConfigurationEvaluation $evaluation): Collection
{
    return collect($evaluation->componentContexts['scope'])->toList()
        ->where('type', ViewType::Input)->values();
}

function rbgByLabel(Collection $fields, string $label): ?View
{
    return $fields->first(fn (View $field) => ($field->config['label'] ?? $field->config['title'] ?? null) === $label);
}

function rbgCurrentStepLabel(Collection $steps, ConfigurationEvaluation $evaluation): ?string
{
    $scopeIds = collect($evaluation->componentContexts['scope'])->toList()->pluck('id');
    $step = $steps->first(fn (View $step) => $scopeIds->contains($step->id));

    return $step?->config['label'] ?? null;
}

it('seeds thirteen top-level steps', function () {
    expect($this->steps)->toHaveCount(13);
});

it('requires at least one instrument before leaving the intro step', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->needGuitar->id, false),
        new PendingAnswer($this->needBass->id, false),
        new PendingAnswer($this->needVocals->id, false),
        new PendingAnswer($this->needDrums->id, false),
    ]);

    $errors = collect($evaluation->componentContexts['scope'])->toList()
        ->flatMap(fn (View $view) => $view->rules->where('component', RuleComponent::Validation)->pluck('meta.message'));

    expect($errors)->not->toBeEmpty();
    expect(rbgCurrentStepLabel($this->steps, app(FetchConfigurationState::class)($this->configuration)))->toBe('Build Your Bands Rig');
});

it('skips straight to the drums branch when only drums is selected', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->needGuitar->id, false),
        new PendingAnswer($this->needBass->id, false),
        new PendingAnswer($this->needVocals->id, false),
        new PendingAnswer($this->needDrums->id, true),
    ]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect(rbgCurrentStepLabel($this->steps, $evaluation))->toBe('Drums — Kit & Brand');
    expect(rbgInputs($evaluation)->pluck('config.label'))->toContain('Kit type');
});

it('shows electric-only body and string fields and hides the acoustic ones', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->needGuitar->id, true),
        new PendingAnswer($this->needBass->id, false),
        new PendingAnswer($this->needVocals->id, false),
        new PendingAnswer($this->needDrums->id, false),
    ]);

    $fields = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    $type = rbgByLabel($fields, 'Electric or acoustic?');
    $brand = rbgByLabel($fields, 'Choose your guitar brand');

    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($type->id, 'Electric'),
        new PendingAnswer($brand->id, 'Fender'),
    ]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration);
    $labels = rbgInputs($evaluation)->pluck('config.label');

    expect($labels)->toContain('Body shape', 'String count')
        ->and(rbgInputs($evaluation))->toHaveCount(6);
});

it('computes a matching grand total after answering a full guitar-only build', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->needGuitar->id, true),
        new PendingAnswer($this->needBass->id, false),
        new PendingAnswer($this->needVocals->id, false),
        new PendingAnswer($this->needDrums->id, false),
    ]);

    $f1 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($f1, 'Electric or acoustic?')->id, 'Electric'),
        new PendingAnswer(rbgByLabel($f1, 'Choose your guitar brand')->id, 'Fender'),
    ]);

    $f2 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    $leftHanded2 = $f2->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Left-handed'));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($f2, 'Body shape')->id, 'Les Paul Style'),
        new PendingAnswer(rbgByLabel($f2, 'String count')->id, '7-String'),
        new PendingAnswer(rbgByLabel($f2, 'String type')->id, 'Coated'),
        new PendingAnswer(rbgByLabel($f2, 'Finish')->id, 'Custom Color'),
        new PendingAnswer($leftHanded2->id, true),
        new PendingAnswer(rbgByLabel($f2, 'Case')->id, 'Hardshell Case'),
    ]);

    $f3 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    $overdrive = $f3->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Overdrive'));
    $delay = $f3->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Delay'));
    $capo = $f3->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Capo'));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($f3, 'Amplifier')->id, 'Combo Amp 40W'),
        new PendingAnswer($overdrive->id, true),
        new PendingAnswer($delay->id, true),
        new PendingAnswer(rbgByLabel($f3, 'Instrument cable')->id, '3m Braided Pro Cable'),
        new PendingAnswer(rbgByLabel($f3, 'Strap')->id, 'Leather Strap'),
        new PendingAnswer($capo->id, true),
    ]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect((float) $evaluation->context['calculation']->guitarTotal)->toBe(1649.0);
    expect((float) $evaluation->context['calculation']->grandTotal)->toBe(1649.0);
    expect(rbgCurrentStepLabel($this->steps, $evaluation))->toBe('Shared Accessories');
});

it('lets bass reuse the guitar cable price and hides its own cable field', function () {
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer($this->needGuitar->id, true),
        new PendingAnswer($this->needBass->id, true),
        new PendingAnswer($this->needVocals->id, false),
        new PendingAnswer($this->needDrums->id, false),
    ]);

    $f1 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($f1, 'Electric or acoustic?')->id, 'Electric'),
        new PendingAnswer(rbgByLabel($f1, 'Choose your guitar brand')->id, 'Fender'),
    ]);

    $f2 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    $leftHanded2 = $f2->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Left-handed'));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($f2, 'Body shape')->id, 'Stratocaster Style'),
        new PendingAnswer(rbgByLabel($f2, 'String count')->id, '6-String'),
        new PendingAnswer(rbgByLabel($f2, 'String type')->id, 'Nickel Wound'),
        new PendingAnswer(rbgByLabel($f2, 'Finish')->id, 'Black'),
        new PendingAnswer($leftHanded2->id, false),
        new PendingAnswer(rbgByLabel($f2, 'Case')->id, 'No Case'),
    ]);

    $f3 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    $capo3 = $f3->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Capo'));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($f3, 'Amplifier')->id, 'Skip The Amp'),
        new PendingAnswer(rbgByLabel($f3, 'Instrument cable')->id, '3m Braided Pro Cable'),
        new PendingAnswer(rbgByLabel($f3, 'Strap')->id, 'Basic Nylon Strap'),
        new PendingAnswer($capo3->id, false),
    ]);

    $b1 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($b1, 'Choose your bass brand')->id, 'Fender'),
        new PendingAnswer(rbgByLabel($b1, 'Body shape')->id, 'Jazz Style'),
    ]);

    $b2 = rbgInputs(app(FetchConfigurationState::class)($this->configuration));
    $leftHandedB2 = $b2->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Left-handed'));
    app(CommitConfigurationAnswers::class)($this->configuration, [
        new PendingAnswer(rbgByLabel($b2, 'String count')->id, '4-String'),
        new PendingAnswer(rbgByLabel($b2, 'String type')->id, 'Roundwound'),
        new PendingAnswer($leftHandedB2->id, false),
        new PendingAnswer(rbgByLabel($b2, 'Case')->id, 'No Case'),
    ]);

    $preReuseEvaluation = app(FetchConfigurationState::class)($this->configuration);
    $guitarCablePrice = (float) $preReuseEvaluation->context['calculation']->guitarCablePrice;

    $b3 = rbgInputs($preReuseEvaluation);
    $reuse = $b3->first(fn (View $field) => str_contains($field->config['label'] ?? '', 'Reuse the same cable'));

    $livePreview = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($reuse->id, true),
    ]);

    $liveFields = rbgInputs($livePreview)->pluck('config.label');

    expect($guitarCablePrice)->toBe(29.0);
    expect((float) $livePreview->context['calculation']->bassCablePrice)->toBe(29.0);
    expect($liveFields)->not->toContain('Instrument cable');
});
