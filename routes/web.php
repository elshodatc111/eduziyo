<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashboxController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ConfirmationController;
use App\Http\Controllers\ContractSettingsController;
use App\Http\Controllers\CourseContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountRuleController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupStudentController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Sales;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLeadController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDirectoryController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentNoteController;
use App\Http\Controllers\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:30,1');

    // Parolni tiklash - faqat Telegram bot kodi orqali (qo'lda tiklash olib tashlangan)
    Route::get('forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'forgot'])->name('password.request');
    Route::post('forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'sendCode'])->middleware('throttle:10,10')->name('password.email');
    Route::get('reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'form'])->name('password.reset');
    Route::post('reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'reset'])->middleware('throttle:10,10')->name('password.update');
});

// EDUZIYO sotuv sahifasi (ochiq, doim `/` da — kirgan foydalanuvchi ham ko'ra oladi). Boshqaruv paneli — `/dashboard`.
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('manifest.webmanifest', [\App\Http\Controllers\Platform\BrandController::class, 'manifest'])->name('manifest');
Route::post('ariza', [LandingController::class, 'apply'])->middleware('throttle:5,60')->name('landing.apply');
Route::get('yangiliklar', [LandingController::class, 'newsIndex'])->name('landing.news');
Route::get('yangiliklar/{post}', [LandingController::class, 'newsShow'])->name('landing.news.show');

// Huquqiy hujjatlar (ochiq) va majburiy rozilik oynasi
Route::get('oferta', [\App\Http\Controllers\LegalController::class, 'show'])->defaults('type', 'offer')->name('legal.offer');
Route::get('maxfiylik', [\App\Http\Controllers\LegalController::class, 'show'])->defaults('type', 'privacy')->name('legal.privacy');
Route::get('shartlar', [\App\Http\Controllers\LegalController::class, 'show'])->defaults('type', 'terms')->name('legal.terms');
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('rozilik', [\App\Http\Controllers\LegalController::class, 'consent'])->name('legal.consent');
    Route::post('rozilik', [\App\Http\Controllers\LegalController::class, 'accept'])->middleware('throttle:20,1')->name('legal.accept');
});

// Saytdan murojaat qoldirish (ochiq)
Route::get('apply', [PublicLeadController::class, 'index'])->name('apply.index');
Route::get('apply/{branch:code}', [PublicLeadController::class, 'show'])->name('apply.show');
Route::post('apply/{branch:code}', [PublicLeadController::class, 'store'])->middleware('throttle:10,60')->name('apply.store');


// Ommaviy demo: rol tanlab bir bosishda kirish (sotuv sahifasidan)
Route::post('demo/chiqish', [\App\Http\Controllers\DemoController::class, 'leave'])->name('demo.leave');
Route::post('demo/{role}', [\App\Http\Controllers\DemoController::class, 'enter'])->middleware('throttle:30,1')->name('demo.enter');

