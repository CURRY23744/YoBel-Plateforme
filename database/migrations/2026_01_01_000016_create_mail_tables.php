<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tables préparées pour la messagerie professionnelle intégrée (version 3).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // null = boîte partagée
            $table->string('provider', 30)->default('google');
            $table->string('email');
            $table->string('display_name')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->text('access_token')->nullable();   // à chiffrer (cast "encrypted")
            $table->text('refresh_token')->nullable();  // à chiffrer (cast "encrypted")
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 20)->default('active'); // active | revoked | error
            $table->string('sync_cursor')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'email']);
        });

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('linked_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('provider_message_id');
            $table->string('thread_id')->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->json('to_emails')->nullable();
            $table->json('cc_emails')->nullable();
            $table->text('snippet')->nullable();
            $table->text('body_summary')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('needs_reply')->default(false);
            $table->string('category', 30)->nullable(); // quote_request | invoice | followup | meeting | other
            $table->text('reply_draft')->nullable(); // jamais envoyé sans validation humaine
            $table->timestamps();

            $table->unique(['mail_account_id', 'provider_message_id'], 'mail_msg_unique');
            $table->index(['project_id', 'received_at']);
            $table->index(['mail_account_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_accounts');
    }
};
