<?php

namespace App\Domain\Dashboard;

use Carbon\CarbonImmutable;

final readonly class DashboardFilters
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $formId = null,
        public ?int $branchId = null,
        public string $preset = '90d',
    ) {}

    /**
     * @param  string  $preset  30d | 90d | 12m | custom
     */
    public static function fromRequest(string $preset = '90d', ?string $from = null, ?string $to = null, ?int $formId = null, ?int $branchId = null, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        $end = $now->endOfDay();

        [$start, $end, $preset] = match ($preset) {
            '30d' => [$end->subDays(29)->startOfDay(), $end, '30d'],
            '12m' => [$end->subMonths(12)->addDay()->startOfDay(), $end, '12m'],
            'custom' => (function () use ($from, $to, $end): array {
                $f = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->subDays(89)->startOfDay();
                $t = $to ? CarbonImmutable::parse($to)->endOfDay() : $end;
                if ($t->lessThan($f)) {
                    [$f, $t] = [$t->startOfDay(), $f->endOfDay()];
                }

                return [$f, $t, 'custom'];
            })(),
            default => [$end->subDays(89)->startOfDay(), $end, '90d'],
        };

        return new self($start, $end, $formId, $branchId, $preset);
    }

    public function previous(): self
    {
        $days = $this->lengthInDays();

        return new self($this->from->subDays($days), $this->from->subSecond(), $this->formId, $this->branchId, $this->preset);
    }

    public function lengthInDays(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /** week when the range is ≤ 6 months, month otherwise */
    public function bucket(): string
    {
        return $this->lengthInDays() > 183 ? 'month' : 'week';
    }

    public function withForm(?int $formId): self
    {
        return new self($this->from, $this->to, $formId, $this->branchId, $this->preset);
    }

    public function label(): string
    {
        return match ($this->preset) {
            '30d' => 'last 30 days',
            '12m' => 'last 12 months',
            'custom' => $this->from->format('d M Y').' – '.$this->to->format('d M Y'),
            default => 'last 90 days',
        };
    }
}
