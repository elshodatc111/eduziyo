<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Center;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserConsent;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Huquqiy hujjatlar: versiyalash, e'lon qilish, foydalanuvchi roziligi va hisobot. */
class LegalService
{
    /** Foydalanuvchi roli qaysi auditoriyaga kiradi. */
    public static function audienceOf(User $user): ?string
    {
        return match ($user->role) {
            Role::SAdmin => null, // platforma egasi — majburiy emas
            Role::Owner => 'owner',
            Role::Student => 'student',
            default => 'staff',
        };
    }

    /** Auditoriya uchun amaldagi (oxirgi e'lon qilingan) hujjatlar, har (tur, auditoriya) bo'yicha bittadan. */
    public function currentFor(string $audience): Collection
    {
        return LegalDocument::inEffect()->whereIn('audience', ['all', $audience])->orderBy('version')->get()
            ->groupBy(fn ($d) => $d->type.'|'.$d->audience)->map->last()->values();
    }

    /** Foydalanuvchi qabul qilishi kerak bo'lgan (hali qabul qilmagan) hujjatlar. */
    public function pendingFor(User $user): Collection
    {
        $aud = self::audienceOf($user);
        if ($aud === null) {
            return collect();
        }

        $accepted = UserConsent::where('user_id', $user->id)->with('document:id,type,audience,version')->get()
            ->map->document->filter();

        return $this->currentFor($aud)->filter(function (LegalDocument $latest) use ($accepted) {
            $maxAccepted = $accepted->where('type', $latest->type)->where('audience', $latest->audience)->max('version');
            if ($maxAccepted !== null && $maxAccepted >= $latest->version) {
                return false;
            }
            if ($maxAccepted === null) {
                return true;
            }
            // Qabul qilingan versiyadan keyingi e'lon qilingan versiyalardan birortasi qayta rozilik talab qilsa — talab qilinadi
            return LegalDocument::inEffect()->where('type', $latest->type)->where('audience', $latest->audience)
                ->where('version', '>', $maxAccepted)->where('requires_reacceptance', true)->exists();
        })->values();
    }

    public function accept(User $user, Collection $docs, Request $request, string $method = 'web'): void
    {
        DB::transaction(function () use ($user, $docs, $request, $method) {
            $center = $user->center_id ? Center::withoutGlobalScopes()->find($user->center_id) : null;

            foreach ($docs as $doc) {
                UserConsent::create([
                    'user_id' => $user->id, 'user_name' => $user->name, 'user_role' => $user->role->value,
                    'center_ref' => $user->center_id, 'center_name' => $center?->name,
                    'legal_document_id' => $doc->id, 'accepted_at' => now(),
                    'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'method' => $method,
                ]);

                // Direktor oferta'ni markaz nomidan qabul qiladi
                if ($doc->type === 'offer' && $user->role === Role::Owner && $center) {
                    DB::table('center_agreements')->insert([
                        'center_ref' => $center->id, 'organization' => $center->name, 'accepted_by_name' => $user->name,
                        'accepted_by_user' => $user->id, 'legal_document_id' => $doc->id, 'accepted_at' => now(), 'ip' => $request->ip(),
                    ]);
                }
            }
        });
    }

    /** Yangi qoralama (mavjud versiyadan nusxa bo'lishi mumkin). */
    public function newDraft(string $type, string $audience, string $title, string $body, bool $requiresReacceptance): LegalDocument
    {
        $version = (int) LegalDocument::where('type', $type)->where('audience', $audience)->max('version') + 1;

        return LegalDocument::create(compact('type', 'audience', 'version', 'title', 'body') + ['status' => 'draft', 'requires_reacceptance' => $requiresReacceptance]);
    }

    public function newVersionFrom(LegalDocument $doc): LegalDocument
    {
        if (LegalDocument::where('type', $doc->type)->where('audience', $doc->audience)->where('status', 'draft')->exists()) {
            throw new DomainException("Bu hujjatning qoralamasi allaqachon bor — avval uni tahrirlang yoki e'lon qiling.");
        }

        return $this->newDraft($doc->type, $doc->audience, $doc->title, $doc->body, true);
    }

    public function publish(LegalDocument $doc, User $actor, ?\DateTimeInterface $effectiveAt = null): void
    {
        if ($doc->isPublished()) {
            throw new DomainException("Hujjat allaqachon e'lon qilingan.");
        }
        if (str_contains($doc->body, 'Qoralama.')) {
            throw new DomainException("Matndagi «Qoralama» eslatmasini o'chiring va rekvizitlarni to'ldiring.");
        }

        $doc->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => now(), 'effective_at' => $effectiveAt ?? now()]);
        AuditLog::record('legal.published', $doc, "{$doc->typeLabel()} v{$doc->version} e'lon qilindi");
    }

    /** Ariza shakllarida rozilik talab qilinadimi (maxfiylik siyosati e'lon qilinganmi). */
    public function consentRequired(): bool
    {
        return $this->publicPrivacy() !== null;
    }

    public function publicPrivacy(): ?LegalDocument
    {
        return LegalDocument::inEffect()->where('type', 'privacy')->orderByDesc('version')->first();
    }

    /** Ariza shakli uchun rozilik belgisi: [consent_at, consent_version]. */
    public function formConsent(): array
    {
        $doc = $this->publicPrivacy();

        return $doc ? ['consent_at' => now(), 'consent_version' => 'privacy v'.$doc->version] : [];
    }

    /**
     * sAdmin hisoboti: har amaldagi hujjat bo'yicha nechta foydalanuvchi qabul qilgan / qilmagan.
     * @return array{rows: array, missing: array}
     */
    public function report(?int $centerId = null): array
    {
        $rows = [];
        $missing = [];

        foreach (LegalDocument::inEffect()->orderBy('type')->orderBy('version')->get()->groupBy(fn ($d) => $d->type.'|'.$d->audience)->map->last() as $doc) {
            $roles = match ($doc->audience) {
                'owner' => [Role::Owner->value], 'student' => [Role::Student->value],
                'staff' => [Role::Admin->value, Role::Manager->value, Role::Teacher->value, Role::Operator->value],
                default => array_map(fn ($r) => $r->value, array_filter(Role::cases(), fn ($r) => $r !== Role::SAdmin)),
            };
            $users = User::withoutGlobalScopes()->whereIn('role', $roles)->whereNotNull('center_id')
                ->when($centerId, fn ($q) => $q->where('center_id', $centerId))->get(['id', 'name', 'role', 'center_id']);

            $acceptedIds = UserConsent::whereIn('user_id', $users->pluck('id'))->where('legal_document_id', $doc->id)->pluck('user_id')->unique();
            $notAccepted = $users->reject(fn ($u) => $acceptedIds->contains($u->id));

            $rows[] = ['doc' => $doc, 'total' => $users->count(), 'accepted' => $acceptedIds->count(), 'missing' => $notAccepted->count()];
            foreach ($notAccepted as $u) {
                $missing[] = ['doc' => $doc, 'user' => $u];
            }
        }

        return compact('rows', 'missing');
    }
}
