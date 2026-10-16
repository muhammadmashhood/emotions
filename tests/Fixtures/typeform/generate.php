<?php

/**
 * Generates synthetic Typeform fixtures that mirror the real rACXmOJ2 form (SPEC.md section 17).
 * Run: php tests/Fixtures/typeform/generate.php
 */
$counter = 0;
$fid = function () use (&$counter): string {
    $counter++;

    return substr(strtr(base64_encode(md5('field'.$counter, true)), '+/=', 'abc'), 0, 10);
};
$ref = fn (string $seed): string => sprintf('%08x-%04x-4%03x-%04x-%012x', crc32($seed), crc32('a'.$seed) & 0xFFFF, crc32('b'.$seed) & 0xFFF, (crc32('c'.$seed) & 0x3FFF) | 0x8000, crc32('d'.$seed));

$choices = function (array $labels, string $seed) use ($fid, $ref): array {
    return array_map(fn ($l) => ['id' => $fid(), 'ref' => $ref($seed.$l), 'label' => $l], $labels);
};

$scale = fn (string $title, ?string $desc = null) => array_filter([
    'id' => $fid(), 'title' => $title, 'ref' => $ref($title),
    'properties' => ['steps' => 11, 'start_at_one' => false, 'labels' => ['left' => 'Very poor', 'right' => 'Excellent']],
    'validations' => ['required' => false],
    'type' => 'opinion_scale',
] + ($desc ? ['description' => $desc] : []));

$yesNoDropdown = fn (string $title) => [
    'id' => $fid(), 'title' => $title, 'ref' => $ref($title),
    'properties' => ['alphabetical_order' => false, 'choices' => $choices(['Yes', 'No', 'N/A'], $title)],
    'validations' => ['required' => false], 'type' => 'dropdown',
];
$yesNo = fn (string $title) => ['id' => $fid(), 'title' => $title, 'ref' => $ref($title), 'properties' => [], 'validations' => ['required' => false], 'type' => 'yes_no'];
$longText = fn (string $title) => ['id' => $fid(), 'title' => $title, 'ref' => $ref($title), 'properties' => [], 'validations' => ['required' => false], 'type' => 'long_text'];
$shortText = fn (string $title) => ['id' => $fid(), 'title' => $title, 'ref' => $ref($title), 'properties' => [], 'validations' => ['required' => false], 'type' => 'short_text'];
$statement = fn (string $title) => ['id' => $fid(), 'title' => $title, 'ref' => $ref('stmt'.$title), 'properties' => ['hide_marks' => false, 'button_text' => 'Continue'], 'type' => 'statement'];

