<?php

use App\Enums\SubmissionStatus;
use App\Livewire\Admin\Review;
use App\Livewire\Hospital\Evaluations\Index;
use App\Livewire\Hospital\Evaluations\Show;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use Livewire\Livewire;

beforeEach(function () {
    $this->hospital = Hospital::factory()->requiresReview()->create(['name' => 'Ittefaq Hospital']);
    $this->form = Form::factory()->for($this->hospital)->create();
    $this->version = FormVersion::factory()->create(['form_id' => $this->form->id]);
    $this->form->forceFill(['current_version_id' => $this->version->id])->save();
    $this->pending = Submission::factory()->pendingReview()->create(['form_id' => $this->form->id, 'form_version_id' => $this->version->id, 'hospital_id' => $this->hospital->id, 'submitted_at' => now()->subDay(), 'info' => ['facility' => 'Pending Place']]);
});

test('pending evaluations are queued, hidden from hospital users, and approving reveals them', function () {
    $viewer = viewer($this->hospital);
    Livewire::actingAs($viewer)->test(Index::class)->assertDontSee('Pending Place');

    Livewire::actingAs(superAdmin())
        ->test(Review::class)
        ->assertSee('Pending Place')
        ->assertSee('1 evaluation waiting')
        ->set("notes.{$this->pending->id}", 'Looks genuine')
        ->call('approve', $this->pending->id)
        ->assertSee('Nothing to review');

    $this->pending->refresh();
    expect($this->pending->status)->toBe(SubmissionStatus::Approved)
        ->and($this->pending->review_note)->toBe('Looks genuine')
        ->and($this->pending->reviewed_by)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'submission.approved', 'subject_id' => $this->pending->id]);

    Livewire::actingAs($viewer)->test(Index::class)->assertSee('Pending Place');
});

test('rejected evaluations stay hidden and are audited with the note', function () {
    Livewire::actingAs(superAdmin())
        ->test(Review::class)
        ->set("notes.{$this->pending->id}", 'Duplicate visit')
        ->call('reject', $this->pending->id);

    expect($this->pending->fresh()->status)->toBe(SubmissionStatus::Rejected);
    $this->assertDatabaseHas('audit_logs', ['action' => 'submission.rejected', 'subject_id' => $this->pending->id]);
    Livewire::actingAs(viewer($this->hospital))->test(Index::class)->assertDontSee('Pending Place');
    $this->actingAs(viewer($this->hospital))->get(route('evaluations.show', $this->pending))->assertNotFound();
});

test('the review queue filters by hospital and is admin-only', function () {
    $other = Hospital::factory()->requiresReview()->create(['name' => 'CMH']);
    $otherForm = Form::factory()->for($other)->create();
    $otherVersion = FormVersion::factory()->create(['form_id' => $otherForm->id]);
    Submission::factory()->pendingReview()->create(['form_id' => $otherForm->id, 'form_version_id' => $otherVersion->id, 'hospital_id' => $other->id, 'info' => ['facility' => 'CMH Place']]);

    Livewire::actingAs(superAdmin())
        ->test(Review::class)
        ->assertSee('Pending Place')->assertSee('CMH Place')
        ->set('hospital', $this->hospital->uuid)
        ->assertSee('Pending Place')->assertDontSee('CMH Place');

    $this->actingAs(hospitalAdmin($this->hospital))->get(route('admin.review'))->assertForbidden();
});

test('review decisions can also be made from the evaluation detail page', function () {
    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['evaluation' => $this->pending])
        ->set('reviewNote', 'ok')
        ->call('approve');

    expect($this->pending->fresh()->status)->toBe(SubmissionStatus::Approved);
});
