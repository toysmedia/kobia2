@extends('admin.layouts.app')
@section('title', 'Edit Router')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Edit Router</h5>
            <a href="{{ route('admin.isp.routers.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.isp.routers.update', $router) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" value="{{ old('name', $router->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Connection Type</label>
                        <select name="connection_type" class="form-select @error('connection_type') is-invalid @enderror" required>
                            <option value="public_ip" {{ old('connection_type', $router->connection_type) === 'public_ip' ? 'selected' : '' }}>Public IP</option>
                            <option value="through_openvpn" {{ old('connection_type', $router->connection_type) === 'through_openvpn' ? 'selected' : '' }}>Through OpenVPN</option>
                        </select>
                        @error('connection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="ip_address" value="{{ old('ip_address', $router->vpn_ip ?: $router->wan_ip) }}" class="form-control @error('ip_address') is-invalid @enderror" required>
                        @error('ip_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">NAS Secret</label>
                        <input type="text" name="nas_secret" value="{{ old('nas_secret', $router->radius_secret) }}" class="form-control @error('nas_secret') is-invalid @enderror" required>
                        @error('nas_secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="port" value="{{ old('port', $router->api_port ?: 8728) }}" class="form-control @error('port') is-invalid @enderror" required>
                        @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $router->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $router->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Is Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Router</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
