<?php

namespace App\Http\Controllers;

use App\Enums\BranchStatus;
use App\Enums\Role;
use App\Http\Requests\BranchRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Services\BranchDeletionService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Filiallarni faqat sAdmin boshqaradi (route darajasida `role:sadmin`). */
class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount([
            'users as staff_count' => fn ($q) => $q->whereIn('role', [Role::Admin, Role::Manager, Role::Teacher, Role::Operator]),
            'users as students_count' => fn ($q) => $q->where('role', Role::Student),
        ])->orderByRaw("status = 'closed'")->orderBy('name')->get();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.form', ['branch' => new Branch]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $centerId = \App\Support\CenterContext::id() ?: (int) $data['center_id'];
        if (($center = \App\Models\Center::find($centerId)) && ! $center->is_demo) {
            app(\App\Services\SubscriptionService::class)->assertCanAddBranch($center);
        }

        $branch = Branch::create([
            ...$data,
            'center_id' => \App\Support\CenterContext::id() ?: (int) $data['center_id'],
            'code' => Branch::makeCode($data['name']),
            'status' => BranchStatus::Active,
            'opened_at' => now(),
        ]);

        AuditLog::record('branch.created', $branch, "Yangi filial ochildi: {$branch->name}");

        return redirect()->route('branches.index')->with('success', "«{$branch->name}» filiali ochildi.");
    }

    public function edit(Branch $branch)
    {
        return view('branches.form', compact('branch'));
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $data = $request->validated();

        $branch->update($data);

        AuditLog::record('branch.updated', $branch, "Filial ma'lumotlari yangilandi: {$branch->name}");

        return redirect()->route('branches.index')->with('success', "«{$branch->name}» ma'lumotlari saqlandi.");
    }

    public function close(Request $request, Branch $branch): RedirectResponse
    {
        $request->validate(['closed_reason' => ['nullable', 'string', 'max:255']]);

        $branch->update([
            'status' => BranchStatus::Closed,
            'closed_at' => now(),
            'closed_reason' => $request->input('closed_reason'),
        ]);

        AuditLog::record('branch.closed', $branch, "Filial yopildi: {$branch->name}");

        return redirect()->route('branches.index')->with('success', "«{$branch->name}» yopildi va arxivga o'tkazildi.");
    }

    public function reopen(Branch $branch): RedirectResponse
    {
        if (($center = \App\Models\Center::find($branch->center_id)) && ! $center->is_demo) {
            app(\App\Services\SubscriptionService::class)->assertCanAddBranch($center);
        }

        $branch->update(['status' => BranchStatus::Active, 'closed_at' => null, 'closed_reason' => null]);

        AuditLog::record('branch.reopened', $branch, "Filial qayta ochildi: {$branch->name}");

        return redirect()->route('branches.index')->with('success', "«{$branch->name}» qayta ochildi.");
    }

    /**
     * v10 (7-band): filialni BUTUNLAY o'chirish - barcha tegishli ma'lumot bilan birga,
     * qaytarib bo'lmaydigan tarzda. Xato bosishdan himoya: filial nomi ANIQ mos kelishi kerak.
     */
    public function destroy(Request $request, Branch $branch, BranchDeletionService $deletion): RedirectResponse
    {
        $request->validate(['confirm_name' => ['required', 'string']]);

        if ($request->input('confirm_name') !== $branch->name) {
            throw ValidationException::withMessages(['confirm_name' => "Filial nomi noto'g'ri kiritildi. O'chirish uchun nomni ANIQ mos holda yozing."]);
        }

        $name = $branch->name;
        $deletion->delete($branch);

        return redirect()->route('branches.index')->with('success', "«{$name}» filiali va unga tegishli barcha ma'lumot butunlay o'chirildi.");
    }

    /** sAdmin ishlayotgan filialni almashtiradi (bo'sh qiymat - barcha filiallar). */
    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['nullable', 'integer', \App\Support\CenterContext::existsBranch(false)]]);
        $user = $request->user();

        if (! $user->isCenterWide()) {
            // v13: faqat qo'shimcha filial berilgan operator, faqat o'ziga ruxsat etilgan filiallarga o'ta oladi
            abort_unless($user->role === \App\Enums\Role::Operator && BranchContext::extraBranchIds($user) !== [], 403);
            $id = (int) ($data['branch_id'] ?? $user->branch_id);
            abort_unless(in_array($id, BranchContext::accessibleBranchIds($user), true), 403);
            $data['branch_id'] = $id;
        }

        BranchContext::select($data['branch_id'] ?? null);

        // v13: filial almashganda har doim bosh sahifaga o'tiladi - aks holda oldingi filialning sahifasi (masalan, /groups/6)
        // yangi filialda 404 berardi.
        return redirect()->route('dashboard');
    }
}
