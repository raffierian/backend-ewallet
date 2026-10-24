<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Family;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $families = Family::all();

        foreach ($families as $family) {
            $this->seedCategories($family);
        }
    }

    private function seedCategories(Family $family)
    {
        // Income categories
        $incomeCategories = [
            ['name' => 'Salary', 'icon' => 'wallet', 'color' => '#4CAF50'],
            ['name' => 'Business', 'icon' => 'briefcase', 'color' => '#2196F3'],
            ['name' => 'Investment', 'icon' => 'trending-up', 'color' => '#FF9800'],
            ['name' => 'Gift', 'icon' => 'gift', 'color' => '#E91E63'],
            ['name' => 'Other Income', 'icon' => 'plus-circle', 'color' => '#9C27B0'],
        ];

        foreach ($incomeCategories as $category) {
            Category::create([
                'family_id' => $family->id,
                'name' => $category['name'],
                'type' => 'income',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'is_system' => true,
            ]);
        }

        // Expense categories
        $expenseCategories = [
            ['name' => 'Food & Dining', 'icon' => 'restaurant', 'color' => '#FF5722'],
            ['name' => 'Transportation', 'icon' => 'car', 'color' => '#607D8B'],
            ['name' => 'Shopping', 'icon' => 'shopping-bag', 'color' => '#E91E63'],
            ['name' => 'Entertainment', 'icon' => 'film', 'color' => '#9C27B0'],
            ['name' => 'Bills & Utilities', 'icon' => 'file-text', 'color' => '#FF9800'],
            ['name' => 'Healthcare', 'icon' => 'heart', 'color' => '#F44336'],
            ['name' => 'Education', 'icon' => 'book', 'color' => '#2196F3'],
            ['name' => 'Home & Garden', 'icon' => 'home', 'color' => '#4CAF50'],
            ['name' => 'Personal Care', 'icon' => 'user', 'color' => '#00BCD4'],
            ['name' => 'Gifts & Donations', 'icon' => 'gift', 'color' => '#673AB7'],
            ['name' => 'Insurance', 'icon' => 'shield', 'color' => '#3F51B5'],
            ['name' => 'Other Expense', 'icon' => 'more-horizontal', 'color' => '#795548'],
        ];

        foreach ($expenseCategories as $category) {
            Category::create([
                'family_id' => $family->id,
                'name' => $category['name'],
                'type' => 'expense',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'is_system' => true,
            ]);
        }
    }
}
