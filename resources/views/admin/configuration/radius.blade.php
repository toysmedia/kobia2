@extends('admin.layouts.app')
@section('title', 'Configure RADIUS')

@section('content')
<div class="row">
    <div class="col-sm-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="bx bx-radio-circle-marked me-2"></i>Configure FreeRADIUS</h5>
                <small class="text-muted">Set RADIUS server parameters and MySQL connection for the SQL module.</small>
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

        <form action="{{ route('admin.isp.configuration.radius.save') }}" method="POST">
            @csrf
            <div class="row">

                {{-- RADIUS Server Settings --}}
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bx bx-broadcast me-2"></i>RADIUS Server</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">RADIUS Shared Secret <span class="text-danger">*</span></label>
                                <input type="text" name="radius_shared_secret" class="form-control @error('radius_shared_secret') is-invalid @enderror"
                                       value="{{ old('radius_shared_secret', $settings['radius_shared_secret']) }}" required>
                                <div class="form-text">The shared secret between your server and NAS devices. You set this yourself.</div>
                                @error('radius_shared_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Auth Port <span class="text-danger">*</span></label>
                                    <input type="number" name="radius_auth_port" class="form-control @error('radius_auth_port') is-invalid @enderror"
                                           value="{{ old('radius_auth_port', $settings['radius_auth_port']) }}" min="1" max="65535" required>
                                    @error('radius_auth_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Accounting Port <span class="text-danger">*</span></label>
                                    <input type="number" name="radius_acct_port" class="form-control @error('radius_acct_port') is-invalid @enderror"
                                           value="{{ old('radius_acct_port', $settings['radius_acct_port']) }}" min="1" max="65535" required>
                                    @error('radius_acct_port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">RADIUS Server IP <span class="text-danger">*</span></label>
                                <input type="text" name="radius_server_ip" class="form-control @error('radius_server_ip') is-invalid @enderror"
                                       value="{{ old('radius_server_ip', $settings['radius_server_ip']) }}" placeholder="e.g. 127.0.0.1" required>
                                <div class="form-text">IP address that FreeRADIUS listens on. Use 127.0.0.1 if RADIUS runs on the same server.</div>
                                @error('radius_server_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- MySQL Connection for FreeRADIUS SQL module --}}
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bx bx-data me-2"></i>MySQL Connection (SQL Module)</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info py-2 small mb-3">
                                <i class="bx bx-info-circle me-1"></i>
                                These credentials let FreeRADIUS read your Laravel database for authentication (radcheck, radreply, radusergroup, etc.) and accounting (radacct).
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">MySQL Host <span class="text-danger">*</span></label>
                                <input type="text" name="radius_mysql_host" class="form-control @error('radius_mysql_host') is-invalid @enderror"
                                       value="{{ old('radius_mysql_host', $settings['radius_mysql_host']) }}" placeholder="127.0.0.1" required>
                                @error('radius_mysql_host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Database Name <span class="text-danger">*</span></label>
                                <input type="text" name="radius_mysql_db" class="form-control @error('radius_mysql_db') is-invalid @enderror"
                                       value="{{ old('radius_mysql_db', $settings['radius_mysql_db']) }}" placeholder="e.g. oxdes_billing" required>
                                @error('radius_mysql_db')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">MySQL Username <span class="text-danger">*</span></label>
                                <input type="text" name="radius_mysql_user" class="form-control @error('radius_mysql_user') is-invalid @enderror"
                                       value="{{ old('radius_mysql_user', $settings['radius_mysql_user']) }}" placeholder="e.g. radius_user" required>
                                @error('radius_mysql_user')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">MySQL Password <span class="text-danger">*</span></label>
                                <input type="password" name="radius_mysql_pass" class="form-control @error('radius_mysql_pass') is-invalid @enderror"
                                       value="{{ old('radius_mysql_pass', $settings['radius_mysql_pass']) }}" required>
                                @error('radius_mysql_pass')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- What happens on save --}}
                <div class="col-12 mb-4">
                    <div class="alert alert-secondary py-2 small mb-0">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>On save:</strong> Settings are persisted to the database. The system will then:
                        <ol class="mb-0 mt-1">
                            <li>Back up existing <code>/etc/freeradius/3.0/mods-available/sql</code> (appends <code>.bak.&lt;timestamp&gt;</code>)</li>
                            <li>Write the SQL module config with your MySQL credentials</li>
                            <li>Back up existing <code>/etc/freeradius/3.0/clients.conf</code></li>
                            <li>Write all active routers as NAS entries to clients.conf</li>
                            <li>Restart FreeRADIUS via Supervisor (fallback: systemd)</li>
                        </ol>
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i> Save &amp; Apply RADIUS Configuration
                    </button>
                    <a href="{{ route('admin.isp.configuration.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
