<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open')->index();
            $table->enum('severity', ['critical', 'warn', 'info'])->default('warn')->index();
            $table->json('info_error')->comment('Array of {type, message} per issue');
            $table->string('telegram_chat_id')->nullable();
            $table->bigInteger('telegram_message_id')->nullable()->comment('For editing the alert message when resolved');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable()->comment('Telegram username or system');
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_issues');
    }
};
