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
        Schema::create('tipe_jadwal_sampah', function (Blueprint $table) {
            $table->id();
            $table->string('tipe_jadwal');
            $table->timestamps();
        });

        // Insert default values
        DB::table('tipe_jadwal_sampah')->insert([
            ['tipe_jadwal' => 'Pemilahan Sampah', 'created_at' => now(), 'updated_at' => now()],
            ['tipe_jadwal' => 'Setoran Sampah', 'created_at' => now(), 'updated_at' => now()],
            ['tipe_jadwal' => 'Rencana Setoran', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipe_jadwal_sampah');
    }
};