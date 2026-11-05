<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EDUZIYO E7b: tariflar katalogi (3 ta), yangiliklarga rasm va YouTube, sotuv videolari, arizaga demo/tarif belgisi.
 * Narxlar ataylab bo'sh (NULL): sAdmin "Sotuv → Tariflar" da kiritadi; kiritilmaguncha sahifada "Narxni bilish" ko'rinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 40)->unique();
            $t->string('name', 60);
            $t->string('tagline', 160)->nullable();
            $t->unsignedBigInteger('price_monthly')->nullable();   // so'm / oy
            $t->unsignedBigInteger('price_yearly')->nullable();    // so'm / yil (to'liq summa)
            $t->unsignedSmallInteger('max_branches')->nullable();  // NULL = cheksiz
            $t->unsignedInteger('max_students')->nullable();       // NULL = cheksiz
            $t->unsignedInteger('sms_monthly')->default(0);        // oylik SMS limiti
            $t->unsignedInteger('ai_monthly')->default(0);         // oylik AI so'rovlar limiti (0 = yo'q)
            $t->text('features')->nullable();                      // har qatorda bittadan
            $t->boolean('is_featured')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedTinyInteger('sort_order')->default(0);
            $t->timestamps();
        });

        $now = now();
        DB::table('tariffs')->insert([
            [
                'slug' => 'start', 'name' => 'Start', 'tagline' => "Bitta filialli kichik markaz uchun",
                'max_branches' => 1, 'max_students' => 300, 'sms_monthly' => 500, 'ai_monthly' => 0,
                'features' => "O'quvchilar, guruhlar va davomad\nTo'lovlar, kassa va ish haqi\nMurojaatlar (varonka)\nHisobotlar va statistika\nExcel'dan import",
                'is_featured' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'standart', 'name' => 'Standart', 'tagline' => "O'sib borayotgan markazlar uchun",
                'max_branches' => 3, 'max_students' => 1500, 'sms_monthly' => 3000, 'ai_monthly' => 100,
                'features' => "Start'dagi barcha imkoniyatlar\nBir nechta filial\nSMS xabarnomalar va ommaviy yuborish\nAI yordamchi (tahlil va tavsiyalar)\nXodimlar uchun ruxsatlar",
                'is_featured' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'slug' => 'premium', 'name' => 'Premium', 'tagline' => "Katta tarmoq va ko'p filiallar uchun",
                'max_branches' => 10, 'max_students' => null, 'sms_monthly' => 10000, 'ai_monthly' => 500,
                'features' => "Standart'dagi barcha imkoniyatlar\nCheksiz o'quvchi\nKo'p filialli markaz boshqaruvi\nKatta SMS va AI limiti\nIstalgan filiallarni solishtirish",
                'is_featured' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        Schema::table('news_posts', function (Blueprint $t) {
            $t->string('image_path', 190)->nullable()->after('body');
            $t->string('youtube_id', 11)->nullable()->after('image_path');
        });

        Schema::create('sales_videos', function (Blueprint $t) {
            $t->id();
            $t->string('title', 160);
            $t->string('youtube_id', 11);
            $t->string('description', 300)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::table('sales_applications', function (Blueprint $t) {
            $t->boolean('wants_demo')->default(false)->after('message');
            $t->string('tariff_slug', 40)->nullable()->after('wants_demo');
        });
    }

    public function down(): void
    {
        // Ma'lumot yo'qolmasligi uchun ataylab bo'sh.
    }
};
