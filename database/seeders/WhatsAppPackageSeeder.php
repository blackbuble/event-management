<?php

namespace Database\Seeders;

use App\Models\WhatsAppPackage;
use Illuminate\Database\Seeder;

class WhatsAppPackageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('whatsapp.packages', []) as $slug => $package) {
            WhatsAppPackage::updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => $package['label'],
                    'quota' => $package['quota'],
                    'amount' => $package['amount'],
                    'is_active' => true,
                ],
            );
        }
    }
}
