<?php

namespace App\Exports;

use App\Models\Registro;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class RegistrosPdfExport
{
    protected $dataInicio;
    protected $dataFim;
    protected $filtros;

    public function __construct($dataInicio = null, $dataFim = null, $filtros = [])
    {
        $this->dataInicio = $dataInicio ? Carbon::parse($dataInicio) : Carbon::now()->startOfMonth();
        $this->dataFim = $dataFim ? Carbon::parse($dataFim) : Carbon::now();
        $this->filtros = $filtros;
    }

    public function export()
    {
        // Busca registros
        $registros = Registro::query()
            ->join('users', 'users.id', '=', 'registros.user_id')
            ->whereBetween('horario', [
                $this->dataInicio->startOfDay(),
                $this->dataFim->endOfDay()
            ])
            ->when(isset($this->filtros['matricula']), function($query) {
                return $query->where('users.matricula', 'like', '%' . $this->filtros['matricula'] . '%');
            })
            ->when(isset($this->filtros['nome']), function($query) {
                return $query->where('users.nome', 'like', '%' . $this->filtros['nome'] . '%');
            })
            ->select(
                'registros.horario',
                'registros.tipo',
                'users.nome',
                'users.matricula'
            )
            ->orderBy('registros.horario', 'desc')
            ->get();

        // Formata dados para o PDF
        $dados = [];
        foreach ($registros as $registro) {
            $horario = Carbon::parse($registro->horario)->setTimezone('America/Sao_Paulo');
            $dados[] = [
                'data' => $horario->format('d/m/Y'),
                'hora' => $horario->format('H:i:s'),
                'nome' => $registro->nome,
                'matricula' => $registro->matricula,
                'tipo' => ucfirst($registro->tipo)
            ];
        }

        // Gera PDF
        $pdf = PDF::loadView('exports.registros', [
            'registros' => $dados,
            'periodo' => [
                'inicio' => $this->dataInicio->format('d/m/Y'),
                'fim' => $this->dataFim->format('d/m/Y')
            ]
        ]);

        return $pdf->download('registros_' . $this->dataInicio->format('Y-m-d') . '_a_' . $this->dataFim->format('Y-m-d') . '.pdf');
    }
}
