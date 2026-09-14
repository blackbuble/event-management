<?php

namespace Database\Seeders;

use App\Enums\EventCategory;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (EventCategory::cases() as $category) {
            Category::updateOrCreate(
                ['slug' => $category->value],
                [
                    'name' => $category->label('id'),
                    'name_en' => $category->label('en'),
                    'is_active' => true,
                ],
            );
        }
    }
}
