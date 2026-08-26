<?php

use App\Actions\CommitConfigurationAnswers;
use App\Actions\DTO\PendingAnswer;
use App\Actions\FetchConfigurationState;
use App\Models\Answer;
use App\Models\Configuration;
use App\Models\Configurator;
use App\Models\View;
use Database\Seeders\WienerbergerWevoltSeeder;

beforeEach(function () {
    app(WienerbergerWevoltSeeder::class)->run();

    $configurator = Configurator::where('name', 'Wienerberger Wevolt Solarkonfigurator')->firstOrFail();
    $this->configuration = Configuration::factory()->create(['configurator_id' => $configurator->id]);

    $root = $configurator->rootView;
    $topLevel = View::where('parent_id', $root->id)->orderBy('order')->get();
    $this->page = $topLevel[0];
    $this->success = $topLevel[1];

    $fields = View::where('parent_id', $this->page->id)->orderBy('order')->get();
    [
        $this->roofAreaField, $this->liveEstimate, $this->roofShapeField, $this->roofOrientationField,
        $this->roofPitchField, $this->consumptionChoiceField, $this->consumptionExactField, $this->tariffField,
        $this->storageField, $this->evPresenceField, $this->evCountField, $this->summary,
        $this->partnerAddress, $this->contactDetails, $this->contactDetails2,
    ] = $fields->all();

    $addressFields = View::where('parent_id', $this->partnerAddress->id)->orderBy('order')->get();
    $this->addressField = $addressFields[0];
    $this->skipAddressField = $addressFields[1];

    $contactFields = View::where('parent_id', $this->contactDetails->id)->orderBy('order')->get();
    $this->firstName = $contactFields[0];
    $this->lastName = $contactFields[1];
    $this->email = $contactFields[2];
    $this->phone = $contactFields[3];
    $this->street = $contactFields[4];
    $this->postcode = $contactFields[5];
    $this->city = $contactFields[6];
    $this->differentAddress = $contactFields[8];
    $this->agree = $contactFields[9];

    $addressFields2 = View::where('parent_id', $this->contactDetails2->id)->orderBy('order')->get();
    $this->street2 = $addressFields2[0];
    $this->postcode2 = $addressFields2[1];
    $this->city2 = $addressFields2[2];
});

/**
 * @return array<int, PendingAnswer>
 */
function wienerbergerFullAnswers($test, array $overrides = []): array
{
    $defaults = [
        $test->page->id => true,
        $test->roofAreaField->id => 100,
        $test->roofShapeField->id => 'satteldach',
        $test->roofOrientationField->id => 'Süd',
        $test->roofPitchField->id => '30',
        $test->consumptionChoiceField->id => '3500',
        $test->tariffField->id => '0.30',
        $test->storageField->id => '0',
        $test->evPresenceField->id => '0',
        $test->addressField->id => 'Musterstraße 1',
        $test->firstName->id => 'Ada',
        $test->lastName->id => 'Lovelace',
        $test->email->id => 'ada@example.com',
        $test->phone->id => '0123456789',
        $test->street->id => 'Hauptstraße 1',
        $test->postcode->id => '12345',
        $test->city->id => 'Berlin',
        $test->differentAddress->id => false,
        $test->agree->id => true,
    ];

    $merged = [...$defaults, ...$overrides];

    return array_map(
        fn (string $viewId, bool|int|string|null $value): PendingAnswer => new PendingAnswer($viewId, $value),
        array_keys($merged),
        array_values($merged),
    );
}

it('keeps the whole questionnaire on a single page', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect(scopeIds($evaluation))->toContain($this->page->id, $this->roofAreaField->id);
});

it('hides the roof shape question until the roof area is answered', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration);

    expect(scopeIds($evaluation))
        ->not->toContain($this->roofShapeField->id)
        ->not->toContain($this->liveEstimate->id);
});

it('reveals the roof shape question and the live estimate as soon as a roof area is entered, without persisting', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
    ]);

    expect(scopeIds($evaluation))
        ->toContain($this->roofShapeField->id)
        ->toContain($this->liveEstimate->id)
        ->not->toContain($this->roofOrientationField->id);
    expect(Answer::count())->toBe(0);
});

it('computes the live estimate from the roof area alone', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
    ]);

    expect(round((float) $evaluation->context['calculation']->systemSizeKwp, 2))->toBe(18.0);
    expect(round((float) $evaluation->context['calculation']->annualProductionKwh, 2))->toBe(17100.0);
});

it('updates the live estimate as consumption and tariff are entered', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
        new PendingAnswer($this->consumptionChoiceField->id, '3500'),
        new PendingAnswer($this->tariffField->id, '0.30'),
    ]);

    expect(round((float) $evaluation->context['calculation']->annualConsumptionKwh, 2))->toBe(3500.0);
    expect(round((float) $evaluation->context['calculation']->estimatedAnnualSavingsEur, 2))->toBe(1050.0);
});

