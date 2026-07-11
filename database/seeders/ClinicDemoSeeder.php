<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Specialist;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;

class ClinicDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ============================
        // شعبه‌ها
        // ============================
        $branchCenter = Branch::create([
            'name' => 'شعبه مرکزی',
            'phone' => '02100000001',
            'address' => 'تهران، خیابان ولیعصر، نرسیده به میدان ونک',
            'city' => 'تهران',
            'is_active' => true,
        ]);

        $branchWest = Branch::create([
            'name' => 'شعبه غرب تهران',
            'phone' => '02100000002',
            'address' => 'تهران، بلوار اشرفی اصفهانی',
            'city' => 'تهران',
            'is_active' => true,
        ]);

        $branches = [$branchCenter, $branchWest];

        // ============================
        // ادمین‌ها
        // ============================
        User::create([
            'name' => 'مدیر اصلی',
            'email' => 'admin@clinic.test',
            'phone' => '09120000001',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        User::create([
            'name' => 'مدیر شعبه غرب',
            'email' => 'admin2@clinic.test',
            'phone' => '09120000011',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        // ============================
        // مشتری‌های تستی
        // ============================
        User::create([
            'name' => 'کاربر تست',
            'email' => 'customer@clinic.test',
            'phone' => '09120000002',
            'password' => 'password123',
            'role' => User::ROLE_CUSTOMER,
        ]);

        User::create([
            'name' => 'مریم احمدی',
            'email' => 'customer2@clinic.test',
            'phone' => '09120000012',
            'password' => 'password123',
            'role' => User::ROLE_CUSTOMER,
        ]);

        // ============================
        // دسته‌بندی‌ها
        // ============================
        $skinCategory = ServiceCategory::create(['name' => 'پوست', 'slug' => 'skin', 'sort_order' => 1]);
        $hairCategory = ServiceCategory::create(['name' => 'مو', 'slug' => 'hair', 'sort_order' => 2]);
        $laserCategory = ServiceCategory::create(['name' => 'لیزر', 'slug' => 'laser', 'sort_order' => 3]);
        $nailsCategory = ServiceCategory::create(['name' => 'ناخن', 'slug' => 'nails', 'sort_order' => 4]);

        // ============================
        // خدمات
        // ============================
        $services = [
            // پوست
            Service::create([
                'service_category_id' => $skinCategory->id,
                'name' => 'پاکسازی پوست',
                'slug' => 'skin-cleaning',
                'description' => 'پاکسازی عمیق پوست صورت',
                'duration_minutes' => 30,
                'price' => 500_000,
                'is_active' => true,
            ]),
            Service::create([
                'service_category_id' => $skinCategory->id,
                'name' => 'میکرودرم‌ابریژن',
                'slug' => 'microdermabrasion',
                'description' => 'لایه‌برداری و جوان‌سازی پوست',
                'duration_minutes' => 40,
                'price' => 700_000,
                'is_active' => true,
            ]),
            Service::create([
                'service_category_id' => $skinCategory->id,
                'name' => 'تزریق ژل لب',
                'slug' => 'lip-filler',
                'description' => 'تزریق ژل هیالورونیک اسید',
                'duration_minutes' => 30,
                'price' => 2_500_000,
                'is_active' => true,
            ]),

            // مو
            Service::create([
                'service_category_id' => $hairCategory->id,
                'name' => 'اصلاح مو',
                'slug' => 'haircut',
                'description' => 'اصلاح و مدل مو',
                'duration_minutes' => 20,
                'price' => 300_000,
                'is_active' => true,
            ]),
            Service::create([
                'service_category_id' => $hairCategory->id,
                'name' => 'کراتینه مو',
                'slug' => 'hair-keratin',
                'description' => 'صافی و کراتینه‌ی مو',
                'duration_minutes' => 120,
                'price' => 3_000_000,
                'is_active' => true,
            ]),

            // لیزر
            Service::create([
                'service_category_id' => $laserCategory->id,
                'name' => 'لیزر موهای زائد صورت',
                'slug' => 'laser-face',
                'description' => 'لیزر تمام صورت',
                'duration_minutes' => 30,
                'price' => 800_000,
                'is_active' => true,
            ]),
            Service::create([
                'service_category_id' => $laserCategory->id,
                'name' => 'لیزر موهای زائد بدن',
                'slug' => 'laser-body',
                'description' => 'لیزر تمام بدن',
                'duration_minutes' => 60,
                'price' => 1_500_000,
                'is_active' => true,
            ]),

            // ناخن
            Service::create([
                'service_category_id' => $nailsCategory->id,
                'name' => 'کاشت ناخن',
                'slug' => 'nail-extension',
                'description' => 'کاشت ناخن با ژل',
                'duration_minutes' => 60,
                'price' => 900_000,
                'is_active' => true,
            ]),
        ];

        // هر خدمت رو به هر دو شعبه وصل می‌کنیم (برای سادگی)
        foreach ($services as $service) {
            $service->branches()->attach([$branchCenter->id, $branchWest->id]);
        }

        [$skinCleaning, $microderm, $lipFiller, $haircut, $keratin, $laserFace, $laserBody, $nails] = $services;

        // ============================
        // متخصص‌ها (هر کدوم تخصص و ساعت کاری متفاوت)
        // ============================

        // متخصص ۱: پوست - فقط شعبه‌ی مرکزی
        $specialistSkin = Specialist::create([
            'full_name' => 'دکتر سارا محمدی',
            'phone' => '09120000003',
            'bio' => 'متخصص پوست و زیبایی با ۱۰ سال سابقه',
            'is_active' => true,
        ]);
        $specialistSkin->branches()->attach($branchCenter->id);
        foreach ([$skinCleaning, $microderm, $lipFiller] as $service) {
            $specialistSkin->services()->attach($service->id, ['branch_id' => $branchCenter->id]);
        }
        User::create([
            'name' => 'دکتر سارا محمدی',
            'email' => 'specialist@clinic.test',
            'phone' => '09120000003',
            'password' => 'password123',
            'role' => User::ROLE_SPECIALIST,
            'specialist_id' => $specialistSkin->id,
        ]);
        foreach ([6, 0, 1, 2, 3] as $day) { // شنبه تا چهارشنبه
            WorkingHour::create([
                'specialist_id' => $specialistSkin->id,
                'branch_id' => $branchCenter->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        // متخصص ۲: مو - هر دو شعبه
        $specialistHair = Specialist::create([
            'full_name' => 'دکتر نگار رضایی',
            'phone' => '09120000004',
            'bio' => 'متخصص مو و کراتینه',
            'is_active' => true,
        ]);
        $specialistHair->branches()->attach([$branchCenter->id, $branchWest->id]);
        foreach ([$haircut, $keratin] as $service) {
            $specialistHair->services()->attach($service->id, ['branch_id' => $branchCenter->id]);
            $specialistHair->services()->attach($service->id, ['branch_id' => $branchWest->id]);
        }
        User::create([
            'name' => 'دکتر نگار رضایی',
            'email' => 'specialist2@clinic.test',
            'phone' => '09120000004',
            'password' => 'password123',
            'role' => User::ROLE_SPECIALIST,
            'specialist_id' => $specialistHair->id,
        ]);
        // شعبه‌ی مرکزی: شنبه تا سه‌شنبه صبح
        foreach ([6, 0, 1, 2] as $day) {
            WorkingHour::create([
                'specialist_id' => $specialistHair->id,
                'branch_id' => $branchCenter->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);
        }
        // شعبه‌ی غرب: چهارشنبه و پنجشنبه عصر
        foreach ([3, 4] as $day) {
            WorkingHour::create([
                'specialist_id' => $specialistHair->id,
                'branch_id' => $branchWest->id,
                'day_of_week' => $day,
                'start_time' => '14:00',
                'end_time' => '20:00',
            ]);
        }

        // متخصص ۳: لیزر - فقط شعبه‌ی غرب
        $specialistLaser = Specialist::create([
            'full_name' => 'دکتر امیر حسینی',
            'phone' => '09120000005',
            'bio' => 'متخصص لیزر موهای زائد',
            'is_active' => true,
        ]);
        $specialistLaser->branches()->attach($branchWest->id);
        foreach ([$laserFace, $laserBody] as $service) {
            $specialistLaser->services()->attach($service->id, ['branch_id' => $branchWest->id]);
        }
        User::create([
            'name' => 'دکتر امیر حسینی',
            'email' => 'specialist3@clinic.test',
            'phone' => '09120000005',
            'password' => 'password123',
            'role' => User::ROLE_SPECIALIST,
            'specialist_id' => $specialistLaser->id,
        ]);
        foreach ([6, 0, 1, 2, 3] as $day) {
            WorkingHour::create([
                'specialist_id' => $specialistLaser->id,
                'branch_id' => $branchWest->id,
                'day_of_week' => $day,
                'start_time' => '10:00',
                'end_time' => '18:00',
            ]);
        }

        // متخصص ۴: ناخن - هر دو شعبه، بدون حساب کاربری (فقط برای تست حالت بدون لاگین)
        $specialistNails = Specialist::create([
            'full_name' => 'دکتر لیلا کریمی',
            'phone' => '09120000006',
            'bio' => 'متخصص کاشت و طراحی ناخن',
            'is_active' => true,
        ]);
        $specialistNails->branches()->attach([$branchCenter->id, $branchWest->id]);
        $specialistNails->services()->attach($nails->id, ['branch_id' => $branchCenter->id]);
        $specialistNails->services()->attach($nails->id, ['branch_id' => $branchWest->id]);
        foreach ([6, 0, 1, 2, 3, 4] as $day) { // شنبه تا پنجشنبه
            WorkingHour::create([
                'specialist_id' => $specialistNails->id,
                'branch_id' => $branchCenter->id,
                'day_of_week' => $day,
                'start_time' => '11:00',
                'end_time' => '19:00',
            ]);
        }

        // ============================
        // خروجی خلاصه توی ترمینال
        // ============================
        $this->command->info('داده‌ی تستی با موفقیت ساخته شد:');
        $this->command->table(
            ['نقش', 'ایمیل', 'پسورد'],
            [
                ['ادمین ۱', 'admin@clinic.test', 'password123'],
                ['ادمین ۲', 'admin2@clinic.test', 'password123'],
                ['مشتری ۱', 'customer@clinic.test', 'password123'],
                ['مشتری ۲', 'customer2@clinic.test', 'password123'],
                ['متخصص پوست', 'specialist@clinic.test', 'password123'],
                ['متخصص مو', 'specialist2@clinic.test', 'password123'],
                ['متخصص لیزر', 'specialist3@clinic.test', 'password123'],
                ['متخصص ناخن (بدون حساب)', '—', '—'],
            ]
        );

        $this->command->info('۲ شعبه، ۴ دسته‌بندی، ۸ خدمت، ۴ متخصص ساخته شد.');
    }
}