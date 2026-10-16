<?php

namespace App\Domain\Typeform\Data;

final class ParsedSection
{
    /** @param list<ParsedField> $fields */
    public function __construct(
        public string $name,
        public ?string $sourceRef,
        public array $fields = [],
    ) {}

    public function add(ParsedField $field): void
    {
        $this->fields[] = $field;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }
}
