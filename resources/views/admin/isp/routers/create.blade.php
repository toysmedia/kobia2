@extends('admin.layouts.app')
@section('title', 'Add Router')

@section('content')
<div class="row justify-content-center">
    <div class="col-sm-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Add Router</h5>
            </div>
            <a href="{{ route('admin.isp.routers.index') }}" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="col-md-10">
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

        <form action="{{ route('admin.isp.routers.store') }}" method="POST">
            @csrf
            <div class="row">

                {{-- Basic Info --}}
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bx bx-router me-2"></i>Router Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Router Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="e.g. Nairobi CBD Router" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Connection Type <span class="text-danger">*</span></label>
                                <select name="connection_type" id="connection_type" class="form-select @error('connection_type') is-invalid @enderror" onchange="toggleConnectionFields()">
                                    <option value="direct" {{ old('connection_type', 'direct') === 'direct' ? 'selected' : '' }}>Direct — MikroTik has a public IP</option>
                                    <option value="vpn" {{ old('connection_type') === 'vpn' ? 'selected' : '' }}>VPN — MikroTik connects via OpenVPN tunnel</option>
                                    <option value="hotspot" {{ old('connection_type') === 'hotspot' ? 'selected' : '' }}>Hotspot — RADIUS only, no API calls</option>
                                </select>
                                @error('connection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">NAS IP Address <span class="text-danger">*</span></label>
                                <input type="text" name="nas_ip" id="nas_ip" class="form-control @error('nas_ip') is-invalid @enderror"
                                       value="{{ old('nas_ip') }}" placeholder="e.g. 41.215.10.5" required>
                                <div class="form-text" id="nas_ip_help">
                                    Public IP of the MikroTik router. Used for API connectivity and FreeRADIUS NAS registration.
                                </div>
                                @error('nas_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">NAS Secret <span class="text-danger">*</span></label>
                                <input type="text" name="nas_secret" class="form-control @error('nas_secret') is-invalid @enderror"
                                       value="{{ old('nas_secret') }}" placeholder="Enter your RADIUS shared secret" required>
                                <div class="form-text">Shared secret between this router and FreeRADIUS. You set this yourself.</div>
                                @error('nas_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">NAS Short Name <span class="text-danger">*</span></label>
                                <input type="text" name="shortname" class="form-control @error('shortname') is-invalid @enderror"
                                       value="{{ old('shortname') }}" placeholder="e.g. nairobi-cbd" required>
                                <div class="form-text">A short identifier for this NAS in FreeRADIUS clients.conf.</div>
                                @error('shortname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- API & Network Config --}}
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bx bx-cog me-2"></i>API & Network</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3" id="api_port_group">
                                <label class="form-label fw-semibold">API Port</label>
                                <input type="number" name="api_port" class="form-control @error('api_port') is-invalid @enderror"
                                       value="{{ old('api_port', 8728) }}" min="1" max="65535">
                                <div class="form-text">RouterOS API port. Default is 8728.</div>
                                @error('api_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Router Model</label>
                                <select name="model" class="form-select @error('model') is-invalid @enderror">
                                    <option value="">-- Select Model --</option>
                                    @foreach([
                                        'hAP ac²','hAP ac³','RB750Gr3 (hEX)','RB760iGS (hEX S)',
                                        'CCR1009-7G-1C-1S+','CCR1036-12G-4S','CCR2004-1G-12S+2XS',
                                        'RB4011iGS+','RB5009UG+S+IN','CRS326-24G-2S+','Other'
                                    ] as $m)
                                    <option value="{{ $m }}" {{ old('model') == $m ? 'selected' : '' }}>{{ $m }}</option>
                                    @endforeach
                                </select>
                                @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">RouterOS Version</label>
                                <select name="routeros_version" class="form-select @error('routeros_version') is-invalid @enderror">
                                    <option value="">-- Select Version --</option>
                                    @foreach([
                                        'RouterOS v6.49','RouterOS v6.49.10',
                                        'RouterOS v7.12','RouterOS v7.13','RouterOS v7.14',
                                        'RouterOS v7.15','RouterOS v7.16'
                                    ] as $v)
                                    <option value="{{ $v }}" {{ old('routeros_version') == $v ? 'selected' : '' }}>{{ $v }}</option>
                                    @endforeach
                                </select>
                                @error('routeros_version')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
                                          placeholder="Optional notes about this router...">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                           {{ old('is_active', 1) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info banner --}}
                <div class="col-12 mb-4">
                    <div class="alert alert-info d-flex align-items-start mb-0" role="alert">
                        <i class="bx bx-info-circle me-2 fs-5 mt-1"></i>
                        <div>
                            <strong>After saving:</strong> The system will attempt to connect to the MikroTik via the RouterOS API (port {{ old('api_port', 8728) }})
                            and automatically fetch the board name, RouterOS version, CPU architecture, serial number, and uptime.
                            If the connection fails (e.g. VPN not yet established), these fields will show "Not yet fetched" and can be retrieved later.
                        </div>
                    </div>
                </div>

                <div class="col-sm-12">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-save me-1"></i> Create Router
                        </button>
                        <a href="{{ route('admin.isp.routers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
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
        nasIpHelp.innerHTML = 'Public IP of the MikroTik. Used for FreeRADIUS NAS registration only (no API calls).';
        apiPortGroup.style.display = 'none';
    } else {
        nasIpHelp.innerHTML = 'Public IP of the MikroTik router. Used for API connectivity and FreeRADIUS NAS registration.';
    }

    if (type !== 'hotspot') {
        apiPortGroup.style.display = '';
    }
}

document.addEventListener('DOMContentLoaded', toggleConnectionFields);
</script>
@endpush
@endsection