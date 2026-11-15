<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tables préparées pour l'assistant intelligent (version 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discussion_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode', 20)->default('on_demand'); // on_demand | proactive | periodic
            $table->text('prompt');
            $table->longText('response')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost', 10, 6)->nullable();
            $table->string('status', 20)->default('completed'); // pending | completed | failed
            $table->text('error')->nullable();
            $table->json('context')->nullable(); // éléments internes utilisés (traçabilité)
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['project_id', 'mode']);
        });

        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('interaction_id')->nullable()->constrained('ai_interactions')->nullOnDelete();
            $table->string('type', 30); // task | decision | summary | priority | reply_draft | alert
            $table->string('title');
            $table->text('content')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending | accepted | rejected | expired
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete(); // validation humaine obligatoire
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('ai_interactions');
    }
};
