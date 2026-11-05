<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Rules\UniquePhonePerRole;
use App\Rules\UzPhone;
use App\Support\BranchContext;
use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new UzPhone, new UniquePhonePerRole($this->route('student')?->branch_id ?? BranchContext::id(), Role::Student, $this->route('student')?->id)],
            'phone2' => ['nullable', 'string', new UzPhone],
            'birthday' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'lead_source_id' => ['nullable', BranchContext::exists('lead_sources')],
            'guardian_name' => $this->needsGuardian() ? ['required', 'string', 'max:160'] : ['nullable', 'string', 'max:160'],
            'guardian_consent' => $this->needsGuardian() ? ['required', 'accepted'] : ['nullable', 'boolean'],
        ];
    }

    /** 18 yoshgacha o'quvchi uchun ota-ona/qonuniy vakil roziligi kerak (allaqachon olingan bo'lsa qayta so'ralmaydi). */
    private function needsGuardian(): bool
    {
        $birthday = $this->input('birthday');
        if (! $birthday || strtotime((string) $birthday) === false) {
            return false;
        }
        if (! \Illuminate\Support\Carbon::parse($birthday)->gt(now()->subYears(18))) {
            return false;
        }

        return ! $this->route('student')?->guardian_consent_at;
    }

    /** Rozilik belgisi o'rniga vaqt yoziladi. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $consent = (bool) ($data['guardian_consent'] ?? false);
        unset($data['guardian_consent']);
        if ($consent) {
            $data['guardian_consent_at'] = now();
        } else {
            unset($data['guardian_name']); // mavjud rozilik ma'lumoti ustiga yozilmaydi
        }

        return $key ? data_get($data, $key, $default) : $data;
    }

    public function attributes(): array
    {
        return [
            'name' => 'F.I.O', 'phone' => 'Telefon', 'phone2' => "Qo'shimcha telefon", 'birthday' => "Tug'ilgan sana",
            'address' => 'Manzil', 'about' => 'Eslatma', 'lead_source_id' => 'Manba',
            'guardian_name' => 'Ota-ona (vakil) F.I.O', 'guardian_consent' => 'Ota-ona roziligi',
        ];
    }
}
