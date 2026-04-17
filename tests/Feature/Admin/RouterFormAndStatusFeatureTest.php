<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Router;
use App\Services\RouterConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterFormAndStatusFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_form_does_not_accept_model_version_and_domain_name_input(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post('/admin/isp/routers', [
            'name' => 'Router A',
            'connection_type' => 'public_ip',
            'ip_address' => '41.215.10.5',
            'nas_secret' => 'testing123',
            'port' => 8728,
            'notes' => 'test',
            'is_active' => 1,
            'model' => 'InjectedModel',
            'routeros_version' => 'InjectedVersion',
            'domain_name' => 'evil.example',
        ]);

        $response->assertRedirect('/admin/isp/routers');

        $router = Router::where('name', 'Router A')->firstOrFail();
        $this->assertNull($router->model);
        $this->assertNull($router->routeros_version);
        $this->assertNull($router->domain_name);
    }

    public function test_connection_type_dropdown_has_only_public_ip_and_through_openvpn(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get('/admin/isp/routers/create');

        $response->assertOk();
        $response->assertSee('Public IP');
        $response->assertSee('Through OpenVPN');
        $response->assertSee('<option value="openvpn"', false);
        $response->assertDontSee('<option value="hotspot"', false);
    }

    public function test_router_store_validates_connection_type_values(): void
    {
        $admin = Admin::factory()->create();

        $valid = $this->actingAs($admin, 'admin')->post('/admin/isp/routers', [
            'name' => 'Router Public',
            'connection_type' => 'public_ip',
            'ip_address' => '41.215.10.6',
            'nas_secret' => 'testing123',
            'port' => 8728,
            'is_active' => 1,
        ]);
        $valid->assertRedirect('/admin/isp/routers');

        $invalid = $this->from('/admin/isp/routers/create')->actingAs($admin, 'admin')->post('/admin/isp/routers', [
            'name' => 'Router Invalid',
            'connection_type' => 'hotspot',
            'ip_address' => '41.215.10.7',
            'nas_secret' => 'testing123',
            'port' => 8728,
            'is_active' => 1,
        ]);
        $invalid->assertSessionHasErrors('connection_type');

        $missing = $this->from('/admin/isp/routers/create')->actingAs($admin, 'admin')->post('/admin/isp/routers', [
            'name' => 'Router Missing',
            'ip_address' => '41.215.10.8',
            'nas_secret' => 'testing123',
            'port' => 8728,
            'is_active' => 1,
        ]);
        $missing->assertSessionHasErrors('connection_type');
    }

    public function test_router_index_uses_cached_status_without_live_mikrotik_call(): void
    {
        $admin = Admin::factory()->create();

        $router = Router::create([
            'name' => 'Cached Router',
            'connection_type' => 'public_ip',
            'wan_ip' => '41.215.10.5',
            'radius_secret' => 'testing123',
            'wan_interface' => 'ether1',
            'customer_interface' => 'ether2',
            'pppoe_pool_range' => '10.10.1.1-10.10.1.254',
            'hotspot_pool_range' => '10.20.1.1-10.20.1.254',
            'is_active' => true,
            'status' => 'offline',
            'last_checked_at' => now(),
        ]);

        $this->mock(RouterConnectionService::class)->shouldNotReceive('test');

        $response = $this->actingAs($admin, 'admin')->get('/admin/isp/routers');

        $response->assertOk()->assertSee('OFFLINE');

        $statusResponse = $this->actingAs($admin, 'admin')->get('/admin/isp/routers/statuses');
        $statusResponse->assertOk()->assertJsonFragment([
            'id' => $router->id,
            'status' => 'offline',
        ]);
    }
}
