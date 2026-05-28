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
            [
                'name' => 'Fast Food & Restaurant',
                'icon' => 'TruckIcon',
                'color' => '#DC2626',
            ],
            [
                'name' => 'School & Tutorial Center',
                'icon' => 'AcademicCapIcon',
                'color' => '#7C3AED',
            ],
            [
                'name' => 'KTV & Entertainment',
                'icon' => 'MusicalNoteIcon',
                'color' => '#D946EF',
            ],
            [
                'name' => 'Grocery & Supermarket',
                'icon' => 'ShoppingCartIcon',
                'color' => '#16A34A',
            ],
            [
                'name' => 'Laundry & Dry Cleaning',
                'icon' => 'ScissorsIcon',
                'color' => '#0891B2',
            ],
            [
                'name' => 'Condo & Real Estate',
                'icon' => 'BuildingOffice2Icon',
                'color' => '#475569',
            ],
            [
                'name' => 'Printing & Graphics',
                'icon' => 'PaintBrushIcon',
                'color' => '#EA580C',
            ],
            [
                'name' => 'Travel & Tours',
                'icon' => 'GlobeAltIcon',
                'color' => '#0D9488',
            ],
            [
                'name' => 'Internet & WiFi Cafe',
                'icon' => 'WifiIcon',
                'color' => '#2563EB',
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