Route::middleware(['auth', 'active', 'legal', 'demo', 'writable'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // O'quvchi kabineti (E6): faqat o'quvchi roli; boshqa rollar 403, o'quvchi esa faqat shu bo'limni ko'radi
    Route::middleware('role:student')->prefix('cabinet')->name('cabinet.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CabinetController::class, 'home'])->name('home');
        Route::get('guruhlar', [\App\Http\Controllers\CabinetController::class, 'groups'])->name('groups');
        Route::get('guruhlar/{group}', [\App\Http\Controllers\CabinetController::class, 'group'])->whereNumber('group')->name('groups.show');
        Route::get('tolovlar', [\App\Http\Controllers\CabinetController::class, 'payments'])->name('payments');
        Route::get('kurslar', [\App\Http\Controllers\CabinetController::class, 'courses'])->name('courses');
        Route::get('kurslar/{course}', [\App\Http\Controllers\CabinetController::class, 'course'])->whereNumber('course')->name('courses.show');
        Route::post('kurslar/{course}/test', [\App\Http\Controllers\CabinetController::class, 'testStart'])->whereNumber('course')->middleware('throttle:30,1')->name('courses.test');
        Route::get('test/{attempt}', [\App\Http\Controllers\CabinetController::class, 'test'])->whereNumber('attempt')->name('test');
        Route::post('test/{attempt}', [\App\Http\Controllers\CabinetController::class, 'testSubmit'])->whereNumber('attempt')->name('test.submit');
        Route::get('xabarlar', [\App\Http\Controllers\CabinetController::class, 'notifications'])->name('notifications');
        Route::get('profil', [\App\Http\Controllers\CabinetController::class, 'profile'])->name('profile');
        Route::put('profil', [\App\Http\Controllers\CabinetController::class, 'updateProfile'])->name('profile.update');
        Route::put('profil/parol', [\App\Http\Controllers\CabinetController::class, 'password'])->name('profile.password');
        Route::post('profil/telegram', [\App\Http\Controllers\TelegramLinkController::class, 'link'])->middleware('throttle:10,1')->name('telegram.link');
        Route::delete('profil/telegram', [\App\Http\Controllers\TelegramLinkController::class, 'unlink'])->name('telegram.unlink');
    });

    Route::post('tasks/dismiss', [DashboardController::class, 'dismissTask'])->name('tasks.dismiss');

    // O'qituvchi: o'z ish haqi va dars jadvali (faqat o'zi)
    Route::middleware('role:teacher')->prefix('mening')->name('teacher.')->group(function () {
        Route::get('ish-haqi', [\App\Http\Controllers\TeacherSelfController::class, 'payroll'])->name('payroll');
        Route::get('jadval', [\App\Http\Controllers\TeacherSelfController::class, 'schedule'])->name('schedule');
    });

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('profile/telegram', [\App\Http\Controllers\TelegramLinkController::class, 'link'])->middleware('throttle:10,1')->name('telegram.link');
    Route::delete('profile/telegram', [\App\Http\Controllers\TelegramLinkController::class, 'unlink'])->name('telegram.unlink');

    // Filial almashtirish: sAdmin (barcha filiallar) va qo'shimcha filialli operator (faqat o'ziga berilganlar)
    Route::post('branch/switch', [BranchController::class, 'switch'])->name('branches.switch');
    Route::post('center/switch', [\App\Http\Controllers\CenterController::class, 'switch'])->name('centers.switch');

    // Filiallar: sAdmin va direktor (o'z markazida)
    Route::middleware('role:sadmin,owner')->group(function () {
        Route::resource('branches', BranchController::class)->except(['show', 'destroy']);
        Route::post('branches/{branch}/close', [BranchController::class, 'close'])->name('branches.close');
        Route::post('branches/{branch}/reopen', [BranchController::class, 'reopen'])->name('branches.reopen');
        // v10 (7-band): filialni BUTUNLAY o'chirish (barcha ma'lumoti bilan), nom tasdiqlash bilan
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });

    // Direktorning «Markaz» bo'limi: obuna, balans, tahlil, eksport
    Route::middleware('role:owner')->prefix('markaz')->name('center.')->group(function () {
        $c = \App\Http\Controllers\CenterPanelController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('tariflar', [$c, 'tariffs'])->name('tariffs');
        Route::post('tariflar/{tariff}', [$c, 'buy'])->whereNumber('tariff')->middleware('throttle:10,1')->name('tariffs.buy');
        Route::get('balans', [$c, 'topupForm'])->name('topup');
        Route::post('balans', [$c, 'topupStore'])->middleware('throttle:10,60')->name('topup.store');
        Route::get('chek/{topup}', [$c, 'receipt'])->whereNumber('topup')->name('receipt');
        Route::get('tahlil', [$c, 'analytics'])->name('analytics');
        Route::get('eksport/{type}', [$c, 'export'])->middleware('throttle:20,1')->name('export');
        Route::post('logotip', [\App\Http\Controllers\CenterBrandController::class, 'update'])->middleware('throttle:10,1')->name('logo.update');
        Route::delete('logotip', [\App\Http\Controllers\CenterBrandController::class, 'destroy'])->name('logo.destroy');
        $sp = \App\Http\Controllers\CenterSupportController::class;
        Route::get('murojaat', [$sp, 'index'])->name('support');
        Route::post('murojaat', [$sp, 'store'])->middleware('throttle:5,60')->name('support.store');
        Route::get('murojaat/{ticket}', [$sp, 'show'])->whereNumber('ticket')->name('support.show');
        Route::post('murojaat/{ticket}/javob', [$sp, 'reply'])->whereNumber('ticket')->middleware('throttle:30,60')->name('support.reply');
        Route::get('murojaat-fayl/{attachment}', [$sp, 'file'])->whereNumber('attachment')->name('support.file');
        Route::post('murojaat/{ticket}/yopish', [$sp, 'close'])->whereNumber('ticket')->name('support.close');
        Route::post('murojaat/{ticket}/baho', [$sp, 'rate'])->whereNumber('ticket')->name('support.rate');
        $dc = \App\Http\Controllers\DirectorController::class;
        Route::get('direktorlar', [$dc, 'index'])->name('directors');
        Route::post('direktorlar', [$dc, 'store'])->middleware('throttle:10,1')->name('directors.store');
        Route::post('direktorlar/{director}/holat', [$dc, 'toggle'])->whereNumber('director')->name('directors.toggle');
        $sc = \App\Http\Controllers\CenterSmsController::class;
        Route::get('sms', [$sc, 'index'])->name('sms');
        Route::post('sms-faktura/{invoice}/qabul', [$sc, 'accept'])->whereNumber('invoice')->middleware('throttle:10,1')->name('sms.accept');
        Route::post('sms-faktura/{invoice}/rad', [$sc, 'decline'])->whereNumber('invoice')->name('sms.decline');
    });

    Route::middleware('role:sadmin')->group(function () {
        // Platforma: markazlar konsoli, balans to'ldirish so'rovlari, kartalar, tahlil
        $pc = \App\Http\Controllers\Platform\CenterConsoleController::class;
        $pt = \App\Http\Controllers\Platform\TopupController::class;
        Route::get('platform/markazlar', [$pc, 'index'])->name('platform.centers.index');
        Route::get('platform/markazlar/yangi', [$pc, 'create'])->name('platform.centers.create');
        Route::post('platform/markazlar', [$pc, 'store'])->name('platform.centers.store');
        Route::get('platform/markazlar/{center}', [$pc, 'show'])->whereNumber('center')->name('platform.centers.show');
        Route::post('platform/markazlar/{center}/tarif', [$pc, 'grant'])->whereNumber('center')->name('platform.centers.grant');
        Route::post('platform/markazlar/{center}/balans', [$pc, 'adjust'])->whereNumber('center')->name('platform.centers.adjust');
        Route::delete('platform/markazlar/{center}', [$pc, 'destroy'])->whereNumber('center')->middleware('throttle:5,1')->name('platform.centers.destroy');
        Route::post('platform/markazlar/{center}/toxtatish', [$pc, 'suspend'])->whereNumber('center')->name('platform.centers.suspend');
        Route::post('platform/markazlar/{center}/tiklash', [$pc, 'resume'])->whereNumber('center')->name('platform.centers.resume');
        Route::get('platform/tahlil', [$pc, 'analytics'])->name('platform.analytics');
        $dc = \App\Http\Controllers\DirectorController::class;
        Route::get('platform/markazlar/{center}/direktorlar', [$dc, 'index'])->whereNumber('center')->name('platform.directors');
        Route::post('platform/markazlar/{center}/direktorlar', [$dc, 'store'])->whereNumber('center')->name('platform.directors.store');
        Route::post('platform/markazlar/{center}/direktorlar/{director}/holat', [$dc, 'toggle'])->whereNumber(['center', 'director'])->name('platform.directors.toggle');
        $vg = \App\Http\Controllers\Platform\VideoGuideController::class;
        Route::get('platform/qollanma-video', [$vg, 'index'])->name('platform.guides');
        Route::post('platform/qollanma-video', [$vg, 'store'])->name('platform.guides.store');
        Route::put('platform/qollanma-video/{guide}', [$vg, 'update'])->whereNumber('guide')->name('platform.guides.update');
        Route::post('platform/qollanma-video/{guide}/muqova', [$vg, 'setCover'])->whereNumber('guide')->name('platform.guides.cover');
        Route::delete('platform/qollanma-video/{guide}/muqova', [$vg, 'removeCover'])->whereNumber('guide')->name('platform.guides.cover.remove');
        Route::post('platform/qollanma-video/{guide}/holat', [$vg, 'toggle'])->whereNumber('guide')->name('platform.guides.toggle');
        Route::delete('platform/qollanma-video/{guide}', [$vg, 'destroy'])->whereNumber('guide')->name('platform.guides.destroy');
        $su = \App\Http\Controllers\Platform\SupportController::class;
        Route::get('platform/murojaatlar', [$su, 'index'])->name('platform.support');
        Route::put('platform/murojaatlar/sozlama', [$su, 'settings'])->name('platform.support.settings');
        Route::get('platform/murojaatlar/{ticket}', [$su, 'show'])->whereNumber('ticket')->name('platform.support.show');
        Route::post('platform/murojaatlar/{ticket}/javob', [$su, 'reply'])->whereNumber('ticket')->name('platform.support.reply');
        Route::get('platform/murojaat-fayl/{attachment}', [$su, 'file'])->whereNumber('attachment')->name('platform.support.file');
        Route::post('platform/murojaatlar/{ticket}/yopish', [$su, 'close'])->whereNumber('ticket')->name('platform.support.close');
        $bc = \App\Http\Controllers\Platform\BrandController::class;
        $ic = \App\Http\Controllers\Platform\IntegrationController::class;
        Route::get('platform/integratsiyalar', [$ic, 'index'])->name('platform.integrations');
        Route::put('platform/integratsiyalar/telegram', [$ic, 'saveTelegram'])->middleware('throttle:20,1')->name('platform.integrations.telegram');
        Route::delete('platform/integratsiyalar/telegram', [$ic, 'clearTelegram'])->name('platform.integrations.telegram.clear');
        Route::post('platform/integratsiyalar/webhook', [$ic, 'setWebhook'])->middleware('throttle:6,1')->name('platform.integrations.webhook');
        Route::delete('platform/integratsiyalar/webhook', [$ic, 'removeWebhook'])->middleware('throttle:6,1')->name('platform.integrations.webhook.remove');
        Route::put('platform/integratsiyalar/modem', [$ic, 'saveModem'])->name('platform.integrations.modem');

        $ld = \App\Http\Controllers\Platform\LegalDocumentController::class;
        Route::get('platform/hujjatlar', [$ld, 'index'])->name('platform.legal');
        Route::get('platform/hujjatlar/yangi', [$ld, 'create'])->name('platform.legal.create');
        Route::post('platform/hujjatlar', [$ld, 'store'])->name('platform.legal.store');
        Route::get('platform/hujjatlar/hisobot', [$ld, 'report'])->name('platform.legal.report');
        Route::get('platform/hujjatlar/{doc}', [$ld, 'edit'])->whereNumber('doc')->name('platform.legal.edit');
        Route::put('platform/hujjatlar/{doc}', [$ld, 'update'])->whereNumber('doc')->name('platform.legal.update');
        Route::post('platform/hujjatlar/{doc}/elon', [$ld, 'publish'])->whereNumber('doc')->name('platform.legal.publish');
        Route::post('platform/hujjatlar/{doc}/versiya', [$ld, 'newVersion'])->whereNumber('doc')->name('platform.legal.version');
        Route::delete('platform/hujjatlar/{doc}', [$ld, 'destroy'])->whereNumber('doc')->name('platform.legal.destroy');

        Route::get('platform/brending', [$bc, 'edit'])->name('platform.brand');
        Route::put('platform/brending', [$bc, 'update'])->name('platform.brand.update');
        Route::delete('platform/brending/{what}', [$bc, 'remove'])->name('platform.brand.remove');
        $mc = \App\Http\Controllers\Platform\ModemController::class;
        Route::get('platform/modemlar', [$mc, 'index'])->name('platform.modems');
        Route::post('platform/modemlar/qidirish', [$mc, 'scan'])->middleware('throttle:6,1')->name('platform.modems.scan');
        Route::post('platform/modemlar/{modem}/holat', [$mc, 'toggle'])->whereNumber('modem')->name('platform.modems.toggle');
        Route::put('platform/modemlar/{modem}', [$mc, 'update'])->whereNumber('modem')->name('platform.modems.update');
        Route::post('platform/modemlar/{modem}/tozalash', [$mc, 'reset'])->whereNumber('modem')->name('platform.modems.reset');
        Route::post('platform/modemlar/{modem}/sinov', [$mc, 'test'])->whereNumber('modem')->middleware('throttle:10,1')->name('platform.modems.test');
        $ps = \App\Http\Controllers\Platform\SmsPlatformController::class;
        Route::get('platform/sms', [$ps, 'index'])->name('platform.sms');
        Route::put('platform/sms/sozlama', [$ps, 'settings'])->name('platform.sms.settings');
        Route::post('platform/markazlar/{center}/sms-faktura', [$ps, 'invoiceStore'])->whereNumber('center')->name('platform.sms.invoices.store');
        Route::post('platform/sms-faktura/{invoice}/bekor', [$ps, 'invoiceCancel'])->whereNumber('invoice')->name('platform.sms.invoices.cancel');
        Route::get('platform/toldirish', [$pt, 'index'])->name('platform.topups');
        Route::get('platform/toldirish/{topup}/chek', [\App\Http\Controllers\Platform\TopupController::class, 'receipt'])->whereNumber('topup')->name('platform.topups.receipt');
        Route::post('platform/toldirish/{topup}/tasdiqlash', [$pt, 'approve'])->whereNumber('topup')->name('platform.topups.approve');
        Route::post('platform/toldirish/{topup}/rad', [$pt, 'reject'])->whereNumber('topup')->name('platform.topups.reject');
        Route::post('platform/kartalar', [$pt, 'cardStore'])->name('platform.cards.store');
        Route::post('platform/kartalar/{card}/almashtirish', [$pt, 'cardToggle'])->whereNumber('card')->name('platform.cards.toggle');
        Route::delete('platform/kartalar/{card}', [$pt, 'cardDestroy'])->whereNumber('card')->name('platform.cards.destroy');

        // v8 A4: butun tizim darajasidagi holat (cron, zaxira, disk, ...) - filialga bog'liq emas
        Route::get('system-status', [SystemStatusController::class, 'index'])->name('system-status.index');
        // v12: zaxirani qo'lda, darhol ishga tushirish (cron jadvalini kutmasdan)
        Route::post('system-status/backup', [SystemStatusController::class, 'runBackupNow'])->middleware('throttle:5,1')->name('system-status.backup');

        // v11: mobil ilova foydalanuvchilariga push/ilova-ichi bildirishnoma yuborish
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications', [NotificationController::class, 'store'])->middleware('throttle:20,1')->name('notifications.store');

        // v12: mobil ilova versiyasi (majburiy/yumshoq yangilash) sozlamalari

        // v13: bir nechta sAdmin - qo'shish, bloklash, olib tashlash
        // Sotuv: arizalar, yangiliklar, aloqa sozlamalari (faqat sAdmin)
        Route::get('sales/applications', [Sales\ApplicationController::class, 'index'])->name('sales.applications');
        Route::put('sales/applications/{application}', [Sales\ApplicationController::class, 'update'])->whereNumber('application')->name('sales.applications.update');
        Route::delete('sales/applications/{application}', [Sales\ApplicationController::class, 'destroy'])->whereNumber('application')->name('sales.applications.destroy');
        Route::get('sales/news', [Sales\NewsController::class, 'index'])->name('sales.news.index');
        Route::get('sales/news/create', [Sales\NewsController::class, 'create'])->name('sales.news.create');
        Route::post('sales/news', [Sales\NewsController::class, 'store'])->name('sales.news.store');
        Route::get('sales/news/{post:id}/edit', [Sales\NewsController::class, 'edit'])->whereNumber('post')->name('sales.news.edit');
        Route::put('sales/news/{post:id}', [Sales\NewsController::class, 'update'])->whereNumber('post')->name('sales.news.update');
        Route::delete('sales/news/{post:id}', [Sales\NewsController::class, 'destroy'])->whereNumber('post')->name('sales.news.destroy');
        Route::get('sales/tariffs', [Sales\TariffController::class, 'index'])->name('sales.tariffs');
        Route::get('sales/tariffs/{tariff}/edit', [Sales\TariffController::class, 'edit'])->whereNumber('tariff')->name('sales.tariffs.edit');
        Route::put('sales/tariffs/{tariff}', [Sales\TariffController::class, 'update'])->whereNumber('tariff')->name('sales.tariffs.update');
        Route::get('sales/videos', [Sales\VideoController::class, 'index'])->name('sales.videos');
        Route::post('sales/videos', [Sales\VideoController::class, 'store'])->name('sales.videos.store');
        Route::post('sales/videos/{video}/toggle', [Sales\VideoController::class, 'toggle'])->whereNumber('video')->name('sales.videos.toggle');
        Route::delete('sales/videos/{video}', [Sales\VideoController::class, 'destroy'])->whereNumber('video')->name('sales.videos.destroy');
        Route::get('sales/demo', [Sales\DemoController::class, 'index'])->name('sales.demo');
        Route::post('sales/demo/reset', [Sales\DemoController::class, 'reset'])->middleware('throttle:3,10')->name('sales.demo.reset');
        Route::get('sales/settings', [Sales\SettingsController::class, 'edit'])->name('sales.settings');
        Route::put('sales/settings', [Sales\SettingsController::class, 'update'])->name('sales.settings.update');

        Route::get('superadmins', [SuperAdminController::class, 'index'])->name('superadmins.index');
        Route::get('superadmins/create', [SuperAdminController::class, 'create'])->name('superadmins.create');
        Route::post('superadmins', [SuperAdminController::class, 'store'])->name('superadmins.store');
        Route::post('superadmins/{id}/toggle', [SuperAdminController::class, 'toggle'])->whereNumber('id')->name('superadmins.toggle');
        Route::delete('superadmins/{id}', [SuperAdminController::class, 'destroy'])->whereNumber('id')->name('superadmins.destroy');
    });

    // Hodimlar va ruxsatlar
    Route::resource('staff', StaffController::class)
        ->parameters(['staff' => 'user'])
        ->except(['show', 'destroy']);
    Route::get('staff/{user}/permissions', [PermissionController::class, 'edit'])->name('staff.permissions.edit');
    Route::put('staff/{user}/permissions', [PermissionController::class, 'update'])->name('staff.permissions.update');
    // v9: filial cheklovisiz, faqat ism/rol/filial ro'yxati (odatda Operatorga)
    Route::get('staff-directory', [StaffDirectoryController::class, 'index'])->name('staff.directory');
    // v10 (1-band): shu ruxsat bilan boshqa filialning o'quvchi/guruh/lidlarini FAQAT KO'RISH (yozish yo'q)
    Route::get('staff-directory/{branch}', [StaffDirectoryController::class, 'branch'])->name('staff.directory.branch');

    // O'quvchilar
    Route::get('students/create', [StudentController::class, 'create'])->middleware('branch')->name('students.create');
    Route::post('students', [StudentController::class, 'store'])->middleware('branch')->name('students.store');
    Route::get('students/search', [StudentController::class, 'search'])->name('students.search');
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('students/{student}/archive', [StudentController::class, 'archive'])->name('students.archive');
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->name('students.restore');
    // v13: yuqori paneldagi eslatmalar qo'ng'iroqchasi (har 15 soniyada so'raladi) va faolsizlantirish
    Route::get('notes/feed', [StudentNoteController::class, 'feed'])->middleware('throttle:120,1')->name('notes.feed');
    Route::post('notes/{note}/close', [StudentNoteController::class, 'close'])->name('notes.close');
    Route::post('notes/{note}/reopen', [StudentNoteController::class, 'reopen'])->name('notes.reopen');
    Route::post('students/{student}/notes', [StudentNoteController::class, 'store'])->name('students.notes.store');
    Route::delete('students/{student}/notes/{note}', [StudentNoteController::class, 'destroy'])->name('students.notes.destroy');

    // Guruhlar
    Route::get('groups/create', [GroupController::class, 'create'])->middleware('branch')->name('groups.create');
    Route::post('groups', [GroupController::class, 'store'])->middleware('branch')->name('groups.store');
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::post('groups/{group}/archive', [GroupController::class, 'archiveInitiate'])->name('groups.archive');
    Route::post('groups/{group}/continue', [GroupController::class, 'continue'])->name('groups.continue');
    Route::post('groups/{group}/students', [GroupStudentController::class, 'store'])->name('groups.students.store');
    Route::delete('groups/{group}/students/{student}', [GroupStudentController::class, 'destroy'])->name('groups.students.destroy');
    Route::get('groups/{group}/students/{student}/contract', [GroupStudentController::class, 'contract'])->name('groups.students.contract');

    // Davomad
    Route::get('attendance', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('attendance/stats', [AttendanceController::class, 'stats'])->name('attendance.stats');
    Route::post('groups/{group}/attendance', [AttendanceController::class, 'take'])->name('attendance.take');
    Route::get('groups/{group}/attendance/past', [AttendanceController::class, 'pastForm'])->name('attendance.past.show');
    Route::post('groups/{group}/attendance/past', [AttendanceController::class, 'editPast'])->name('attendance.past.store');

    // Yordam (AI): barcha panel foydalanuvchilari uchun
    Route::get('qollanma', [\App\Http\Controllers\GuideController::class, 'index'])->name('guides.index');
    Route::get('qollanma/{guide}', [\App\Http\Controllers\GuideController::class, 'show'])->whereNumber('guide')->name('guides.show');
    Route::get('help', [HelpController::class, 'index'])->name('help.index');
    Route::post('help', [HelpController::class, 'send'])->middleware('throttle:30,1')->name('help.send');
    Route::delete('help/{chat}', [HelpController::class, 'destroy'])->name('help.destroy');

    // AI yordamchi
    Route::get('ai', [AiChatController::class, 'index'])->name('ai.index');
    Route::post('ai', [AiChatController::class, 'send'])->middleware('throttle:30,1')->name('ai.send');
    Route::delete('ai/{chat}', [AiChatController::class, 'destroy'])->name('ai.destroy');

    // Excel import
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports', [ImportController::class, 'upload'])->name('imports.upload');
    Route::get('imports/{batch}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{batch}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
    Route::post('imports/{batch}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');
    Route::get('imports/{batch}/result', [ImportController::class, 'result'])->name('imports.result');

    // Statistika va hisobotlar
    Route::get('statistics', [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])->name('reports.export');

    // Varonka (lidlar)
    Route::get('leads/create', [LeadController::class, 'create'])->middleware('branch')->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->middleware('branch')->name('leads.store');
    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::post('leads/{lead}/note', [LeadController::class, 'note'])->name('leads.note');
    Route::post('leads/{lead}/cancel', [LeadController::class, 'cancel'])->name('leads.cancel');
    Route::post('leads/{lead}/analyze', [LeadController::class, 'analyze'])->middleware('throttle:20,1')->name('leads.analyze');
    Route::post('leads/{lead}/reopen', [LeadController::class, 'reopen'])->name('leads.reopen');
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->middleware('branch')->name('leads.convert');

    // SMS
    Route::get('sms', [SmsController::class, 'index'])->name('sms.index');
    Route::middleware('branch')->group(function () {
        Route::put('sms/settings', [SmsController::class, 'settings'])->name('sms.settings');
        Route::post('sms/bulk', [SmsController::class, 'bulk'])->name('sms.bulk');
    });

    // Kurs materiallari
    Route::get('courses/{course}', [CourseContentController::class, 'show'])->name('courses.show');
    Route::post('courses/{course}/videos', [CourseContentController::class, 'storeVideo'])->name('courses.videos.store');
    Route::delete('courses/{course}/videos/{video}', [CourseContentController::class, 'destroyVideo'])->name('courses.videos.destroy');
    Route::post('courses/{course}/audios', [CourseContentController::class, 'storeAudio'])->name('courses.audios.store');
    Route::delete('courses/{course}/audios/{audio}', [CourseContentController::class, 'destroyAudio'])->name('courses.audios.destroy');
    Route::post('courses/{course}/questions', [CourseContentController::class, 'storeQuestion'])->name('courses.questions.store');
    Route::post('courses/{course}/questions/ai', [CourseContentController::class, 'aiGenerate'])->middleware('throttle:20,60')->name('courses.questions.ai');
    Route::post('courses/{course}/questions/ai-store', [CourseContentController::class, 'storeAiQuestions'])->name('courses.questions.ai-store');
    Route::delete('courses/{course}/questions/{question}', [CourseContentController::class, 'destroyQuestion'])->name('courses.questions.destroy');

    // To'lovlar
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('students/{student}/payments', [PaymentController::class, 'store'])->middleware('once')->name('payments.store');
    Route::post('students/{student}/discount', [PaymentController::class, 'discount'])->middleware('once')->name('payments.discount');
    Route::post('students/{student}/special-discount', [PaymentController::class, 'specialDiscountInitiate'])->name('payments.special-discount');
    Route::post('students/{student}/refund', [PaymentController::class, 'refund'])->middleware('once')->name('payments.refund');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverseInitiate'])->name('payments.reverse');
    Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    // Kassa, moliya, ish haqi (filial tanlangan bo'lishi kerak)
    Route::middleware('branch')->group(function () {
        Route::get('cashbox', [CashboxController::class, 'index'])->name('cashbox.index');
        Route::post('cashbox/requests', [CashboxController::class, 'store'])->middleware('once')->name('cashbox.store');
        Route::post('cashbox/requests/{cashRequest}/approve', [CashboxController::class, 'approve'])->name('cashbox.approve');
        Route::post('cashbox/requests/{cashRequest}/cancel', [CashboxController::class, 'cancel'])->name('cashbox.cancel');
        Route::post('cashbox/refunds/{payment}/confirm', [CashboxController::class, 'confirmRefund'])->name('cashbox.refund-confirm');
        Route::post('cashbox/refunds/{payment}/reject', [CashboxController::class, 'rejectRefund'])->name('cashbox.refund-reject');
        Route::post('cashbox/close', [CashboxController::class, 'closeShift'])->middleware('once')->name('cashbox.close');

        Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::post('finance/withdraw', [FinanceController::class, 'withdraw'])->name('finance.withdraw');
        Route::post('finance/charity-withdraw', [FinanceController::class, 'charityWithdraw'])->name('finance.charity-withdraw');
        Route::post('finance/expense', [FinanceController::class, 'expense'])->name('finance.expense');
        Route::post('finance/charity', [FinanceController::class, 'charity'])->name('finance.charity');
        Route::post('finance/deposit', [FinanceController::class, 'deposit'])->middleware('once')->name('finance.deposit');

        // Ikki bosqichli tasdiqlash ("Tekshiring" sahifasi): pul chiqadigan amallar shu yerda bajariladi
        Route::get('confirm/{token}', [ConfirmationController::class, 'show'])->name('confirm.show');
        Route::post('confirm/{token}', [ConfirmationController::class, 'store'])->name('confirm.store');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('payroll/{user}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{user}', [PayrollController::class, 'pay'])->name('payroll.pay');
    });

    // Filial sozlamalari va kurslar (sAdmin uchun filial tanlangan bo'lishi kerak)
    Route::middleware('branch')->group(function () {
        Route::get('settings/discount-rules', [DiscountRuleController::class, 'edit'])->name('discount-rules.edit');
        Route::put('settings/discount-rules', [DiscountRuleController::class, 'update'])->name('discount-rules.update');
        Route::get('settings/holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('settings/holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::post('settings/holidays/generate', [HolidayController::class, 'generate'])->name('holidays.generate');
        Route::delete('settings/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        Route::get('settings/contract', [ContractSettingsController::class, 'edit'])->name('contract-settings.edit');
        Route::put('settings/contract', [ContractSettingsController::class, 'update'])->name('contract-settings.update');
        Route::put('settings/contract/reset', [ContractSettingsController::class, 'reset'])->name('contract-settings.reset');
        Route::get('settings/usluga', [\App\Http\Controllers\UslugaSettingsController::class, 'edit'])->name('usluga-settings.edit');
        Route::put('settings/usluga', [\App\Http\Controllers\UslugaSettingsController::class, 'update'])->name('usluga-settings.update');

        Route::prefix('settings/{catalog}')->whereIn('catalog', ['courses', 'rooms', 'lesson-times', 'price-plans', 'campaigns', 'books', 'lead-sources', 'expense-categories'])->group(function () {
            Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
            Route::post('/', [CatalogController::class, 'store'])->name('catalog.store');
            Route::put('{id}', [CatalogController::class, 'update'])->name('catalog.update');
            Route::post('{id}/toggle', [CatalogController::class, 'toggle'])->name('catalog.toggle');
        });
    });

    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');
});
