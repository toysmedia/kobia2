<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\OpenvpnConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OpenvpnConfigurationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_openvpn_index_lists_configurations(): void
    {
        $admin = Admin::factory()->create();
        OpenvpnConfiguration::create([
            'name' => 'Main OVPN',
            'client_name' => 'RTR-018',
            'auth_username' => 'RTR-018',
            'tunnel_ip' => '10.8.0.6',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/isp/openvpn-configurations');

        $response->assertOk()
            ->assertSee('Main OVPN')
            ->assertSee('RTR-018');
    }

    public function test_configure_script_generation_uses_env_domain_and_cert_filenames(): void
    {
        config(['app.url' => 'https://oxdes.com']);
        config(['vpn.domain' => 'oxdes.com']);

        $config = OpenvpnConfiguration::create([
            'name' => 'Main OVPN',
            'client_name' => 'RTR-018',
            'auth_username' => 'RTR-018',
            'tunnel_ip' => '10.8.0.6',
            'ca_cert_filename' => 'ca.crt',
            'client_cert_filename' => 'RTR-018.crt',
            'client_key_filename' => 'RTR-018.key',
        ]);

        $signedUrl = URL::temporarySignedRoute('ovpn.mikrotik.download_script', now()->addMinutes(10), [
            'openvpnConfiguration' => $config->id,
        ]);

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertSee('https://oxdes.com/ovpn/certs/' . $config->id, false);
        $response->assertSee('RTR-018.crt', false);
        $response->assertSee('RTR-018.key', false);
        $response->assertDontSee('BEGIN CERTIFICATE', false);
        $response->assertDontSee('BEGIN PRIVATE KEY', false);
    }
}
