<?php

namespace App\Support;

use App\Enums\Rag;

/**
 * Presentation of a score band (eMotion design): Good / Watch / Poor with the
 * design's text, tint and bar colours. Thresholds come from the form settings.
 */
final class ScoreBand
{
    public const HEX = ['good' => '#C2B700', 'watch' => '#E3A048', 'poor' => '#F44336'];

    private function __construct(public readonly ?string $key) {}

    public static function of(float|int|string|null $pct, float $green = 80, float $amber = 60): self
    {
        return self::fromRag(Rag::fromPct($pct === null ? null : (float) $pct, $green, $amber));
    }

    public static function fromRag(?Rag $rag): self
    {
        return new self(match ($rag) {
            Rag::Green => 'good',
            Rag::Amber => 'watch',
            Rag::Red => 'poor',
            null => null,
        });
    }

    public function label(): string
    {
        return match ($this->key) {
            'good' => 'Good', 'watch' => 'Watch', 'poor' => 'Poor', default => 'No score'
        };
    }

    /** Pill tone for <x-ui.pill>. */
    public function tone(): string
    {
        return match ($this->key) {
            'good' => 'ok', 'watch' => 'warn', 'poor' => 'bad', default => 'muted'
        };
    }

    public function text(): string
    {
        return match ($this->key) {
            'good' => 'text-em-ok', 'watch' => 'text-em-warn', 'poor' => 'text-em-bad', default => 'text-em-mute'
        };
    }

    public function tint(): string
    {
        return match ($this->key) {
            'good' => 'bg-em-ok-bg', 'watch' => 'bg-em-warn-bg', 'poor' => 'bg-em-bad-bg', default => 'bg-em-muted-bg'
        };
    }

    public function bar(): string
    {
        return match ($this->key) {
            'good' => 'bg-rag-green', 'watch' => 'bg-rag-amber', 'poor' => 'bg-rag-red', default => 'bg-rag-neutral'
        };
    }

    public function hex(): string
    {
        return self::HEX[$this->key] ?? '#D9D3D9';
    }

    /** Heat-map cell: soft tints, with poor scores solid so they stand out. */
    public function cell(): string
    {
        return match ($this->key) {
            'good' => 'bg-em-ok-bg text-em-ok',
            'watch' => 'bg-em-warn-bg text-em-warn',
            'poor' => 'bg-em-bad text-white',
            default => 'bg-em-muted-bg text-em-mute',
        };
    }
}
