<?php

use App\Models\QuestOffer;
use App\Services\Proposals\ProposalReferenceGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_offers', 'reference_code')) {
                $table->string('reference_code', 32)->nullable()->unique()->after('uuid');
            }
        });

        $generator = app(ProposalReferenceGenerator::class);

        QuestOffer::query()
            ->whereNull('reference_code')
            ->with('quest')
            ->orderBy('id')
            ->each(function (QuestOffer $offer) use ($generator): void {
                if ($offer->quest === null) {
                    return;
                }

                $offer->forceFill([
                    'reference_code' => $generator->nextForQuest($offer->quest),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quest_offers') || ! Schema::hasColumn('quest_offers', 'reference_code')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            $table->dropColumn('reference_code');
        });
    }
};