$fields = [];
$s1 = $statement('*Section 1 — General Information*');
$fields[] = $s1;
$fields[] = $shortText('Healthcare Facility');
$fields[] = ['id' => $fid(), 'title' => 'Location', 'ref' => $ref('Location'), 'properties' => ['alphabetical_order' => false, 'choices' => $choices(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Multan'], 'loc')], 'validations' => ['required' => false], 'type' => 'dropdown'];
$fields[] = ['id' => $fid(), 'title' => 'Vsite Date', 'ref' => $ref('Visit Date'), 'properties' => ['structure' => 'DDMMYYYY', 'separator' => '/'], 'validations' => ['required' => false], 'type' => 'date'];
$booking = ['id' => $fid(), 'title' => 'Appointment Booking method', 'ref' => $ref('booking'), 'properties' => ['randomize' => false, 'allow_multiple_selection' => false, 'allow_other_choice' => false, 'vertical_alignment' => true, 'choices' => $choices(['Phone call', 'Website/ App', 'Walk-in'], 'booking')], 'validations' => ['required' => true], 'type' => 'multiple_choice'];
$fields[] = $booking;
$fields[] = $shortText('Name of the agent who handled the booking');
$websiteEasy = $scale('The website/ App was easy to access', 'Rate from 1 to 10');
$fields[] = $websiteEasy; // misplaced above the Section 2 statement on purpose (section 17)

$s2 = $statement('Section 2 — Website/ App booking');
$fields[] = $s2;
foreach (['Appointment slots were clearly displayed', 'The booking process was quick', 'Confirmation details were accurate', 'The app/website looked professional', 'It was easy to find the right doctor', 'Payment options were clear', 'Reminders were sent before the visit'] as $t) {
    $fields[] = $scale($t, 'Rate from 1 to 5');
}
$fields[] = $yesNoDropdown('Was a booking confirmation received?');
$fields[] = $longText('Additional Comments');

$s3 = $statement('Section 3 — Phone booking');
$fields[] = $s3;
$fields[] = $yesNo('Was the call answered within 3 rings?');
foreach (['The agent greeted you politely', 'The agent understood your request', 'The agent offered suitable appointment times', 'The agent confirmed the appointment details', 'Hold time was acceptable', 'The agent spoke clearly', 'The agent thanked you at the end'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $longText('Additional Comments');

$s4 = $statement('Section 4 — Arrival & Reception');
$fields[] = $s4;
foreach (['Signage made the reception easy to find', 'Receptionist greeted you promptly', 'Check-in was quick', 'Receptionist was courteous', 'Receptionist explained the next steps', 'Parking was easy to find', 'Security staff were helpful', 'The entrance was accessible'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $yesNoDropdown('Was your appointment found in the system immediately?');
$fields[] = $longText('Additional Comments');

$s5 = $statement('Section 5 — Waiting Area');
$fields[] = $s5;
foreach (['The waiting area was clean', 'Seating was comfortable and sufficient', 'Waiting time was acceptable', 'Updates were given about delays', 'Refreshments / water were available', 'Temperature and lighting were comfortable', 'Information displays were useful'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $longText('Additional Comments');

$s6 = $statement('Section 6 — Nursing Staff');
$fields[] = $s6;
foreach (['Nurse introduced themselves', 'Vitals were taken professionally', 'Nurse explained what they were doing', 'Nurse was attentive to your comfort', 'Nurse maintained privacy', 'Nurse was friendly', 'Nurse answered your questions', 'Hand hygiene was observed'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $yesNo('Did the nurse wear a name badge?');
$fields[] = $longText('Additional Comments');

$s7 = $statement('Section 7 — Doctor Consultation');
$fields[] = $s7;
$fields[] = $shortText('Name of the Doctor');
foreach (['Doctor saw you at the scheduled time', 'Doctor listened carefully', 'Doctor explained the diagnosis clearly', 'Doctor explained the treatment options', 'Doctor gave you time to ask questions', 'Doctor was respectful', 'Doctor examined you thoroughly', 'Doctor explained follow-up steps', 'Doctor washed hands / used sanitiser', 'Overall confidence in the doctor'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $yesNoDropdown('Did the doctor explain possible side effects?');
$fields[] = $longText('Additional Comments');

$s8 = $statement('Section 8 — Pharmacy & Billing');
$fields[] = $s8;
foreach (['Pharmacy was easy to locate', 'Pharmacist explained the medication', 'Waiting time at pharmacy was acceptable', 'Billing was accurate', 'Billing staff were courteous', 'Payment methods were convenient', 'Receipt was itemised and clear'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $yesNoDropdown('Were all prescribed medicines available?');
$fields[] = $longText('Additional Comments');

$s9 = $statement('Section 9 — Facility & Cleanliness');
$fields[] = $s9;
foreach (['Toilets were clean', 'Corridors were clean', 'Consultation room was clean', 'Equipment looked well maintained', 'Waste was disposed of properly', 'Hand sanitiser was available', 'The building was well lit', 'Signage was clear throughout'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $yesNo('Were visible safety hazards present?');
$fields[] = $longText('Additional Comments');

$s10 = $statement('Section 10 — Overall');
$fields[] = $s10;
$rating = ['id' => $fid(), 'title' => 'Overall rating of your visit', 'ref' => $ref('rating'), 'properties' => ['steps' => 5, 'shape' => 'star'], 'validations' => ['required' => false], 'type' => 'rating'];
$fields[] = $rating;
$nps = ['id' => $fid(), 'title' => 'How likely are you to recommend this hospital to a friend?', 'ref' => $ref('nps'), 'properties' => ['steps' => 11], 'validations' => ['required' => false], 'type' => 'nps'];
$fields[] = $nps;
$fields[] = $yesNo('Would you return to this hospital?');
foreach (['Value for money', 'Overall staff attitude', 'Overall cleanliness'] as $t) {
    $fields[] = $scale($t);
}
$fields[] = $longText('Additional Comments');
$fields[] = ['id' => $fid(), 'title' => 'Upload a photo of your receipt or visit evidence', 'ref' => $ref('upload'), 'properties' => [], 'validations' => ['required' => false], 'type' => 'file_upload'];

$byTitle = function (string $title, ?string $after = null) use ($fields): array {
    $seen = $after === null;
    foreach ($fields as $f) {
        if (! $seen) {
            $seen = $f['title'] === $after;

            continue;
        }
        if ($f['title'] === $title) {
            return $f;
        }
    }
    throw new RuntimeException("field not found: $title");
};

$definition = [
    'id' => 'rACXmOJ2',
    'type' => 'quiz',
    'title' => 'Hospital Mystery Shopping Evaluation',
    'workspace' => ['href' => 'https://api.typeform.com/workspaces/ws1'],
    'theme' => ['href' => 'https://api.typeform.com/themes/t1'],
    'settings' => [
        'language' => 'en', 'progress_bar' => 'proportion', 'meta' => ['allow_indexing' => false],
        'hide_navigation' => false, 'is_public' => true, 'is_trial' => false, 'show_progress_bar' => true,
        'show_typeform_branding' => true, 'are_uploads_public' => false, 'show_time_to_complete' => true,
        'show_number_of_submissions' => false, 'show_cookie_consent' => false, 'show_question_number' => true,
        'show_key_hint_on_choices' => true, 'autosave_progress' => true, 'free_form_navigation' => false,
        'use_lead_qualification' => false, 'pro_subdomain_enabled' => false, 'capabilities' => ['e2e_encryption' => ['enabled' => false, 'modifiable' => false]],
        'partial_responses_to_all_integrations' => true,
    ],
    'welcome_screens' => [[
        'id' => $fid(), 'ref' => $ref('welcome'), 'title' => 'Welcome to the Mystery Shopping Evaluation',
        'properties' => ['show_button' => true, 'button_text' => 'Start', 'description' => 'Please answer honestly.'],
    ]],
    'thankyou_screens' => [[
        'id' => $fid(), 'ref' => 'default_tys', 'title' => 'Thank you for your evaluation', 'type' => 'thankyou_screen',
        'properties' => ['show_button' => false, 'share_icons' => false],
    ]],
    'fields' => $fields,
    'logic' => [
        [
            'type' => 'field', 'ref' => $booking['ref'],
            'actions' => [
                ['action' => 'jump', 'details' => ['to' => ['type' => 'field', 'value' => $s3['ref']]], 'condition' => ['op' => 'is', 'vars' => [['type' => 'field', 'value' => $booking['ref']], ['type' => 'choice', 'value' => $booking['properties']['choices'][0]['ref']]]]],
                ['action' => 'jump', 'details' => ['to' => ['type' => 'field', 'value' => $s2['ref']]], 'condition' => ['op' => 'always', 'vars' => []]],
            ],
        ],
        [
            // Broken score logic from the real form: both Yes and No subtract 1.
            'type' => 'field', 'ref' => $byTitle('Was the call answered within 3 rings?')['ref'],
            'actions' => [
                ['action' => 'add', 'details' => ['target' => ['type' => 'variable', 'value' => 'score'], 'value' => ['type' => 'constant', 'value' => -1]], 'condition' => ['op' => 'is', 'vars' => [['type' => 'field', 'value' => $byTitle('Was the call answered within 3 rings?')['ref']], ['type' => 'constant', 'value' => true]]]],
                ['action' => 'add', 'details' => ['target' => ['type' => 'variable', 'value' => 'score'], 'value' => ['type' => 'constant', 'value' => -1]], 'condition' => ['op' => 'is', 'vars' => [['type' => 'field', 'value' => $byTitle('Was the call answered within 3 rings?')['ref']], ['type' => 'constant', 'value' => false]]]],
            ],
        ],
        [
            'type' => 'field', 'ref' => $rating['ref'],
            'actions' => [
                ['action' => 'add', 'details' => ['target' => ['type' => 'variable', 'value' => 'score'], 'value' => ['type' => 'constant', 'value' => 3]], 'condition' => ['op' => 'equal', 'vars' => [['type' => 'field', 'value' => $rating['ref']], ['type' => 'constant', 'value' => 5]]]],
            ],
        ],
        [
            // The "always" jump that returns to Section 1 (a real-form quirk).
            'type' => 'field', 'ref' => $byTitle('Additional Comments', 'Section 2 — Website/ App booking')['ref'],
            'actions' => [
                ['action' => 'jump', 'details' => ['to' => ['type' => 'field', 'value' => $s1['ref']]], 'condition' => ['op' => 'always', 'vars' => []]],
            ],
        ],
    ],
    'variables' => ['score' => 0],
    '_links' => ['display' => 'https://form.typeform.com/to/rACXmOJ2', 'responses' => 'https://api.typeform.com/forms/rACXmOJ2/responses'],
];

file_put_contents(__DIR__.'/form_definition_rACXmOJ2.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

// ---------------------------------------------------------------- webhook payload (phone booking; website section skipped)
$answers = [];
$fieldRef = fn (array $f) => ['id' => $f['id'], 'type' => $f['type'], 'ref' => $f['ref']];
$text = fn (string $title, string $value, ?string $after = null) => ['type' => 'text', 'text' => $value, 'field' => $fieldRef($byTitle($title, $after))];
$num = fn (string $title, int $value, ?string $after = null) => ['type' => 'number', 'number' => $value, 'field' => $fieldRef($byTitle($title, $after))];
$bool = fn (string $title, bool $value) => ['type' => 'boolean', 'boolean' => $value, 'field' => $fieldRef($byTitle($title))];
$choice = function (string $title, string $label) use ($byTitle, $fieldRef) {
    $f = $byTitle($title);
    $c = array_values(array_filter($f['properties']['choices'], fn ($c) => $c['label'] === $label))[0];

    return ['type' => 'choice', 'choice' => ['id' => $c['id'], 'ref' => $c['ref'], 'label' => $c['label']], 'field' => $fieldRef($f)];
};

$answers[] = $text('Healthcare Facility', 'Ittefaq Hospital');
$answers[] = $choice('Location', 'Lahore');
$answers[] = ['type' => 'date', 'date' => '2026-03-14', 'field' => $fieldRef($byTitle('Vsite Date'))];
$answers[] = $choice('Appointment Booking method', 'Phone call');
$answers[] = $text('Name of the agent who handled the booking', 'Ayesha');
// Section 3: Yes + 8,8,8  → (10 + 24) / (10 + 30) = 85 %
$answers[] = $bool('Was the call answered within 3 rings?', true);
$answers[] = $num('The agent greeted you politely', 8);
$answers[] = $num('The agent understood your request', 8);
$answers[] = $num('The agent offered suitable appointment times', 8);
$answers[] = $text('Additional Comments', 'Quick and friendly call.', 'Section 3 — Phone booking');
// Section 4: 7,8,9,6 + N/A → 30/40 = 75 %
$answers[] = $num('Signage made the reception easy to find', 7);
$answers[] = $num('Receptionist greeted you promptly', 8);
$answers[] = $num('Check-in was quick', 9);
$answers[] = $num('Receptionist was courteous', 6);
$answers[] = $choice('Was your appointment found in the system immediately?', 'N/A');
// Section 5: 5,6,7 → 18/30 = 60 %
$answers[] = $num('The waiting area was clean', 5);
$answers[] = $num('Seating was comfortable and sufficient', 6);
$answers[] = $num('Waiting time was acceptable', 7);
$answers[] = $text('Additional Comments', 'Waited about 40 minutes.', 'Section 5 — Waiting Area');
// Section 6: 9,9,10,8 + Yes → 46/50 = 92 %
$answers[] = $num('Nurse introduced themselves', 9);
$answers[] = $num('Vitals were taken professionally', 9);
$answers[] = $num('Nurse explained what they were doing', 10);
$answers[] = $num('Nurse was attentive to your comfort', 8);
$answers[] = $bool('Did the nurse wear a name badge?', true);
// Section 7: 10,9,10,9,10 + No → 48/60 = 80 %
$answers[] = $text('Name of the Doctor', 'Dr. Farhan Siddiqui');
$answers[] = $num('Doctor saw you at the scheduled time', 10);
$answers[] = $num('Doctor listened carefully', 9);
$answers[] = $num('Doctor explained the diagnosis clearly', 10);
$answers[] = $num('Doctor explained the treatment options', 9);
$answers[] = $num('Doctor gave you time to ask questions', 10);
$answers[] = $choice('Did the doctor explain possible side effects?', 'No');
// Section 8: 6,7,8 + Yes → 31/40 = 77.5 %
$answers[] = $num('Pharmacy was easy to locate', 6);
$answers[] = $num('Pharmacist explained the medication', 7);
$answers[] = $num('Waiting time at pharmacy was acceptable', 8);
$answers[] = $choice('Were all prescribed medicines available?', 'Yes');
// Section 9: 8,8,7,7 + No → 30/50 = 60 %
$answers[] = $num('Toilets were clean', 8);
$answers[] = $num('Corridors were clean', 8);
$answers[] = $num('Consultation room was clean', 7);
$answers[] = $num('Equipment looked well maintained', 7);
$answers[] = $bool('Were visible safety hazards present?', false);
// Section 10: rating 4 (3/4), nps 9 (9/10), Yes (10/10) → 22/24 = 91.67 %
$answers[] = $num('Overall rating of your visit', 4);
$answers[] = $num('How likely are you to recommend this hospital to a friend?', 9);
$answers[] = $bool('Would you return to this hospital?', true);
$answers[] = $text('Additional Comments', 'Overall a good experience, pharmacy could be faster.', 'Section 10 — Overall');
$answers[] = ['type' => 'file_url', 'file_url' => 'https://api.typeform.com/responses/files/8f3a1c2d9e/receipt.jpg', 'field' => $fieldRef($byTitle('Upload a photo of your receipt or visit evidence'))];

$definitionForPayload = ['id' => 'rACXmOJ2', 'title' => $definition['title'], 'fields' => array_map(fn ($f) => array_filter([
    'id' => $f['id'], 'ref' => $f['ref'], 'type' => $f['type'], 'title' => $f['title'], 'properties' => (object) [],
    'choices' => $f['properties']['choices'] ?? null,
]), array_values(array_filter($fields, fn ($f) => $f['type'] !== 'statement')))];

$payload = [
    'event_id' => '01JXYZ6WJ2P5K3Q8R9S0T1U2V3',
    'event_type' => 'form_response',
    'form_response' => [
        'form_id' => 'rACXmOJ2',
        'token' => 'r8f3a1c2d9e0b7f6a5d4c3b2a1',
        'landed_at' => '2026-03-14T10:02:11Z',
        'submitted_at' => '2026-03-14T10:19:47Z',
        'definition' => $definitionForPayload,
        'answers' => $answers,
        'hidden' => ['branch' => 'Main campus'],
        'calculated' => ['score' => 2],
        'variables' => [['key' => 'score', 'type' => 'number', 'number' => 2]],
    ],
];
file_put_contents(__DIR__.'/webhook_payload_rACXmOJ2.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

// ---------------------------------------------------------------- small fixtures
$small = fn (string $id, string $title, array $fields, array $extra = []) => array_merge([
    'id' => $id, 'type' => 'form', 'title' => $title, 'settings' => ['language' => 'en'],
    'welcome_screens' => [], 'thankyou_screens' => [], 'fields' => $fields, 'logic' => [], 'variables' => (object) [],
], $extra);

$write = fn (string $name, array $data) => file_put_contents(__DIR__.'/'.$name, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

$write('form_groups.json', $small('GRP00001', 'Grouped form', [
    $shortText('Hospital name'),
    ['id' => $fid(), 'title' => 'Reception', 'ref' => $ref('grp-reception'), 'properties' => ['fields' => [
        $scale('Greeting'), $yesNo('Badge worn?'),
    ]], 'type' => 'group'],
    $scale('After the group, back to General'),
    ['id' => $fid(), 'title' => 'Doctor', 'ref' => $ref('grp-doctor'), 'properties' => ['fields' => [
        $scale('Listening'),
        ['id' => $fid(), 'title' => 'Nested', 'ref' => $ref('grp-nested'), 'properties' => ['fields' => [$scale('Nested question')]], 'type' => 'group'],
    ]], 'type' => 'group'],
]));

$write('form_no_statements.json', $small('NOSTMT01', 'Flat form', [
    $shortText('Facility'), $scale('Cleanliness'), $yesNo('Recommend?'), $longText('Comments'),
]));

$write('form_rating_yesno.json', $small('RATEYN01', 'Rating and yes/no', [
    $statement('Section 1 — Experience'),
    ['id' => $fid(), 'title' => 'Stars', 'ref' => $ref('stars'), 'properties' => ['steps' => 5, 'shape' => 'star'], 'type' => 'rating'],
    $yesNo('Would you come back?'),
    ['id' => $fid(), 'title' => 'Scale one to five', 'ref' => $ref('scale15'), 'properties' => ['steps' => 5, 'start_at_one' => true], 'type' => 'opinion_scale'],
    ['id' => $fid(), 'title' => 'NPS', 'ref' => $ref('nps-small'), 'properties' => ['steps' => 11], 'type' => 'nps'],
    $yesNoDropdown('Clean?'),
    ['id' => $fid(), 'title' => 'Not applicable choice', 'ref' => $ref('na-choice'), 'properties' => ['choices' => $choices(['yes', 'no', 'Not applicable'], 'na')], 'type' => 'multiple_choice'],
]));

// Partial response (event_type form_response_partial) for the big form: only section 1 answered.
$partial = $payload;
$partial['event_id'] = '01JXYZ6WJ2P5K3Q8R9S0T1U2V2';
$partial['event_type'] = 'form_response_partial';
$partial['form_response']['token'] = 'r8f3a1c2d9e0b7f6a5d4c3b2a1';
$partial['form_response']['submitted_at'] = null;
$partial['form_response']['answers'] = array_slice($answers, 0, 5);
$write('webhook_payload_partial.json', $partial);

// Response with an unknown field id (form edited after linking).
$unknown = $payload;
$unknown['event_id'] = '01JXYZ6WJ2P5K3Q8R9S0T1U2V9';
$unknown['form_response']['token'] = 'unknownfieldtoken0001';
$unknown['form_response']['answers'][] = ['type' => 'number', 'number' => 7, 'field' => ['id' => 'NEWFIELD01', 'type' => 'opinion_scale', 'ref' => 'new-field-ref']];
$write('webhook_payload_unknown_field.json', $unknown);

// Updated definition: a new question added to Section 10 and Section 4 renamed in Typeform.
$updated = $definition;
foreach ($updated['fields'] as &$f) {
    if ($f['title'] === 'Section 4 — Arrival & Reception') {
        $f['title'] = 'Section 4 — Arrival, Parking & Reception';
    }
}
unset($f);
$newField = ['id' => 'NEWFIELD01', 'title' => 'Was the discharge process explained?', 'ref' => 'new-field-ref', 'properties' => ['steps' => 11, 'start_at_one' => false], 'validations' => ['required' => false], 'type' => 'opinion_scale'];
array_splice($updated['fields'], count($updated['fields']) - 2, 0, [$newField]);
$write('form_definition_rACXmOJ2_v2.json', $updated);

// Responses API page (same answer shapes as webhooks).
$write('responses_page.json', [
    'total_items' => 2, 'page_count' => 1,
    'items' => [
        ['landing_id' => 'l1', 'token' => 'apitoken000000000001', 'response_id' => 'l1', 'landed_at' => '2026-02-01T09:00:00Z', 'submitted_at' => '2026-02-01T09:20:00Z', 'metadata' => ['platform' => 'other'], 'hidden' => (object) [], 'calculated' => ['score' => 1], 'answers' => $answers, 'variables' => []],
        ['landing_id' => 'l2', 'token' => 'apitoken000000000002', 'response_id' => 'l2', 'landed_at' => '2026-02-02T09:00:00Z', 'submitted_at' => '2026-02-02T09:20:00Z', 'metadata' => ['platform' => 'other'], 'hidden' => (object) [], 'calculated' => ['score' => 1], 'answers' => array_slice($answers, 0, 9), 'variables' => []],
    ],
]);

echo 'fields: '.count($fields).PHP_EOL;
