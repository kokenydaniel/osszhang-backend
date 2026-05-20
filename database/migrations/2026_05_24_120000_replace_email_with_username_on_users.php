<?php

use App\Support\Username;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->after('last_name');
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $row) {
            $base = Username::fromEmail((string) $row->email);
            $username = Username::uniqueFromBase($base);
            DB::table('users')->where('id', $row->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
        } else {
            DB::statement('ALTER TABLE users MODIFY username VARCHAR(32) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('last_name');
            $table->timestamp('email_verified_at')->nullable();
            $table->dropColumn(['username', 'must_change_password']);
        });

        foreach (DB::table('users')->get(['id', 'username']) as $row) {
            DB::table('users')->where('id', $row->id)->update([
                'email' => $row->username.'@migrated.local',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->unique()->change();
        });
    }
};
