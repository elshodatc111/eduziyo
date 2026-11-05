<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\CenterContext;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() || ($this->user()?->role === \App\Enums\Role::Owner);
    }

    public function rules(): array
    {
        $branch = $this->route('branch');
        $branchId = $branch?->id;
        $ctx = CenterContext::id();
        // Filial nomi markaz ichida takrorlanmaydi (turli markazlarda bir xil nom bo'lishi mumkin)
        $centerId = $branch?->center_id ?? ($ctx ?: (int) $this->input('center_id'));

        return [
            'center_id' => $branch ? ['prohibited'] : ($ctx ? ['nullable'] : ['required', Rule::exists('centers', 'id')->where('status', '!=', 'deleted')]),
            'name' => ['required', 'string', 'max:120', Rule::unique('branches', 'name')->where('center_id', $centerId)->ignore($branchId)],
            'phone' => ['nullable', 'string', new \App\Rules\UzPhone],
            'address' => ['nullable', 'string', 'max:255'],
            'eskiz_email' => ['nullable', 'email', 'max:255'],
            'eskiz_password' => ['nullable', 'string', 'max:255'],
            'eskiz_from' => ['nullable', 'string', 'max:30'],
            // v9: ochiq murojaat sahifasida Edunova o'rniga ko'rsatiladigan filial rangi va ma'lumoti.
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'public_about' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'center_id' => 'Markaz',
            'name' => 'Filial nomi',
            'phone' => 'Telefon',
            'address' => 'Manzil',
            'eskiz_email' => 'Eskiz email',
            'eskiz_password' => 'Eskiz paroli',
            'eskiz_from' => 'Eskiz yuboruvchi nomi',
            'brand_color' => 'Rang',
            'public_about' => "Filial haqida ma'lumot",
        ];
    }
}
