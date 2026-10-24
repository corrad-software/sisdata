<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRS-RBC-01: the subject identifier ("sub" claim) issued by the SSO MAIS
     * OIDC IdP. Nullable because password-only users never get one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('oidc_sub')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['oidc_sub']);
            $table->dropColumn('oidc_sub');
        });
    }
};