it('resets the live estimate to zero instead of erroring when a number field is cleared', function () {
    app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
    ]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, ''),
    ]);

    expect((float) $evaluation->context['calculation']->systemSizeKwp)->toBe(0.0);
});

it('resets the live estimate to zero instead of erroring when a number field holds a partially-typed value', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, '1--'),
    ]);

    expect((float) $evaluation->context['calculation']->systemSizeKwp)->toBe(0.0);
});

it('asks for the exact consumption when the respondent chooses to enter it', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
        new PendingAnswer($this->roofShapeField->id, 'satteldach'),
        new PendingAnswer($this->roofOrientationField->id, 'Süd'),
        new PendingAnswer($this->roofPitchField->id, '30'),
        new PendingAnswer($this->consumptionChoiceField->id, 'custom'),
    ]);

    expect(scopeIds($evaluation))
        ->toContain($this->consumptionExactField->id)
        ->not->toContain($this->tariffField->id);
});

it('skips the exact-consumption question when the respondent picks a preset value', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
        new PendingAnswer($this->roofShapeField->id, 'satteldach'),
        new PendingAnswer($this->roofOrientationField->id, 'Süd'),
        new PendingAnswer($this->roofPitchField->id, '30'),
        new PendingAnswer($this->consumptionChoiceField->id, '3500'),
    ]);

    expect(scopeIds($evaluation))
        ->toContain($this->tariffField->id)
        ->not->toContain($this->consumptionExactField->id);
});

it('skips the electric-car count question when no cars are present or planned', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
        new PendingAnswer($this->roofShapeField->id, 'satteldach'),
        new PendingAnswer($this->roofOrientationField->id, 'Süd'),
        new PendingAnswer($this->roofPitchField->id, '30'),
        new PendingAnswer($this->consumptionChoiceField->id, '3500'),
        new PendingAnswer($this->tariffField->id, '0.30'),
        new PendingAnswer($this->storageField->id, '0'),
        new PendingAnswer($this->evPresenceField->id, '0'),
    ]);

    expect(scopeIds($evaluation))
        ->toContain($this->summary->id, $this->partnerAddress->id, $this->contactDetails->id)
        ->not->toContain($this->evCountField->id);
});

it('asks how many electric cars when cars are present or planned', function () {
    $evaluation = app(FetchConfigurationState::class)($this->configuration, [
        new PendingAnswer($this->roofAreaField->id, 100),
        new PendingAnswer($this->roofShapeField->id, 'satteldach'),
        new PendingAnswer($this->roofOrientationField->id, 'Süd'),
        new PendingAnswer($this->roofPitchField->id, '30'),
        new PendingAnswer($this->consumptionChoiceField->id, '3500'),
        new PendingAnswer($this->tariffField->id, '0.30'),
        new PendingAnswer($this->storageField->id, '0'),
        new PendingAnswer($this->evPresenceField->id, '1'),
    ]);

    expect(scopeIds($evaluation))
        ->toContain($this->evCountField->id)
        ->not->toContain($this->summary->id, $this->contactDetails->id);
});

it('keeps the second address fields hidden unless a different construction site address is requested', function () {
    $answers = wienerbergerFullAnswers($this, [$this->differentAddress->id => false]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration, $answers);

    expect(scopeIds($evaluation))->not->toContain($this->contactDetails2->id);
});

it('reveals the second address fields when a different construction site address is requested', function () {
    $answers = wienerbergerFullAnswers($this, [$this->differentAddress->id => true]);

    $evaluation = app(FetchConfigurationState::class)($this->configuration, $answers);

    expect(scopeIds($evaluation))->toContain($this->contactDetails2->id);
});

it('requires agreeing to the AGBs and privacy policy before the request can be submitted', function () {
    $evaluation = app(CommitConfigurationAnswers::class)(
        $this->configuration,
        wienerbergerFullAnswers($this, [$this->agree->id => false]),
    );

    expect(remainingValidationRules($evaluation, $this->agree->id))->not->toBeEmpty();
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->page->id)
        ->not->toContain($this->success->id);
});

it('reveals the thank-you page once the full request is submitted', function () {
    $evaluation = app(CommitConfigurationAnswers::class)($this->configuration, wienerbergerFullAnswers($this));

    expect(remainingValidationRules($evaluation, $this->agree->id))->toBeEmpty();
    expect(scopeIds(app(FetchConfigurationState::class)($this->configuration)))
        ->toContain($this->success->id)
        ->not->toContain($this->page->id);
});
