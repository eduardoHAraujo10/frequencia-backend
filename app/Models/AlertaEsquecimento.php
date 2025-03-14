<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaEsquecimento extends Model
{
    protected $table = 'alerta_esquecimentos';

    protected $fillable = [
        'user_id',
        'data',
        'horario_previsto',
        'tipo',
        'justificativa',
        'status',
        'observacao_coordenador',
        'data_aprovacao',
        'coordenador_id'
    ];

    protected $casts = [
        'data' => 'date',
        'horario_previsto' => 'datetime',
        'data_aprovacao' => 'datetime'
    ];

    /**
     * Obtém o aluno que fez a solicitação
     */
    public function aluno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtém o coordenador que aprovou/rejeitou a solicitação
     */
    public function coordenador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordenador_id');
    }
} 