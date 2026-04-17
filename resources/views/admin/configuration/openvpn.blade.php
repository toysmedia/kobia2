@extends('admin.layouts.app')
@section('title', 'Configure OpenVPN')

@section('content')
<div class="row">
    <div class="col-sm-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="bx bx-lock-alt me-2"></i>Configure OpenVPN</h5>
                <small class="text-muted">Set OpenVPN server parameters. The server.conf file will be generated from these values.</small>
            </div>
            <a href="{{ route('admin.isp.configuration.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bx bx-arrow-back me-1"></i> Back to Services
            </a>
        </div>
    </div>

    <div class="col-sm-12">
        @if(session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <i class="bx bx-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        @if(session('warning'))
        <div class="alert alert-warning alert-dismissible" role="alert">
            <i class="bx bx-error me-1"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

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

        <form action="{{ route('admin.isp.configuration.openvpn.save') }}" method="POST">
            @csrf
            <div class="row">

                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bx bx-globe me-2"></i>OpenVPN Server Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">OpenVPN Server IP <span class="text-danger">*</span></label>
                                <input type="text" name="openvpn_server_ip" class="form-control @error('openvpn_server_ip') is-invalid @enderror"
                                       value="{{ old('openvpn_server_ip', $settings['openvpn_server_ip']) }}" placeholder="e.g. 0.0.0.0 or your public IP" required>
                                <div class="form-text">The IP address OpenVPN binds to. Use <code>0.0.0.0</code> to listen on all interfaces, or your specific public IP.</div>
                                @error('openvpn_server_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">OpenVPN Port <span class="text-danger">*</span></label>
                                    <input type="number" name="openvpn_port" class="form-control @error('openvpn_port') is-invalid @enderror"
                                           value="{{ old('openvpn_port', $settings['openvpn_port']) }}" min="1" max="65535" required>
                                    @error('openvpn_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Protocol <span class="text-danger">*</span></label>
                                    <select name="openvpn_protocol" class="form-select @error('openvpn_protocol') is-invalid @enderror">
                                        <option value="udp" {{ old('openvpn_protocol', $settings['openvpn_protocol']) === 'udp' ? 'selected' : '' }}>UDP (recommended)</option>
                                        <option value="tcp" {{ old('openvpn_protocol', $settings['openvpn_protocol']) === 'tcp' ? 'selected' : '' }}>TCP</option>
                                    </select>
                                    @error('openvpn_protocol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">VPN Subnet <span class="text-danger">*</span></label>
                                <input type="text" name="openvpn_subnet" class="form-control @error('openvpn_subnet') is-invalid @enderror"
                                       value="{{ old('openvpn_subnet', $settings['openvpn_subnet']) }}" placeholder="e.g. 10.8.0.0/24" required>
                                <div class="form-text">The subnet used for VPN tunnel IPs. Each MikroTik router will get an IP from this range (e.g. 10.8.0.6).</div>
                                @error('openvpn_subnet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pre-shared Key / Certificate Path <span class="text-danger">*</span></label>
                                <input type="text" name="openvpn_key_path" class="form-control @error('openvpn_key_path') is-invalid @enderror"
                                       value="{{ old('openvpn_key_path', $settings['openvpn_key_path']) }}" placeholder="/etc/openvpn/ta.key" required>
                                <div class="form-text">Full path to the TLS auth key or pre-shared key on this server. You set this yourself.</div>
                                @error('openvpn_key_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info panel --}}
                <div class="col-md-4 mb-4">
                    <div class="card bg-label-info h-100">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="bx bx-info-circle me-1"></i> Important Notes</h6>
                            <ul class="small mb-0">
                                <li class="mb-2">Ensure the CA cert, server cert, server key, and DH params exist at <code>/etc/openvpn/</code> before starting OpenVPN.</li>
                                <li class="mb-2">The <code>ipp.txt</code> file at <code>/etc/openvpn/ipp.txt</code> records client-to-IP mappings — Type B (VPN) routers will read their tunnel IP from this file.</li>
                                <li class="mb-2">After saving, the system will restart OpenVPN. Existing VPN sessions will reconnect automatically.</li>
                                <li>The management interface is enabled on <code>127.0.0.1:7505</code> for monitoring.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- What happens on save --}}
                <div class="col-12 mb-4">
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>On save:</strong> Settings are persisted to the database. The system will then:
                        <ol class="mb-0 mt-1">
                            <li>Back up existing <code>/etc/openvpn/server.conf</code> (appends <code>.bak.&lt;timestamp&gt;</code>)</li>
                            <li>Generate a new <code>server.conf</code> from your settings</li>
                            <li>Restart OpenVPN via Supervisor (fallback: systemd)</li>
                        </ol>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save &amp; Apply OpenVPN Configuration
                    </button>
                    <a href="{{ route('admin.isp.configuration.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection