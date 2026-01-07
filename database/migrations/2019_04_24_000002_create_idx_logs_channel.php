<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("SET SESSION lock_wait_timeout = 5");
        DB::statement("CREATE INDEX idx_logs_channel ON logs (channel)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX idx_logs_channel ON logs");
    }
};

