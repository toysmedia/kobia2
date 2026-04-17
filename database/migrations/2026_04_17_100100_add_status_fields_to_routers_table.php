<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (!Schema::hasColumn('routers', 'status')) {
                $table->string('status', 20)->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('routers', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('routers', 'router_identity')) {
                $table->string('router_identity')->nullable()->after('routeros_version');
            }
            if (!Schema::hasColumn('routers', 'domain_name')) {
                $table->string('domain_name')->nullable()->after('router_identity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $drop = [];
            foreach (['status', 'last_checked_at', 'router_identity', 'domain_name'] as $column) {
                if (Schema::hasColumn('routers', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
