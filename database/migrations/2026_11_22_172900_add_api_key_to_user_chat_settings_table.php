<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_chat_settings', function (Blueprint $table) {
            // TEXT: encrypted payload is longer than the plaintext key.
            $table->text('api_key')->nullable()->after('api_base_url');
        });
    }

    public function down(): void
    {
        Schema::table('user_chat_settings', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
