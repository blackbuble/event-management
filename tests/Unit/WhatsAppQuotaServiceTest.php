<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Services\WhatsAppQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WhatsAppQuotaService
    {
        return app(WhatsAppQuotaService::class);
    }

    public function test_top_up_credits_quota_and_records_history(): void
    {
        $user = User::factory()->create(['whatsapp_quota' => 0]);

        $topUp = $this->service()->topUp($user, 'starter', PaymentMethod::Qris);

        $this->assertSame('paid', $topUp->status);
        $this->assertSame(100, $topUp->quota);
        $this->assertSame(100, $user->fresh()->whatsapp_quota);
        $this->assertCount(1, $this->service()->history($user));
    }

    public function test_consume_reduces_quota_and_blocks_when_empty(): void
    {
        $user = User::factory()->create(['whatsapp_quota' => 1]);

        $this->assertTrue($this->service()->consume($user, 1));
        $this->assertSame(0, $user->fresh()->whatsapp_quota);

        $this->assertFalse($this->service()->consume($user, 1));
        $this->assertSame(0, $user->fresh()->whatsapp_quota);
    }

    public function test_top_up_rejects_unknown_package(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->topUp(User::factory()->create(), 'unlimited', PaymentMethod::Qris);
    }

    public function test_packages_expose_locale_prices(): void
    {
        $packages = $this->service()->packages('id');

        $this->assertNotEmpty($packages);
        $this->assertArrayHasKey('key', $packages[0]);
        $this->assertArrayHasKey('quota', $packages[0]);
        $this->assertStringContainsString('Rp', $packages[0]['amount_label']);
    }
}
