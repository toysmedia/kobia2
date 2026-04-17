<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('routers', 'connection_type')) {
            Schema::table('routers', function (Blueprint $table) {
                $table->string('connection_type')->default('public_ip')->after('name');
            });
            return;
        }

        DB::table('routers')
            ->whereNull('connection_type')
            ->orWhere('connection_type', '')
            ->update(['connection_type' => 'public_ip']);

        DB::table('routers')
            ->whereIn('connection_type', ['through_openvpn', 'vpn'])
            ->update(['connection_type' => 'openvpn']);

        DB::table('routers')
            ->whereNotIn('connection_type', ['public_ip', 'openvpn'])
            ->update(['connection_type' => 'public_ip']);

        Schema::table('routers', function (Blueprint $table) {
            $table->string('connection_type')->default('public_ip')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('routers', 'connection_type')) {
            return;
        }

        Schema::table('routers', function (Blueprint $table) {
            $table->string('connection_type')->nullable()->default(null)->change();
        });
    }
};
