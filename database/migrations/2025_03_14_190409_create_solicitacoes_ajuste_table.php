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
        Schema::create('solicitacoes_ajuste', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registro_id')->constrained('registros')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('horario_atual');
            $table->dateTime('horario_solicitado');
            $table->text('justificativa');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->text('observacao_coordenador')->nullable();
            $table->foreignId('coordenador_id')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('data_resposta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_ajuste');
    }
};
