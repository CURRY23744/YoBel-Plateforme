<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_type_id')->constrained('project_types')->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('reference', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            // idea | feasibility | validated | in_progress | on_hold | completed | abandoned | archived
            $table->string('status', 30)->default('idea');
            // low | medium | high | critical
            $table->string('priority', 20)->default('medium');
            $table->decimal('priority_score', 5, 2)->nullable(); // score calculé par l'évaluation (0-100)
            $table->string('health', 20)->nullable(); // on_track | at_risk | late
            $table->decimal('budget_amount', 14, 2)->nullable();
            $table->string('budget_currency', 3)->nullable();
            $table->decimal('actual_cost', 14, 2)->nullable();
            $table->json('technologies')->nullable();
            $table->string('repository_url', 500)->nullable();
            $table->string('staging_url', 500)->nullable();
            $table->string('production_url', 500)->nullable();
            $table->date('start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->date('decision_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->string('cover_path')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'project_type_id']);
            $table->index(['organization_id', 'priority']);
            $table->index('planned_end_date');
            $table->index('start_date');
        });

        Schema::create('project_tag', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'tag_id']);
        });

        Schema::create('project_favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_favorites');
        Schema::dropIfExists('project_tag');
        Schema::dropIfExists('projects');
    }
};
