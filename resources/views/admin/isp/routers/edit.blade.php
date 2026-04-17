@extends('admin.layouts.app')
@section('title', 'Edit Router')

@section('content')
<div class="row">
    <div class="col-sm-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Edit Router: {{ $router->name }}</h5>
            </div>
            <a href="{{ route('admin.isp.routers.index') }}" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="col-sm-12">
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('system_info_error'))
        <div class="alert alert-warning alert-dismissible" role="alert">
            <i class="bx bx-error me-1"></i> <strong>System info fetch failed:</strong> {{ session('system_info_error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        <form action="{{ route('admin.isp.routers.update', $router) }}" method="POST">
            @csrf @method('PUT')
            <div class="row">

                {{-- Basic Info + NAS --}}
                <div class="col-sm-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0"><i class="bx bx-router me-2"></i>Basic Information & NAS</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Router Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $router->name) }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Connection Type <span class="text-danger">*</span></label>
                                <select name="connection_type" id="connection_type" class="form-select @error('connection_type') is-invalid @enderror" onchange="toggleConnectionFields()">
                                    <option value="direct" {{ old('connection_type', $router->connection_type ?? 'direct') === 'direct' ? 'selected' : '' }}>Direct — Public IP</option>
                                    <option value="vpn" {{ old('connection_type', $router->connection_type) === 'vpn' ? 'selected' : '' }}>VPN — OpenVPN tunnel</option>
                                    <option value="hotspot" {{ old('connection_type', $router->connection_type) === 'hotspot' ? 'selected' : '' }}>Hotspot — RADIUS only</option>
                                </select>
                                @error('connection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">NAS IP Address <span class="text-danger">*</span></label>
                                @php
                                    // For VPN routers, the NAS IP is the vpn_ip; for Direct/Hotspot it's the wan_ip
                                    $nasIpValue = old('wan_ip', $router->isVpn() ? $router->vpn_ip : $router->wan_ip);
                                @endphp
                                <input type="text" name="wan_ip" id="nas_ip" class="form-control @error('wan_ip') is-invalid @enderror"
                                       value="{{ $nasIpValue }}" placeholder="e.g. 41.215.10.5" required>
                                <div class="form-text" id="nas_ip_help">
                                    @if($router->isVpn())
                                        <i class="bx bx-info-circle text-warning me-1"></i> This router has no public IP. Enter the OpenVPN tunnel IP assigned to this MikroTik by your VPN server (e.g. 10.8.0.6).
                                    @else
                                        Public/WAN IP used for FreeRADIUS NAS registration and API connectivity.
                                    @endif
                                </div>
                                @error('wan_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            @if($router->isVpn() || old('connection_type') === 'vpn')
                            <div class="mb-3" id="vpn_ip_field">
                                <label class="form-label fw-semibold">VPN Tunnel IP</label>
                                <input type="text" name="vpn_ip" class="form-control @error('vpn_ip') is-invalid @enderror"
                                       value="{{ old('vpn_ip', $router->vpn_ip) }}" placeholder="e.g. 10.8.0.6">
                                @error('vpn_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @else
                            <input type="hidden" name="vpn_ip" value="{{ $router->vpn_ip }}">
                            @endif

                            <div class="mb-3">
                                <label class="form-label fw-semibold">NAS Secret <span class="text-danger">*</span></label>
                                <input type="text" name="radius_secret" class="form-control @error('radius_secret') is-invalid @enderror"
                                       value="{{ old('radius_secret', $router->radius_secret) }}" required>
                                <div class="form-text">RADIUS shared secret for this NAS. You set this yourself.</div>
                                @error('radius_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">NAS Short Name <span class="text-danger">*</span></label>
                                <input type="text" name="shortname" class="form-control @error('shortname') is-invalid @enderror"
                                       value="{{ old('shortname', $router->shortname ?? $router->name) }}" required>
                                <div class="form-text">Short identifier for this NAS in FreeRADIUS.</div>
                                @error('shortname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3" id="api_port_group">
                                <label class="form-label fw-semibold">API Port</label>
                                <input type="number" name="api_port" class="form-control @error('api_port') is-invalid @enderror"
                                       value="{{ old('api_port', $router->api_port ?? 8728) }}" min="1" max="65535">
                                <div class="form-text">RouterOS API port (default 8728).</div>
                                @error('api_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                           {{ old('is_active', $router->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Network Config --}}
                <div class="col-sm-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0"><i class="bx bx-network-chart me-2"></i>Network Configuration</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">WAN Interface</label>
                                <input type="text" name="wan_interface" class="form-control @error('wan_interface') is-invalid @enderror"
                                       value="{{ old('wan_interface', $router->wan_interface) }}">
                                @error('wan_interface')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Customer Interface</label>
                                <input type="text" name="customer_interface" class="form-control @error('customer_interface') is-invalid @enderror"
                                       value="{{ old('customer_interface', $router->customer_interface) }}">
                                @error('customer_interface')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">PPPoE Pool Range</label>
                                <input type="text" name="pppoe_pool_range" class="form-control @error('pppoe_pool_range') is-invalid @enderror"
                                       value="{{ old('pppoe_pool_range', $router->pppoe_pool_range) }}">
                                @error('pppoe_pool_range')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hotspot Pool Range</label>
                                <input type="text" name="hotspot_pool_range" class="form-control @error('hotspot_pool_range') is-invalid @enderror"
                                       value="{{ old('hotspot_pool_range', $router->hotspot_pool_range) }}">
                                @error('hotspot_pool_range')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Billing Domain</label>
                                <input type="text" name="billing_domain" class="form-control @error('billing_domain') is-invalid @enderror"
                                       value="{{ old('billing_domain', $router->billing_domain) }}">
                                @error('billing_domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fetched System Info (read-only) --}}
                <div class="col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bx bx-chip me-2"></i>System Info (fetched from MikroTik)</h6>
                            @if($router->system_info_fetched_at)
                                <small class="text-muted">Last fetched: {{ $router->system_info_fetched_at->diffForHumans() }}</small>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-muted small mb-0">Board Name</label>
                                    <div class="fw-semibold">{{ $router->board_name ?? 'Not yet fetched' }}</div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-muted small mb-0">RouterOS Version (live)</label>
                                    <div class="fw-semibold">{{ $router->fetched_routeros_version ?? 'Not yet fetched' }}</div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-muted small mb-0">CPU Architecture</label>
                                    <div class="fw-semibold">{{ $router->cpu_architecture ?? 'Not yet fetched' }}</div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-muted small mb-0">Serial Number</label>
                                    <div class="fw-semibold">{{ $router->serial_number ?? 'Not yet fetched' }}</div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label text-muted small mb-0">Uptime at Last Fetch</label>
                                    <div class="fw-semibold">{{ $router->fetched_uptime ?? 'Not yet fetched' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Notes</h6></div>
                        <div class="card-body">
                            <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $router->notes) }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bx bx-save me-1"></i> Update Router
                    </button>
                    <a href="{{ route('admin.isp.routers.show', $router) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleConnectionFields() {
    const type = document.getElementById('connection_type').value;
    const nasIpHelp = document.getElementById('nas_ip_help');
    const apiPortGroup = document.getElementById('api_port_group');

    if (type === 'vpn') {
        nasIpHelp.innerHTML = '<i class="bx bx-info-circle text-warning me-1"></i> This router has no public IP. Enter the OpenVPN tunnel IP assigned to this MikroTik by your VPN server (e.g. 10.8.0.6).';
    } else if (type === 'hotspot') {
        nasIpHelp.innerHTML = 'Public IP for FreeRADIUS NAS registration only (no API calls will be made).';
        apiPortGroup.style.display = 'none';
    } else {
        nasIpHelp.innerHTML = 'Public/WAN IP used for FreeRADIUS NAS registration and API connectivity.';
    }

    if (type !== 'hotspot') {
        apiPortGroup.style.display = '';
    }
}

document.addEventListener('DOMContentLoaded', toggleConnectionFields);
</script>
@endpush
@endsection