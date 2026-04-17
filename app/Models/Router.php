<?php
namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Router extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
    ];

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class, 'router_id');
    }

    public function nas()
    {
        return $this->hasOne(Nas::class, 'nasname', 'vpn_ip');
    }

    public function syncLogs()
    {
        return $this->hasMany(\App\Models\RouterSyncLog::class, 'router_id');
    }

    public function pendingCommands()
    {
        return $this->hasMany(\App\Models\PendingRouterCommand::class, 'router_id');
    }

    // ── Section 6: NAS sync helper ────────────────────────────────────

    /**
     * Synchronise the FreeRADIUS NAS table for this router.
     *
     * For Type B routers (VPN-connected), the NAS IP is the vpn_ip because
     * RADIUS packets arrive from the VPN tunnel, not from the WAN IP.
     *
     * When the VPN IP changes (e.g. tunnel reconnect), the OLD NAS entry is
     * cleaned up so FreeRADIUS doesn't reject packets from the new IP.
     *
     * @param string|null $previousVpnIp  The old vpn_ip before the update (if known)
     * @param string|null $previousWanIp  The old wan_ip before the update (if known)
     */
    public function syncNas(?string $previousVpnIp = null, ?string $previousWanIp = null): void
    {
        $currentNasIp = $this->vpn_ip ?: $this->wan_ip;

        if (!$currentNasIp) {
            return;
        }

        // 1. Clean up stale NAS entries when IP has changed
        $previousNasIp = $previousVpnIp ?: $previousWanIp;
        if ($previousNasIp && $previousNasIp !== $currentNasIp) {
            $deleted = Nas::where('nasname', $previousNasIp)
                ->where('shortname', $this->name)
                ->delete();

            if ($deleted) {
                Log::info('Router::syncNas: removed stale NAS entry', [
                    'router_id'       => $this->id,
                    'old_nasname'     => $previousNasIp,
                    'new_nasname'     => $currentNasIp,
                ]);
            }
        }

        // 2. Upsert the current NAS entry
        Nas::updateOrCreate(
            ['nasname' => $currentNasIp],
            [
                'shortname'   => $this->name,
                'type'        => 'other',
                'secret'      => $this->radius_secret,
                'description' => $this->name . ' - MikroTik',
            ]
        );
    }
    
        /**
     * Check if this router is hotspot-only (no API access needed)
     */
    public function isHotspot(): bool
    {
        // Check if service_mode contains 'hotspot' and not PPPoE
        // or if it's specifically a hotspot-only router
        if (isset($this->service_mode)) {
            return str_contains($this->service_mode, 'hotspot') 
                && !str_contains($this->service_mode, 'pppoe');
        }
        
        // Alternative logic based on bridge names
        return !empty($this->hotspot_bridge_name) && empty($this->pppoe_bridge_name);
    }
    public function isVpn(): bool
{
    // Determine based on your router data
    // Example: check a 'type' column or 'vpn_enabled' flag
    return $this->type === 'vpn' || ($this->vpn_enabled ?? false);
}
    
}