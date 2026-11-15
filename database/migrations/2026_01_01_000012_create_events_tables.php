<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('meeting'); // meeting | delivery | client_meeting | reminder | deadline | other
            $table->string('location')->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('visibility', 20)->default('project'); // private | project | organization
            $table->string('recurrence_rule')->nullable(); // RRULE (récurrence, pour plus tard)
            $table->unsignedSmallInteger('reminder_minutes')->nullable();
            $table->string('external_id')->nullable(); // synchronisation Google/Outlook (plus tard)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'starts_at']);
            $table->index(['project_id', 'starts_at']);
        });

        Schema::create('event_participants', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('response_status', 20)->default('pending'); // pending | accepted | declined | tentative
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->primary(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('events');
    }
};
