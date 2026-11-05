<?php

namespace App\Models\Concerns;

use App\Support\CenterContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Markazlar o'rtasidagi izolyatsiya: har bir so'rovga `center_id` sharti qo'shiladi. */
class CenterScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $centerId = CenterContext::id();

        if ($centerId === null) {
            return;
        }

        // 0 - markazsiz foydalanuvchi: hech narsa ko'rmaydi
        if ($centerId === 0) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $column = $model->qualifyColumn('center_id');

        // Platforma bildirishnomalari (center_id = NULL) hamma markaz foydalanuvchilariga ko'rinadi
        if (method_exists($model, 'isCenterNullVisible') && $model->isCenterNullVisible()) {
            $builder->where(fn ($q) => $q->where($column, $centerId)->orWhereNull($column));

            return;
        }

        $builder->where($column, $centerId);
    }
}
