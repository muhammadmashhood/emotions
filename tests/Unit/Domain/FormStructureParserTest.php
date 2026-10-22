<?php

use App\Domain\Typeform\Data\ParsedField;
use App\Domain\Typeform\Data\ParsedSection;
use App\Domain\Typeform\FormStructureParser;

$parser = fn () => new FormStructureParser;

test('real fixture produces 10 sections in order with cleaned names', function () use ($parser) {
    $structure = $parser()->parse(fixture('form_definition_rACXmOJ2.json'));

    expect(array_map(fn (ParsedSection $s) => $s->name, $structure->sections))->toBe([
        'General Information', 'Website/ App booking', 'Phone booking', 'Arrival & Reception', 'Waiting Area',
        'Nursing Staff', 'Doctor Consultation', 'Pharmacy & Billing', 'Facility & Cleanliness', 'Overall',
    ]);
    expect($structure->sections[0]->sourceRef)->not->toBeNull();
    expect(count($structure->allFields()))->toBe(92);
});

test('the misplaced website question lands in General Information', function () use ($parser) {
    $structure = $parser()->parse(fixture('form_definition_rACXmOJ2.json'));
    $general = $structure->sections[0];

    expect(array_map(fn (ParsedField $f) => $f->title, $general->fields))->toContain('The website/ App was easy to access');
    expect(array_map(fn (ParsedField $f) => $f->title, $structure->sections[1]->fields))->not->toContain('The website/ App was easy to access');
});

test('statements, welcome and thank-you screens never become fields', function () use ($parser) {
    $structure = $parser()->parse(fixture('form_definition_rACXmOJ2.json'));
    $types = array_unique(array_map(fn (ParsedField $f) => $f->type, $structure->allFields()));

    expect($types)->not->toContain('statement')->not->toContain('group')->not->toContain('welcome_screen')->not->toContain('thankyou_screen');
    expect(array_map(fn (ParsedField $f) => $f->title, $structure->allFields()))->not->toContain('Welcome to the Mystery Shopping Evaluation');
});

test('questions before any statement go to General, which is dropped when empty', function () use ($parser) {
    $flat = $parser()->parse(fixture('form_no_statements.json'));
    expect($flat->sections)->toHaveCount(1)
        ->and($flat->sections[0]->name)->toBe('General')
        ->and($flat->sections[0]->sourceRef)->toBeNull()
        ->and($flat->sections[0]->fields)->toHaveCount(4);

    $real = $parser()->parse(fixture('form_definition_rACXmOJ2.json'));
    expect(array_map(fn (ParsedSection $s) => $s->name, $real->sections))->not->toContain('General');
});

test('groups produce sections with their children and following fields return to the current section', function () use ($parser) {
    $structure = $parser()->parse(fixture('form_groups.json'));

    expect(array_map(fn (ParsedSection $s) => $s->name, $structure->sections))->toBe(['General', 'Reception', 'Doctor']);
    expect(array_map(fn (ParsedField $f) => $f->title, $structure->sections[0]->fields))->toBe(['Hospital name', 'After the group, back to General']);
    expect(array_map(fn (ParsedField $f) => $f->title, $structure->sections[1]->fields))->toBe(['Greeting', 'Badge worn?']);
    expect(array_map(fn (ParsedField $f) => $f->title, $structure->sections[2]->fields))->toBe(['Listening', 'Nested question']);
    expect($structure->sections[1]->sourceRef)->not->toBeNull();

    // display order follows the form order, depth-first
    $orders = array_map(fn (ParsedField $f) => $f->displayOrder, $structure->allFieldsInOrder());
    expect($orders)->toBe(range(0, 5));
});

test('field scale defaults follow Typeform properties', function () use ($parser) {
    $structure = $parser()->parse(fixture('form_rating_yesno.json'));
    $byTitle = collect($structure->allFields())->keyBy('title');

    expect($byTitle['Stars'])->toMatchObject(['type' => 'rating', 'scaleMin' => 1, 'scaleMax' => 5, 'isScored' => true]);
    expect($byTitle['Scale one to five'])->toMatchObject(['scaleMin' => 1, 'scaleMax' => 5, 'isScored' => true]);
    expect($byTitle['NPS'])->toMatchObject(['scaleMin' => 0, 'scaleMax' => 10, 'isScored' => true]);
    expect($byTitle['Would you come back?'])->toMatchObject(['type' => 'yes_no', 'isScored' => true, 'scaleMin' => null]);

    $real = collect($parser()->parse(fixture('form_definition_rACXmOJ2.json'))->allFields())->keyBy('title');
    expect($real['The agent greeted you politely'])->toMatchObject(['scaleMin' => 0, 'scaleMax' => 10, 'isScored' => true]);
    // descriptions saying "1 to 5" are ignored; properties win
    expect($real['Appointment slots were clearly displayed'])->toMatchObject(['scaleMin' => 0, 'scaleMax' => 10, 'description' => 'Rate from 1 to 5']);
});

