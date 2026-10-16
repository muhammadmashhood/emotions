<?php

use App\Domain\Forms\FormSettings;
use App\Domain\Scoring\MappedField;
use App\Domain\Scoring\MappedSection;
use App\Domain\Scoring\ScoringEngine;
use App\Domain\Scoring\ScoringMapping;
use App\Domain\Typeform\AnswerNormalizer;
use App\Enums\Rag;

function mapping(array $sections, array $fields): ScoringMapping
{
    $s = [];
    foreach ($sections as $i => [$id, $weight]) {
        $s[] = new MappedSection($id, "Section $id", $i, $weight);
    }
    $f = [];
    foreach ($fields as [$fid, $type, $sectionId, $scored, $min, $max]) {
        $f[$fid] = new MappedField(null, $fid, $type, $sectionId, $scored, $min, $max);
    }

    return new ScoringMapping($s, $f);
}

function answers(array $raw): array
{
    return (new AnswerNormalizer)->normalizeAll(array_map(fn ($a) => $a + ['field' => ['id' => $a['fid'], 'type' => 'x']], $raw));
}

$engine = new ScoringEngine;
$settings = new FormSettings;

test('scales are normalised so 1–5 and 0–10 both map to percent', function () use ($engine, $settings) {
    $m = mapping([[1, 1.0]], [
        ['rating', 'rating', 1, true, 1, 5],
        ['scale', 'opinion_scale', 1, true, 0, 10],
        ['nps', 'nps', 1, true, 0, 10],
    ]);
    $r = $engine->score($m, answers([
        ['fid' => 'rating', 'type' => 'number', 'number' => 4],   // 3/4
        ['fid' => 'scale', 'type' => 'number', 'number' => 7],    // 7/10
        ['fid' => 'nps', 'type' => 'number', 'number' => 10],     // 10/10
    ]), $settings);

    expect($r->answerPoints['rating'])->toBe(['points' => 3.0, 'max_points' => 4.0])
        ->and($r->answerPoints['scale'])->toBe(['points' => 7.0, 'max_points' => 10.0])
        ->and($r->sections[0]->points)->toBe(20.0)->and($r->sections[0]->maxPoints)->toBe(24.0)
        ->and($r->sections[0]->pct)->toBe(83.33)
        ->and($r->overallPct)->toBe(83.33)
        ->and($r->rag)->toBe(Rag::Green);

    // a 1 on a 1–5 scale is 0 %, a 5 is 100 %
    $low = $engine->score($m, answers([['fid' => 'rating', 'type' => 'number', 'number' => 1]]), $settings);
    expect($low->sections[0]->pct)->toBe(0.0)->and($low->rag)->toBe(Rag::Red);
    $high = $engine->score($m, answers([['fid' => 'rating', 'type' => 'number', 'number' => 5]]), $settings);
    expect($high->sections[0]->pct)->toBe(100.0);
});

test('yes, no and not-applicable', function () use ($engine) {
    $settings = new FormSettings(yesNoPoints: 10);
    $m = mapping([[1, 1.0]], [
        ['yn', 'yes_no', 1, true, null, null],
        ['dd', 'dropdown', 1, true, null, null],
        ['na', 'dropdown', 1, true, null, null],
    ]);
    $r = $engine->score($m, answers([
        ['fid' => 'yn', 'type' => 'boolean', 'boolean' => false],
        ['fid' => 'dd', 'type' => 'choice', 'choice' => ['label' => 'Yes']],
        ['fid' => 'na', 'type' => 'choice', 'choice' => ['label' => 'Not applicable']],
    ]), $settings);

    expect($r->answerPoints['yn'])->toBe(['points' => 0.0, 'max_points' => 10.0])
        ->and($r->answerPoints['dd'])->toBe(['points' => 10.0, 'max_points' => 10.0])
        ->and($r->answerPoints['na'])->toBeNull()
        ->and($r->sections[0]->pct)->toBe(50.0);

    $custom = $engine->score($m, answers([['fid' => 'yn', 'type' => 'boolean', 'boolean' => true]]), new FormSettings(yesNoPoints: 4));
    expect($custom->answerPoints['yn'])->toBe(['points' => 4.0, 'max_points' => 4.0]);
});

