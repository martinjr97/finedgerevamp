<?php

use App\Models\Channel;
use App\Support\ChannelTypeResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('type', 32)
                ->default(Channel::TYPE_MOBILE_WALLET)
                ->after('code');
        });

        DB::table('channels')->orderBy('id')->each(function (object $channel): void {
            DB::table('channels')
                ->where('id', $channel->id)
                ->update([
                    'type' => ChannelTypeResolver::infer((string) $channel->name, (string) $channel->code),
                ]);
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
