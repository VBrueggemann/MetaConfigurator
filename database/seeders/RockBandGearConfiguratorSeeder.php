<?php

namespace Database\Seeders;

use App\Models\Component;
use App\Models\Configurator;
use App\Models\Rule;
use App\Models\View;
use Illuminate\Database\Seeder;

class RockBandGearConfiguratorSeeder extends Seeder
{
    public function __construct(private readonly int $depth = 3) {}

    /** @var array<string, string> */
    private array $formulas = [];

    public function run(): void
    {
        $this->formulas = [];

        $configurator = Configurator::factory()->create([
            'name' => 'Rock Band Gear Configurator',
            'slug' => 'rock-band-gear-configurator',
        ]);

        $root = View::factory()->group()->create(['configurator_id' => $configurator->id]);
        $shell = View::factory()->group()->childOf($root)->create(['order' => 0]);
        $allQuestions = View::factory()->group()->childOf($shell)->create(['order' => 0]);

        $stepOrder = 0;
        $steps = [];

        [$intro, $needGuitar, $needBass, $needVocals, $needDrums] = $this->buildIntroStep($allQuestions, $stepOrder++);
        $steps[] = ['view' => $intro, 'applicable' => null];

        $needGuitarExpr = "answer('{$needGuitar->id}') == true";
        $needBassExpr = "answer('{$needBass->id}') == true";
        $needVocalsExpr = "answer('{$needVocals->id}') == true";
        $needDrumsExpr = "answer('{$needDrums->id}') == true";

        $guitarFields = $this->buildGuitarSteps($allQuestions, $stepOrder, $steps, $needGuitarExpr);
        $bassFields = $this->buildBassSteps($allQuestions, $stepOrder, $steps, $needBassExpr, $needGuitarExpr, $guitarFields['cable']);
        $vocalsFields = $this->buildVocalsSteps($allQuestions, $stepOrder, $steps, $needVocalsExpr);
        $drumsFields = $this->buildDrumsSteps($allQuestions, $stepOrder, $steps, $needDrumsExpr);

        $this->buildSharedAccessoriesStep($allQuestions, $stepOrder, $steps, $needGuitar, $needBass, $needVocals, $needDrums, $needGuitarExpr, $needBassExpr, $needVocalsExpr);

        $this->formulas['guitarTotal'] = implode(' + ', array_map(
            fn (string $name) => "calculation.{$name}",
            ['guitarBrandPrice', 'guitarBodyElectricPrice', 'guitarBodyAcousticPrice', 'guitarStringsElectricPrice', 'guitarStringsAcousticPrice', 'guitarStringTypePrice', 'guitarFinishPrice', 'guitarLeftHandedPrice', 'guitarCasePrice', 'guitarAmpPrice', 'guitarPickupPrice', 'guitarPedalsPrice', 'guitarCablePrice', 'guitarStrapPrice', 'guitarCapoPrice'],
        ));

        $this->formulas['bassTotal'] = implode(' + ', array_map(
            fn (string $name) => "calculation.{$name}",
            ['bassBrandPrice', 'bassBodyPrice', 'bassStringsPrice', 'bassStringTypePrice', 'bassLeftHandedPrice', 'bassCasePrice', 'bassAmpPrice', 'bassCablePrice', 'bassStrapPrice'],
        ));

        $this->formulas['vocalsTotal'] = implode(' + ', array_map(
            fn (string $name) => "calculation.{$name}",
            ['vocalsMicPrice', 'vocalsStandPrice', 'vocalsPaPrice', 'vocalsXlrPrice'],
        ));

        $this->formulas['drumsTotal'] = implode(' + ', array_map(
            fn (string $name) => "calculation.{$name}",
            ['drumsKitPrice', 'drumsBrandPrice', 'drumsCymbalPrice', 'drumsHardwarePrice', 'drumsSticksPrice', 'drumsThronePrice', 'drumsPracticePadPrice', 'drumsHeadphonesPrice'],
        ));

        $this->formulas['sharedTotal'] = implode(' + ', array_map(
            fn (string $name) => "calculation.{$name}",
            ['sharedMonitorPrice', 'sharedCableSnakePrice', 'sharedTunerPrice', 'sharedPowerStripPrice'],
        ));

        $this->formulas['grandTotal'] = 'calculation.guitarTotal + calculation.bassTotal + calculation.vocalsTotal + calculation.drumsTotal + calculation.sharedTotal';

        $this->buildCartStep($allQuestions, $stepOrder, $steps, $needGuitarExpr, $needBassExpr, $needVocalsExpr, $needDrumsExpr, $guitarFields, $bassFields, $vocalsFields, $drumsFields);

        $this->wireLinearFlow($steps);

        Component::factory()->calculation($this->formulas)->create(['configurator_id' => $configurator->id]);
        Component::factory()->scope(depth: $this->depth)->create(['configurator_id' => $configurator->id]);

        $this->command?->info('   Configurator: '.route('configurators.play', ['configurator' => $configurator]));
    }

