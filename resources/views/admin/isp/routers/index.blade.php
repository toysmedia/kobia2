@extends('admin.layouts.app')
@section('title', 'Routers')

@section('content')
<div class="row">
    <div class="col-sm-12 mb-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Routers</h5>
        <a href="{{ route('admin.isp.routers.create') }}" class="btn btn-primary btn-sm">Add Router</a>
    </div>

    @if(session('success'))
        <div class="col-sm-12 mb-2"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif

    <div class="col-sm-12">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped mb-0" id="routersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Model</th>
                            <th>Version</th>
                            <th>IP Address</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routers as $router)
                            <tr data-router-id="{{ $router->id }}">
                                <td>{{ $router->name }}</td>
                                <td>{{ $router->model ?: '-' }}</td>
                                <td>{{ $router->routeros_version ?: '-' }}</td>
                                <td>{{ $router->vpn_ip ?: $router->wan_ip ?: '-' }}</td>
                                <td>
                                    <span class="badge bg-secondary router-status" id="status-{{ $router->id }}" data-status="{{ $router->status ?: 'unreachable' }}">
                                        {{ strtoupper($router->status ?: 'unreachable') }}
                                    </span>
                                    @if($router->last_checked_at)
                                        <small class="text-muted d-block">{{ $router->last_checked_at->diffForHumans() }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.isp.routers.edit', $router) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="openTestConnection({{ $router->id }}, '{{ addslashes($router->name) }}')">Test Connection</button>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="openWinbox({{ $router->id }}, '{{ addslashes($router->name) }}', '{{ $router->vpn_ip ?: $router->wan_ip }}')">WinBox</button>
                                    <a href="{{ route('admin.isp.routers.script', $router) }}" class="btn btn-sm btn-outline-warning" target="_blank">Script</a>
                                    <form action="{{ route('admin.isp.routers.destroy', $router) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete router {{ addslashes($router->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No routers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="testConnectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testConnectionModalTitle">Test Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="testRouterId">
                <div class="mb-2">
                    <label class="form-label">MikroTik Username</label>
                    <input type="text" class="form-control" id="testUsername" value="admin">
                </div>
                <div class="mb-2">
                    <label class="form-label">MikroTik Password</label>
                    <input type="password" class="form-control" id="testPassword">
                </div>
                <div id="testConnectionResult" class="small text-muted"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="submitRouterConnectionTest()">Run Test</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="winboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="winboxModalTitle">Open WebFig</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="winboxIp">
                <div class="mb-2">
                    <label class="form-label">MikroTik Username</label>
                    <input type="text" class="form-control" id="winboxUsername" value="admin">
                </div>
                <div class="mb-2">
                    <label class="form-label">MikroTik Password</label>
                    <input type="password" class="form-control" id="winboxPassword">
                </div>
                <p class="text-muted small mb-0">Credentials are collected for convenience; WebFig will open in a new tab.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="openWebfig()">Open WebFig</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function setStatusBadge(routerId, status) {
    const badge = document.getElementById('status-' + routerId);
    if (!badge) return;

    const normalized = (status || 'unreachable').toLowerCase();
    badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-secondary');

    if (normalized === 'online') {
        badge.classList.add('bg-success');
    } else if (normalized === 'offline') {
        badge.classList.add('bg-warning');
    } else {
        badge.classList.add('bg-danger');
    }

    badge.textContent = normalized.toUpperCase();
}

function refreshStatuses() {
    fetch('{{ route('admin.isp.routers.statuses') }}')
        .then(response => response.json())
        .then(items => {
            (items || []).forEach(item => setStatusBadge(item.id, item.status));
        });
}

function openTestConnection(routerId, routerName) {
    document.getElementById('testRouterId').value = routerId;
    document.getElementById('testConnectionModalTitle').textContent = 'Test Connection — ' + routerName;
    document.getElementById('testConnectionResult').textContent = '';
    new bootstrap.Modal(document.getElementById('testConnectionModal')).show();
}

function submitRouterConnectionTest() {
    const routerId = document.getElementById('testRouterId').value;
    const username = document.getElementById('testUsername').value;
    const password = document.getElementById('testPassword').value;

    document.getElementById('testConnectionResult').textContent = 'Testing connection...';

    fetch(`/admin/isp/routers/${routerId}/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ username, password }),
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('testConnectionResult').textContent = `${(data.status || 'unreachable').toUpperCase()}: ${data.message || ''}`;
        setStatusBadge(routerId, data.status || 'unreachable');
    })
    .catch(() => {
        document.getElementById('testConnectionResult').textContent = 'UNREACHABLE: Unable to test connection.';
        setStatusBadge(routerId, 'unreachable');
    });
}

function openWinbox(routerId, routerName, ip) {
    document.getElementById('winboxIp').value = ip || '';
    document.getElementById('winboxModalTitle').textContent = 'Open WebFig — ' + routerName;
    new bootstrap.Modal(document.getElementById('winboxModal')).show();
}

function openWebfig() {
    const ip = document.getElementById('winboxIp').value;
    if (!ip) return;

    window.open(`https://${ip}/`, '_blank');
}

refreshStatuses();
setInterval(refreshStatuses, 45000);
</script>
@endpush
