<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // AI Conversations - stores chat history for context
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->index(); // For anonymous users (customers)
            $table->string('feature', 50)->index(); // analytics, chatbot, etc.
            $table->json('messages'); // Array of {role, content, timestamp}
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            $table->index(['tenant_id', 'feature']);
            $table->index(['session_id', 'feature']);
        });

        // AI Usage Logs - track API usage for monitoring and billing
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('feature', 50)->index(); // Which AI feature was used
            $table->string('model', 50); // gemini-1.5-flash, gpt-4, etc.
            $table->unsignedInteger('tokens_used')->default(0);
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['feature', 'created_at']);
        });

        // Menu Item Embeddings - for recommendation engine
        Schema::create('menu_item_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->json('embedding'); // Vector embedding from AI
            $table->string('model', 50); // Model used for embedding
            $table->string('text_hash', 64); // Hash of text to detect changes
            $table->timestamps();

            $table->unique('menu_item_id');
        });

        // AI Settings per tenant - feature toggles and custom prompts
        Schema::create('ai_tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->boolean('ai_enabled')->default(true);
            $table->json('enabled_features')->nullable(); // Override global feature toggles
            $table->json('custom_prompts')->nullable(); // Custom system prompts
            $table->string('chatbot_name', 100)->nullable();
            $table->string('chatbot_greeting')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_tenant_settings');
        Schema::dropIfExists('menu_item_embeddings');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_conversations');
    }
};