    /**
     * @return array{0: View, 1: View, 2: View, 3: View, 4: View}
     */
    private function buildIntroStep(View $allQuestions, int $order): array
    {
        $intro = View::factory()->group('Build Your Bands Rig')->childOf($allQuestions)->create(['order' => $order]);

        $o = 0;
        View::factory()->html(
            '<p>Answer a few questions per instrument and watch the price update live. '.
            'Only pick what your band actually needs — every step can be skipped entirely.</p>'
        )->childOf($intro)->create(['order' => $o++]);

        $picker = View::factory()->group('Which instruments do you need gear for?', 'horizontal')
            ->childOf($intro)->create(['order' => $o++]);

        $needGuitar = View::factory()->cardToggle('Electric Guitar', '🎸', 'Six or more strings, plugged in')
            ->updateOn('live')->childOf($picker)->create(['order' => 0]);
        $needBass = View::factory()->cardToggle('Bass Guitar', '🎸', 'Low end for the rhythm section')
            ->updateOn('live')->childOf($picker)->create(['order' => 1]);
        $needVocals = View::factory()->cardToggle('Vocals / PA', '🎤', 'Microphones and PA gear')
            ->updateOn('live')->childOf($picker)->create(['order' => 2]);
        $needDrums = View::factory()->cardToggle('Drums', '🥁', 'Acoustic, electronic or a cajon')
            ->updateOn('live')->childOf($picker)->create(['order' => 3]);

        Rule::factory()->validation(
            "answer('{$needGuitar->id}') == true || answer('{$needBass->id}') == true || answer('{$needVocals->id}') == true || answer('{$needDrums->id}') == true"
        )->create(['view_id' => $picker->id, 'meta' => ['message' => 'Pick at least one instrument to start building your rig.']]);

        $this->addRunningTotal($intro, $o);

        return [$intro, $needGuitar, $needBass, $needVocals, $needDrums];
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     * @return array<string, View>
     */
    private function buildGuitarSteps(View $allQuestions, int &$stepOrder, array &$steps, string $needGuitarExpr): array
    {
        $step1 = View::factory()->group('Guitar — Brand & Type')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step1, 'applicable' => $needGuitarExpr];
        $o = 0;

        $type = View::factory()->radio([
            ['value' => 'Electric', 'label' => 'Electric'],
            ['value' => 'Acoustic', 'label' => 'Acoustic'],
        ], 'Electric or acoustic?')->updateOn('live')->childOf($step1)->create(['order' => $o++]);
        Rule::factory()->validation('answer(this) != null')
            ->create(['view_id' => $type->id, 'meta' => ['message' => 'Please choose electric or acoustic.']]);

        $brand = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Squier', 'label' => 'Squier', 'price' => 189, 'icon' => '🎸', 'subtitle' => 'Budget friendly'],
            ['value' => 'Yamaha', 'label' => 'Yamaha', 'price' => 229, 'icon' => '🎸', 'subtitle' => 'Reliable all-rounder'],
            ['value' => 'Ibanez', 'label' => 'Ibanez', 'price' => 399, 'icon' => '🎸', 'subtitle' => 'Fast necks, metal-ready'],
            ['value' => 'Fender', 'label' => 'Fender', 'price' => 699, 'icon' => '🎸', 'subtitle' => 'The classic choice'],
            ['value' => 'PRS', 'label' => 'PRS', 'price' => 999, 'icon' => '🎸', 'subtitle' => 'Premium build quality'],
            ['value' => 'Gibson', 'label' => 'Gibson', 'price' => 1899, 'icon' => '🎸', 'subtitle' => 'Iconic American craft'],
        ], 'Choose your guitar brand', 'guitarBrandPrice');
        Rule::factory()->validation('answer(this) != null')
            ->create(['view_id' => $brand->id, 'meta' => ['message' => 'Please choose a guitar brand.']]);

        $this->addRunningTotal($step1, $o);

        $step2 = View::factory()->group('Guitar — Body & Specs')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step2, 'applicable' => $needGuitarExpr];
        $o = 0;

        $electricExpr = "answer('{$type->id}') == 'Electric'";
        $acousticExpr = "answer('{$type->id}') == 'Acoustic'";

        $bodyElectric = $this->pricedChoice($step2, $o, 'cardRadio', [
            ['value' => 'Stratocaster Style', 'label' => 'Stratocaster Style', 'price' => 0],
            ['value' => 'Telecaster Style', 'label' => 'Telecaster Style', 'price' => 0],
            ['value' => 'Superstrat', 'label' => 'Superstrat', 'price' => 100],
            ['value' => 'SG Style', 'label' => 'SG Style', 'price' => 50],
            ['value' => 'Les Paul Style', 'label' => 'Les Paul Style', 'price' => 150],
            ['value' => 'Semi-Hollow', 'label' => 'Semi-Hollow', 'price' => 250],
        ], 'Body shape', 'guitarBodyElectricPrice');
        Rule::factory()->visibility($electricExpr)->create(['view_id' => $bodyElectric->id]);

        $bodyAcoustic = $this->pricedChoice($step2, $o, 'cardRadio', [
            ['value' => 'Dreadnought', 'label' => 'Dreadnought', 'price' => 0],
            ['value' => 'Concert', 'label' => 'Concert', 'price' => 50],
            ['value' => 'Parlor', 'label' => 'Parlor', 'price' => 40],
            ['value' => 'Jumbo', 'label' => 'Jumbo', 'price' => 80],
        ], 'Body shape', 'guitarBodyAcousticPrice');
        Rule::factory()->visibility($acousticExpr)->create(['view_id' => $bodyAcoustic->id]);

        $stringsElectric = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => '6-String', 'label' => '6-String', 'price' => 0],
            ['value' => '7-String', 'label' => '7-String', 'price' => 120],
            ['value' => '8-String', 'label' => '8-String', 'price' => 220],
        ], 'String count', 'guitarStringsElectricPrice');
        Rule::factory()->visibility($electricExpr)->create(['view_id' => $stringsElectric->id]);

        $stringsAcoustic = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => '6-String', 'label' => '6-String', 'price' => 0],
            ['value' => '12-String', 'label' => '12-String', 'price' => 90],
        ], 'String count', 'guitarStringsAcousticPrice');
        Rule::factory()->visibility($acousticExpr)->create(['view_id' => $stringsAcoustic->id]);

        $stringType = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => 'Nickel Wound', 'label' => 'Nickel Wound', 'price' => 0],
            ['value' => 'Coated', 'label' => 'Coated', 'price' => 15],
            ['value' => 'Flatwound', 'label' => 'Flatwound', 'price' => 25],
        ], 'String type', 'guitarStringTypePrice');

        $finish = $this->pricedChoice($step2, $o, 'select', [
            ['value' => 'Sunburst', 'label' => 'Sunburst', 'price' => 0],
            ['value' => 'Black', 'label' => 'Black', 'price' => 0],
            ['value' => 'Olympic White', 'label' => 'Olympic White', 'price' => 20],
            ['value' => 'Custom Color', 'label' => 'Custom Color', 'price' => 60],
        ], 'Finish', 'guitarFinishPrice');

        $leftHanded = $this->pricedCheckbox($step2, $o, 'Left-handed model (+30 €)', 30, 'guitarLeftHandedPrice');

        $case = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => 'No Case', 'label' => 'No Case', 'price' => 0],
            ['value' => 'Gig Bag', 'label' => 'Gig Bag', 'price' => 39],
            ['value' => 'Hardshell Case', 'label' => 'Hardshell Case', 'price' => 99],
        ], 'Case', 'guitarCasePrice');

        $this->addRunningTotal($step2, $o);

        $step3 = View::factory()->group('Guitar — Amp, Pedals & Cables')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step3, 'applicable' => $needGuitarExpr];
        $o = 0;

        $amp = $this->pricedChoice($step3, $o, 'cardRadio', [
            ['value' => 'Skip The Amp', 'label' => 'Skip The Amp', 'price' => 0, 'icon' => '🚫'],
            ['value' => 'Practice Amp 10W', 'label' => 'Practice Amp 10W', 'price' => 79, 'icon' => '🔊'],
            ['value' => 'Combo Amp 40W', 'label' => 'Combo Amp 40W', 'price' => 249, 'icon' => '🔊'],
            ['value' => 'Tube Combo 40W', 'label' => 'Tube Combo 40W', 'price' => 599, 'icon' => '🔊'],
            ['value' => 'Full Stack Rig', 'label' => 'Full Stack Rig', 'price' => 1299, 'icon' => '🔊'],
        ], 'Amplifier', 'guitarAmpPrice');
        Rule::factory()->visibility($electricExpr)->create(['view_id' => $amp->id]);

        $pickup = $this->pricedChoice($step3, $o, 'radio', [
            ['value' => 'No Pickup', 'label' => 'No Pickup', 'price' => 0],
            ['value' => 'Soundhole Pickup', 'label' => 'Soundhole Pickup', 'price' => 69],
            ['value' => 'Under-Saddle Pickup', 'label' => 'Under-Saddle Pickup', 'price' => 89],
        ], 'Acoustic pickup / pre-amp', 'guitarPickupPrice');
        Rule::factory()->visibility($acousticExpr)->create(['view_id' => $pickup->id]);

        [$pedalsWrapper, $pedalsFormula] = $this->checkboxBundle($step3, $o, 'Pedals', [
            ['label' => 'Overdrive', 'price' => 59],
            ['label' => 'Delay', 'price' => 89],
            ['label' => 'Reverb', 'price' => 69],
            ['label' => 'Tuner Pedal', 'price' => 35],
            ['label' => 'Wah', 'price' => 79],
        ]);
        $this->formulas['guitarPedalsPrice'] = $pedalsFormula;
        Rule::factory()->visibility($electricExpr)->create(['view_id' => $pedalsWrapper->id]);

        $cableOptions = [
            ['value' => '3m Standard Cable', 'label' => '3m Standard Cable', 'price' => 12, 'icon' => '🔌'],
            ['value' => '6m Standard Cable', 'label' => '6m Standard Cable', 'price' => 18, 'icon' => '🔌'],
            ['value' => '3m Braided Pro Cable', 'label' => '3m Braided Pro Cable', 'price' => 29, 'icon' => '🔌'],
            ['value' => 'Wireless Instrument System', 'label' => 'Wireless Instrument System', 'price' => 189, 'icon' => '📡'],
        ];
        $cable = $this->pricedChoice($step3, $o, 'cardRadio', $cableOptions, 'Instrument cable', 'guitarCablePrice');

        $strapOptions = [
            ['value' => 'Basic Nylon Strap', 'label' => 'Basic Nylon Strap', 'price' => 9],
            ['value' => 'Padded Strap', 'label' => 'Padded Strap', 'price' => 19],
            ['value' => 'Leather Strap', 'label' => 'Leather Strap', 'price' => 35],
        ];
        $strap = $this->pricedChoice($step3, $o, 'radio', $strapOptions, 'Strap', 'guitarStrapPrice');

        $capo = $this->pricedCheckbox($step3, $o, 'Capo (+15 €)', 15, 'guitarCapoPrice');

        $this->addRunningTotal($step3, $o);

        return [
            'type' => $type, 'brand' => $brand, 'bodyElectric' => $bodyElectric, 'bodyAcoustic' => $bodyAcoustic,
            'stringsElectric' => $stringsElectric, 'stringsAcoustic' => $stringsAcoustic, 'stringType' => $stringType,
            'finish' => $finish, 'leftHanded' => $leftHanded, 'case' => $case, 'amp' => $amp, 'pickup' => $pickup,
            'cable' => $cable, 'strap' => $strap, 'capo' => $capo, 'cableOptions' => $cableOptions,
        ];
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     * @return array<string, View>
     */
    private function buildBassSteps(View $allQuestions, int &$stepOrder, array &$steps, string $needBassExpr, string $needGuitarExpr, View $guitarCable): array
    {
        $step1 = View::factory()->group('Bass — Brand & Body')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step1, 'applicable' => $needBassExpr];
        $o = 0;

        $brand = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Squier', 'label' => 'Squier', 'price' => 199, 'icon' => '🎸', 'subtitle' => 'Budget friendly'],
            ['value' => 'Yamaha', 'label' => 'Yamaha', 'price' => 259, 'icon' => '🎸', 'subtitle' => 'Reliable all-rounder'],
            ['value' => 'Ibanez', 'label' => 'Ibanez', 'price' => 349, 'icon' => '🎸', 'subtitle' => 'Fast necks'],
            ['value' => 'Fender', 'label' => 'Fender', 'price' => 799, 'icon' => '🎸', 'subtitle' => 'The classic choice'],
            ['value' => 'Warwick', 'label' => 'Warwick', 'price' => 1199, 'icon' => '🎸', 'subtitle' => 'German craftsmanship'],
            ['value' => 'Music Man', 'label' => 'Music Man', 'price' => 1499, 'icon' => '🎸', 'subtitle' => 'Studio favorite'],
        ], 'Choose your bass brand', 'bassBrandPrice');
        Rule::factory()->validation('answer(this) != null')
            ->create(['view_id' => $brand->id, 'meta' => ['message' => 'Please choose a bass brand.']]);

        $body = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Precision Style', 'label' => 'Precision Style', 'price' => 0],
            ['value' => 'Jazz Style', 'label' => 'Jazz Style', 'price' => 50],
            ['value' => 'Superstrat Style', 'label' => 'Superstrat Style', 'price' => 80],
        ], 'Body shape', 'bassBodyPrice');

        $this->addRunningTotal($step1, $o);

        $step2 = View::factory()->group('Bass — Strings & Case')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step2, 'applicable' => $needBassExpr];
        $o = 0;

        $strings = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => '4-String', 'label' => '4-String', 'price' => 0],
            ['value' => '5-String', 'label' => '5-String', 'price' => 150],
            ['value' => '6-String', 'label' => '6-String', 'price' => 280],
        ], 'String count', 'bassStringsPrice');

        $stringType = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => 'Roundwound', 'label' => 'Roundwound', 'price' => 0],
            ['value' => 'Flatwound', 'label' => 'Flatwound', 'price' => 30],
        ], 'String type', 'bassStringTypePrice');

        $leftHanded = $this->pricedCheckbox($step2, $o, 'Left-handed model (+30 €)', 30, 'bassLeftHandedPrice');

        $case = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => 'No Case', 'label' => 'No Case', 'price' => 0],
            ['value' => 'Gig Bag', 'label' => 'Gig Bag', 'price' => 39],
            ['value' => 'Hardshell Case', 'label' => 'Hardshell Case', 'price' => 99],
        ], 'Case', 'bassCasePrice');

        $this->addRunningTotal($step2, $o);

        $step3 = View::factory()->group('Bass — Amp & Cable')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step3, 'applicable' => $needBassExpr];
        $o = 0;

        $amp = $this->pricedChoice($step3, $o, 'cardRadio', [
            ['value' => 'Skip The Amp', 'label' => 'Skip The Amp', 'price' => 0, 'icon' => '🚫'],
            ['value' => 'Practice Combo 20W', 'label' => 'Practice Combo 20W', 'price' => 99, 'icon' => '🔊'],
            ['value' => 'Combo 100W', 'label' => 'Combo 100W', 'price' => 349, 'icon' => '🔊'],
            ['value' => 'Head And Cab Stack', 'label' => 'Head And Cab Stack', 'price' => 899, 'icon' => '🔊'],
        ], 'Amplifier', 'bassAmpPrice');

        $reuseNote = View::factory()->html(
            "<p>You already picked a <strong>{{ answer('{$guitarCable->id}') }}</strong> for guitar — want a second one for bass instead of choosing separately?</p>"
        )->childOf($step3)->create(['order' => $o++]);
        Rule::factory()->visibility("({$needGuitarExpr}) && answer('{$guitarCable->id}') != null")->create(['view_id' => $reuseNote->id]);

        $reuseCable = View::factory()->checkbox('Reuse the same cable I chose for guitar')
            ->updateOn('live')->childOf($step3)->create(['order' => $o++]);
        Rule::factory()->visibility($needGuitarExpr)->create(['view_id' => $reuseCable->id]);

        $ownCableOptions = [
            ['value' => '3m Standard Cable', 'label' => '3m Standard Cable', 'price' => 12, 'icon' => '🔌'],
            ['value' => '6m Standard Cable', 'label' => '6m Standard Cable', 'price' => 18, 'icon' => '🔌'],
            ['value' => '3m Braided Pro Cable', 'label' => '3m Braided Pro Cable', 'price' => 29, 'icon' => '🔌'],
            ['value' => 'Wireless Instrument System', 'label' => 'Wireless Instrument System', 'price' => 189, 'icon' => '📡'],
        ];
        $ownCable = $this->pricedChoice($step3, $o, 'cardRadio', $ownCableOptions, 'Instrument cable', 'bassOwnCablePrice');
        Rule::factory()->visibility("answer('{$reuseCable->id}') != true")->create(['view_id' => $ownCable->id]);

        $this->formulas['bassCablePrice'] = "answer('{$reuseCable->id}') == true ? calculation.guitarCablePrice : calculation.bassOwnCablePrice";

        $strap = $this->pricedChoice($step3, $o, 'radio', [
            ['value' => 'Basic Nylon Strap', 'label' => 'Basic Nylon Strap', 'price' => 9],
            ['value' => 'Padded Strap', 'label' => 'Padded Strap', 'price' => 19],
            ['value' => 'Leather Strap', 'label' => 'Leather Strap', 'price' => 35],
        ], 'Strap', 'bassStrapPrice');

        $this->addRunningTotal($step3, $o);

        return [
            'brand' => $brand, 'body' => $body, 'strings' => $strings, 'stringType' => $stringType,
            'leftHanded' => $leftHanded, 'case' => $case, 'amp' => $amp, 'reuseCable' => $reuseCable,
            'ownCable' => $ownCable, 'strap' => $strap,
        ];
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     * @return array<string, View>
     */
    private function buildVocalsSteps(View $allQuestions, int &$stepOrder, array &$steps, string $needVocalsExpr): array
    {
        $step1 = View::factory()->group('Vocals — Mic & PA')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step1, 'applicable' => $needVocalsExpr];
        $o = 0;

        $mic = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Dynamic Stage Mic', 'label' => 'Dynamic Stage Mic', 'price' => 109, 'icon' => '🎤'],
            ['value' => 'Headset Mic', 'label' => 'Headset Mic', 'price' => 149, 'icon' => '🎤'],
            ['value' => 'Condenser Studio Mic', 'label' => 'Condenser Studio Mic', 'price' => 199, 'icon' => '🎤'],
            ['value' => 'Wireless Handheld System', 'label' => 'Wireless Handheld System', 'price' => 349, 'icon' => '📡'],
        ], 'Microphone', 'vocalsMicPrice');
        Rule::factory()->validation('answer(this) != null')
            ->create(['view_id' => $mic->id, 'meta' => ['message' => 'Please choose a microphone.']]);

        $stand = $this->pricedChoice($step1, $o, 'radio', [
            ['value' => 'Tabletop Stand', 'label' => 'Tabletop Stand', 'price' => 19],
            ['value' => 'Straight Stand', 'label' => 'Straight Stand', 'price' => 29],
            ['value' => 'Boom Stand', 'label' => 'Boom Stand', 'price' => 39],
        ], 'Mic stand', 'vocalsStandPrice');

        $needPa = View::factory()->checkbox('I need a PA speaker system')
            ->updateOn('live')->childOf($step1)->create(['order' => $o++]);

        $pa = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Compact PA 500W', 'label' => 'Compact PA 500W', 'price' => 399, 'icon' => '🔊'],
            ['value' => 'In-Ear Monitor System', 'label' => 'In-Ear Monitor System', 'price' => 599, 'icon' => '🎧'],
            ['value' => 'Full PA 1000W', 'label' => 'Full PA 1000W', 'price' => 899, 'icon' => '🔊'],
        ], 'PA system', 'vocalsPaPrice');
        Rule::factory()->visibility("answer('{$needPa->id}') == true")->create(['view_id' => $pa->id]);

        $this->addRunningTotal($step1, $o);

        $step2 = View::factory()->group('Vocals — Cables')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step2, 'applicable' => $needVocalsExpr];
        $o = 0;

        $wirelessExpr = "answer('{$mic->id}') == 'Wireless Handheld System'";

        $xlr = $this->pricedChoice($step2, $o, 'cardRadio', [
            ['value' => '3m XLR Cable', 'label' => '3m XLR Cable', 'price' => 15, 'icon' => '🔌'],
            ['value' => '6m XLR Cable', 'label' => '6m XLR Cable', 'price' => 22, 'icon' => '🔌'],
            ['value' => '10m XLR Cable', 'label' => '10m XLR Cable', 'price' => 29, 'icon' => '🔌'],
        ], 'XLR cable', 'vocalsXlrPrice');
        Rule::factory()->visibility("!({$wirelessExpr})")->create(['view_id' => $xlr->id]);

        $wirelessNote = View::factory()->html('<p>Your wireless system already covers the mic-to-PA connection — no XLR cable needed.</p>')
            ->childOf($step2)->create(['order' => $o++]);
        Rule::factory()->visibility($wirelessExpr)->create(['view_id' => $wirelessNote->id]);

        $this->addRunningTotal($step2, $o);

        return ['mic' => $mic, 'stand' => $stand, 'needPa' => $needPa, 'pa' => $pa, 'xlr' => $xlr];
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     * @return array<string, View>
     */
    private function buildDrumsSteps(View $allQuestions, int &$stepOrder, array &$steps, string $needDrumsExpr): array
    {
        $step1 = View::factory()->group('Drums — Kit & Brand')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step1, 'applicable' => $needDrumsExpr];
        $o = 0;

        $kit = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Cajon Or Percussion', 'label' => 'Cajon Or Percussion', 'price' => 129, 'icon' => '🪘'],
            ['value' => 'Electronic Kit', 'label' => 'Electronic Kit', 'price' => 799, 'icon' => '🥁'],
            ['value' => 'Acoustic 5-Piece Starter', 'label' => 'Acoustic 5-Piece Starter', 'price' => 599, 'icon' => '🥁'],
            ['value' => 'Acoustic 5-Piece Pro', 'label' => 'Acoustic 5-Piece Pro', 'price' => 1499, 'icon' => '🥁'],
        ], 'Kit type', 'drumsKitPrice');
        Rule::factory()->validation('answer(this) != null')
            ->create(['view_id' => $kit->id, 'meta' => ['message' => 'Please choose a kit type.']]);

        $acousticExpr = "answer('{$kit->id}') == 'Acoustic 5-Piece Starter' || answer('{$kit->id}') == 'Acoustic 5-Piece Pro'";

        $brand = $this->pricedChoice($step1, $o, 'cardRadio', [
            ['value' => 'Mapex', 'label' => 'Mapex', 'price' => 0],
            ['value' => 'Tama', 'label' => 'Tama', 'price' => 50],
            ['value' => 'Pearl', 'label' => 'Pearl', 'price' => 80],
            ['value' => 'DW', 'label' => 'DW', 'price' => 400],
        ], 'Brand', 'drumsBrandPrice');
        Rule::factory()->visibility($acousticExpr)->create(['view_id' => $brand->id]);

        $this->addRunningTotal($step1, $o);

        $step2 = View::factory()->group('Drums — Cymbals, Hardware & Heads')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step2, 'applicable' => $needDrumsExpr];
        $o = 0;

        $cymbals = $this->pricedChoice($step2, $o, 'cardRadio', [
            ['value' => 'None Included', 'label' => 'None Included', 'price' => 0],
            ['value' => 'Starter Cymbal Pack', 'label' => 'Starter Cymbal Pack', 'price' => 129],
            ['value' => 'Pro Cymbal Pack', 'label' => 'Pro Cymbal Pack', 'price' => 349],
        ], 'Cymbal pack', 'drumsCymbalPrice');
        Rule::factory()->visibility("answer('{$kit->id}') != 'Cajon Or Percussion'")->create(['view_id' => $cymbals->id]);

        [, $hardwareFormula] = $this->checkboxBundle($step2, $o, 'Hardware', [
            ['label' => 'Hi-Hat Stand', 'price' => 49],
            ['label' => 'Snare Stand', 'price' => 39],
            ['label' => 'Extra Boom Stand', 'price' => 45],
            ['label' => 'Double Bass Pedal', 'price' => 89],
        ]);
        $this->formulas['drumsHardwarePrice'] = $hardwareFormula;

        $sticks = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => 'Wood Tip 5A', 'label' => 'Wood Tip 5A', 'price' => 9],
            ['value' => 'Nylon Tip 5A', 'label' => 'Nylon Tip 5A', 'price' => 9],
            ['value' => 'Rods And Brushes', 'label' => 'Rods And Brushes', 'price' => 19],
        ], 'Sticks', 'drumsSticksPrice');

        $throne = $this->pricedCheckbox($step2, $o, 'Drum throne (+59 €)', 59, 'drumsThronePrice');
        $practicePad = $this->pricedCheckbox($step2, $o, 'Practice pad (+25 €)', 25, 'drumsPracticePadPrice');

        $headphones = $this->pricedChoice($step2, $o, 'radio', [
            ['value' => 'Basic Headphones', 'label' => 'Basic Headphones', 'price' => 19],
            ['value' => 'Studio Headphones', 'label' => 'Studio Headphones', 'price' => 79],
        ], 'Headphones', 'drumsHeadphonesPrice');
        Rule::factory()->visibility("answer('{$kit->id}') == 'Electronic Kit'")->create(['view_id' => $headphones->id]);

        $this->addRunningTotal($step2, $o);

        return [
            'kit' => $kit, 'brand' => $brand, 'cymbals' => $cymbals, 'sticks' => $sticks,
            'throne' => $throne, 'practicePad' => $practicePad, 'headphones' => $headphones,
        ];
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     */
    private function buildSharedAccessoriesStep(
        View $allQuestions,
        int &$stepOrder,
        array &$steps,
        View $needGuitar,
        View $needBass,
        View $needVocals,
        View $needDrums,
        string $needGuitarExpr,
        string $needBassExpr,
        string $needVocalsExpr,
    ): void {
        $step = View::factory()->group('Shared Accessories')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $step, 'applicable' => null];
        $o = 0;

        View::factory()->html('<p>A few extras worth sharing across the whole band.</p>')
            ->childOf($step)->create(['order' => $o++]);

        $this->pricedCheckbox($step, $o, 'Stage / floor monitor (+229 €)', 229, 'sharedMonitorPrice');
        $monitorNote = View::factory()->html('<p>Recommended since you are bringing vocals/PA.</p>')
            ->childOf($step)->create(['order' => $o++]);
        Rule::factory()->visibility($needVocalsExpr)->create(['view_id' => $monitorNote->id]);

        $instrumentCount = "((answer('{$needGuitar->id}') == true ? 1 : 0) + (answer('{$needBass->id}') == true ? 1 : 0) + (answer('{$needVocals->id}') == true ? 1 : 0) + (answer('{$needDrums->id}') == true ? 1 : 0))";
        $cableSnake = $this->pricedCheckbox($step, $o, 'Cable snake / multicore (+89 €)', 89, 'sharedCableSnakePrice');
        Rule::factory()->visibility("{$instrumentCount} >= 2")->create(['view_id' => $cableSnake->id]);

        $tuner = $this->pricedCheckbox($step, $o, 'Clip-on tuner (+15 €)', 15, 'sharedTunerPrice');
        Rule::factory()->visibility("({$needGuitarExpr}) || ({$needBassExpr})")->create(['view_id' => $tuner->id]);

        $this->pricedCheckbox($step, $o, 'Power strip / extension cable (+19 €)', 19, 'sharedPowerStripPrice');

        $this->addRunningTotal($step, $o);
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     * @param  array<string, View>  $guitarFields
     * @param  array<string, View>  $bassFields
     * @param  array<string, View>  $vocalsFields
     * @param  array<string, View>  $drumsFields
     */
    private function buildCartStep(
        View $allQuestions,
        int &$stepOrder,
        array &$steps,
        string $needGuitarExpr,
        string $needBassExpr,
        string $needVocalsExpr,
        string $needDrumsExpr,
        array $guitarFields,
        array $bassFields,
        array $vocalsFields,
        array $drumsFields,
    ): void {
        $cart = View::factory()->group('Your Cart')->childOf($allQuestions)->create(['order' => $stepOrder++]);
        $steps[] = ['view' => $cart, 'applicable' => null];
        $o = 0;

        View::factory()->stat('Grand total', '{{ calculation.grandTotal }} €')->childOf($cart)->create(['order' => $o++]);

        $guitarSummary = View::factory()->html($this->cartSection('🎸 Guitar', [
            "Type: {{ answer('{$guitarFields['type']->id}') }} — Brand: {{ answer('{$guitarFields['brand']->id}') }}",
            "Body: {{ answer('{$guitarFields['bodyElectric']->id}') ?? answer('{$guitarFields['bodyAcoustic']->id}') }} · Strings: {{ answer('{$guitarFields['stringsElectric']->id}') ?? answer('{$guitarFields['stringsAcoustic']->id}') }} {{ answer('{$guitarFields['stringType']->id}') }}",
            "Finish: {{ answer('{$guitarFields['finish']->id}') }}{{ answer('{$guitarFields['leftHanded']->id}') == true ? ' · Left-handed' : '' }} · Case: {{ answer('{$guitarFields['case']->id}') }}",
            "Amp/Pickup: {{ answer('{$guitarFields['amp']->id}') ?? answer('{$guitarFields['pickup']->id}') }} · Cable: {{ answer('{$guitarFields['cable']->id}') }} · Strap: {{ answer('{$guitarFields['strap']->id}') }}{{ answer('{$guitarFields['capo']->id}') == true ? ' · Capo' : '' }}",
        ], 'guitarTotal'))->childOf($cart)->create(['order' => $o++]);
        Rule::factory()->visibility($needGuitarExpr)->create(['view_id' => $guitarSummary->id]);

        $bassSummary = View::factory()->html($this->cartSection('🎸 Bass', [
            "Brand: {{ answer('{$bassFields['brand']->id}') }} · Body: {{ answer('{$bassFields['body']->id}') }}",
            "Strings: {{ answer('{$bassFields['strings']->id}') }} {{ answer('{$bassFields['stringType']->id}') }}{{ answer('{$bassFields['leftHanded']->id}') == true ? ' · Left-handed' : '' }} · Case: {{ answer('{$bassFields['case']->id}') }}",
            "Amp: {{ answer('{$bassFields['amp']->id}') }} · Cable: {{ answer('{$bassFields['reuseCable']->id}') == true ? 'reused guitar cable' : answer('{$bassFields['ownCable']->id}') }} · Strap: {{ answer('{$bassFields['strap']->id}') }}",
        ], 'bassTotal'))->childOf($cart)->create(['order' => $o++]);
        Rule::factory()->visibility($needBassExpr)->create(['view_id' => $bassSummary->id]);

        $vocalsSummary = View::factory()->html($this->cartSection('🎤 Vocals', [
            "Mic: {{ answer('{$vocalsFields['mic']->id}') }} · Stand: {{ answer('{$vocalsFields['stand']->id}') }}",
            "PA: {{ answer('{$vocalsFields['needPa']->id}') == true ? answer('{$vocalsFields['pa']->id}') : 'not needed' }} · Cable: {{ answer('{$vocalsFields['xlr']->id}') ?? 'built into wireless system' }}",
        ], 'vocalsTotal'))->childOf($cart)->create(['order' => $o++]);
        Rule::factory()->visibility($needVocalsExpr)->create(['view_id' => $vocalsSummary->id]);

        $drumsSummary = View::factory()->html($this->cartSection('🥁 Drums', [
            "Kit: {{ answer('{$drumsFields['kit']->id}') }}{{ answer('{$drumsFields['brand']->id}') != null ? ' · '.answer('{$drumsFields['brand']->id}') : '' }}",
            "Cymbals: {{ answer('{$drumsFields['cymbals']->id}') ?? 'n/a' }} · Sticks: {{ answer('{$drumsFields['sticks']->id}') }}{{ answer('{$drumsFields['throne']->id}') == true ? ' · Throne' : '' }}{{ answer('{$drumsFields['practicePad']->id}') == true ? ' · Practice pad' : '' }}",
        ], 'drumsTotal'))->childOf($cart)->create(['order' => $o++]);
        Rule::factory()->visibility($needDrumsExpr)->create(['view_id' => $drumsSummary->id]);

        View::factory()->html($this->cartSection('🎁 Shared Accessories', [
            'Monitors, tuner, cables and power — whatever you ticked on the shared step.',
        ], 'sharedTotal'))->childOf($cart)->create(['order' => $o++]);

        View::factory()->html('<p>Thanks for building your rig with us — this is a demo, nothing was actually ordered.</p>')
            ->childOf($cart)->create(['order' => $o++]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function cartSection(string $title, array $lines, string $totalFormula): string
    {
        $body = implode('<br>', $lines);

        return "<div class=\"rounded-lg border border-gray-200 p-4\"><strong class=\"text-base\">{$title}</strong><p class=\"mt-1 text-sm\">{$body}</p><p class=\"mt-2 font-semibold\">Subtotal: {{ calculation.{$totalFormula} }} €</p></div>";
    }

    private function pricedChoice(View $parent, int &$order, string $factoryMethod, array $options, string $label, string $formulaName): View
    {
        $view = View::factory()->{$factoryMethod}($options, $label)
            ->updateOn('live')->childOf($parent)->create(['order' => $order++]);

        $this->formulas[$formulaName] = $this->ternary($view->id, $options);

        return $view;
    }

    private function pricedCheckbox(View $parent, int &$order, string $label, int $price, string $formulaName): View
    {
        $view = View::factory()->checkbox($label)
            ->updateOn('live')->childOf($parent)->create(['order' => $order++]);

        $this->formulas[$formulaName] = "answer('{$view->id}') == true ? {$price} : 0";

        return $view;
    }

    /**
     * @param  list<array{label: string, price: int}>  $items
     * @return array{0: View, 1: string}
     */
    private function checkboxBundle(View $parent, int &$order, string $groupLabel, array $items): array
    {
        $wrapper = View::factory()->group($groupLabel, 'horizontal')->childOf($parent)->create(['order' => $order++]);

        $terms = [];
        foreach ($items as $index => $itemDef) {
            $view = View::factory()->checkbox("{$itemDef['label']} (+{$itemDef['price']} €)")
                ->updateOn('live')->childOf($wrapper)->create(['order' => $index]);
            $terms[] = "(answer('{$view->id}') == true ? {$itemDef['price']} : 0)";
        }

        return [$wrapper, implode(' + ', $terms)];
    }

    /**
     * @param  list<array{value: string, price?: int}>  $options
     */
    private function ternary(string $viewId, array $options): string
    {
        $expr = '0';
        foreach (array_reverse($options) as $option) {
            $price = $option['price'] ?? 0;
            $expr = "answer('{$viewId}') == '{$option['value']}' ? {$price} : ({$expr})";
        }

        return $expr;
    }

    private function addRunningTotal(View $step, int $order): void
    {
        View::factory()->stat('Estimated total so far', '{{ calculation.grandTotal }} €')
            ->childOf($step)->create(['order' => $order]);
    }

    /**
     * @param  list<array{view: View, applicable: ?string}>  $steps
     */
    private function wireLinearFlow(array $steps): void
    {
        for ($i = 1; $i < count($steps); $i++) {
            $clauses = [];

            for ($j = $i - 1; $j >= 0; $j--) {
                $between = array_slice($steps, $j + 1, $i - $j - 1);
                $betweenApplicables = collect($between)->pluck('applicable');

                if ($betweenApplicables->contains(null)) {
                    continue;
                }

                $predecessor = "answer('{$steps[$j]['view']->id}') == true";
                $guard = $betweenApplicables->map(fn (string $expr) => "!({$expr})")->implode(' && ');

                $clauses[] = $guard === '' ? $predecessor : "({$predecessor}) && ({$guard})";
            }

            $reachable = '('.implode(') || (', $clauses).')';
            $applicable = $steps[$i]['applicable'];
            $expression = $applicable === null ? $reachable : "({$reachable}) && ({$applicable})";

            Rule::factory()->flow($expression, $steps[$i]['view']->id)->create(['view_id' => $steps[$i]['view']->id]);
        }
    }
}
