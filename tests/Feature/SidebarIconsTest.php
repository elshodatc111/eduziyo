<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Yon menyudagi har bir bo'lim uchun ikonka mavjudligini tekshiradi (ikonkasiz menyu bandi bo'lmasin). */
class SidebarIconsTest extends TestCase
{
    public function test_every_sidebar_icon_exists_in_icon_component(): void
    {
        $nav = file_get_contents(resource_path('views/partials/sidebar-nav.blade.php'));
        $icons = file_get_contents(resource_path('views/components/icon.blade.php'));

        preg_match_all("/'icon' => '([a-z-]+)'|icon=\"([a-z-]+)\"/", $nav, $m);
        $used = array_unique(array_filter(array_merge($m[1], $m[2])));
        $this->assertNotEmpty($used);

        foreach ($used as $name) {
            $this->assertStringContainsString("'{$name}' =>", $icons, "Ikonka topilmadi: {$name}");
        }
    }
}
