<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Populate shipped_at for orders that were already shipped before this column
     * existed, so historical reconciliation still works.
     */
    public function up(): void
    {
        // 1) Best source: the moment the LAST shipout position was scanned complete
        //    across all items of the order — i.e. when the order physically shipped.
        DB::statement("
            UPDATE orders
            SET shipped_at = (
                SELECT MAX(oiw.completed_at)
                FROM order_item_workflows oiw
                JOIN order_items oi ON oi.id = oiw.order_item_id
                WHERE oi.order_id = orders.id
                  AND oiw.stage = 'shipout'
                  AND oiw.completed_at IS NOT NULL
            )
            WHERE fulfill_status = 'shipped' AND shipped_at IS NULL
        ");

        // 2) Fallback for older shipped orders that have no shipout workflow rows:
        //    use the recorded completion time, otherwise the last update time.
        DB::statement("
            UPDATE orders
            SET shipped_at = COALESCE(complete_time, updated_at)
            WHERE fulfill_status = 'shipped' AND shipped_at IS NULL
        ");
    }

    public function down(): void
    {
        // Data-only backfill — clearing it would also wipe values set after the
        // migration ran, so intentionally a no-op.
    }
};
