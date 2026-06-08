<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('province_id')
                ->nullable()
                ->constrained('provinces')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('district_id')
                ->nullable()
                ->constrained('districts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('branch_manager_id')
                ->nullable()
                ->constrained('admins')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Ensure an International province and district exist for non-Zambia locations
        $internationalProvinceId = DB::table('provinces')
            ->where('code', 'INT')
            ->value('id');

        if (! $internationalProvinceId) {
            $internationalProvinceId = DB::table('provinces')->insertGetId([
                'name' => 'International',
                'code' => 'INT',
                'country' => 'International',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $internationalDistrictId = DB::table('districts')
            ->where('code', 'INT')
            ->value('id');

        if (! $internationalDistrictId) {
            DB::table('districts')->insert([
                'province_id' => $internationalProvinceId,
                'name' => 'International',
                'code' => 'INT',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create a default Head Office branch if it doesn't exist
        if (! DB::table('branches')->where('code', 'HEAD_OFFICE')->exists()) {
            DB::table('branches')->insert([
                'name' => 'Head Office',
                'code' => 'HEAD_OFFICE',
                'province_id' => null,
                'district_id' => null,
                'branch_manager_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
