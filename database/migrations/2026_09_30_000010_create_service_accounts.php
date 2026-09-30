<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comptes de service (rôle « service ») : accès à l'API par codes
     * d'application, avec des autorisations choisies par un super-administrateur.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('global_role', ['superadmin', 'admin', 'moderator', 'user', 'service'])->default('user')->change();
            $table->json('permissions')->nullable()->after('global_role');
            $table->string('description', 500)->nullable()->after('permissions');
            $table->boolean('is_active')->default(true)->after('description');
        });

        Schema::create('service_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['permissions', 'description', 'is_active']);
        });
        \Illuminate\Support\Facades\DB::table('users')->where('global_role', 'service')->delete();
        Schema::table('users', function (Blueprint $table) {
            $table->enum('global_role', ['superadmin', 'admin', 'moderator', 'user'])->default('user')->change();
        });
    }
};
