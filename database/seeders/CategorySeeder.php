<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Sari-Sari Store & Tindahan',
                'icon' => 'BuildingStorefrontIcon',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Carinderia & Eatery',
                'icon' => 'FireIcon',
                'color' => '#EF4444',
            ],
            [
                'name' => 'Coffee Shops & Cafes',
                'icon' => 'SparklesIcon',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Bakeries',
                'icon' => 'CakeIcon',
                'color' => '#EC4899',
            ],
            [
                'name' => 'Palengke & Retail',
                'icon' => 'ShoppingBagIcon',
                'color' => '#10B981',
            ],
            [
                'name' => 'Services & Repair',
                'icon' => 'WrenchScrewdriverIcon',
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Pharmacy & Botika',
                'icon' => 'HeartIcon',
                'color' => '#14B8A6',
            ],
            [
                'name' => 'Hotels & Transient',
                'icon' => 'HomeIcon',
                'color' => '#6366F1',
            ],
            [
                'name' => 'Barbershop & Salon',
                'icon' => 'FaceSmileIcon',
                'color' => '#F43F5E',
            ],
            [
                'name' => 'Computer Shop & Tech',
                'icon' => 'ComputerDesktopIcon',
                'color' => '#0284C7',
            ],
            [
                'name' => 'Bayad Center & Pawnshop',
                'icon' => 'BanknotesIcon',
                'color' => '#059669',
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
