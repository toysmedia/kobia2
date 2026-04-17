<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (!Schema::hasColumn('routers', 'connection_type')) {
                $table->string('connection_type')->nullable()->after('name');
            }
            if (!Schema::hasColumn('routers', 'shortname')) {
                $table->string('shortname')->nullable()->after('radius_secret');
            }
            if (!Schema::hasColumn('routers', 'api_port')) {
                $table->unsignedSmallInteger('api_port')->nullable()->default(8728)->after('shortname');
            }
            if (!Schema::hasColumn('routers', 'api_username')) {
                $table->string('api_username')->nullable()->default('admin')->after('api_port');
            }
            if (!Schema::hasColumn('routers', 'api_password')) {
                $table->string('api_password')->nullable()->after('api_username');
            }
            if (!Schema::hasColumn('routers', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $drop = [];
            foreach (['connection_type', 'shortname', 'api_port', 'api_username', 'api_password', 'deleted_at'] as $column) {
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
