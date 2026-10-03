<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'members' => ['updated_at'],
        'investments' => ['created_at', 'updated_at', 'closed_at'],
        'roi_transactions' => ['created_at', 'updated_at'],
        'level_commission_transactions' => ['created_at', 'updated_at'],
        'rank_achievements' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, static function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dateTime($column)->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        $bounds = $this->timestampBounds();

        foreach (self::COLUMNS as $tableName => $columns) {
            foreach ($columns as $column) {
                if (DB::table($tableName)
                    ->where(function ($query) use ($column, $bounds): void {
                        $query->where($column, '<', $bounds['minimum'])
                            ->orWhere($column, '>', $bounds['maximum']);
                    })
                    ->exists()) {
                    throw new RuntimeException("Cannot restore TIMESTAMP for {$tableName}.{$column}; values exceed its supported range.");
                }
            }
        }

        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, static function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->timestamp($column)->nullable()->change();
                }
            });
        }
    }

    private function timestampBounds(): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return [
                'minimum' => '1970-01-01 00:00:01',
                'maximum' => '2038-01-19 03:14:07',
            ];
        }

        $bounds = DB::selectOne(
            "SELECT CONVERT_TZ('1970-01-01 00:00:01', '+00:00', @@session.time_zone) AS minimum, " .
            "CONVERT_TZ('2038-01-19 03:14:07', '+00:00', @@session.time_zone) AS maximum"
        );

        if ($bounds === null || $bounds->minimum === null || $bounds->maximum === null) {
            throw new RuntimeException('Cannot determine the current database session timezone; refusing to narrow DATETIME columns to TIMESTAMP.');
        }

        return ['minimum' => $bounds->minimum, 'maximum' => $bounds->maximum];
    }
};