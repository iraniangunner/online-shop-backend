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
        // ── شعبه ──
        $branch = Branch::create([
            'name' => 'شعبه مرکزی',
            'phone' => '02100000000',
            'address' => 'تهران، خیابان ولیعصر',
            'city' => 'تهران',
            'is_active' => true,
        ]);

        // ── ادمین ──
        User::create([
            'name' => 'مدیر کلینیک',
            'email' => 'admin@clinic.test',
            'phone' => '09120000001',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        // ── مشتری تستی ──
        User::create([
            'name' => 'کاربر تست',
            'email' => 'customer@clinic.test',
            'phone' => '09120000002',
            'password' => 'password123',
            'role' => User::ROLE_CUSTOMER,
        ]);

        // ── دسته‌بندی‌ها و خدمات ──
        $skinCategory = ServiceCategory::create(['name' => 'پوست', 'slug' => 'skin', 'sort_order' => 1]);
        $hairCategory = ServiceCategory::create(['name' => 'مو', 'slug' => 'hair', 'sort_order' => 2]);

        $skinCleaning = Service::create([
            'service_category_id' => $skinCategory->id,
            'name' => 'پاکسازی پوست',
            'slug' => 'skin-cleaning',
            'description' => 'پاکسازی عمیق پوست صورت',
            'duration_minutes' => 30,
            'price' => 500_000,
            'is_active' => true,
        ]);

        $laser = Service::create([
            'service_category_id' => $skinCategory->id,
            'name' => 'لیزر موهای زائد',
            'slug' => 'laser-hair-removal',
            'description' => 'لیزر تمام صورت',
            'duration_minutes' => 45,
            'price' => 800_000,
            'is_active' => true,
        ]);

        $haircut = Service::create([
            'service_category_id' => $hairCategory->id,
            'name' => 'اصلاح مو',
            'slug' => 'haircut',
            'description' => 'اصلاح و مدل مو',
            'duration_minutes' => 20,
            'price' => 300_000,
            'is_active' => true,
        ]);

        foreach ([$skinCleaning, $laser, $haircut] as $service) {
            $service->branches()->attach($branch->id);
        }

        // ── متخصص با حساب کاربری برای ورود به پنل ──
        $specialist = Specialist::create([
            'full_name' => 'دکتر نمونه',
            'phone' => '09120000003',
            'bio' => 'متخصص پوست و مو با ۱۰ سال سابقه',
            'is_active' => true,
        ]);

        $specialist->branches()->attach($branch->id);

        foreach ([$skinCleaning, $laser, $haircut] as $service) {
            $specialist->services()->attach($service->id, ['branch_id' => $branch->id]);
        }

        User::create([
            'name' => 'دکتر نمونه',
            'email' => 'specialist@clinic.test',
            'phone' => '09120000003',
            'password' => 'password123',
            'role' => User::ROLE_SPECIALIST,
            'specialist_id' => $specialist->id,
        ]);

        // ── ساعات کاری: شنبه تا چهارشنبه، ۹ تا ۱۷ ──
        // Carbon dayOfWeek: 0=یکشنبه، 1=دوشنبه، ... 6=شنبه
        foreach ([6, 0, 1, 2, 3] as $day) {
            WorkingHour::create([
                'specialist_id' => $specialist->id,
                'branch_id' => $branch->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        $this->command->info('داده‌ی تستی با موفقیت ساخته شد:');
        $this->command->table(['نقش', 'ایمیل', 'پسورد'], [
            ['ادمین', 'admin@clinic.test', 'password123'],
            ['مشتری', 'customer@clinic.test', 'password123'],
            ['متخصص', 'specialist@clinic.test', 'password123'],
        ]);
    }
}