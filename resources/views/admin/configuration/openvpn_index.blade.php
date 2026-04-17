@extends('admin.layouts.app')
@section('title', 'OpenVPN Configurations')

@section('content')
<div class="row">
    <div class="col-sm-12 d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">OpenVPN Configurations</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#newConfigForm">New Configuration</button>
    </div>

    @if(session('success'))
        <div class="col-sm-12"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif

    <div class="col-sm-12 mb-3 collapse" id="newConfigForm">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.isp.openvpn_configurations.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-3 mb-2"><input class="form-control" name="name" placeholder="Name" required></div>
                        <div class="col-md-2 mb-2"><input class="form-control" name="client_name" placeholder="Client Name" required></div>
                        <div class="col-md-2 mb-2"><input class="form-control" name="auth_username" placeholder="Auth User"></div>
                        <div class="col-md-2 mb-2"><input class="form-control" name="tunnel_ip" placeholder="Tunnel IP"></div>
                        <div class="col-md-2 mb-2"><input class="form-control" name="router_ip" placeholder="Router IP"></div>
                        <div class="col-md-1 mb-2"><input class="form-control" name="api_port" value="8728" placeholder="Port"></div>
                        <div class="col-md-12 mb-2"><textarea class="form-control" name="notes" rows="2" placeholder="Notes"></textarea></div>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-sm-12">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Client</th>
                            <th>Assigned IP</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($configs as $config)
                            <tr>
                                <td>{{ $config->name }}</td>
                                <td>{{ $config->client_name }}</td>
                                <td>{{ $config->tunnel_ip ?: '-' }}</td>
                                <td><span class="badge bg-secondary">{{ strtoupper($config->status) }}</span></td>
                                <td>{{ $config->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openEditModal({{ $config->id }}, {{ Js::from($config) }})">Edit</button>
                                    <button class="btn btn-sm btn-outline-info" onclick="openConfigureModal({{ $config->id }})">Configure</button>
                                    <button class="btn btn-sm btn-outline-success" onclick="openTestModal({{ $config->id }}, '{{ addslashes($config->name) }}')">Test Connection</button>
                                    <form method="POST" action="{{ route('admin.isp.openvpn_configurations.destroy', $config) }}" class="d-inline" onsubmit="return confirm('Delete this configuration?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No OpenVPN configurations found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="configureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Configure MikroTik</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Copy and paste this one-liner in MikroTik terminal:</p>
                <textarea id="configureOneLiner" class="form-control" rows="4" readonly></textarea>
                <button class="btn btn-outline-primary btn-sm mt-2" onclick="regenerateOneLiner()">Regenerate</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit OpenVPN Configuration</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" id="edit_name" required></div>
                    <div class="mb-2"><label class="form-label">Client Name</label><input class="form-control" name="client_name" id="edit_client_name" required></div>
                    <div class="mb-2"><label class="form-label">Auth Username</label><input class="form-control" name="auth_username" id="edit_auth_username"></div>
                    <div class="mb-2"><label class="form-label">Tunnel IP</label><input class="form-control" name="tunnel_ip" id="edit_tunnel_ip"></div>
                    <div class="mb-2"><label class="form-label">Router IP</label><input class="form-control" name="router_ip" id="edit_router_ip"></div>
                    <div class="mb-2"><label class="form-label">API Port</label><input class="form-control" name="api_port" id="edit_api_port"></div>
                    <div class="mb-2"><label class="form-label">Status</label><input class="form-control" name="status" id="edit_status"></div>
                    <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary" type="submit">Update</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="testModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="testModalTitle">Test Connection</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="testConfigId">
                <div class="mb-2"><label class="form-label">MikroTik Username</label><input type="text" id="testUser" class="form-control" value="admin"></div>
                <div class="mb-2"><label class="form-label">MikroTik Password</label><input type="password" id="testPass" class="form-control"></div>
                <div id="testResult" class="small text-muted"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary" type="button" onclick="submitOpenvpnTest()">Run Test</button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let activeConfigId = null;

function openConfigureModal(configId) {
    activeConfigId = configId;
    regenerateOneLiner();
    new bootstrap.Modal(document.getElementById('configureModal')).show();
}

function regenerateOneLiner() {
    if (!activeConfigId) return;

    fetch(`/admin/isp/openvpn-configurations/${activeConfigId}/one-liner`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('configureOneLiner').value = data.one_liner || '';
        });
}

function openEditModal(configId, data) {
    document.getElementById('editForm').action = `/admin/isp/openvpn-configurations/${configId}`;
    document.getElementById('edit_name').value = data.name || '';
    document.getElementById('edit_client_name').value = data.client_name || '';
    document.getElementById('edit_auth_username').value = data.auth_username || '';
    document.getElementById('edit_tunnel_ip').value = data.tunnel_ip || '';
    document.getElementById('edit_router_ip').value = data.router_ip || '';
    document.getElementById('edit_api_port').value = data.api_port || 8728;
    document.getElementById('edit_status').value = data.status || 'draft';
    document.getElementById('edit_notes').value = data.notes || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openTestModal(configId, name) {
    document.getElementById('testConfigId').value = configId;
    document.getElementById('testModalTitle').textContent = 'Test Connection — ' + name;
    document.getElementById('testResult').textContent = '';
    new bootstrap.Modal(document.getElementById('testModal')).show();
}

function submitOpenvpnTest() {
    const configId = document.getElementById('testConfigId').value;
    const username = document.getElementById('testUser').value;
    const password = document.getElementById('testPass').value;

    document.getElementById('testResult').textContent = 'Testing connection...';

    fetch(`/admin/isp/openvpn-configurations/${configId}/test-connection`, {
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
        document.getElementById('testResult').textContent = `${(data.status || 'unreachable').toUpperCase()}: ${data.message || ''}`;
    })
    .catch(() => {
        document.getElementById('testResult').textContent = 'UNREACHABLE: Unable to test connection.';
    });
}
</script>
@endpush
