<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use LogicException;

/**
 * Joriy so'rov qaysi MARKAZ ma'lumotlari bilan ishlashini aniqlaydi (BranchContext'ning bir daraja yuqorisi).
 *
 *  - sAdmin: tanlangan filialning markazi (sessiyadagi filial orqali), yoki sessiyada tanlangan markaz;
 *    hech narsa tanlanmagan bo'lsa - barcha markazlar (null, cheklovsiz);
 *  - direktor / admin / menejer / operator / o'qituvchi / o'quvchi: doim o'z markazi;
 *  - markazi yo'q (center_id = null) oddiy foydalanuvchi: 0 - hech narsa ko'rmaydi (xavfsiz tomonga xato);
 *  - tizimga kirmagan holat (konsol, navbat): null - cheklov yo'q, lekin yozuv yaratishda markaz
 *    aniq ko'rsatilishi shart (`resolveFor`).
 */
class CenterContext
{
    public const SESSION_KEY = 'current_center_id';

    /** Ma'lumotlar cheklanishi kerak bo'lgan markaz ID si, 0 (hech narsa) yoki null (cheklovsiz). */
    public static function id(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->role === Role::SAdmin) {
            $branchId = BranchContext::id();

            if ($branchId) {
                return self::centerOfBranch($branchId) ?: 0;
            }

            $id = session(self::SESSION_KEY);

            return $id ? (int) $id : null;
        }

        return $user->center_id ? (int) $user->center_id : 0;
    }

    /** Filialning markazi (so'rov davomida keshlanadi; global scope'siz, rekursiya bo'lmasligi uchun). */
    public static function centerOfBranch(int $branchId): ?int
    {
        $attrs = request()?->attributes;
        $key = 'center_of_branch_'.$branchId;

        if ($attrs && $attrs->has($key)) {
            return $attrs->get($key);
        }

        $value = DB::table('branches')->where('id', $branchId)->value('center_id');
        $value = $value ? (int) $value : null;
        $attrs?->set($key, $value);

        return $value;
    }

    /** Yaratilayotgan yozuv uchun markaz ID sini aniqlaydi. Aniqlab bo'lmasa - xato (jim ishlamaydi). */
    public static function resolveFor(Model $model, bool $optional = false): ?int
    {
        $branchId = $model->getAttribute('branch_id');

        if ($branchId && ($center = self::centerOfBranch((int) $branchId))) {
            return $center;
        }

        if (method_exists($model, 'centerIdFromParent') && ($center = $model->centerIdFromParent())) {
            return (int) $center;
        }

        $ctx = self::id();

        if ($ctx) {
            return $ctx;
        }

        if ($optional) {
            return null;
        }

        throw new LogicException('Yozuv yaratish uchun markaz aniqlanmadi ('.class_basename($model).'): markaz yoki filial ko\'rsatilishi kerak.');
    }

    /** sAdmin ishlayotgan markazni tanlaydi (bo'sh qiymat - barcha markazlar). */
    public static function select(?int $centerId): void
    {
        if ($centerId) {
            session([self::SESSION_KEY => $centerId]);
        } else {
            session()->forget(self::SESSION_KEY);
        }
    }

    /** Filial shu markazga tegishlimi. */
    public static function branchInCenter(int $branchId, int $centerId): bool
    {
        return self::centerOfBranch($branchId) === $centerId;
    }

    /**
     * `exists:branches,id` qoidasi, lekin joriy markaz bilan cheklangan (direktor boshqa markaz filialini
     * ko'rsata olmasligi uchun). sAdmin "barcha markazlar" rejimida cheklanmaydi.
     */
    public static function existsBranch(bool $activeOnly = true): Exists
    {
        $rule = Rule::exists('branches', 'id');

        if ($activeOnly) {
            $rule->where('status', 'active');
        }

        $ctx = self::id();

        return $ctx === null ? $rule : $rule->where('center_id', $ctx);
    }

    /** Foydalanuvchi shu markazga tegishlimi (yoki sAdmin). */
    public static function canAccess(User $user, int $centerId): bool
    {
        return $user->role === Role::SAdmin || (int) $user->center_id === $centerId;
    }
}
