<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Meta-test (TZ 5-bo'lim, 8-band): `center_id` ustuni bor har bir jadvalning modeli `BelongsToCenter` ishlatishi shart.
 * Yangi jadval markaz skoupisiz qolsa - test yiqiladi.
 */
class TenancyMetaTest extends TestCase
{
    use RefreshDatabase;

    /** center_id bor, lekin atayin global scope'siz jadvallar va sabablari. */
    private const EXEMPT = [
        'users' => "User modelida global scope yo'q (autentifikatsiya rekursiyasi); `User::visibleToContext()` va marshrut bog'lash ishlatiladi",
        'user_branches' => "Model yo'q: operator-filial bog'lamasi, faqat `users`/`branches` orqali o'qiladi",
        'centers' => 'markazning o\'zi',
    ];

    /** @return array<string, class-string<Model>> jadval => model */
    private function models(): array
    {
        $map = [];

        foreach (File::files(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');

            if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $map[(new $class)->getTable()] = $class;
        }

        return $map;
    }

    public function test_every_table_with_center_id_has_a_scoped_model(): void
    {
        $models = $this->models();
        $missing = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (! Schema::hasColumn($name, 'center_id') || array_key_exists($name, self::EXEMPT)) {
                continue;
            }

            $class = $models[$name] ?? null;

            if ($class === null) {
                $missing[] = "{$name}: modeli yo'q";
            } elseif (! in_array(BelongsToCenter::class, class_uses_recursive($class), true)) {
                $missing[] = "{$name}: {$class} BelongsToCenter ishlatmaydi";
            }
        }

        $this->assertSame([], $missing, "Markaz skoupisiz jadvallar:\n".implode("\n", $missing));
    }

    public function test_every_branch_scoped_model_has_center_id_column(): void
    {
        $bad = [];

        foreach ($this->models() as $table => $class) {
            if (in_array(BelongsToBranch::class, class_uses_recursive($class), true) && ! Schema::hasColumn($table, 'center_id')) {
                $bad[] = "{$table} ({$class})";
            }
        }

        $this->assertSame([], $bad, "center_id ustuni yo'q: ".implode(', ', $bad));
    }

    public function test_every_table_with_branch_id_also_has_center_id(): void
    {
        $bad = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (Schema::hasColumn($name, 'branch_id') && ! Schema::hasColumn($name, 'center_id')) {
                $bad[] = $name;
            }
        }

        $this->assertSame([], $bad, "branch_id bor, center_id yo'q: ".implode(', ', $bad));
    }

    public function test_exempt_list_is_honest(): void
    {
        // Istisno ro'yxatidagi jadvallar haqiqatan mavjud bo'lishi kerak (eskirgan istisnolar to'planib qolmasin)
        foreach (array_keys(self::EXEMPT) as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} jadvali yo'q");
        }
    }
}
