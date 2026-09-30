<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validation des articles rédigés par les contributeurs :
     * review_status = pending (soumis) | changes_requested (renvoyé en brouillon) | null.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('review_status', 20)->nullable()->after('published_at')->index();
            $table->timestamp('submitted_at')->nullable()->after('review_status');
            $table->text('review_note')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'submitted_at', 'review_note', 'reviewed_at']);
        });
    }
};
