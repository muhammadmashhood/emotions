<?php

namespace App\Domain\Scoring;

final readonly class MappedField
{
    public function __construct(
        public ?int $id,
        public string $typeformFieldId,
        public string $type,
        public int $sectionId,
        public bool $isScored,
        public ?int $scaleMin,
        public ?int $scaleMax,
        public ?string $infoKey = null,
        public int $displayOrder = 0,
        public string $title = '',
    ) {}

    public function isScale(): bool
    {
        return in_array($this->type, ['opinion_scale', 'rating', 'nps'], true) && $this->scaleMin !== null && $this->scaleMax !== null;
    }

    public function isYesNoChoice(): bool
    {
        return in_array($this->type, ['dropdown', 'multiple_choice'], true);
    }
}
