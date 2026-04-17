<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpenvpnConfiguration;
use App\Services\RouterConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OpenvpnConfigurationController extends Controller
{
    public function index()
    {
        $configs = OpenvpnConfiguration::query()->latest()->get();

        return view('admin.configuration.openvpn_index', compact('configs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'client_name' => 'required|string|max:120',
            'auth_username' => 'nullable|string|max:120',
            'tunnel_ip' => 'nullable|ip',
            'router_ip' => 'nullable|ip',
            'api_port' => 'nullable|integer|min:1|max:65535',
            'openvpn_port' => 'nullable|integer|min:1|max:65535',
            'notes' => 'nullable|string',
        ]);

        $data['status'] = 'draft';
        $data['api_port'] = $data['api_port'] ?? 8728;
        $data['openvpn_port'] = $data['openvpn_port'] ?? 443;
        $data['auth_username'] = $data['auth_username'] ?: $data['client_name'];

        OpenvpnConfiguration::create($data);

        return back()->with('success', 'OpenVPN configuration created.');
    }

    public function update(Request $request, OpenvpnConfiguration $openvpnConfiguration)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'client_name' => 'required|string|max:120',
            'auth_username' => 'nullable|string|max:120',
            'tunnel_ip' => 'nullable|ip',
            'router_ip' => 'nullable|ip',
            'api_port' => 'nullable|integer|min:1|max:65535',
            'openvpn_port' => 'nullable|integer|min:1|max:65535',
            'status' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

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
        abort_unless($request->hasValidSignature(), 403);

        $domain = $this->domain();
        $certUrls = [
            'ca' => $this->signedCertUrl($openvpnConfiguration, 'ca.crt'),
            'crt' => $this->signedCertUrl($openvpnConfiguration, 'client.crt'),
            'key' => $this->signedCertUrl($openvpnConfiguration, 'client.key'),
        ];

        $remoteHost = $domain;
        $remotePort = (int) ($openvpnConfiguration->openvpn_port ?: 443);
        $user = $openvpnConfiguration->auth_username ?: $openvpnConfiguration->client_name;
        $certName = pathinfo($openvpnConfiguration->client_cert_filename, PATHINFO_FILENAME);

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
            '/interface ovpn-client add name="ovpn-kobia" connect-to="' . $remoteHost . '" port=' . $remotePort . ' user="' . $user . '" certificate="' . $certName . '" auth=sha1 cipher=aes256 mode=ip disabled=no',
        ]) . "\n";

        $filename = 'kobia-ovpn-' . $openvpnConfiguration->id . '.rsc';

        return response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadCert(Request $request, OpenvpnConfiguration $openvpnConfiguration, string $file)
    {
        abort_unless($request->hasValidSignature(), 403);

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
        $ttl = now()->addMinutes((int) config('vpn.script_ttl_minutes', 30));
        $url = URL::temporarySignedRoute('ovpn.mikrotik.download_script', $ttl, ['openvpnConfiguration' => $openvpnConfiguration->id]);

        return ':local ver [/system resource get version] ; :local version [:pick $ver 0 [:find $ver " "]] ; /tool fetch url="' . $url . '&v=$version" dst-path="kobia-ovpn-setup.rsc" ; :delay 2s ; /import file-name="kobia-ovpn-setup.rsc" ; :delay 2s ; /file remove "kobia-ovpn-setup.rsc";';
    }

    protected function signedCertUrl(OpenvpnConfiguration $openvpnConfiguration, string $file): string
    {
        return URL::temporarySignedRoute(
            'ovpn.mikrotik.download_cert',
            now()->addMinutes((int) config('vpn.script_ttl_minutes', 30)),
            ['openvpnConfiguration' => $openvpnConfiguration->id, 'file' => $file]
        );
    }

    protected function domain(): string
    {
        return (string) config('vpn.domain', parse_url(config('app.url'), PHP_URL_HOST));
    }
}
