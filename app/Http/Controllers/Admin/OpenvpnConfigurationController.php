<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpenvpnConfiguration;
use App\Http\Requests\Admin\StoreOpenvpnConfigurationRequest;
use App\Http\Requests\Admin\UpdateOpenvpnConfigurationRequest;
use App\Services\RouterConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OpenvpnConfigurationController extends Controller
{
    public function index()
    {
        $configs = OpenvpnConfiguration::query()->latest()->get();

        return view('admin.configuration.openvpn_index', compact('configs'));
    }

    public function store(StoreOpenvpnConfigurationRequest $request)
    {
        $validated = $request->validated();
        $data = [
            'name' => $validated['name'],
            'connect_to' => $validated['connect_to'],
            'port' => (int) $validated['port'],
            'client_name' => 'router',
            'tunnel_ip' => $validated['connect_to'],
            'openvpn_port' => (int) $validated['port'],
            'status' => 'draft',
            'api_port' => 8728,
            'certificate_name' => 'router',
            'auth' => 'sha1',
            'cipher' => 'aes256',
            'mode' => 'ip',
            'protocol' => 'tcp',
        ];

        OpenvpnConfiguration::create($data);

        return back()->with('success', 'OpenVPN configuration created.');
    }

    public function update(UpdateOpenvpnConfigurationRequest $request, OpenvpnConfiguration $openvpnConfiguration)
    {
        $validated = $request->validated();
        $data = [
            'name' => $validated['name'],
            'connect_to' => $validated['connect_to'],
            'port' => (int) $validated['port'],
            'tunnel_ip' => $validated['connect_to'],
            'openvpn_port' => (int) $validated['port'],
        ];

        $openvpnConfiguration->update($data);

        return back()->with('success', 'OpenVPN configuration updated.');
    }

    public function destroy(OpenvpnConfiguration $openvpnConfiguration)
    {
        foreach ([$openvpnConfiguration->client_cert_filename, $openvpnConfiguration->client_key_filename] as $fileName) {
            if ($fileName) {
                $path = storage_path('app/certs/' . basename($fileName));
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $openvpnConfiguration->delete();

        return back()->with('success', 'OpenVPN configuration deleted.');
    }

    public function oneLiner(OpenvpnConfiguration $openvpnConfiguration)
    {
        return response()->json([
            'one_liner' => $this->buildOneLiner($openvpnConfiguration),
        ]);
    }

    public function downloadScript(Request $request, OpenvpnConfiguration $openvpnConfiguration)
    {
        abort_unless($this->hasValidDownloadToken($request, $openvpnConfiguration), 403);

        $certToken = $this->issueDownloadToken($openvpnConfiguration);

        $domain = $this->domain();
        $certUrls = [
            'ca' => $this->certUrl($openvpnConfiguration, 'ca.crt', $certToken),
            'crt' => $this->certUrl($openvpnConfiguration, 'client.crt', $certToken),
            'key' => $this->certUrl($openvpnConfiguration, 'client.key', $certToken),
        ];

        $remoteHost = $openvpnConfiguration->connect_to ?: $openvpnConfiguration->tunnel_ip ?: $domain;
        $remotePort = (int) ($openvpnConfiguration->port ?: $openvpnConfiguration->openvpn_port ?: 1194);
        $certificateName = $openvpnConfiguration->certificate_name ?: 'router';
        $auth = $openvpnConfiguration->auth ?: 'sha1';
        $cipher = $openvpnConfiguration->cipher ?: 'aes256';
        $mode = $openvpnConfiguration->mode ?: 'ip';
        $protocol = $openvpnConfiguration->protocol ?: 'tcp';

        $script = implode("\n", [
            '/tool fetch url="' . $certUrls['ca'] . '" dst-path="ca.crt"',
            '/tool fetch url="' . $certUrls['crt'] . '" dst-path="' . basename($openvpnConfiguration->client_cert_filename) . '"',
            '/tool fetch url="' . $certUrls['key'] . '" dst-path="' . basename($openvpnConfiguration->client_key_filename) . '"',
            ':delay 2s',
            '/certificate import file-name="ca.crt" passphrase=""',
            '/certificate import file-name="' . basename($openvpnConfiguration->client_cert_filename) . '" passphrase=""',
            '/certificate import file-name="' . basename($openvpnConfiguration->client_key_filename) . '" passphrase=""',
            '/file remove "ca.crt"',
            '/file remove "' . basename($openvpnConfiguration->client_cert_filename) . '"',
            '/file remove "' . basename($openvpnConfiguration->client_key_filename) . '"',
            '/interface ovpn-client add name=' . $openvpnConfiguration->name . ' connect-to=' . $remoteHost . ' port=' . $remotePort . ' certificate=' . $certificateName . ' auth=' . $auth . ' cipher=' . $cipher . ' mode=' . $mode . ' protocol=' . $protocol . ' disabled=no',
        ]) . "\n";

        $filename = 'oxdes-ovpn-' . $openvpnConfiguration->id . '.rsc';

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadCert(Request $request, OpenvpnConfiguration $openvpnConfiguration, string $file)
    {
        abort_unless($this->hasValidDownloadToken($request, $openvpnConfiguration), 403);

        $map = [
            'ca.crt' => $openvpnConfiguration->ca_cert_filename,
            'client.crt' => $openvpnConfiguration->client_cert_filename,
            'client.key' => $openvpnConfiguration->client_key_filename,
        ];

        abort_unless(array_key_exists($file, $map), 404);

        $path = storage_path('app/certs/' . basename($map[$file]));
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function testConnection(Request $request, OpenvpnConfiguration $openvpnConfiguration, RouterConnectionService $connectionService)
    {
        $payload = $request->validate([
            'username' => 'required|string|max:120',
            'password' => 'required|string|max:255',
        ]);

        $host = $openvpnConfiguration->router_ip ?: $openvpnConfiguration->tunnel_ip;
        if (!$host) {
            return response()->json([
                'status' => 'unreachable',
                'message' => 'Router IP / tunnel IP is not set.',
            ]);
        }

        $result = $connectionService->test($host, (int) $openvpnConfiguration->api_port, $payload['username'], $payload['password']);

        $openvpnConfiguration->update([
            'last_test_status' => $result['status'],
            'last_test_message' => $result['message'],
            'last_tested_at' => now(),
            'status' => $result['status'] === 'online' ? 'active' : 'draft',
        ]);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
        ]);
    }

    public function buildOneLiner(OpenvpnConfiguration $openvpnConfiguration): string
    {
        $token = $this->issueDownloadToken($openvpnConfiguration);
        $url = route('ovpn.mikrotik.download_script', [
            'openvpnConfiguration' => $openvpnConfiguration->id,
            'token' => $token,
        ]);

        return ':local ver [/system resource get version] ; :local version [:pick $ver 0 [:find $ver " "]] ; /tool fetch url="' . $url . '&v=$version" dst-path="oxdes-ovpn-setup.rsc" ; :delay 2s ; /import file-name="oxdes-ovpn-setup.rsc" ; :delay 2s ; /file remove "oxdes-ovpn-setup.rsc";';
    }

    protected function certUrl(OpenvpnConfiguration $openvpnConfiguration, string $file, string $token): string
    {
        return route(
            'ovpn.mikrotik.download_cert',
            ['openvpnConfiguration' => $openvpnConfiguration->id, 'file' => $file, 'token' => $token]
        );
    }

    protected function domain(): string
    {
        return (string) config('vpn.domain', parse_url(config('app.url'), PHP_URL_HOST));
    }

    protected function issueDownloadToken(OpenvpnConfiguration $openvpnConfiguration): string
    {
        $token = Str::random(64);
        $ttl = now()->addMinutes((int) config('vpn.script_ttl_minutes', 30));

        Cache::put($this->tokenCacheKey($token), ['openvpn_configuration_id' => $openvpnConfiguration->id], $ttl);

        return $token;
    }

    protected function hasValidDownloadToken(Request $request, OpenvpnConfiguration $openvpnConfiguration): bool
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            return false;
        }

        $data = Cache::get($this->tokenCacheKey($token));

        return is_array($data) && (int) ($data['openvpn_configuration_id'] ?? 0) === (int) $openvpnConfiguration->id;
    }

    protected function tokenCacheKey(string $token): string
    {
        return 'ovpn:download-token:' . $token;
    }
}
