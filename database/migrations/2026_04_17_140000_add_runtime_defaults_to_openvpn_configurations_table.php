<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('openvpn_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('openvpn_configurations', 'connect_to')) {
                $table->string('connect_to')->nullable()->after('name');
            }
            if (!Schema::hasColumn('openvpn_configurations', 'port')) {
                $table->unsignedSmallInteger('port')->default(1194)->after('connect_to');
            }
            if (!Schema::hasColumn('openvpn_configurations', 'certificate_name')) {
                $table->string('certificate_name')->default('router')->after('port');
            }
            if (!Schema::hasColumn('openvpn_configurations', 'auth')) {
                $table->string('auth')->default('sha1')->after('certificate_name');
            }
            if (!Schema::hasColumn('openvpn_configurations', 'cipher')) {
                $table->string('cipher')->default('aes256')->after('auth');
            }
            if (!Schema::hasColumn('openvpn_configurations', 'mode')) {
                $table->string('mode')->default('ip')->after('cipher');
            }
            if (!Schema::hasColumn('openvpn_configurations', 'protocol')) {
                $table->string('protocol')->default('tcp')->after('mode');
            }
        });

        if (Schema::hasColumn('openvpn_configurations', 'connect_to') && Schema::hasColumn('openvpn_configurations', 'tunnel_ip')) {
            DB::table('openvpn_configurations')
                ->whereNull('connect_to')
                ->update(['connect_to' => DB::raw('tunnel_ip')]);
        }

        if (Schema::hasColumn('openvpn_configurations', 'port') && Schema::hasColumn('openvpn_configurations', 'openvpn_port')) {
            DB::table('openvpn_configurations')
                ->whereNull('port')
                ->update(['port' => DB::raw('openvpn_port')]);
        }
    }

    public function down(): void
    {
        Schema::table('openvpn_configurations', function (Blueprint $table) {
            $drop = [];
            foreach (['connect_to', 'port', 'certificate_name', 'auth', 'cipher', 'mode', 'protocol'] as $column) {
                if (Schema::hasColumn('openvpn_configurations', $column)) {
                    $drop[] = $column;
                }
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
