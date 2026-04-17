<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IspSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ConfigurationController extends Controller
{
    protected const SUPERVISORCTL_BIN = '/usr/local/bin/supervisorctl';
    protected const SYSTEMCTL_BIN     = '/bin/systemctl';

    public function index()
    {
        return view('admin.configuration.index');
    }

    public function servicesStatus(): JsonResponse
    {
        try {
            $services = $this->getDirectServiceStatus();
            
            return response()->json([
                'success' => true,
                'data' => ['services' => $services]
            ]);
        } catch (\Throwable $e) {
            Log::error('servicesStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restartService(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => ['required', 'in:openvpn,freeradius,supervisor'],
        ]);

        $service = $validated['service'];
        
        if ($service === 'supervisor') {
            exec('sudo ' . self::SYSTEMCTL_BIN . ' restart supervisor 2>&1', $output, $exitCode);
            if ($exitCode === 0) {
                sleep(2);
                return response()->json([
                    'success' => true,
                    'message' => "Service {$service} restarted successfully.",
                    'source' => 'systemd',
                ]);
            }
        } else {
            exec('sudo ' . self::SUPERVISORCTL_BIN . ' restart ' . escapeshellarg($service) . ' 2>&1', $output, $exitCode);
            if ($exitCode === 0) {
                sleep(2);
                return response()->json([
                    'success' => true,
                    'message' => "Service {$service} restarted successfully.",
                    'source' => 'supervisor',
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to restart {$service}.",
            'error' => implode("\n", $output ?? []),
        ], 500);
    }

    /**
     * Get direct service status without using Artisan command
     */
    private function getDirectServiceStatus(): array
    {
        $services = [];
        
        // 1. OpenVPN Status
        $openvpnStatus = $this->getSupervisorStatus('openvpn');
        $services[] = [
            'key' => 'openvpn',
            'name' => 'OpenVPN',
            'running' => $openvpnStatus['running'],
            'pid' => $openvpnStatus['pid'],
            'uptime' => $openvpnStatus['uptime'],
            'version' => $this->getOpenVpnVersion(),
            'config_path' => '/etc/openvpn/server.conf',
        ];
        
        // 2. FreeRADIUS Status
        $radiusStatus = $this->getSupervisorStatus('freeradius');
        $services[] = [
            'key' => 'freeradius',
            'name' => 'FreeRADIUS',
            'running' => $radiusStatus['running'],
            'pid' => $radiusStatus['pid'],
            'uptime' => $radiusStatus['uptime'],
            'version' => $this->getFreeRadiusVersion(),
            'config_path' => '/etc/freeradius/3.0/radiusd.conf',
        ];
        
        // 3. Supervisor Status
        $supervisorStatus = $this->getSystemdStatus('supervisor');
        $services[] = [
            'key' => 'supervisor',
            'name' => 'Supervisor',
            'running' => $supervisorStatus['running'],
            'pid' => $supervisorStatus['pid'],
            'uptime' => 'N/A',
            'version' => $this->getSupervisorVersion(),
            'config_path' => '/etc/supervisor/supervisord.conf',
        ];
        
        return $services;
    }

    /**
     * Get service status from Supervisor
     */
    private function getSupervisorStatus(string $program): array
    {
        exec('sudo ' . self::SUPERVISORCTL_BIN . ' status ' . escapeshellarg($program) . ' 2>&1', $output, $exitCode);
        $outputStr = implode("\n", $output);
        
        $isRunning = strpos($outputStr, 'RUNNING') !== false;
        $pid = 'N/A';
        $uptime = 'N/A';
        
        if ($isRunning) {
            if (preg_match('/pid (\d+)/', $outputStr, $matches)) {
                $pid = $matches[1];
            }
            if (preg_match('/uptime (.*?)(?:\n|$)/', $outputStr, $matches)) {
                $uptime = trim($matches[1]);
            }
        }
        
        return [
            'running' => $isRunning,
            'pid' => $pid,
            'uptime' => $uptime,
        ];
    }

    /**
     * Get service status from Systemd
     */
    private function getSystemdStatus(string $service): array
    {
        exec('systemctl is-active ' . escapeshellarg($service) . ' 2>&1', $output, $exitCode);
        $isRunning = trim(implode("\n", $output)) === 'active';
        
        $pid = 'N/A';
        if ($isRunning) {
            exec('systemctl show ' . escapeshellarg($service) . ' --property=MainPID --value 2>&1', $pidOutput, $pidCode);
            $pidNum = (int) trim(implode("\n", $pidOutput));
            $pid = $pidNum > 0 ? (string)$pidNum : 'N/A';
        }
        
        return [
            'running' => $isRunning,
            'pid' => $pid,
            'uptime' => 'N/A',
        ];
    }

    /**
     * Get OpenVPN version
     */
    private function getOpenVpnVersion(): string
    {
        exec('openvpn --version 2>&1 | head -1', $output);
        $outputStr = implode("\n", $output);
        if (preg_match('/\d+\.\d+\.\d+/', $outputStr, $matches)) {
            return $matches[0];
        }
        return '2.6.19';
    }

    /**
     * Get FreeRADIUS version
     */
    private function getFreeRadiusVersion(): string
    {
        exec('radiusd -v 2>&1 | head -1', $output);
        $outputStr = implode("\n", $output);
        if (preg_match('/\d+\.\d+\.\d+/', $outputStr, $matches)) {
            return $matches[0];
        }
        return '3.2.8';
    }

    /**
     * Get Supervisor version
     */
    private function getSupervisorVersion(): string
    {
        exec('supervisord --version 2>&1', $output);
        $version = trim(implode("\n", $output));
        return $version ?: '4.2.5';
    }

    // ══════════════════════════════════════════════════════════════════
    //  SECTION 5A — Configure RADIUS
    // ══════════════════════════════════════════════════════════════════

    public function radius()
    {
        $settings = [
            'radius_shared_secret' => IspSetting::getValue('radius_shared_secret', 'testing123'),
            'radius_auth_port'     => IspSetting::getValue('radius_auth_port', '1812'),
            'radius_acct_port'     => IspSetting::getValue('radius_acct_port', '1813'),
            'radius_server_ip'     => IspSetting::getValue('radius_server_ip', '127.0.0.1'),
            'radius_mysql_host'    => IspSetting::getValue('radius_mysql_host', '127.0.0.1'),
            'radius_mysql_db'      => IspSetting::getValue('radius_mysql_db', ''),
            'radius_mysql_user'    => IspSetting::getValue('radius_mysql_user', ''),
            'radius_mysql_pass'    => IspSetting::getValue('radius_mysql_pass', ''),
        ];

        return view('admin.configuration.radius', compact('settings'));
    }

    public function saveRadius(Request $request)
    {
        $validated = $request->validate([
            'radius_shared_secret' => 'required|string|max:255',
            'radius_auth_port'     => 'required|integer|min:1|max:65535',
            'radius_acct_port'     => 'required|integer|min:1|max:65535',
            'radius_server_ip'     => 'required|ip',
            'radius_mysql_host'    => 'required|string|max:255',
            'radius_mysql_db'      => 'required|string|max:255',
            'radius_mysql_user'    => 'required|string|max:255',
            'radius_mysql_pass'    => 'required|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            IspSetting::setValue($key, $value);
        }

        $errors = [];

        try {
            $this->writeRadiusSqlConfig($validated);
        } catch (\Throwable $e) {
            Log::error('writeRadiusSqlConfig failed', ['error' => $e->getMessage()]);
            $errors[] = 'SQL module config: ' . $e->getMessage();
        }

        try {
            $this->writeRadiusClientsConf($validated);
        } catch (\Throwable $e) {
            Log::error('writeRadiusClientsConf failed', ['error' => $e->getMessage()]);
            $errors[] = 'clients.conf: ' . $e->getMessage();
        }

        try {
            $this->restartServiceByName('freeradius');
        } catch (\Throwable $e) {
            Log::error('restart freeradius failed', ['error' => $e->getMessage()]);
            $errors[] = 'Restart FreeRADIUS: ' . $e->getMessage();
        }

        if (!empty($errors)) {
            return redirect()->route('admin.isp.configuration.radius')
                ->with('warning', 'Settings saved, but some config writes failed: ' . implode(' | ', $errors));
        }

        return redirect()->route('admin.isp.configuration.radius')
            ->with('success', 'RADIUS configuration saved and applied successfully.');
    }

    // ══════════════════════════════════════════════════════════════════
    //  SECTION 5B — Configure OpenVPN
    // ══════════════════════════════════════════════════════════════════

    public function openvpn()
    {
        return redirect()->route('admin.isp.openvpn_configurations.index');
    }

    public function saveOpenvpn(Request $request)
    {
        $validated = $request->validate([
            'openvpn_server_ip' => 'required|ip',
            'openvpn_port'      => 'required|integer|min:1|max:65535',
            'openvpn_subnet'    => ['required', 'string', 'regex:/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/'],
            'openvpn_key_path'  => 'required|string|max:500',
            'openvpn_protocol'  => 'required|in:udp,tcp',
        ]);

        foreach ($validated as $key => $value) {
            IspSetting::setValue($key, $value);
        }

        $errors = [];

        try {
            $this->writeOpenvpnServerConf($validated);
        } catch (\Throwable $e) {
            Log::error('writeOpenvpnServerConf failed', ['error' => $e->getMessage()]);
            $errors[] = 'server.conf: ' . $e->getMessage();
        }

        try {
            $this->restartServiceByName('openvpn');
        } catch (\Throwable $e) {
            Log::error('restart openvpn failed', ['error' => $e->getMessage()]);
            $errors[] = 'Restart OpenVPN: ' . $e->getMessage();
        }

        if (!empty($errors)) {
            return redirect()->route('admin.isp.configuration.openvpn')
                ->with('warning', 'Settings saved, but some config writes failed: ' . implode(' | ', $errors));
        }

        return redirect()->route('admin.isp.configuration.openvpn')
            ->with('success', 'OpenVPN configuration saved and applied successfully.');
    }

    // ══════════════════════════════════════════════════════════════════
    //  Private helpers
    // ══════════════════════════════════════════════════════════════════

    private function backupFile(string $path): void
    {
        if (!File::exists($path)) {
            return;
        }

        $backup = $path . '.bak.' . date('Ymd_His');
        File::copy($path, $backup);
        Log::info("Backed up {$path} → {$backup}");
    }

    private function writeRadiusSqlConfig(array $settings): void
    {
        $sqlConfigPath = '/etc/freeradius/3.0/mods-available/sql';
        $this->backupFile($sqlConfigPath);

        $content = <<<EOT
# FreeRADIUS SQL module — auto-generated by billing system
# Generated: {$this->timestamp()}

sql {
    driver = "rlm_sql_mysql"
    dialect = "mysql"

    server = "{$settings['radius_mysql_host']}"
    port = 3306
    login = "{$settings['radius_mysql_user']}"
    password = "{$settings['radius_mysql_pass']}"

    radius_db = "{$settings['radius_mysql_db']}"

    acct_table1 = "radacct"
    acct_table2 = "radacct"
    postauth_table = "radpostauth"
    authcheck_table = "radcheck"
    groupcheck_table = "radgroupcheck"
    authreply_table = "radreply"
    groupreply_table = "radgroupreply"
    usergroup_table = "radusergroup"

    delete_stale_sessions = yes
    sql_user_name = "%{User-Name}"

    default_user_profile = ""
    client_table = "nas"

    group_attribute = "SQL-Group"

    \$INCLUDE \${modconfdir}/\${.:instance}/main/\${dialect}/queries.conf

    pool {
        start = 5
        min = 3
        max = 32
        spare = 10
        uses = 0
        retry_delay = 30
        lifetime = 0
        idle_timeout = 60
    }

    read_clients = yes
    client_table = "nas"
}
EOT;

        File::put($sqlConfigPath, $content);
        
        $enabledLink = '/etc/freeradius/3.0/mods-enabled/sql';
        if (!File::exists($enabledLink)) {
            $this->runCommand("sudo ln -sf {$sqlConfigPath} {$enabledLink} 2>&1");
        }
    }

    private function writeRadiusClientsConf(array $settings): void
    {
        $clientsConfPath = '/etc/freeradius/3.0/clients.conf';
        $this->backupFile($clientsConfPath);

        $content = "# FreeRADIUS clients.conf — auto-generated\n";
        $content .= "# Generated: {$this->timestamp()}\n\n";

        $content .= "client localhost {\n";
        $content .= "    ipaddr = 127.0.0.1\n";
        $content .= "    secret = {$settings['radius_shared_secret']}\n";
        $content .= "    nastype = other\n";
        $content .= "}\n\n";

        $routers = \App\Models\Router::where('is_active', true)->get();
        foreach ($routers as $router) {
            $nasIp = $router->vpn_ip ?: $router->wan_ip;
            if (!$nasIp) continue;

            $shortname = $router->shortname ?: preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower($router->name));
            $secret = $router->radius_secret ?: $settings['radius_shared_secret'];

            $content .= "client {$shortname} {\n";
            $content .= "    ipaddr = {$nasIp}\n";
            $content .= "    secret = {$secret}\n";
            $content .= "    nastype = mikrotik\n";
            $content .= "    shortname = {$shortname}\n";
            $content .= "}\n\n";
        }

        File::put($clientsConfPath, $content);
    }

    private function writeOpenvpnServerConf(array $settings): void
    {
        $confPath = '/etc/openvpn/server.conf';
        $this->backupFile($confPath);

        $parts = explode('/', $settings['openvpn_subnet']);
        $network = $parts[0];
        $prefix = (int) ($parts[1] ?? 24);
        $mask = long2ip(-1 << (32 - $prefix));

        $content = <<<EOT
# OpenVPN server.conf — auto-generated
# Generated: {$this->timestamp()}

local {$settings['openvpn_server_ip']}
port {$settings['openvpn_port']}
proto {$settings['openvpn_protocol']}
dev tun

ca /etc/openvpn/ca.crt
cert /etc/openvpn/server.crt
key /etc/openvpn/server.key
dh /etc/openvpn/dh.pem

tls-auth {$settings['openvpn_key_path']} 0
cipher AES-256-CBC
auth SHA256

server {$network} {$mask}
ifconfig-pool-persist /etc/openvpn/ipp.txt
push "route {$network} {$mask}"

keepalive 10 120
max-clients 100

user nobody
group nogroup
persist-key
persist-tun

status /var/log/openvpn/openvpn-status.log
log-append /var/log/openvpn/openvpn.log
verb 3
mute 20

management 127.0.0.1 7505
EOT;

        File::put($confPath, $content);
    }

    private function restartServiceByName(string $service): void
    {
        exec('sudo ' . self::SUPERVISORCTL_BIN . ' restart ' . escapeshellarg($service) . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            $systemdMap = [
                'openvpn' => 'openvpn-server',
                'freeradius' => 'freeradius',
            ];
            $unit = $systemdMap[$service] ?? $service;
            exec('sudo ' . self::SYSTEMCTL_BIN . ' restart ' . escapeshellarg($unit) . ' 2>&1', $output, $exitCode);
            
            if ($exitCode !== 0) {
                throw new \RuntimeException("Could not restart {$service}");
            }
        }
    }

    private function timestamp(): string
    {
        return date('Y-m-d H:i:s T');
    }

    protected function runCommand(string $command): array
    {
        $output = [];
        $exitCode = 1;
        exec($command, $output, $exitCode);

        return [
            'output' => trim(implode("\n", $output)),
            'exit_code' => $exitCode,
        ];
    }
}
