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
        Schema::table('alerta_esquecimentos', function (Blueprint $table) {
            // Renomear coluna se existir
            if (Schema::hasColumn('alerta_esquecimentos', 'horario_previsto')) {
                $table->renameColumn('horario_previsto', 'horario_entrada');
            }

            // Remover coluna tipo se existir
            if (Schema::hasColumn('alerta_esquecimentos', 'tipo')) {
                $table->dropColumn('tipo');
            }

            // Adicionar novas colunas se ainda não existirem
            if (!Schema::hasColumn('alerta_esquecimentos', 'horario_saida')) {
                $table->time('horario_saida')->nullable()->after('horario_entrada');
            }

            if (!Schema::hasColumn('alerta_esquecimentos', 'observacao_coordenador')) {
                $table->text('observacao_coordenador')->nullable()->after('justificativa');
            }

            if (!Schema::hasColumn('alerta_esquecimentos', 'data_aprovacao')) {
                $table->dateTime('data_aprovacao')->nullable()->after('observacao_coordenador');
            }

            if (!Schema::hasColumn('alerta_esquecimentos', 'coordenador_id')) {
                $table->bigInteger('coordenador_id')->unsigned()->nullable()->after('data_aprovacao');

                // Evita erro se a FK já existir
                $foreignKeys = DB::select("SHOW CREATE TABLE alerta_esquecimentos");
                if (strpos($foreignKeys[0]->{'Create Table'}, 'coordenador_id') === false) {
                    $table->foreign('coordenador_id')->references('id')->on('users');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerta_esquecimentos', function (Blueprint $table) {
            // Remover foreign key se existir
            if (Schema::hasColumn('alerta_esquecimentos', 'coordenador_id')) {
                try {
                    $table->dropForeign(['coordenador_id']);
                } catch (\Throwable $e) {
                    // Silencia erro se FK já foi removida manualmente
                }

                $table->dropColumn('coordenador_id');
            }

            // Remover colunas se existirem
            foreach (['horario_saida', 'observacao_coordenador', 'data_aprovacao'] as $coluna) {
                if (Schema::hasColumn('alerta_esquecimentos', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }

            // Recria coluna tipo
            if (!Schema::hasColumn('alerta_esquecimentos', 'tipo')) {
                $table->enum('tipo', ['entrada', 'saida', 'entrada_saida'])->after('data');
            }

            // Renomeia coluna de volta se existir
            if (Schema::hasColumn('alerta_esquecimentos', 'horario_entrada')) {
                $table->renameColumn('horario_entrada', 'horario_previsto');
            }
        });
    }
};
