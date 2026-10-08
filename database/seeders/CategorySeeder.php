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
                'icon' => 'store',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Carinderia & Eatery',
                'icon' => 'utensils',
                'color' => '#EF4444',
            ],
            [
                'name' => 'Coffee Shops & Cafes',
                'icon' => 'coffee',
                'color' => '#8B5CF6',
            ],
            [
                'name' => 'Bakeries',
                'icon' => 'croissant',
                'color' => '#EC4899',
            ],
            [
                'name' => 'Palengke & Retail',
                'icon' => 'shopping-bag',
                'color' => '#10B981',
            ],
            [
                'name' => 'Services & Repair',
                'icon' => 'wrench',
                'color' => '#3B82F6',
            ],
            [
                'name' => 'Pharmacy & Botika',
                'icon' => 'pill',
                'color' => '#14B8A6',
            ],
            [
                'name' => 'Hotels & Transient',
                'icon' => 'bed-double',
                'color' => '#6366F1',
            ],
            [
                'name' => 'Barbershop & Salon',
                'icon' => 'scissors',
                'color' => '#F43F5E',
            ],
            [
                'name' => 'Computer Shop & Tech',
                'icon' => 'monitor',
                'color' => '#0284C7',
            ],
            [
                'name' => 'Bayad Center & Pawnshop',
                'icon' => 'banknote',
                'color' => '#059669',
            ],
            [
                'name' => 'Fast Food & Restaurant',
                'icon' => 'utensils-crossed',
                'color' => '#DC2626',
            ],
            [
                'name' => 'School & Tutorial Center',
                'icon' => 'graduation-cap',
                'color' => '#7C3AED',
            ],
            [
                'name' => 'KTV & Entertainment',
                'icon' => 'music',
                'color' => '#D946EF',
            ],
            [
                'name' => 'Grocery & Supermarket',
                'icon' => 'shopping-cart',
                'color' => '#16A34A',
            ],
            [
                'name' => 'Laundry & Dry Cleaning',
                'icon' => 'washing-machine',
                'color' => '#0891B2',
            ],
            [
                'name' => 'Condo & Real Estate',
                'icon' => 'building-2',
                'color' => '#475569',
            ],
            [
                'name' => 'Printing & Graphics',
                'icon' => 'printer',
                'color' => '#EA580C',
            ],
            [
                'name' => 'Travel & Tours',
                'icon' => 'plane',
                'color' => '#0D9488',
            ],
            [
                'name' => 'Internet & WiFi Cafe',
                'icon' => 'wifi',
                'color' => '#2563EB',
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
