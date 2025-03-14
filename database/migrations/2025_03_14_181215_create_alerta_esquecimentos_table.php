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
        Schema::create('alerta_esquecimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('data');
            $table->time('horario_previsto');
            $table->enum('tipo', ['entrada', 'saida']);
            $table->text('justificativa');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->text('observacao_coordenador')->nullable();
            $table->timestamp('data_aprovacao')->nullable();
            $table->foreignId('coordenador_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Índices
            $table->index('data');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerta_esquecimentos');
    }
};
