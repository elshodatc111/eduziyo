<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Direktor filialsiz foydalanuvchi (branch_id = null): parametrsiz barcha veb-sahifalar 500 xato bermasligi kerak
 * (masalan, `$user->branch->name` kabi null murojaatlar). "Barcha filiallar" va bitta filial rejimida tekshiriladi.
 */
class TenancyOwnerSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function getUrls(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => in_array('GET', $r->methods(), true)
                && ! str_contains($r->uri(), '{')
                && ! str_starts_with($r->uri(), 'api/')
                && ! in_array($r->uri(), ['up', 'logout', 'login', 'forgot-password', 'reset-password'], true)
                && ! str_contains($r->uri(), 'download') && ! str_contains($r->uri(), 'export') && ! str_contains($r->uri(), 'template'))
            ->map(fn (Route $r) => '/'.ltrim($r->uri(), '/'))
            ->unique()->values()->all();
    }

    private function makeData(): array
    {
        $center = $this->newCenter('Markaz');
        $b1 = $this->branch('Birinchi', $center);
        $b2 = $this->branch('Ikkinchi', $center);
        $this->student($b1, ['name' => 'Talaba Bir']);
        $this->student($b2, ['name' => 'Talaba Ikki']);
        $this->catalog($b1);

        return [$center, $b1];
    }

    public function test_owner_pages_do_not_crash_in_all_branches_mode(): void
    {
        [$center] = $this->makeData();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $center->id]);
        $bad = [];

        foreach ($this->getUrls() as $url) {
            $status = $this->actingAs($owner)->get($url)->getStatusCode();
            if ($status >= 500) {
                $bad[] = "{$url} → {$status}";
            }
        }

        $this->assertSame([], $bad, "500 xato: \n".implode("\n", $bad));
    }

    public function test_owner_pages_do_not_crash_with_selected_branch(): void
    {
        [$center, $b1] = $this->makeData();
        $owner = $this->user(Role::Owner, null, [], ['center_id' => $center->id]);
        $bad = [];

        foreach ($this->getUrls() as $url) {
            $status = $this->actingAs($owner)->withSession(['current_branch_id' => $b1->id])->get($url)->getStatusCode();
            if ($status >= 500) {
                $bad[] = "{$url} → {$status}";
            }
        }

        $this->assertSame([], $bad, "500 xato: \n".implode("\n", $bad));
    }
}
