<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Support\BranchContext;
use App\Support\CenterContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** sAdmin ishlayotgan markazni almashtiradi (bo'sh qiymat - barcha markazlar). Markazlar CRUD'i keyingi bosqichda (E4). */
class CenterController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate(['center_id' => ['nullable', 'integer', Rule::exists('centers', 'id')->where('status', '!=', 'deleted')]]);

        CenterContext::select($data['center_id'] ?? null);
        // Markaz almashganda oldingi markazning filiali tanlangan bo'lib qolmasligi kerak
        BranchContext::select(null);

        return redirect()->route('dashboard');
    }
}
