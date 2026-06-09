<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        if (! Schema::hasColumn('conversation_message_flags', 'proposal_clarification_thread_id')) {
            Schema::table('conversation_message_flags', function (Blueprint $table): void {
                $table->unsignedBigInteger('proposal_clarification_thread_id')->nullable()->after('quest_conversation_message_id');
            });
        }

        if (! Schema::hasColumn('conversation_message_flags', 'proposal_clarification_message_id')) {
            Schema::table('conversation_message_flags', function (Blueprint $table): void {
                $table->unsignedBigInteger('proposal_clarification_message_id')->nullable()->after('proposal_clarification_thread_id');
            });
        }

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            if ($this->foreignKeyExists('conversation_message_flags', 'conversation_message_flags_quest_conversation_thread_id_foreign')) {
                $table->dropForeign('conversation_message_flags_quest_conversation_thread_id_foreign');
            }

            if ($this->foreignKeyExists('conversation_message_flags', 'conversation_message_flags_quest_conversation_message_id_foreign')) {
                $table->dropForeign('conversation_message_flags_quest_conversation_message_id_foreign');
            }
        });

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            $table->unsignedBigInteger('quest_conversation_thread_id')->nullable()->change();
            $table->unsignedBigInteger('quest_conversation_message_id')->nullable()->change();
        });

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            if (! $this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_clarify_thread_fk')) {
                $table->foreign('proposal_clarification_thread_id', 'conv_msg_flags_clarify_thread_fk')
                    ->references('id')
                    ->on('proposal_clarification_threads')
                    ->cascadeOnDelete();
            }

            if (! $this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_clarify_message_fk')) {
                $table->foreign('proposal_clarification_message_id', 'conv_msg_flags_clarify_message_fk')
                    ->references('id')
                    ->on('proposal_clarification_messages')
                    ->cascadeOnDelete();
            }

            if (! $this->indexExists('conversation_message_flags', 'conv_msg_flags_clarify_thread_status_idx')) {
                $table->index(
                    ['proposal_clarification_thread_id', 'status'],
                    'conv_msg_flags_clarify_thread_status_idx',
                );
            }

            if (! $this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_thread_fk')) {
                $table->foreign('quest_conversation_thread_id', 'conv_msg_flags_quest_thread_fk')
                    ->references('id')
                    ->on('quest_conversation_threads')
                    ->cascadeOnDelete();
            }

            if (! $this->foreignKeyExists('conversation_message_flags', 'conv_msg_flags_quest_message_fk')) {
                $table->foreign('quest_conversation_message_id', 'conv_msg_flags_quest_message_fk')
                    ->references('id')
                    ->on('quest_conversation_messages')
                    ->cascadeOnDelete();
            }
        });

        if (! Schema::hasTable('conversation_thread_reviews')) {
            return;
        }

        if (! Schema::hasColumn('conversation_thread_reviews', 'proposal_clarification_thread_id')) {
            if ($this->foreignKeyExists('conversation_thread_reviews', 'conversation_thread_reviews_quest_conversation_thread_id_foreign')) {
                Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                    $table->dropForeign(['quest_conversation_thread_id']);
                });
            }

            if ($this->indexExists('conversation_thread_reviews', 'conversation_thread_reviews_quest_conversation_thread_id_unique')) {
                Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                    $table->dropUnique(['quest_conversation_thread_id']);
                });
            }

            Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                $table->unsignedBigInteger('quest_conversation_thread_id')->nullable()->change();
                $table->unsignedBigInteger('proposal_clarification_thread_id')->nullable()->after('quest_conversation_thread_id');
            });
        }

        Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
            if (! $this->foreignKeyExists('conversation_thread_reviews', 'conv_thread_reviews_clarify_fk')) {
                $table->foreign('proposal_clarification_thread_id', 'conv_thread_reviews_clarify_fk')
                    ->references('id')
                    ->on('proposal_clarification_threads')
                    ->cascadeOnDelete();
            }

            if (! $this->indexExists('conversation_thread_reviews', 'conv_thread_reviews_clarify_thread_unique')) {
                $table->unique('proposal_clarification_thread_id', 'conv_thread_reviews_clarify_thread_unique');
            }

            if (! $this->foreignKeyExists('conversation_thread_reviews', 'conv_thread_reviews_quest_thread_fk')) {
                $table->foreign('quest_conversation_thread_id', 'conv_thread_reviews_quest_thread_fk')
                    ->references('id')
                    ->on('quest_conversation_threads')
                    ->cascadeOnDelete();
            }

            if (! $this->indexExists('conversation_thread_reviews', 'conv_thread_reviews_quest_thread_unique')) {
                $table->unique('quest_conversation_thread_id', 'conv_thread_reviews_quest_thread_unique');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_thread_reviews')) {
            Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                if ($this->foreignKeyExists('conversation_thread_reviews', 'conv_thread_reviews_quest_thread_fk')) {
                    $table->dropForeign('conv_thread_reviews_quest_thread_fk');
                }
                if ($this->indexExists('conversation_thread_reviews', 'conv_thread_reviews_quest_thread_unique')) {
                    $table->dropUnique('conv_thread_reviews_quest_thread_unique');
                }
                if ($this->foreignKeyExists('conversation_thread_reviews', 'conv_thread_reviews_clarify_fk')) {
                    $table->dropForeign('conv_thread_reviews_clarify_fk');
                }
                if ($this->indexExists('conversation_thread_reviews', 'conv_thread_reviews_clarify_thread_unique')) {
                    $table->dropUnique('conv_thread_reviews_clarify_thread_unique');
                }
            });

            if (Schema::hasColumn('conversation_thread_reviews', 'proposal_clarification_thread_id')) {
                Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                    $table->dropColumn('proposal_clarification_thread_id');
                });
            }
        }

        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            foreach ([
                'conv_msg_flags_quest_message_fk',
                'conv_msg_flags_quest_thread_fk',
                'conv_msg_flags_clarify_message_fk',
                'conv_msg_flags_clarify_thread_fk',
            ] as $fk) {
                if ($this->foreignKeyExists('conversation_message_flags', $fk)) {
                    $table->dropForeign($fk);
                }
            }

            if ($this->indexExists('conversation_message_flags', 'conv_msg_flags_clarify_thread_status_idx')) {
                $table->dropIndex('conv_msg_flags_clarify_thread_status_idx');
            }
        });

        if (Schema::hasColumn('conversation_message_flags', 'proposal_clarification_thread_id')) {
            Schema::table('conversation_message_flags', function (Blueprint $table): void {
                $table->dropColumn(['proposal_clarification_thread_id', 'proposal_clarification_message_id']);
            });
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $name, 'FOREIGN KEY'],
        );

        return $result !== [];
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $name],
        );

        return $result !== [];
    }
};
