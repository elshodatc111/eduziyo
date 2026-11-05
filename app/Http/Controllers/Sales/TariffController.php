<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Tariflar (3 ta): faqat sAdmin tahrirlaydi. Yangi tarif qo'shish/o'chirish yo'q — soni qat'iy 3. */
class TariffController extends Controller
{
    public function index()
    {
        return view('sales.tariffs.index', ['tariffs' => Tariff::orderBy('sort_order')->get()]);
    }

    public function edit(Tariff $tariff)
    {
        return view('sales.tariffs.form', ['tariff' => $tariff]);
    }

    public function update(Request $request, Tariff $tariff): RedirectResponse
    {
        // "money" maydonlari bo'shliq bilan keladi ("199 000")
        foreach (['price_monthly', 'price_yearly'] as $k) {
            if ($request->has($k)) {
                $request->merge([$k => preg_replace('/\D/', '', (string) $request->input($k)) ?: null]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'price_monthly' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'price_yearly' => ['nullable', 'integer', 'min:1', 'max:10000000000'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_students' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'sms_monthly' => ['required', 'integer', 'min:0', 'max:10000000'],
            'ai_monthly' => ['required', 'integer', 'min:0', 'max:10000000'],
            'features' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
        ], [], [
            'name' => 'Nomi', 'tagline' => 'Qisqa tavsif', 'price_monthly' => 'Oylik narx', 'price_yearly' => 'Yillik narx',
            'max_branches' => 'Filial limiti', 'max_students' => "O'quvchi limiti", 'sms_monthly' => 'SMS limiti', 'ai_monthly' => 'AI limiti',
            'features' => 'Imkoniyatlar', 'sort_order' => 'Tartib',
        ]);

        if (! empty($data['price_yearly']) && empty($data['price_monthly'])) {
            return back()->withInput()->withErrors(['price_monthly' => 'Yillik narx uchun avval oylik narxni kiriting.']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $featured = $request->boolean('is_featured');
        $tariff->update($data + ['is_featured' => $featured]);

        // Faqat bitta tarif "Mashhur" bo'ladi
        if ($featured) {
            Tariff::where('id', '!=', $tariff->id)->update(['is_featured' => false]);
        }

        return redirect()->route('sales.tariffs')->with('success', "«{$tariff->name}» tarifi saqlandi.");
    }
}
