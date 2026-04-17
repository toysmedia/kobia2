<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpenvpnConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'connect_to',
        'port',
        'certificate_name',
        'auth',
        'cipher',
        'mode',
        'protocol',
        'client_name',
        'auth_username',
        'tunnel_ip',
        'router_ip',
        'api_port',
        'status',
        'notes',
        'openvpn_port',
        'ca_cert_filename',
        'client_cert_filename',
        'client_key_filename',
        'last_test_status',
        'last_test_message',
        'last_tested_at',
    ];

    protected $casts = [
        'last_tested_at' => 'datetime',
    ];
}
