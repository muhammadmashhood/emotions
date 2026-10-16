<?php

namespace App\Domain\Typeform\Data;

final readonly class ParsedStructure
{
    /** @param list<ParsedSection> $sections */
    public function __construct(public array $sections) {}

    /** @return list<ParsedField> grouped by section, section order */
    public function allFields(): array
    {
        $all = [];
        foreach ($this->sections as $section) {
            foreach ($section->fields as $field) {
                $all[] = $field;
            }
        }

        return $all;
    }

    /** @return list<ParsedField> in form (display) order */
    public function allFieldsInOrder(): array
    {
        $all = $this->allFields();
        usort($all, fn (ParsedField $a, ParsedField $b) => $a->displayOrder <=> $b->displayOrder);

        return $all;
    }

    /** @return list<string> */
    public function fieldIds(): array
    {
        return array_map(fn (ParsedField $f) => $f->id, $this->allFields());
    }
}
