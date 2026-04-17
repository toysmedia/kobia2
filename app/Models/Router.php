<?php
namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Router extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'name',
        'connection_type',
        'wan_ip',
        'vpn_ip',
        'radius_secret',
        'shortname',
        'api_port',
        'api_username',
        'api_password',
        'model',
        'routeros_version',
        'router_identity',
        'domain_name',
        'notes',
        'is_active',
        'status',
        'last_checked_at',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'last_checked_at' => 'datetime',
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
        return ($this->connection_type ?? '') === 'hotspot';
    }
    public function isVpn(): bool
{
    return in_array($this->connection_type, ['vpn', 'through_openvpn'], true);
}

    public function supportsApi(): bool
    {
        return !$this->isHotspot();
    }

    public function getNasIp(): ?string
    {
        return $this->isVpn() ? ($this->vpn_ip ?: $this->wan_ip) : ($this->wan_ip ?: $this->vpn_ip);
    }
    
}
