<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\RouterConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckRouterStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'router-status';

    public function __construct(public int $routerId)
    {
    }

    public function handle(RouterConnectionService $connectionService): void
    {
        $router = Router::find($this->routerId);

        if (!$router || !$router->is_active) {
            return;
        }

        $host = $router->connection_type === 'openvpn'
            ? ($router->vpn_ip ?: $router->wan_ip)
            : ($router->wan_ip ?: $router->vpn_ip);

        if (!$host) {
            $router->update([
                'status' => 'unreachable',
                'last_checked_at' => now(),
            ]);

            return;
        }

        $password = '';
        if (!empty($router->api_password)) {
            try {
                $password = decrypt($router->api_password);
            } catch (\Throwable) {
                $password = (string) $router->api_password;
            }
        }

        $result = $connectionService->test(
            $host,
            (int) ($router->api_port ?? 8728),
            (string) ($router->api_username ?? 'admin'),
            $password
        );

        $router->update([
            'status' => $result['status'],
            'last_checked_at' => now(),
        ]);
    }
}
