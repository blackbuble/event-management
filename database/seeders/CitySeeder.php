<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang',
            'Medan', 'Makassar', 'Denpasar', 'Balikpapan', 'Palembang',
        ];

        foreach ($cities as $city) {
            City::firstOrCreate(['name' => $city], ['is_active' => true]);
        }
    }
}
