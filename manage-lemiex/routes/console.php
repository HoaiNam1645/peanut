<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Stock Allocation Command - Runs every 10 minutes
Schedule::command('stock:allocate')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Stock allocation completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Stock allocation failed');
    });

// Auto Payment Command - Runs every 15 minutes
Schedule::command('orders:auto-pay')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Auto payment completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Auto payment failed');
    });

// Auto Buy-Label / Create-Shipping via ShipDVX — runs every 10 minutes.
// FORWARD shipped HAS_LABEL orders (tạo vận chuyển, free) + BUY labels for eligible
// NO_LABEL orders (mua label, costs money). Same flow as the manual buttons
// (BuyLabelService::buyLabelViaShipDvx), processed per-order. Selection guards keep it
// safe: only label_status NULL (never sent); buy is PAID + production + aged + not shipped.
Schedule::command('app:buy-label --limit=50')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Auto buy-label cron completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Auto buy-label cron failed');
    });

// Order Validation - Runs every 5 minutes, batch 10 orders
Schedule::command('orders:validate --batch=10 --min-age=10')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Order validation completed');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Order validation failed');
    });
