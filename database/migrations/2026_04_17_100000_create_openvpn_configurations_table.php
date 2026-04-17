<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('openvpn_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_name');
            $table->string('auth_username')->nullable();
            $table->string('tunnel_ip')->nullable();
            $table->string('router_ip')->nullable();
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->unsignedSmallInteger('openvpn_port')->default(443);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->string('ca_cert_filename')->default('ca.crt');
            $table->string('client_cert_filename')->default('RTR-018.crt');
            $table->string('client_key_filename')->default('RTR-018.key');
            $table->string('last_test_status')->nullable();
            $table->string('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('openvpn_configurations');
    }
};
