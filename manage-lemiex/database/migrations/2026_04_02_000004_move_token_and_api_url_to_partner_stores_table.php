<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('partner_stores')
            ->where('status', 'Pending')
            ->update([
                'status' => 'Active',
                'updated_at' => now(),
            ]);

        Schema::table('partner_stores', function (Blueprint $table) {
            $table->text('token')->nullable()->after('partner_app_id');
            $table->string('api_url')->nullable()->after('token');
        });

        if (Schema::hasColumn('partner_apps', 'token') || Schema::hasColumn('partner_apps', 'api_url')) {
            $partnerApps = DB::table('partner_apps')
                ->select(['id', 'token', 'api_url'])
                ->get();

            foreach ($partnerApps as $partnerApp) {
                if (!$partnerApp->token && !$partnerApp->api_url) {
                    continue;
                }

                DB::table('partner_stores')
                    ->where('partner_app_id', $partnerApp->id)
                    ->update([
                        'token' => $partnerApp->token,
                        'api_url' => $partnerApp->api_url,
                        'updated_at' => now(),
                    ]);
            }

            Schema::table('partner_apps', function (Blueprint $table) {
                $table->dropColumn(['token', 'api_url']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('partner_apps', function (Blueprint $table) {
            $table->text('token')->nullable()->after('auth_url');
            $table->string('api_url')->nullable()->after('token');
        });

        if (Schema::hasColumn('partner_stores', 'token') || Schema::hasColumn('partner_stores', 'api_url')) {
            $partnerStores = DB::table('partner_stores')
                ->select(['partner_app_id', 'token', 'api_url'])
                ->whereNotNull('partner_app_id')
                ->where(function ($query) {
                    $query->whereNotNull('token')
                        ->orWhereNotNull('api_url');
                })
                ->get();

            foreach ($partnerStores as $partnerStore) {
                DB::table('partner_apps')
                    ->where('id', $partnerStore->partner_app_id)
                    ->update([
                        'token' => $partnerStore->token,
                        'api_url' => $partnerStore->api_url,
                        'updated_at' => now(),
                    ]);
            }

            Schema::table('partner_stores', function (Blueprint $table) {
                $table->dropColumn(['token', 'api_url']);
            });
        }
    }
};
