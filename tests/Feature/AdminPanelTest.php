<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Models\WhatsAppPackage;
use App\Services\SettingsService;
use App\Services\WhatsAppQuotaService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['password' => Hash::make('secret-pass')]);
        $user->assignRole('admin');

        return $user;
    }

    private function organizer(): User
    {
        $user = User::factory()->create(['phone' => fake()->unique()->numerify('+62812#########')]);
        $user->assignRole('organizer');

        return $user;
    }

    public function test_non_admin_cannot_access_admin_panel(): void
    {
        $this->actingAs($this->organizer())
            ->get(route('admin.settings'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error');

        $this->actingAs($this->organizer())
            ->get(route('admin.categories'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_settings(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Admin/Settings')
                    ->has('settings.payment_gateway')
                    ->has('settings.whatsapp_provider')
                    ->has('settings.platform_fee')
            );
    }

    public function test_admin_can_update_general_settings(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.general'), [
            'app_name' => 'EventHub',
            'contact_center' => '0800-1234',
            'contact_email' => 'help@example.com',
        ])->assertRedirect();

        $general = app(SettingsService::class)->general();

        $this->assertSame('EventHub', $general['app_name']);
        $this->assertSame('0800-1234', $general['contact_center']);
        $this->assertSame('help@example.com', $general['contact_email']);
    }

    public function test_admin_can_update_payment_gateway_and_blank_secret_is_kept(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.payment'), [
            'enabled' => true,
            'provider' => 'midtrans',
            'api_key' => 'pk_test_123',
            'secret_key' => 'sk_test_456',
        ])->assertRedirect();

        $settings = app(SettingsService::class);
        $gateway = $settings->paymentGateway();
        $this->assertTrue($gateway['enabled']);
        $this->assertSame('midtrans', $gateway['provider']);
        $this->assertTrue($gateway['secret_key']);

        // Blank secret keeps the stored value.
        $this->actingAs($admin)->put(route('admin.settings.payment'), [
            'enabled' => true,
            'provider' => 'midtrans',
            'api_key' => 'pk_test_123',
            'secret_key' => '',
        ])->assertRedirect();

        $this->assertSame('sk_test_456', $settings->get('payment_gateway.secret_key'));
    }

    public function test_admin_can_update_platform_fee(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.fee'), [
            'type' => 'percent',
            'amount' => 5,
        ])->assertRedirect();

        $settings = app(SettingsService::class);
        $this->assertSame('percent', $settings->platformFee()['type']);
        $this->assertSame(5000.0, $settings->platformFeeFor(100000));
    }

    public function test_admin_can_manage_categories(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.categories'))->assertOk();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'slug' => 'gaming',
            'name' => 'Gaming',
            'name_en' => 'Gaming',
            'is_active' => true,
        ])->assertRedirect();

        $category = Category::where('slug', 'gaming')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'slug' => 'gaming',
            'name' => 'E-Sports',
            'name_en' => 'E-Sports',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('E-Sports', $category->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_admin_can_manage_cities(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.cities.store'), [
            'name' => 'Manado',
            'is_active' => true,
        ])->assertRedirect();

        $city = City::where('name', 'Manado')->firstOrFail();
        $this->assertTrue($city->is_active);
    }

    public function test_admin_can_manage_whatsapp_packages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'slug' => 'mega',
            'label' => 'Mega',
            'quota' => 5000,
            'amount' => 1500000,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('whatsapp_packages', ['slug' => 'mega', 'quota' => 5000]);

        $packages = app(WhatsAppQuotaService::class)->packages('en');
        $this->assertContains('mega', array_column($packages, 'key'));

        $package = WhatsAppPackage::where('slug', 'mega')->firstOrFail();
        $this->actingAs($admin)->delete(route('admin.packages.destroy', $package))->assertRedirect();
        $this->assertDatabaseMissing('whatsapp_packages', ['id' => $package->id]);
    }
}
