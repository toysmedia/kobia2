<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Models\Nas;
use App\Models\AuditLog;
use App\Jobs\RefreshAllRouterStatusesJob;
use App\Services\RouterConnectionService;
use App\Services\MikrotikScriptService;
use App\Services\RouterOSApiService;
use App\Http\Requests\Admin\StoreRouterRequest;
use App\Http\Requests\Admin\UpdateRouterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class RouterController extends Controller
{
    public function __construct(protected MikrotikScriptService $scriptService) {}

   public function index()
    {
        $routers = Router::orderBy('name')->get();
        $nasIps  = Nas::pluck('nasname')->flip()->toArray();

        // TODO: enable router auto-configure in a future release.
        // $routers->each(fn (Router $router) => $this->autoConfigure($router));
        if (request()->boolean('refresh_statuses')) {
            RefreshAllRouterStatusesJob::dispatch()->onQueue('router-status');
        }

        return view('admin.isp.routers.index', compact('routers', 'nasIps'));
    }

    public function create()
    {
        return view('admin.isp.routers.create');
    }

    public function store(StoreRouterRequest $request)
    {
        $validated = $request->validated();

        // Map form fields to DB columns
        $data = [
            'name'              => $validated['name'],
            'connection_type'   => $validated['connection_type'],
            'radius_secret'     => $validated['nas_secret'],
            'shortname'         => Str::slug($validated['name']),
            'api_port'          => $validated['port'] ?? 8728,
            'notes'             => $validated['notes'] ?? null,
            'is_active'         => $request->boolean('is_active', true),
            'wan_interface'     => 'ether1',
            'customer_interface' => 'bridge1',
            'billing_domain'    => parse_url((string) config('app.url'), PHP_URL_HOST) ?: '',
            'service_mode'      => 'pppoe_hotspot',
            'openvpn_port'      => config('openvpn.port', 443),
            'pppoe_bridge_name' => 'pppoe_bridge',
            'hotspot_bridge_name' => 'hotspot_bridge',
            'hotspot_gateway_ip' => '11.220.0.1',
            'hotspot_prefix'    => 16,
            'pppoe_gateway_ip'  => '19.225.0.1',
            'timezone'          => 'Africa/Nairobi',
        ];

        // Set the correct IP field based on connection type
        if ($validated['connection_type'] === 'openvpn') {
            $data['vpn_ip'] = $validated['ip_address'];
            $data['wan_ip'] = null;
        } else {
            $data['wan_ip'] = $validated['ip_address'];
            $data['vpn_ip'] = null;
        }

        // Default pool ranges
        $data['pppoe_pool_range']   = '10.10.1.1-10.10.1.254';
        $data['hotspot_pool_range'] = '10.20.1.1-10.20.1.254';

        $router = Router::create($data);

        // Generate ref_code and unique pool ranges
        $poolOctet = (($router->id - 1) % 254) + 1;
        $router->update([
            'ref_code'           => 'RTR-' . str_pad($router->id, 3, '0', STR_PAD_LEFT),
            'pppoe_pool_range'   => "10.10.{$poolOctet}.1-10.10.{$poolOctet}.254",
            'hotspot_pool_range' => "10.20.{$poolOctet}.1-10.20.{$poolOctet}.254",
        ]);

        // Sync NAS entry in FreeRADIUS
        $this->syncNas($router);

        // Auto-fetch system info from MikroTik (silently fails if unreachable)
        $fetchError = $this->fetchAndSaveSystemInfo($router);

        AuditLog::record('router.created', Router::class, $router->id, [], $router->fresh()->toArray());

        $msg = "Router '{$router->name}' created successfully.";
        if ($fetchError) {
            $msg .= " (System info could not be fetched: {$fetchError})";
        }

        return redirect()->route('admin.isp.routers.index')
            ->with('success', $msg);
    }

    public function edit(Router $router)
    {
        return view('admin.isp.routers.edit', compact('router'));
    }

    public function update(UpdateRouterRequest $request, Router $router)
    {
        $old  = $router->toArray();
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'connection_type' => $validated['connection_type'],
            'radius_secret' => $validated['nas_secret'],
            'api_port' => $validated['port'],
            'notes' => $validated['notes'] ?? null,
            'shortname' => Str::slug($validated['name']),
            'wan_interface' => $router->wan_interface ?: 'ether1',
            'customer_interface' => $router->customer_interface ?: 'bridge1',
        ];

        if ($validated['connection_type'] === 'openvpn') {
            $data['vpn_ip'] = $validated['ip_address'];
            $data['wan_ip'] = null;
        } else {
            $data['wan_ip'] = $validated['ip_address'];
            $data['vpn_ip'] = null;
        }

        $data['is_active'] = $request->boolean('is_active', true);
        $router->update($data);
        $router->refresh();

        // Sync NAS entry
        if ($router->vpn_ip || $router->wan_ip) {
            $this->syncNas($router);
        }

        // Auto-fetch system info if router supports API
        $fetchError = null;
        if ($router->supportsApi()) {
            $fetchError = $this->fetchAndSaveSystemInfo($router);
        }

        AuditLog::record('router.updated', Router::class, $router->id, $old, $router->fresh()->toArray());

        $redirect = redirect()->route('admin.isp.routers.index')
            ->with('success', "Router '{$router->name}' updated.");

        if ($fetchError) {
            $redirect->with('system_info_error', $fetchError);
        }

        return $redirect;
    }

    public function destroy(Router $router)
    {
        AuditLog::record('router.deleted', Router::class, $router->id, $router->toArray(), []);
        $nasIp = $router->vpn_ip ?: $router->wan_ip;
        if ($nasIp) {
            Nas::where('nasname', $nasIp)->delete();
        }
        $router->delete();
        return redirect()->route('admin.isp.routers.index')->with('success', 'Router deleted.');
    }

    public function show(Router $router)
    {
        return view('admin.isp.routers.show', compact('router'));
    }

    public function script(Router $router)
    {
        $foundationScript = $this->scriptService->generateFoundation($router);
        $servicesScript   = $this->scriptService->generateServices($router);

        return view('admin.isp.routers.script', compact('router', 'foundationScript', 'servicesScript'));
    }

    public function downloadScript(Router $router, Request $request)
    {
        $section = $request->query('section', 'full');

        switch ($section) {
            case 'foundation':
                $script = $this->scriptService->generateFoundation($router);
                $suffix = '-foundation';
                break;
            case 'services':
                $script = $this->scriptService->generateServices($router);
                $suffix = '-services';
                break;
            default:
                $script = $this->scriptService->generate($router);
                $suffix = '-full';
        }

        $filename = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $router->name)) . $suffix . '.rsc';
        return response($script, 200, [
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function provisionStatus(Router $router)
    {
        $router->refresh();

        $ip     = $router->vpn_ip ?: $router->wan_ip;
        $online = false;

        if ($ip) {
            try {
                $apiPort = $router->api_port ?? 80;
                $apiUser = $router->api_username ?? 'admin';
                $apiPass = $router->api_password ?? '';

                $response = Http::withBasicAuth($apiUser, $apiPass)
                    ->timeout(3)
                    ->withoutVerifying()
                    ->get("http://{$ip}:{$apiPort}/rest/system/identity");
                $online = $response->successful();
            } catch (\Exception $e) {
                $online = false;
            }
        }

        return response()->json([
            'provision_phase' => (int) ($router->provision_phase ?? 0),
            'vpn_ip'          => $router->vpn_ip,
            'wan_ip'          => $router->wan_ip,
            'online'          => $online,
            'last_heartbeat'  => $router->last_heartbeat_at?->diffForHumans(),
        ]);
    }

    public function provision(string $token)
    {
        $router = Router::where('ref_code', $token)
                        ->where('is_active', true)
                        ->firstOrFail();

        $script   = app(MikrotikScriptService::class)->generate($router);
        $filename = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $router->name)) . '-mikrotik.rsc';

        return response($script, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function serveHotspotFile(Router $router, string $file)
    {
        $allowedFiles = [
            'login.html'    => ['view' => 'hotspot.login',    'type' => 'text/html; charset=utf-8'],
            'alogin.html'   => ['view' => 'hotspot.alogin',   'type' => 'text/html; charset=utf-8'],
            'status.html'   => ['view' => 'hotspot.status',   'type' => 'text/html; charset=utf-8'],
            'logout.html'   => ['view' => 'hotspot.logout',   'type' => 'text/html; charset=utf-8'],
            'redirect.html' => ['view' => 'hotspot.redirect', 'type' => 'text/html; charset=utf-8'],
            'error.html'    => ['view' => 'hotspot.error',    'type' => 'text/html; charset=utf-8'],
            'flogin.html'   => ['view' => 'hotspot.flogin',   'type' => 'text/html; charset=utf-8'],
            'fstatus.html'  => ['view' => 'hotspot.fstatus',  'type' => 'text/html; charset=utf-8'],
            'flogout.html'  => ['view' => 'hotspot.flogout',  'type' => 'text/html; charset=utf-8'],
            'rlogin.html'   => ['view' => 'hotspot.rlogin',   'type' => 'text/html; charset=utf-8'],
            'rstatus.html'  => ['view' => 'hotspot.rstatus',  'type' => 'text/html; charset=utf-8'],
            'radvert.html'  => ['view' => 'hotspot.radvert',  'type' => 'text/html; charset=utf-8'],
            'md5.js'        => ['view' => 'hotspot.md5',      'type' => 'application/javascript'],
            'errors.txt'    => ['view' => 'hotspot.errors',   'type' => 'text/plain; charset=utf-8'],
            'api.json'      => ['view' => 'hotspot.api_json', 'type' => 'application/json'],
        ];

        if (!array_key_exists($file, $allowedFiles)) {
            abort(404);
        }

        $entry    = $allowedFiles[$file];
        $viewName = $entry['view'];

        if (!view()->exists($viewName)) {
            abort(404);
        }

        $parsedHost    = parse_url(config('app.url'), PHP_URL_HOST);
        $billingDomain = $router->billing_domain ?: ($parsedHost ?: 'localhost');
        $appName       = config('app.name', 'iNettotik');

        return response(
            view($viewName, compact('router', 'billingDomain', 'appName'))->render(),
            200,
            ['Content-Type' => $entry['type']]
        );
    }

    public function serveHotspotFileByRefCode(string $refCode, string $file)
    {
        $router = Router::where('ref_code', $refCode)
                        ->where('is_active', true)
                        ->firstOrFail();

        return $this->serveHotspotFile($router, $file);
    }

    public function serveCertFile(string $refCode, string $file)
    {
        $allowedFiles = ['ca.crt', 'router.crt', 'router.key'];

        if (!in_array($file, $allowedFiles, true)) {
            abort(404);
        }

        $router = Router::where('ref_code', $refCode)
                        ->where('is_active', true)
                        ->firstOrFail();

        if ($file === 'ca.crt') {
            $filename = basename($router->ca_cert_filename ?? 'ca.crt');
        } elseif ($file === 'router.crt') {
            $filename = basename($router->router_cert_filename ?? 'router.crt');
        } else {
            // router.key — derive from the cert filename
            $certFilename = $router->router_cert_filename ?? 'router.crt';
            $filename = basename(pathinfo($certFilename, PATHINFO_FILENAME) . '.key');
        }

        $path = storage_path('app/certs/' . $filename);

        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function downloadHotspotFiles(Router $router)
    {
        $tmpDir  = sys_get_temp_dir();
        $zipPath = $tmpDir . '/hotspot-' . $router->id . '-' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Failed to create ZIP file.');
        }

        $billingDomain = $router->billing_domain ?: parse_url(config('app.url'), PHP_URL_HOST);
        $appName       = config('app.name', 'iNettotik');

        $zip->addFromString('login.html',    view('hotspot.login',    compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('alogin.html',   view('hotspot.alogin',   compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('status.html',   view('hotspot.status',   compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('logout.html',   view('hotspot.logout',   compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('redirect.html', view('hotspot.redirect', compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('error.html',    view('hotspot.error',    compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('flogin.html',   view('hotspot.flogin',   compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('fstatus.html',  view('hotspot.fstatus',  compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('flogout.html',  view('hotspot.flogout',  compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('rlogin.html',   view('hotspot.rlogin',   compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('rstatus.html',  view('hotspot.rstatus',  compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('radvert.html',  view('hotspot.radvert',  compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('md5.js',        view('hotspot.md5',      compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('errors.txt',    view('hotspot.errors',   compact('router', 'billingDomain', 'appName'))->render());
        $zip->addFromString('api.json',      view('hotspot.api_json', compact('router', 'billingDomain', 'appName'))->render());
        $zip->close();

        return response()->download($zipPath, "hotspot-files-{$router->id}.zip", [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function testConnection(Request $request, Router $router, RouterConnectionService $connectionService)
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:120',
            'password' => 'required|string|max:255',
        ]);

        $host = $router->getNasIp();
        if (!$host) {
            return response()->json([
                'status' => 'unreachable',
                'message' => 'Router IP is not configured.',
            ]);
        }

        $result = $connectionService->test(
            $host,
            (int) ($router->api_port ?? 8728),
            $credentials['username'],
            $credentials['password'],
        );

        $router->update([
            'status' => $result['status'],
            'last_checked_at' => now(),
            'model' => $result['data']['model'] ?? $router->model,
            'routeros_version' => $result['data']['version'] ?? $router->routeros_version,
            'router_identity' => $result['data']['identity'] ?? $router->router_identity,
            'domain_name' => $result['data']['domain_name'] ?? $router->domain_name,
        ]);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
        ]);
    }

    public function configureRouter(Request $request, Router $router)
    {
        $step = (int) $request->input('step');
        if (!in_array($step, [1, 2, 3, 4, 5], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid configure step.',
            ], 422);
        }

        Log::info('Router configure attempt started', [
            'router_id' => $router->id,
            'router_name' => $router->name,
            'step' => $step,
        ]);

        try {
            switch ($step) {
                case 1:
                    if ($router->isHotspot()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Hotspot mode — API steps skipped',
                            'data' => ['skipped' => true],
                        ]);
                    }

                    if ($router->isVpn() && !$router->vpn_ip) {
                        return response()->json([
                            'success' => false,
                            'message' => 'VPN tunnel not established yet',
                        ]);
                    }

                    $api = RouterOSApiService::fromRouter($router);
                    $api->connect();
                    $systemInfo = $api->getSystemInfo();

                    $updates = [];
                    if (!empty($systemInfo['board-name']) && $router->model !== $systemInfo['board-name']) {
                        $updates['model'] = $systemInfo['board-name'];
                    }
                    if (!empty($systemInfo['version']) && $router->routeros_version !== $systemInfo['version']) {
                        $updates['routeros_version'] = $systemInfo['version'];
                    }
                    if (!empty($updates)) {
                        $router->update($updates);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Connected to MikroTik API successfully',
                        'data' => ['system_info' => $systemInfo],
                    ]);

                case 2:
                    $nasIp = $router->getNasIp();
                    if (!$nasIp) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No NAS IP available for this router',
                        ]);
                    }

                    Nas::updateOrCreate(
                        ['nasname' => $nasIp],
                        [
                            'shortname' => $router->name,
                            'secret' => $router->radius_secret,
                            'type' => 'other',
                        ]
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'NAS entry registered in FreeRADIUS',
                        'data' => ['nas_ip' => $nasIp],
                    ]);

                case 3:
                    if ($router->isHotspot()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Skipped — Hotspot mode',
                            'data' => ['skipped' => true],
                        ]);
                    }

                    $api = RouterOSApiService::fromRouter($router);
                    $api->connect();

                    $billingServerIp = (string) ($router->billing_server_vpn_ip ?: config('app.billing_server_ip', '127.0.0.1'));
                    $radiusRows = $api->sendCommand('/radius/print');
                    $radiusId = null;

                    foreach ($radiusRows as $row) {
                        if (($row['address'] ?? null) === $billingServerIp) {
                            $radiusId = $row['.id'] ?? $row['id'] ?? null;
                            break;
                        }
                    }

                    $radiusArgs = [
                        'service' => 'ppp,hotspot',
                        'address' => $billingServerIp,
                        'secret' => $router->radius_secret,
                    ];

                    if ($radiusId) {
                        $api->sendCommand('/radius/set', array_merge(['numbers' => (string) $radiusId], $radiusArgs));
                    } else {
                        $api->sendCommand('/radius/add', $radiusArgs);
                    }

                    $api->sendCommand('/radius/incoming/set', [
                        'accept' => 'yes',
                        'port' => '3799',
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'RADIUS configuration pushed to MikroTik',
                    ]);

                case 4:
                    if ($router->isHotspot()) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Skipped — Hotspot mode',
                            'data' => ['skipped' => true],
                        ]);
                    }

                    $api = RouterOSApiService::fromRouter($router);
                    $api->connect();

                    $profileRows = $api->sendCommand('/ppp/profile/print');
                    $profileId = null;
                    foreach ($profileRows as $row) {
                        if (($row['name'] ?? null) === 'pppoe-profile') {
                            $profileId = $row['.id'] ?? $row['id'] ?? null;
                            break;
                        }
                    }

                    $profileArgs = [
                        'name' => 'pppoe-profile',
                        'local-address' => $router->pppoe_gateway_ip ?: '19.225.0.1',
                        'use-radius' => 'yes',
                        'only-one' => 'yes',
                    ];

                    if ($profileId) {
                        $api->sendCommand('/ppp/profile/set', array_merge(['numbers' => (string) $profileId], $profileArgs));
                    } else {
                        $api->sendCommand('/ppp/profile/add', $profileArgs);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'PPPoE server profile configured',
                    ]);

                case 5:
                    $router->update([
                        'provision_phase' => 3,
                        'last_heartbeat_at' => now(),
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Provisioning completed successfully',
                    ]);
            }
        } catch (\Throwable $e) {
            Log::error('Router configure step failed', [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'step' => $step,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Quick online check for the index table status column.
     * Lighter than full testConnection — just pings the REST API.
     */
    public function pingStatus(Router $router)
    {
        return response()->json([
            'status' => $router->status ?? 'unreachable',
            'last_checked_at' => optional($router->last_checked_at)->toIso8601String(),
        ]);
    }

    public function statuses()
    {
        return response()->json(
            Router::query()
                ->orderBy('name')
                ->get(['id', 'status', 'last_checked_at'])
                ->map(fn (Router $router) => [
                    'id' => $router->id,
                    'status' => $router->status ?? 'unreachable',
                    'last_checked_at' => optional($router->last_checked_at)->toIso8601String(),
                ])
        );
    }

    protected function syncNas(Router $router): void
    {
        $nasIp = $router->vpn_ip ?: $router->wan_ip;
        if (!$nasIp) {
            return;
        }
        Nas::updateOrCreate(
            ['nasname' => $nasIp],
            [
                'shortname'   => $router->name,
                'type'        => 'mikrotik',
                'secret'      => $router->radius_secret,
                'description' => $router->name . ' - MikroTik',
            ]
        );
    }
    
        /**
     * Fetch system info from MikroTik router via API
     * 
     * @param Router $router
     * @return string|null Returns error message or null on success
     */
    private function fetchAndSaveSystemInfo(Router $router): ?string
    {
        // Skip for hotspot-only routers (no API)
        if ($router->isHotspot()) {
            return null;
        }
        
        // Get the host IP (VPN IP takes precedence for VPN connections)
        $host = $router->vpn_ip ?: $router->wan_ip;
        
        if (!$host) {
            return 'No IP address available for API connection.';
        }
        
        try {
            // Use the RouterOSApiService to connect and fetch info
            $api = RouterOSApiService::fromRouter($router);
            $api->connect();
            $info = $api->getSystemInfo();
            $api->disconnect();
            
            // Update router with fetched info
            $updates = [];
            if (!empty($info['board-name']) && $router->model !== $info['board-name']) {
                $updates['model'] = $info['board-name'];
            }
            if (!empty($info['version']) && $router->routeros_version !== $info['version']) {
                $updates['routeros_version'] = $info['version'];
            }
            if (!empty($updates)) {
                $router->update($updates);
            }
            
            // Update the system_info_fetched_at timestamp if the column exists
            if (Schema::hasColumn('routers', 'system_info_fetched_at')) {
                $router->update(['system_info_fetched_at' => now()]);
            }
            
            return null; // Success
            
        } catch (\Exception $e) {
            // Log error but don't fail the router creation
            Log::warning('Could not fetch system info for router ' . $router->id, [
                'host' => $host,
                'error' => $e->getMessage()
            ]);
            return $e->getMessage();
        }
    }

    protected function autoConfigure(Router $router): void
    {
        // TODO: enable router auto-configure in a future release.
        // Planned flow:
        // 1) Connect to RouterOS API
        // 2) Push identity, DNS, PPPoE and Hotspot baseline
        // 3) Register/verify RADIUS AAA settings
    }
}
