<?php

use Livewire\Volt\Volt;

/**
 * The sidebar is one Livewire component. Its root element must wrap the whole sidebar, otherwise
 * wire:click="logout" sits outside the component and clicking it does nothing (regression seen 19 Nov 2026).
 */
function logoutButtonIsInsideComponentRoot(string $html): bool
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    $roots = (new DOMXPath($dom))->query("//*[@*[name()='wire:id']]");
    if ($roots === false || $roots->length !== 1) {
        return false;
    }
    /** @var DOMElement $root */
    $root = $roots->item(0);

    foreach ($root->getElementsByTagName('button') as $button) {
        if ($button->getAttribute('wire:click') === 'logout') {
            return true;
        }
    }

    return false;
}

test('the log out button is inside the sidebar component for staff', function () {
    $this->actingAs(superAdmin());

    expect(logoutButtonIsInsideComponentRoot(Volt::test('layout.navigation')->html()))->toBeTrue();
});

test('the log out button is inside the sidebar component for hospital users', function () {
    $this->actingAs(hospitalAdmin());

    expect(logoutButtonIsInsideComponentRoot(Volt::test('layout.navigation')->html()))->toBeTrue();
});
