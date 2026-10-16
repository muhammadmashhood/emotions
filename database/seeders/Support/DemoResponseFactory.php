<?php

namespace Database\Seeders\Support;

use App\Models\FormVersion;
use Carbon\CarbonImmutable;

/**
 * Builds realistic Typeform-shaped response payloads for a form version (demo / performance seeding).
 * Deterministic for a given seed so the demo numbers are reproducible.
 */
class DemoResponseFactory
{
    /** @var list<array<string, mixed>> */
    private array $fields;

    /** @var list<string> */
    private array $facilities;

    /** @var list<string> */
    private array $doctors = ['Dr. Farhan Siddiqui', 'Dr. Ayesha Malik', 'Dr. Imran Qureshi', 'Dr. Sana Tariq', 'Dr. Bilal Ahmed', 'Dr. Hira Shah'];

    /** @var list<string> */
    private array $agents = ['Ayesha', 'Hamza', 'Zainab', 'Usman', 'Maryam'];

    /** @var list<string> */
    private array $comments = ['Quick and friendly.', 'Waited a long time.', 'Staff were helpful.', 'Could be cleaner.', 'Excellent experience overall.', 'Billing took too long.', '', '', ''];

    /**
     * @param  list<string>  $facilities
     */
    public function __construct(FormVersion $version, array $facilities, private readonly float $quality, int $seed)
    {
        $this->fields = $version->fields()->with('section')->orderBy('display_order')->get()->map(fn ($f) => [
            'id' => $f->typeform_field_id, 'ref' => $f->typeform_ref, 'type' => $f->type, 'title' => $f->title,
            'section' => $f->section->name, 'min' => $f->scale_min, 'max' => $f->scale_max, 'choices' => $f->choices,
        ])->all();
        $this->facilities = $facilities;
        mt_srand($seed);
    }

    /**
     * @return array<string, mixed> a `form_response`-shaped payload
     */
    public function make(CarbonImmutable $submittedAt, int $index): array
    {
        $phone = mt_rand(0, 99) < 55;
        $skipSection = $phone ? 'Website/ App booking' : 'Phone booking';
        // Per-visit mood shifts everything a little; some sections are systematically weaker.
        $visitBias = (mt_rand(-15, 15)) / 100;
        $sectionBias = ['Waiting Area' => -0.12, 'Pharmacy & Billing' => -0.08, 'Doctor Consultation' => 0.08, 'Nursing Staff' => 0.05];

        $answers = [];
        foreach ($this->fields as $f) {
            if ($f['section'] === $skipSection) {
                continue;
            }
            if ($f['title'] === 'The website/ App was easy to access' && $phone) {
                continue;
            }
            $p = max(0.05, min(0.98, $this->quality + $visitBias + ($sectionBias[$f['section']] ?? 0)));
            $answer = $this->answerFor($f, $p, $phone);
            if ($answer !== null) {
                $answers[] = $answer + ['field' => ['id' => $f['id'], 'type' => $f['type'], 'ref' => $f['ref']]];
            }
        }

        return [
            'form_id' => 'demo',
            'token' => sprintf('demo%06d%s', $index, substr(md5((string) $index), 0, 10)),
            'landed_at' => $submittedAt->subMinutes(mt_rand(8, 25))->toIso8601ZuluString(),
            'submitted_at' => $submittedAt->toIso8601ZuluString(),
            'answers' => $answers,
            'hidden' => ['branch' => ['Main campus', 'North wing', 'City clinic'][mt_rand(0, 2)]],
            'calculated' => ['score' => mt_rand(-3, 12)],
            'variables' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>|null
     */
    private function answerFor(array $f, float $p, bool $phone): ?array
    {
        switch ($f['type']) {
            case 'opinion_scale':
            case 'rating':
            case 'nps':
                $min = (int) $f['min'];
                $max = (int) $f['max'];
                $value = (int) round($min + ($max - $min) * $this->beta($p));

                return ['type' => 'number', 'number' => $value];
            case 'yes_no':
                return ['type' => 'boolean', 'boolean' => mt_rand(0, 99) < $p * 100];
            case 'dropdown':
            case 'multiple_choice':
                $choices = $f['choices'] ?? [];
                if ($choices === []) {
                    return null;
                }
                if ($f['title'] === 'Appointment Booking method') {
                    $label = $phone ? 'Phone call' : (mt_rand(0, 4) === 0 ? 'Walk-in' : 'Website/ App');
                } elseif ($f['title'] === 'Location') {
                    $label = $choices[mt_rand(0, count($choices) - 1)]['label'];
                } else {
                    $roll = mt_rand(0, 99);
                    $label = $roll < 10 ? 'N/A' : ($roll < 10 + $p * 90 ? 'Yes' : 'No');
                    $labels = array_column($choices, 'label');
                    if (! in_array($label, $labels, true)) {
                        $label = $labels[0];
                    }
                }
                $choice = array_values(array_filter($choices, fn ($c) => $c['label'] === $label))[0];

                return ['type' => 'choice', 'choice' => ['id' => $choice['id'], 'ref' => $choice['ref'], 'label' => $choice['label']]];
            case 'short_text':
                if (str_contains($f['title'], 'Facility')) {
                    return ['type' => 'text', 'text' => $this->facilities[mt_rand(0, count($this->facilities) - 1)]];
                }
                if (str_contains($f['title'], 'Doctor')) {
                    return ['type' => 'text', 'text' => $this->doctors[mt_rand(0, count($this->doctors) - 1)]];
                }

                return ['type' => 'text', 'text' => $this->agents[mt_rand(0, count($this->agents) - 1)]];
            case 'long_text':
                $c = $this->comments[mt_rand(0, count($this->comments) - 1)];

                return $c === '' ? null : ['type' => 'text', 'text' => $c];
            case 'date':
                return ['type' => 'date', 'date' => CarbonImmutable::now()->subDays(mt_rand(0, 3))->format('Y-m-d')];
            case 'file_upload':
                return mt_rand(0, 3) === 0 ? ['type' => 'file_url', 'file_url' => 'https://api.typeform.com/responses/files/demo/receipt.jpg'] : null;
            default:
                return null;
        }
    }

    /** Skewed 0–1 random value whose mean is roughly $p. */
    private function beta(float $p): float
    {
        $u = (mt_rand(0, 10000) / 10000 + mt_rand(0, 10000) / 10000) / 2; // triangular, mean 0.5
        $v = $p + ($u - 0.5) * 0.7;

        return max(0.0, min(1.0, $v));
    }
}