test('skipped, unscored and unknown fields are not scored', function () use ($engine, $settings) {
    $m = mapping([[1, 1.0]], [
        ['a', 'opinion_scale', 1, true, 0, 10],
        ['b', 'opinion_scale', 1, false, 0, 10],
        ['c', 'long_text', 1, false, null, null],
    ]);
    $r = $engine->score($m, answers([
        ['fid' => 'b', 'type' => 'number', 'number' => 10],
        ['fid' => 'c', 'type' => 'text', 'text' => 'hi'],
        ['fid' => 'zzz', 'type' => 'number', 'number' => 10],
    ]), $settings);

    expect($r->answerPoints)->toBe(['b' => null, 'c' => null, 'zzz' => null])
        ->and($r->sections[0]->pct)->toBeNull()
        ->and($r->overallPct)->toBeNull()
        ->and($r->rag)->toBeNull();
});

test('unanswered sections have null pct and are excluded from the weighted overall', function () use ($engine, $settings) {
    $m = mapping([[1, 1.0], [2, 1.0], [3, 2.0]], [
        ['a', 'opinion_scale', 1, true, 0, 10],
        ['b', 'opinion_scale', 2, true, 0, 10],
        ['c', 'opinion_scale', 3, true, 0, 10],
    ]);
    $r = $engine->score($m, answers([
        ['fid' => 'a', 'type' => 'number', 'number' => 6],   // 60 %, w1
        ['fid' => 'c', 'type' => 'number', 'number' => 9],   // 90 %, w2
    ]), $settings);

    expect($r->sections[1]->pct)->toBeNull()
        ->and($r->overallPct)->toBe(80.0)   // (60×1 + 90×2) / 3
        ->and($r->points)->toBe(15.0)->and($r->maxPoints)->toBe(20.0);
});

test('weight 0 sections are scored but excluded from overall', function () use ($engine, $settings) {
    $m = mapping([[1, 0.0], [2, 1.0]], [
        ['a', 'opinion_scale', 1, true, 0, 10],
        ['b', 'opinion_scale', 2, true, 0, 10],
    ]);
    $r = $engine->score($m, answers([
        ['fid' => 'a', 'type' => 'number', 'number' => 0],
        ['fid' => 'b', 'type' => 'number', 'number' => 10],
    ]), $settings);

    expect($r->sections[0]->pct)->toBe(0.0)->and($r->overallPct)->toBe(100.0);

    $onlyZero = $engine->score(mapping([[1, 0.0]], [['a', 'opinion_scale', 1, true, 0, 10]]), answers([['fid' => 'a', 'type' => 'number', 'number' => 5]]), $settings);
    expect($onlyZero->overallPct)->toBeNull();
});

test('RAG thresholds at the boundaries', function (float $pct, ?Rag $expected) {
    expect(Rag::fromPct($pct, 80, 60))->toBe($expected);
})->with([
    [59.99, Rag::Red],
    [60.0, Rag::Amber],
    [79.99, Rag::Amber],
    [80.0, Rag::Green],
    [100.0, Rag::Green],
    [0.0, Rag::Red],
]);

test('RAG uses the form settings thresholds', function () use ($engine) {
    $m = mapping([[1, 1.0]], [['a', 'opinion_scale', 1, true, 0, 10]]);
    $r = $engine->score($m, answers([['fid' => 'a', 'type' => 'number', 'number' => 7]]), new FormSettings(ragGreenMin: 70, ragAmberMin: 50));
    expect($r->rag)->toBe(Rag::Green);
});

test('out-of-range scale values are clamped', function () use ($engine, $settings) {
    $m = mapping([[1, 1.0]], [['a', 'rating', 1, true, 1, 5]]);
    expect($engine->score($m, answers([['fid' => 'a', 'type' => 'number', 'number' => 0]]), $settings)->answerPoints['a']['points'])->toBe(0.0);
    expect($engine->score($m, answers([['fid' => 'a', 'type' => 'number', 'number' => 9]]), $settings)->answerPoints['a']['points'])->toBe(4.0);
});
