<?php

namespace App\Console\Commands;

use App\Models\Nas;
use App\Models\Router;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Bulk-sync all active routers into the FreeRADIUS NAS table.
 *
 * For each active router, ensures a NAS entry exists with the correct IP:
 *   - Type B routers (VPN-connected): NAS IP = vpn_ip
 *   - Type A routers (WAN-only):      NAS IP = wan_ip
 *
 * Also cleans up orphaned NAS entries that don't match any active router.
 */
class SyncRouterNasEntries extends Command
{
    protected $signature   = 'app:sync-router-nas';
    protected $description = 'Sync all active routers into the FreeRADIUS NAS table and clean orphans';

    public function handle(): int
    {
        $routers = Router::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            $this->info('No active routers found.');
            return 0;
        }

        $synced  = 0;
        $skipped = 0;
        $validNasIps = [];

        foreach ($routers as $router) {
            $nasIp = $router->vpn_ip ?: $router->wan_ip;

            if (!$nasIp) {
                $skipped++;
                continue;
            }

            $validNasIps[] = $nasIp;

            Nas::updateOrCreate(
                ['nasname' => $nasIp],
                [
                    'shortname'   => $router->name,
                    'type'        => 'other',
                    'secret'      => $router->radius_secret,
                    'description' => $router->name . ' - MikroTik',
                ]
            );

            $synced++;
        }

        // Clean up orphaned NAS entries that belonged to routers
        // (identified by description containing '- MikroTik')
        // but whose nasname no longer matches any active router's IP
        $orphaned = 0;
        if (!empty($validNasIps)) {
            $orphaned = Nas::where('description', 'LIKE', '%- MikroTik')
                ->whereNotIn('nasname', $validNasIps)
                ->delete();
        }

        $message = "NAS sync complete: {$synced} synced, {$skipped} skipped (no IP), {$orphaned} orphans removed.";
        $this->info($message);
        Log::info('SyncRouterNasEntries: ' . $message);

        return 0;
    }
}