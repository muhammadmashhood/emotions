<?php

namespace App\Domain\Typeform\Data;

use App\Domain\Typeform\FormStructureParser;

final readonly class NormalizedAnswer
{
    /**
     * @param  array<string, mixed>|null  $valueJson
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $fieldId,
        public ?string $fieldType,
        public ?string $fieldRef,
        public string $answerType,
        public ?string $valueText,
        public ?float $valueNumber,
        public ?array $valueJson,
        public array $raw,
    ) {}

    /** True when the answer is boolean true or a choice whose label is "yes". */
    public function isYes(): bool
    {
        if ($this->answerType === 'boolean') {
            return $this->valueNumber === 1.0;
        }

        return $this->answerType === 'choice' && mb_strtolower(trim((string) ($this->valueJson['label'] ?? ''))) === 'yes';
    }

    public function isNo(): bool
    {
        if ($this->answerType === 'boolean') {
            return $this->valueNumber === 0.0;
        }

        return $this->answerType === 'choice' && mb_strtolower(trim((string) ($this->valueJson['label'] ?? ''))) === 'no';
    }

    public function isNotApplicable(): bool
    {
        return $this->answerType === 'choice'
            && FormStructureParser::isNotApplicableLabel((string) ($this->valueJson['label'] ?? ''));
    }
}
