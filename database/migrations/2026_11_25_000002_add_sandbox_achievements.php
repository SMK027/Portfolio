<?php

use App\Achievements\Triggers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Succès liés à la machine SSH (terminal web et défis Linux). */
    public function up(): void
    {
        $now = now();
        $position = (int) DB::table('achievements')->max('position');

        foreach (Triggers::sandboxDefaults() as $row) {
            DB::table('achievements')->insert($row + ['position' => ++$position, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('achievements')->whereIn('trigger', ['sandbox_terminal_opened', 'sandbox_challenges'])->delete();
    }
};
