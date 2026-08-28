<?php

namespace Database\Seeders;

use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Database\Seeder;

class WienerbergerWevoltSeeder extends Seeder
{
    public function run(): void
    {
        $configurator = Configurator::factory()->create([
            'name' => 'Wienerberger Wevolt Solarkonfigurator',
            'slug' => 'wienerberger-wevolt-solarkonfigurator',
        ]);

        $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
        $page = View::factory()->group()->childOf($root)->create(['order' => 0]);

        $roofAreaField = View::factory()->number('Wie groß ist Ihre Dachfläche?')
            ->updateOn('live')->childOf($page)->create(['order' => 0]);

        $liveEstimate = View::factory()->html(implode('<br>', [
            '<strong>Ihre Live-Schätzung</strong>',
            'Geschätzte Anlagengröße: {{ calculation.systemSizeKwp }} kWp',
            'Geschätzte Jahresproduktion: {{ calculation.annualProductionKwh }} kWh/Jahr',
            'Geschätzter Jahresverbrauch: {{ calculation.annualConsumptionKwh }} kWh/Jahr',
            'Geschätzte jährliche Ersparnis: {{ calculation.estimatedAnnualSavingsEur }} €',
        ]))->childOf($page)->create(['order' => 1]);

        $roofShapeField = View::factory()->radio([
            ['value' => 'pultdach', 'label' => 'Pultdach'],
            ['value' => 'satteldach', 'label' => 'Satteldach'],
            ['value' => 'zeltdach', 'label' => 'Zeltdach'],
            ['value' => 'walmdach', 'label' => 'Walmdach'],
            ['value' => 'krueppelwalmdach', 'label' => 'Krüppelwalmdach'],
            ['value' => 'mansardendach', 'label' => 'Mansardendach'],
            ['value' => 'individuell', 'label' => 'Individuelle Dachform'],
        ], 'Welche Form hat Ihr Dach?')->updateOn('live')->childOf($page)->create(['order' => 2]);

        $roofOrientationField = View::factory()->textInput('In welche Himmelsrichtung ist das Dach ausgerichtet?')
            ->childOf($page)->create(['order' => 3]);

        $roofPitchField = View::factory()->radio([
            ['value' => '20', 'label' => 'Flach (~20°)'],
            ['value' => '30', 'label' => 'Normal (~30°)'],
            ['value' => '45', 'label' => 'Steil (~45°)'],
            ['value' => '75', 'label' => 'Sehr Steil (~75°)'],
        ], 'Wie steil ist Ihr Dach?')->updateOn('live')->childOf($page)->create(['order' => 4]);

        $consumptionChoiceField = View::factory()->radio([
            ['value' => 'custom', 'label' => 'Ich kenne den genauen Verbrauch'],
            ['value' => '2000', 'label' => '2000 kWh'],
            ['value' => '3500', 'label' => '3500 kWh'],
            ['value' => '5000', 'label' => '5000 kWh'],
            ['value' => '7000', 'label' => '7000 kWh'],
        ], 'Wie hoch ist Ihr Jahresstromverbrauch?')->updateOn('live')->childOf($page)->create(['order' => 5]);

        $consumptionExactField = View::factory()->number('Wie hoch ist Ihr genauer Jahresstromverbrauch? (kWh)')
            ->updateOn('live')->childOf($page)->create(['order' => 6]);

        $tariffField = View::factory()->number('Wie hoch ist Ihr aktueller Stromtarif? (€/kWh)')
            ->updateOn('live')->childOf($page)->create(['order' => 7]);

        $storageField = View::factory()->radio([
            ['value' => '1', 'label' => 'Ja'],
            ['value' => '0', 'label' => 'Nein'],
        ], 'Wollen Sie einen Stromspeicher verwenden?')->updateOn('live')->childOf($page)->create(['order' => 8]);

        $evPresenceField = View::factory()->radio([
            ['value' => '1', 'label' => 'Ja'],
            ['value' => '0', 'label' => 'Nein'],
        ], 'Sind Elektro-Autos vorhanden oder geplant?')->updateOn('live')->childOf($page)->create(['order' => 9]);

        $evCountField = View::factory()->select([
            ['value' => '1', 'label' => '1 Auto'],
            ['value' => '2', 'label' => '2 Autos'],
            ['value' => '3', 'label' => '3 Autos'],
            ['value' => '4', 'label' => '4 Autos'],
            ['value' => '5', 'label' => '5 Autos'],
        ], 'Wie viele Elektro-Autos gehören zu Ihrem Haushalt?')->updateOn('live')->childOf($page)->create(['order' => 10]);

        $summary = View::factory()->group()->childOf($page)->create(['order' => 11]);
        View::factory()->html(implode('<br>', [
            "Dachfläche: {{ answer('{$roofAreaField->id}') }} m²",
            "Dachform: {{ answer('{$roofShapeField->id}') }}",
            "Ausrichtung: {{ answer('{$roofOrientationField->id}') }}",
            "Dachneigung: {{ answer('{$roofPitchField->id}') }}°",
            "Jahresstromverbrauch (Auswahl): {{ answer('{$consumptionChoiceField->id}') }}",
            "Jahresstromverbrauch (genau, falls angegeben): {{ answer('{$consumptionExactField->id}') }} kWh",
            "Stromtarif: {{ answer('{$tariffField->id}') }} €/kWh",
            "Stromspeicher gewünscht: {{ answer('{$storageField->id}') }}",
            "Elektro-Autos vorhanden: {{ answer('{$evPresenceField->id}') }}",
            "Anzahl Elektro-Autos: {{ answer('{$evCountField->id}') }}",
        ]))->childOf($summary)->create(['order' => 0]);

        $partnerAddress = View::factory()->group()->childOf($page)->create(['order' => 12]);
        $addressField = View::factory()->textInput('Adresse')->childOf($partnerAddress)->create(['order' => 0]);
        $skipAddressField = View::factory()->checkbox("I don't want to enter my address")
            ->updateOn('live')->childOf($partnerAddress)->create(['order' => 1]);

        $contactDetails = View::factory()->group()->childOf($page)->create(['order' => 13]);
        $firstName = View::factory()->textInput('First name*')->childOf($contactDetails)->create(['order' => 0]);
        $lastName = View::factory()->textInput('Last name*')->childOf($contactDetails)->create(['order' => 1]);
        $email = View::factory()->email('Email*')->childOf($contactDetails)->create(['order' => 2]);
        $phone = View::factory()->textInput('Phone*')->childOf($contactDetails)->create(['order' => 3]);
        $street = View::factory()->textInput('Street/Number*')->childOf($contactDetails)->create(['order' => 4]);
        $postcode = View::factory()->textInput('Postcode*')->childOf($contactDetails)->create(['order' => 5]);
        $city = View::factory()->textInput('City*')->childOf($contactDetails)->create(['order' => 6]);
        View::factory()->textInput('Note')->childOf($contactDetails)->create(['order' => 7]);
        $differentAddress = View::factory()->checkbox('Different construction site address')
            ->updateOn('live')->childOf($contactDetails)->create(['order' => 8]);
        $agree = View::factory()->checkbox('I agree with the AGBs and privacy policy*')
            ->updateOn('live')->childOf($contactDetails)->create(['order' => 9]);
        View::factory()->html("When you press 'Next' below you submit the request and the configuration is locked.")
            ->childOf($contactDetails)->create(['order' => 10]);

        $contactDetails2 = View::factory()->group()->childOf($page)->create(['order' => 14]);
        $street2 = View::factory()->textInput('Street/Number*')->childOf($contactDetails2)->create(['order' => 0]);
        $postcode2 = View::factory()->textInput('Postcode*')->childOf($contactDetails2)->create(['order' => 1]);
        $city2 = View::factory()->textInput('City*')->childOf($contactDetails2)->create(['order' => 2]);

        $success = View::factory()->group()->childOf($root)->create(['order' => 1]);
        View::factory()->html('Thank you! Your configuration has been submitted.')
            ->childOf($success)->create(['order' => 0]);

        $configurator->calculations()->createMany([
            ['name' => 'systemSizeKwp', 'expression' => "(answer('{$roofAreaField->id}') ?? 0) * 0.18"],
            ['name' => 'annualProductionKwh', 'expression' => 'calculation.systemSizeKwp * 950'],
            ['name' => 'annualConsumptionKwh', 'expression' => "(answer('{$consumptionChoiceField->id}') == 'custom' ? answer('{$consumptionExactField->id}') : answer('{$consumptionChoiceField->id}')) ?? 0"],
            ['name' => 'estimatedAnnualSavingsEur', 'expression' => "(calculation.annualProductionKwh < calculation.annualConsumptionKwh ? calculation.annualProductionKwh : calculation.annualConsumptionKwh) * (answer('{$tariffField->id}') ?? 0)"],
        ]);

        Rule::factory()->validation('answer(this) != null && answer(this) > 0')
            ->message('Bitte geben Sie die Dachfläche in Quadratmetern an.')->create(['view_id' => $roofAreaField->id]);
        Rule::factory()->validation('answer(this) != null')
            ->message('Bitte wählen Sie eine Dachform aus.')->create(['view_id' => $roofShapeField->id]);
        Rule::factory()->validation('answer(this) != null && strlen(answer(this)) > 0')
            ->message('Bitte geben Sie die Ausrichtung des Daches an.')->create(['view_id' => $roofOrientationField->id]);
        Rule::factory()->validation('answer(this) != null')
            ->message('Bitte wählen Sie die Dachneigung aus.')->create(['view_id' => $roofPitchField->id]);
        Rule::factory()->validation('answer(this) != null')
            ->message('Bitte wählen Sie Ihren Jahresstromverbrauch aus.')->create(['view_id' => $consumptionChoiceField->id]);
        Rule::factory()->validation('answer(this) != null && answer(this) > 0')
            ->message('Bitte geben Sie Ihren genauen Jahresstromverbrauch an.')->create(['view_id' => $consumptionExactField->id]);
        Rule::factory()->validation('answer(this) != null && answer(this) > 0')
            ->message('Bitte geben Sie Ihren aktuellen Strompreis an.')->create(['view_id' => $tariffField->id]);
        Rule::factory()->validation('answer(this) != null')
            ->message('Bitte wählen Sie aus, ob Sie einen Stromspeicher nutzen möchten.')->create(['view_id' => $storageField->id]);
        Rule::factory()->validation('answer(this) != null')
            ->message('Bitte geben Sie an, ob Elektro-Autos vorhanden oder geplant sind.')->create(['view_id' => $evPresenceField->id]);
        Rule::factory()->validation('answer(this) != null')
            ->message('Bitte wählen Sie die Anzahl der Elektro-Autos aus.')->create(['view_id' => $evCountField->id]);

        Rule::factory()->validation(
            "(answer('{$addressField->id}') != null && strlen(answer('{$addressField->id}')) > 0) || answer('{$skipAddressField->id}') == true"
        )->message('Please enter your address, or check the box above.')->create(['view_id' => $partnerAddress->id]);

        foreach ([
            $firstName->id => 'First name is required.',
            $lastName->id => 'Last name is required.',
            $email->id => 'Email is required.',
            $phone->id => 'Phone is required.',
            $street->id => 'Street/Number is required.',
            $postcode->id => 'Postcode is required.',
            $city->id => 'City is required.',
            $street2->id => 'Street/Number is required.',
            $postcode2->id => 'Postcode is required.',
            $city2->id => 'City is required.',
        ] as $viewId => $message) {
            Rule::factory()->validation('answer(this) != null && strlen(answer(this)) > 0')
                ->message($message)->create(['view_id' => $viewId]);
        }

        Rule::factory()->validation('answer(this) == true')
            ->message('Please agree to the AGBs and privacy policy.')->create(['view_id' => $agree->id]);

        Rule::factory()->visibility("answer('{$roofAreaField->id}') != null && answer('{$roofAreaField->id}') > 0")
            ->create(['view_id' => $liveEstimate->id]);
        Rule::factory()->visibility("answer('{$roofAreaField->id}') != null && answer('{$roofAreaField->id}') > 0")
            ->create(['view_id' => $roofShapeField->id]);
        Rule::factory()->visibility("answer('{$roofShapeField->id}') != null")
            ->create(['view_id' => $roofOrientationField->id]);
        Rule::factory()->visibility("answer('{$roofOrientationField->id}') != null && strlen(answer('{$roofOrientationField->id}')) > 0")
            ->create(['view_id' => $roofPitchField->id]);
        Rule::factory()->visibility("answer('{$roofPitchField->id}') != null")
            ->create(['view_id' => $consumptionChoiceField->id]);
        Rule::factory()->visibility("answer('{$consumptionChoiceField->id}') == 'custom'")
            ->create(['view_id' => $consumptionExactField->id]);

        Rule::factory()->visibility(
            "(answer('{$consumptionExactField->id}') != null && answer('{$consumptionExactField->id}') > 0)".
            " || (answer('{$consumptionChoiceField->id}') != null && answer('{$consumptionChoiceField->id}') != 'custom')"
        )->create(['view_id' => $tariffField->id]);

        Rule::factory()->visibility("answer('{$tariffField->id}') != null && answer('{$tariffField->id}') > 0")
            ->create(['view_id' => $storageField->id]);
        Rule::factory()->visibility("answer('{$storageField->id}') != null")
            ->create(['view_id' => $evPresenceField->id]);
        Rule::factory()->visibility("answer('{$evPresenceField->id}') == '1'")
            ->create(['view_id' => $evCountField->id]);

        $readyForContact = "(answer('{$evCountField->id}') != null) || (answer('{$evPresenceField->id}') == '0')";

        Rule::factory()->visibility($readyForContact)->create(['view_id' => $summary->id]);
        Rule::factory()->visibility($readyForContact)->create(['view_id' => $partnerAddress->id]);
        Rule::factory()->visibility($readyForContact)->create(['view_id' => $contactDetails->id]);
        Rule::factory()->visibility("({$readyForContact}) && answer('{$differentAddress->id}') == true")
            ->create(['view_id' => $contactDetails2->id]);

        Rule::factory()->flow("answer('{$page->id}') == true", $success->id)->create(['view_id' => $success->id]);

        $configurator->update(['scope_depth' => 1]);

        $this->command?->info('   Configurator: '.route('configurators.play', ['configurator' => $configurator]));
    }
}
