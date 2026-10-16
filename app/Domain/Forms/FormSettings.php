<?php

namespace App\Domain\Forms;

final readonly class FormSettings
{
    /**
     * @param  array{type:string,field_id?:string,key?:string}|null  $branchSource
     */
    public function __construct(
        public float $ragGreenMin = 80,
        public float $ragAmberMin = 60,
        public float $yesNoPoints = 10,
        public bool $includePartial = false,
        public ?array $branchSource = null,
    ) {}

    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            ragGreenMin: (float) ($data['rag_green_min'] ?? 80),
            ragAmberMin: (float) ($data['rag_amber_min'] ?? 60),
            yesNoPoints: (float) ($data['yes_no_points'] ?? 10),
            includePartial: (bool) ($data['include_partial'] ?? false),
            branchSource: $data['branch_source'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'rag_green_min' => $this->ragGreenMin,
            'rag_amber_min' => $this->ragAmberMin,
            'yes_no_points' => $this->yesNoPoints,
            'include_partial' => $this->includePartial,
            'branch_source' => $this->branchSource,
        ];
    }

    public function with(array $changes): self
    {
        return self::fromArray(array_merge($this->toArray(), $changes));
    }
}
