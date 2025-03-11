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
        Schema::table('users', function (Blueprint $table) {
            // Renomear 'name' para 'nome' se existir
            if (Schema::hasColumn('users', 'name')) {
                $table->renameColumn('name', 'nome');
            }

            // Adicionar novos campos
            if (!Schema::hasColumn('users', 'tipo')) {
                $table->enum('tipo', ['aluno', 'coordenador'])->after('email'); // 0 = aluno, 1 = coordenador
            }

            if (!Schema::hasColumn('users', 'matricula')) {
                $table->string('matricula')->nullable()->after('tipo');
            }

            if (!Schema::hasColumn('users', 'ativo')) {
                $table->boolean('ativo')->default(true)->after('matricula');
            }

            if (!Schema::hasColumn('users', 'ultimo_acesso')) {
                $table->timestamp('ultimo_acesso')->nullable()->after('ativo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverter as alterações
            if (Schema::hasColumn('users', 'nome')) {
                $table->renameColumn('nome', 'name');
            }

            $table->dropColumn([
                'tipo',
                'matricula',
                'ativo',
                'ultimo_acesso'
            ]);
        });
    }
};