test('yes/no/na dropdowns are scored while other choice lists and text are not', function () use ($parser) {
    $byTitle = collect($parser()->parse(fixture('form_rating_yesno.json'))->allFields())->keyBy('title');
    expect($byTitle['Clean?']->isScored)->toBeTrue()
        ->and($byTitle['Clean?']->choices)->toHaveCount(3)
        ->and($byTitle['Clean?']->choices[0])->toHaveKeys(['id', 'ref', 'label'])
        ->and($byTitle['Not applicable choice']->isScored)->toBeTrue();

    $real = collect($parser()->parse(fixture('form_definition_rACXmOJ2.json'))->allFields())->keyBy('title');
    expect($real['Location']->isScored)->toBeFalse()
        ->and($real['Appointment Booking method']->isScored)->toBeFalse()
        ->and($real['Healthcare Facility']->isScored)->toBeFalse()
        ->and($real['Vsite Date']->isScored)->toBeFalse()
        ->and($real['Upload a photo of your receipt or visit evidence']->isScored)->toBeFalse()
        ->and($real['Was your appointment found in the system immediately?']->isScored)->toBeTrue();
});

test('info keys are detected from titles, first match wins per key', function () use ($parser) {
    $fields = collect($parser()->parse(fixture('form_definition_rACXmOJ2.json'))->allFields());
    $info = $fields->filter(fn (ParsedField $f) => $f->infoKey !== null)->mapWithKeys(fn (ParsedField $f) => [$f->infoKey => $f->title]);

    expect($info->all())->toBe([
        'facility' => 'Healthcare Facility',
        'location' => 'Location',
        'visit_date' => 'Vsite Date',
        'booking_method' => 'Appointment Booking method',
        'doctor' => 'Name of the Doctor',
        'recommend' => 'How likely are you to recommend this hospital to a friend?',
    ]);
    // "Additional Comments" repeats; none of them carry an info key, and each has a unique id
    $ids = $fields->pluck('id');
    expect($ids->unique()->count())->toBe($ids->count());
});

test('scale and yes/no questions never become info fields, except the recommend KPI role', function () use ($parser) {
    $def = fixture('form_no_statements.json');
    $def['fields'][] = ['id' => 'cleanF', 'title' => 'The healthcare facility was clean', 'ref' => 'r-clean', 'properties' => ['steps' => 11], 'type' => 'opinion_scale'];
    $def['fields'][] = ['id' => 'recF', 'title' => 'How likely is that you would recommend this hospital/clinic?', 'ref' => 'r-rec', 'properties' => ['steps' => 11], 'type' => 'opinion_scale'];
    $def['fields'][] = ['id' => 'locYN', 'title' => 'Location was convenient?', 'ref' => 'r-yn', 'properties' => [], 'type' => 'yes_no'];

    $byTitle = collect($parser()->parse($def)->allFields())->keyBy('title');
    expect($byTitle['The healthcare facility was clean']->infoKey)->toBeNull()
        ->and($byTitle['Facility']->infoKey)->toBe('facility')
        ->and($byTitle['Location was convenient?']->infoKey)->toBeNull()
        ->and($byTitle['How likely is that you would recommend this hospital/clinic?']->infoKey)->toBe('recommend')
        ->and($byTitle['How likely is that you would recommend this hospital/clinic?']->isScored)->toBeTrue();
});

test('section names are cleaned', function (string $input, string $expected) {
    expect(FormStructureParser::cleanTitle($input))->toBe($expected);
})->with([
    ['Section 2 — Website/ App booking', 'Website/ App booking'],
    ['section 10: Overall', 'Overall'],
    ['SECTION 3 - Arrival', 'Arrival'],
    ['Overall', 'Overall'],
    ['*Section 1 — General Information*', 'General Information'],
    ['_Section 4 – Waiting_', 'Waiting'],
    ['Section 5.', 'Section 5.'],
    ['  Section 6 ', 'Section 6'],
]);

test('structure hash changes on title change only, not on logic or settings changes', function () {
    $definition = fixture('form_definition_rACXmOJ2.json');
    $hash = FormStructureParser::hash($definition);
    expect($hash)->toHaveLength(64)->and($hash)->toBe(FormStructureParser::hash($definition));

    $logicChanged = $definition;
    $logicChanged['logic'] = [];
    $logicChanged['settings']['is_public'] = false;
    expect(FormStructureParser::hash($logicChanged))->toBe($hash);

    $renamed = $definition;
    $renamed['fields'][5]['title'] .= ' (edited)';
    expect(FormStructureParser::hash($renamed))->not->toBe($hash);

    $stepsChanged = $definition;
    $stepsChanged['fields'][6]['properties']['steps'] = 5;
    expect(FormStructureParser::hash($stepsChanged))->not->toBe($hash);

    expect(FormStructureParser::hash(fixture('form_definition_rACXmOJ2_v2.json')))->not->toBe($hash);
});
