<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\OpenvpnConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OpenvpnConfigurationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_openvpn_index_lists_configurations(): void
    {
        $admin = Admin::factory()->create();
        OpenvpnConfiguration::create([
            'name' => 'Main OVPN',
            'client_name' => 'router',
            'connect_to' => '10.8.0.6',
            'port' => 1194,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/isp/openvpn-configurations');

        $response->assertOk()
            ->assertSee('Main OVPN')
            ->assertSee('10.8.0.6')
            ->assertSee('1194');
    }

    public function test_configure_script_download_accepts_token_with_extra_query_param(): void
    {
        config(['app.url' => 'https://oxdes.com']);
        config(['vpn.domain' => 'oxdes.com']);
        config(['vpn.script_ttl_minutes' => 30]);

        $config = OpenvpnConfiguration::create([
            'name' => 'ovpn-test',
            'client_name' => 'router',
            'connect_to' => '10.8.0.1',
            'port' => 1194,
            'certificate_name' => 'router',
            'auth' => 'sha1',
            'cipher' => 'aes256',
            'mode' => 'ip',
            'protocol' => 'tcp',
            'ca_cert_filename' => 'ca.crt',
            'client_cert_filename' => 'RTR-018.crt',
            'client_key_filename' => 'RTR-018.key',
        ]);

        $controller = app(\App\Http\Controllers\Admin\OpenvpnConfigurationController::class);
        $oneLiner = $controller->buildOneLiner($config);
        preg_match('/url="([^"]+)"/', $oneLiner, $matches);

        $this->assertNotEmpty($matches[1] ?? null);

        $response = $this->get($matches[1]);
        $response->assertOk();
        $response->assertSee('https://oxdes.com/ovpn/certs/' . $config->id, false);
        $response->assertSee('RTR-018.crt', false);
        $response->assertSee('RTR-018.key', false);
        $response->assertSee('/interface ovpn-client add name=ovpn-test connect-to=10.8.0.1 port=1194 certificate=router auth=sha1 cipher=aes256 mode=ip protocol=tcp disabled=no', false);
        $response->assertDontSee('BEGIN CERTIFICATE', false);
        $response->assertDontSee('BEGIN PRIVATE KEY', false);

        $responseWithExtra = $this->get($matches[1] . '&v=6.49.10');
        $responseWithExtra->assertOk();

        File::ensureDirectoryExists(storage_path('app/certs'));
        File::put(storage_path('app/certs/ca.crt'), 'fake-ca');
        File::put(storage_path('app/certs/RTR-018.crt'), 'fake-crt');
        File::put(storage_path('app/certs/RTR-018.key'), 'fake-key');

        preg_match('/\/tool fetch url="([^"]+\/ca\.crt[^"]*)"/', $response->getContent(), $certMatch);
        $this->assertNotEmpty($certMatch[1] ?? null);

        $certResponse = $this->get($certMatch[1] . '&v=6.49.10');
        $certResponse->assertOk();
        $certResponse->assertHeader('Content-Type', 'application/octet-stream');

        File::delete([
            storage_path('app/certs/ca.crt'),
            storage_path('app/certs/RTR-018.crt'),
            storage_path('app/certs/RTR-018.key'),
        ]);
    }

    public function test_download_script_rejects_invalid_or_expired_token(): void
    {
        $config = OpenvpnConfiguration::create([
            'name' => 'Main OVPN',
            'client_name' => 'router',
            'connect_to' => '10.8.0.1',
            'port' => 1194,
        ]);

        $invalid = route('ovpn.mikrotik.download_script', [
            'openvpnConfiguration' => $config->id,
            'token' => 'invalid-token',
        ]);
        $this->get($invalid)->assertForbidden();

        $expiredToken = 'expired-token';
        Cache::put('ovpn:download-token:' . $expiredToken, ['openvpn_configuration_id' => $config->id], now()->addSecond());
        $this->travel(2)->seconds();

        $expired = route('ovpn.mikrotik.download_script', [
            'openvpnConfiguration' => $config->id,
            'token' => $expiredToken,
        ]);
        $this->get($expired)->assertForbidden();
    }
}
