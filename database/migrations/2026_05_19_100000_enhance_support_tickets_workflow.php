<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_to_id')
                ->nullable()
                ->after('handled_by_admin_id')
                ->constrained('admins')
                ->nullOnDelete();
            $table->foreignId('assigned_by_id')
                ->nullable()
                ->after('assigned_to_id')
                ->constrained('admins')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_id');
            $table->timestamp('last_assigned_at')->nullable()->after('assigned_at');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
        });

        Schema::create('support_ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('author_type');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_visible_to_customer')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('support_ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('previous_assigned_to_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_assignments');
        Schema::dropIfExists('support_ticket_comments');

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to_id');
            $table->dropConstrainedForeignId('assigned_by_id');
            $table->dropColumn(['assigned_at', 'last_assigned_at', 'closed_at']);
        });
    }
};
