<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('promotion_recipients', 'status')) {
                $table->string('status', 20)->default('pending')->after('email');
            }
            if (! Schema::hasColumn('promotion_recipients', 'last_error')) {
                $table->text('last_error')->nullable()->after('attempts');
            }
        });

        DB::table('promotion_recipients')->whereNotNull('sent_at')->update(['status' => 'sent']);
        DB::table('promotion_recipients')
            ->whereNull('sent_at')
            ->where('attempts', '>=', 2)
            ->update(['status' => 'failed']);
        DB::table('promotion_recipients')
            ->whereNull('sent_at')
            ->where('attempts', '<', 2)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'pending']);

        $duplicates = DB::table('promotion_recipients')
            ->select('promotion_id', 'email', DB::raw('COUNT(*) as c'))
            ->groupBy('promotion_id', 'email')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('promotion_recipients')
                ->where('promotion_id', $dup->promotion_id)
                ->where('email', $dup->email)
                ->orderByRaw('CASE WHEN sent_at IS NOT NULL THEN 0 ELSE 1 END')
                ->orderByDesc('id')
                ->pluck('id');

            $keepId = $rows->first();
            if (! $keepId) {
                continue;
            }

            DB::table('promotion_recipients')
                ->whereIn('id', $rows->slice(1)->all())
                ->delete();
        }

        if (! $this->indexExists('promotion_recipients_promotion_email_unique')) {
            Schema::table('promotion_recipients', function (Blueprint $table) {
                $table->unique(['promotion_id', 'email'], 'promotion_recipients_promotion_email_unique');
            });
        }

        if (! $this->indexExists('promotion_recipients_status_index')) {
            Schema::table('promotion_recipients', function (Blueprint $table) {
                $table->index('status', 'promotion_recipients_status_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('promotion_recipients_promotion_email_unique')) {
            Schema::table('promotion_recipients', function (Blueprint $table) {
                $table->dropUnique('promotion_recipients_promotion_email_unique');
            });
        }

        if ($this->indexExists('promotion_recipients_status_index')) {
            Schema::table('promotion_recipients', function (Blueprint $table) {
                $table->dropIndex('promotion_recipients_status_index');
            });
        }

        Schema::table('promotion_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('promotion_recipients', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('promotion_recipients', 'last_error')) {
                $table->dropColumn('last_error');
            }
        });
    }

    protected function indexExists(string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('promotion_recipients')");

            return collect($indexes)->contains(fn ($idx) => ($idx->name ?? '') === $name);
        }

        if ($driver === 'mysql') {
            $db = Schema::getConnection()->getDatabaseName();
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$db, 'promotion_recipients', $name],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                ['promotion_recipients', $name],
            );

            return ((int) ($row->c ?? 0)) > 0;
        }

        return false;
    }
};
