<?php

use App\Domain\Typeform\AnswerNormalizer;

$field = fn (string $type) => ['id' => 'f1', 'type' => $type, 'ref' => 'r1'];
$n = fn (array $answer) => (new AnswerNormalizer)->normalize($answer);

test('text-like answers', function () use ($field, $n) {
    expect($n(['type' => 'text', 'text' => 'Ittefaq', 'field' => $field('short_text')]))->toMatchObject(['valueText' => 'Ittefaq', 'valueNumber' => null, 'valueJson' => null, 'fieldId' => 'f1', 'fieldType' => 'short_text']);
    expect($n(['type' => 'email', 'email' => 'a@b.c', 'field' => $field('email')])->valueText)->toBe('a@b.c');
    expect($n(['type' => 'url', 'url' => 'https://x.y', 'field' => $field('website')])->valueText)->toBe('https://x.y');
    expect($n(['type' => 'phone_number', 'phone_number' => '+9230', 'field' => $field('phone_number')])->valueText)->toBe('+9230');
});

test('number answers', function () use ($field, $n) {
    $a = $n(['type' => 'number', 'number' => 8, 'field' => $field('opinion_scale')]);
    expect($a->valueText)->toBe('8')->and($a->valueNumber)->toBe(8.0);
    expect($n(['type' => 'number', 'number' => 7.5, 'field' => $field('number')])->valueText)->toBe('7.5');
});

test('boolean answers', function () use ($field, $n) {
    $yes = $n(['type' => 'boolean', 'boolean' => true, 'field' => $field('yes_no')]);
    $no = $n(['type' => 'boolean', 'boolean' => false, 'field' => $field('yes_no')]);
    expect($yes->valueText)->toBe('Yes')->and($yes->valueNumber)->toBe(1.0)->and($yes->isYes())->toBeTrue()
        ->and($no->valueText)->toBe('No')->and($no->valueNumber)->toBe(0.0)->and($no->isNo())->toBeTrue();
});

test('choice answers use label or other', function () use ($field, $n) {
    $a = $n(['type' => 'choice', 'choice' => ['id' => 'c1', 'ref' => 'cr', 'label' => 'N/A'], 'field' => $field('dropdown')]);
    expect($a->valueText)->toBe('N/A')->and($a->valueJson)->toBe(['id' => 'c1', 'ref' => 'cr', 'label' => 'N/A', 'other' => null])->and($a->isNotApplicable())->toBeTrue();

    $other = $n(['type' => 'choice', 'choice' => ['other' => 'Something else'], 'field' => $field('multiple_choice')]);
    expect($other->valueText)->toBe('Something else')->and($other->isYes())->toBeFalse();

    expect($n(['type' => 'choice', 'choice' => ['label' => ' yes '], 'field' => $field('dropdown')])->isYes())->toBeTrue();
});

test('multiple choices are joined', function () use ($field, $n) {
    $a = $n(['type' => 'choices', 'choices' => ['ids' => ['1', '2'], 'refs' => ['a', 'b'], 'labels' => ['Clean', 'Fast'], 'other' => 'Cheap'], 'field' => $field('multiple_choice')]);
    expect($a->valueText)->toBe('Clean, Fast, Cheap')->and($a->valueJson['labels'])->toBe(['Clean', 'Fast'])->and($a->valueJson['other'])->toBe('Cheap');
});

test('dates are normalised to Y-m-d', function () use ($field, $n) {
    expect($n(['type' => 'date', 'date' => '2026-03-14', 'field' => $field('date')])->valueText)->toBe('2026-03-14');
    expect($n(['type' => 'date', 'date' => '2026-03-14T00:00:00Z', 'field' => $field('date')])->valueText)->toBe('2026-03-14');
});

test('file uploads keep the url privately and show the file name', function () use ($field, $n) {
    $a = $n(['type' => 'file_url', 'file_url' => 'https://api.typeform.com/responses/files/abc/receipt.jpg', 'field' => $field('file_upload')]);
    expect($a->valueText)->toBe('receipt.jpg')->and($a->valueJson)->toBe(['url' => 'https://api.typeform.com/responses/files/abc/receipt.jpg']);
});

test('payments are formatted', function () use ($field, $n) {
    $a = $n(['type' => 'payment', 'payment' => ['amount' => '12.5', 'currency' => 'USD', 'last4' => '4242', 'name' => 'A'], 'field' => $field('payment')]);
    expect($a->valueText)->toBe('12.50 USD')->and($a->valueNumber)->toBe(12.5)->and($a->valueJson['last4'])->toBe('4242');
});

test('unknown types are JSON encoded', function () use ($field, $n) {
    $a = $n(['type' => 'matrix', 'matrix' => ['rows' => [1, 2]], 'field' => $field('matrix')]);
    expect($a->valueText)->toBe('{"rows":[1,2]}')->and($a->valueJson)->toBe(['raw' => ['rows' => [1, 2]]])->and($a->answerType)->toBe('matrix');
});

test('normalizeAll skips malformed entries', function () {
    $out = (new AnswerNormalizer)->normalizeAll([['type' => 'text', 'text' => 'x', 'field' => ['id' => 'a']], ['type' => 'text'], 'junk']);
    expect($out)->toHaveCount(1);
});
