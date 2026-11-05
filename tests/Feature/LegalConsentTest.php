<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\LegalDocument;
use App\Models\Lead;
use App\Models\SalesApplication;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\LegalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalConsentTest extends TestCase
{
    use RefreshDatabase;

    private function publishAll(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        foreach (LegalDocument::all() as $d) {
            $d->update(['body' => str_replace('> **Qoralama.**', 'Eslatma', $d->body)]);
            app(LegalService::class)->publish($d->fresh(), $sadmin);
        }
    }

    public function test_seeded_drafts_do_not_gate_users_until_published(): void
    {
        $this->assertSame(3, LegalDocument::where('status', 'draft')->count());
        $staff = $this->user(Role::Admin);
        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
        $this->get(route('legal.privacy'))->assertOk()->assertSee("tez orada e'lon qilinadi", false);
    }

    public function test_draft_with_note_cannot_be_published(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $doc = LegalDocument::where('type', 'privacy')->first();
        $this->actingAs($sadmin)->post(route('platform.legal.publish', $doc->id))->assertSessionHas('error');
        $this->assertSame('draft', $doc->fresh()->status);
    }

    public function test_published_gate_redirects_until_accepted_and_is_logged(): void
    {
        $this->publishAll();
        $owner = $this->user(Role::Owner);
        $staff = $this->user(Role::Teacher);

        $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('legal.consent'));
        $this->get(route('legal.consent'))->assertOk()->assertSee('Maxfiylik siyosati');
        // xodimga oferta (direktorlar uchun) ko'rinmaydi
        $this->get(route('legal.consent'))->assertDontSee('Ommaviy oferta');

        $this->post(route('legal.accept'))->assertSessionHasErrors('agree');
        $this->post(route('legal.accept'), ['agree' => 1])->assertRedirect();
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(2, UserConsent::where('user_id', $staff->id)->count());

        // direktor oferta'ni ham qabul qiladi va markaz bitimi yoziladi
        $this->actingAs($owner)->post(route('legal.accept'), ['agree' => 1]);
        $this->assertSame(3, UserConsent::where('user_id', $owner->id)->count());
        $this->assertDatabaseHas('center_agreements', ['center_ref' => $owner->center_id, 'accepted_by_user' => $owner->id]);
    }

    public function test_student_can_reach_consent_and_sadmin_is_exempt(): void
    {
        $this->publishAll();
        $student = $this->student($this->branch('Talaba'));
        $this->actingAs($student)->get(route('cabinet.home'))->assertRedirect(route('legal.consent'));
        $this->get(route('legal.consent'))->assertOk();
        $this->post(route('legal.accept'), ['agree' => 1]);
        $this->get(route('cabinet.home'))->assertOk();

        $this->actingAs($this->user(Role::SAdmin))->get(route('platform.legal'))->assertOk();
    }

    public function test_refusal_logs_out_to_home(): void
    {
        $this->publishAll();
        $staff = $this->user(Role::Teacher);
        $this->actingAs($staff)->post(route('legal.accept'), ['refuse' => 1])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_new_version_requires_reacceptance_only_when_flagged(): void
    {
        $this->publishAll();
        $staff = $this->user(Role::Teacher);
        $this->actingAs($staff)->post(route('legal.accept'), ['agree' => 1]);

        $sadmin = $this->user(Role::SAdmin);
        $legal = app(LegalService::class);
        $terms = LegalDocument::where('type', 'terms')->where('version', 1)->first();

        // yangi versiya, qayta rozilik talab qilinmaydi
        $v2 = $legal->newVersionFrom($terms);
        $v2->update(['body' => 'Yangi matn', 'requires_reacceptance' => false]);
        $legal->publish($v2->fresh(), $sadmin);
        $this->actingAs($staff)->get(route('dashboard'))->assertOk();

        // keyingisi talab qiladi
        $v3 = $legal->newVersionFrom($v2->fresh());
        $v3->update(['body' => 'Yana yangi', 'requires_reacceptance' => true]);
        $legal->publish($v3->fresh(), $sadmin);
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('legal.consent'));
        $this->post(route('legal.accept'), ['agree' => 1]);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_published_document_is_immutable(): void
    {
        $this->publishAll();
        $sadmin = $this->user(Role::SAdmin);
        $doc = LegalDocument::where('type', 'terms')->first();
        $this->actingAs($sadmin)->put(route('platform.legal.update', $doc->id), ['title' => 'X', 'body' => 'Y'])->assertSessionHas('error');
        $this->assertNotSame('Y', $doc->fresh()->body);
        $this->actingAs($sadmin)->delete(route('platform.legal.destroy', $doc->id))->assertSessionHas('error');
    }

    public function test_only_sadmin_manages_documents(): void
    {
        $this->actingAs($this->user(Role::Owner))->get(route('platform.legal'))->assertForbidden();
        $this->actingAs($this->user(Role::Admin))->get(route('platform.legal.report'))->assertForbidden();
    }

    public function test_public_forms_require_and_store_consent_when_privacy_published(): void
    {
        $branch = $this->branch('Markaz');
        // e'lon qilinmagan: rozilik talab qilinmaydi
        $this->post(route('apply.store', $branch->code), ['name' => 'Ali', 'phone' => '+998 90 123 4567'])->assertSessionHasNoErrors();

        $this->publishAll();
        $this->post(route('apply.store', $branch->code), ['name' => 'Vali', 'phone' => '+998 91 123 4567'])->assertSessionHasErrors('consent');
        $this->post(route('apply.store', $branch->code), ['name' => 'Vali', 'phone' => '+998 91 123 4567', 'consent' => 1])->assertSessionHasNoErrors();
        $lead = Lead::withoutGlobalScopes()->where('name', 'VALI')->first();
        $this->assertNotNull($lead->consent_at);
        $this->assertSame('privacy v1', $lead->consent_version);

        $this->post(route('landing.apply'), ['name' => 'A', 'center_name' => 'B', 'phone' => '+998 93 123 4567'])->assertSessionHasErrors('consent');
        $this->post(route('landing.apply'), ['name' => 'A', 'center_name' => 'B', 'phone' => '+998 93 123 4567', 'consent' => 1]);
        $this->assertSame('privacy v1', SalesApplication::first()->consent_version);
    }

    public function test_guardian_consent_required_for_minor_student(): void
    {
        $admin = $this->user(Role::Admin, null, ['students.create']);
        $base = ['name' => 'Bola', 'phone' => '+998 90 555 1122', 'birthday' => now()->subYears(12)->format('Y-m-d')];

        $this->actingAs($admin)->post(route('students.store'), $base)->assertSessionHasErrors(['guardian_name', 'guardian_consent']);
        $this->post(route('students.store'), $base + ['guardian_name' => 'Ota Ona', 'guardian_consent' => 1])->assertSessionHasNoErrors();
        $s = User::withoutGlobalScopes()->where('name', 'BOLA')->first();
        $this->assertSame('Ota Ona', $s->guardian_name);
        $this->assertNotNull($s->guardian_consent_at);

        // kattalar uchun talab qilinmaydi
        $this->post(route('students.store'), ['name' => 'Katta', 'phone' => '+998 90 555 3344', 'birthday' => '1990-01-01'])->assertSessionHasNoErrors();
    }

    public function test_consent_log_survives_user_deletion_and_report_works(): void
    {
        $this->publishAll();
        $staff = $this->user(Role::Teacher);
        $this->actingAs($staff)->post(route('legal.accept'), ['agree' => 1]);
        $center = $staff->center_id;
        User::withoutGlobalScopes()->whereKey($staff->id)->delete();
        $this->assertSame(2, UserConsent::where('user_id', $staff->id)->count());

        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->get(route('platform.legal.report', ['center' => $center]))->assertOk()->assertSee('Foydalanuvchi');
        $this->get(route('platform.legal.report', ['export' => 'xlsx']))->assertOk();
    }
}
