<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ServicesStatus extends Command
{
    protected $signature = 'services:status';
    protected $description = 'Get service status as JSON';

    public function handle()
    {
        $services = [
            $this->getOpenVpnStatus(),
            $this->getFreeRadiusStatus(),
            $this->getSupervisorStatus(),
        ];

        $this->info(json_encode(['services' => $services], JSON_PRETTY_PRINT));
        return 0;
    }

    protected function getOpenVpnStatus()
    {
        exec("sudo supervisorctl status openvpn 2>&1", $output, $code);
        $outputStr = implode("\n", $output);
        
        $isRunning = strpos($outputStr, 'RUNNING') !== false;
        $pid = 'N/A';
        $uptime = 'N/A';
        
        if ($isRunning) {
            preg_match('/pid (\d+)/', $outputStr, $pidMatch);
            preg_match('/uptime (.*?)(?:\n|$)/', $outputStr, $uptimeMatch);
            $pid = $pidMatch[1] ?? 'N/A';
            $uptime = trim($uptimeMatch[1] ?? 'N/A');
        }
        
        return [
            'key' => 'openvpn',
            'name' => 'OpenVPN',
            'running' => $isRunning,
            'pid' => $pid,
            'uptime' => $uptime,
            'version' => '2.6.19',
            'config_path' => '/etc/openvpn/server.conf',
        ];
    }

    protected function getFreeRadiusStatus()
    {
        exec("sudo supervisorctl status freeradius 2>&1", $output, $code);
        $outputStr = implode("\n", $output);
        
        $isRunning = strpos($outputStr, 'RUNNING') !== false;
        $pid = 'N/A';
        $uptime = 'N/A';
        
        if ($isRunning) {
            preg_match('/pid (\d+)/', $outputStr, $pidMatch);
            preg_match('/uptime (.*?)(?:\n|$)/', $outputStr, $uptimeMatch);
            $pid = $pidMatch[1] ?? 'N/A';
            $uptime = trim($uptimeMatch[1] ?? 'N/A');
        }
        
        return [
            'key' => 'freeradius',
            'name' => 'FreeRADIUS',
            'running' => $isRunning,
            'pid' => $pid,
            'uptime' => $uptime,
            'version' => '3.2.8',
            'config_path' => '/etc/freeradius/3.0/radiusd.conf',
        ];
    }

    protected function getSupervisorStatus()
    {
        exec("systemctl is-active supervisor 2>&1", $output, $code);
        $isRunning = trim(implode("\n", $output)) === 'active';
        
        $pid = 'N/A';
        if ($isRunning) {
            exec("systemctl show supervisor --property=MainPID --value 2>&1", $pidOutput, $pidCode);
            $pidNum = (int) trim(implode("\n", $pidOutput));
            $pid = $pidNum > 0 ? (string)$pidNum : 'N/A';
        }
        
        return [
            'key' => 'supervisor',
            'name' => 'Supervisor',
            'running' => $isRunning,
            'pid' => $pid,
            'uptime' => 'N/A',
            'version' => '4.2.5',
            'config_path' => '/etc/supervisor/supervisord.conf',
        ];
    }
}
