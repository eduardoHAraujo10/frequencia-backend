<?php

namespace App\Exports;

use App\Models\Registro;
use App\Models\User;
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
        // Busca alunos e seus registros
        $alunos = User::where('tipo', 'aluno')
            ->when(isset($this->filtros['matricula']), function($query) {
                return $query->where('matricula', 'like', '%' . $this->filtros['matricula'] . '%');
            })
            ->when(isset($this->filtros['nome']), function($query) {
                return $query->where('nome', 'like', '%' . $this->filtros['nome'] . '%');
            })
            ->get();

        $dadosAlunos = [];
        $registrosDiarios = [];
        $totalDias = $this->dataInicio->diffInDays($this->dataFim) + 1;

        foreach ($alunos as $aluno) {
            // Busca registros do aluno no período
            $registros = Registro::where('user_id', $aluno->id)
                ->whereBetween('horario', [
                    $this->dataInicio->startOfDay(),
                    $this->dataFim->endOfDay()
                ])
                ->orderBy('horario')
                ->get();

            // Agrupa registros por dia
            $registrosPorDia = $registros->groupBy(function ($registro) {
                return Carbon::parse($registro->horario)->format('Y-m-d');
            });

            // Processa registros diários
            foreach ($registrosPorDia as $data => $registrosDia) {
                $entrada = $registrosDia->firstWhere('tipo', 'entrada');
                $saida = $registrosDia->firstWhere('tipo', 'saida');
                
                if ($entrada && $saida) {
                    $minutos = Carbon::parse($entrada->horario)
                        ->diffInMinutes(Carbon::parse($saida->horario));
                    $horas = floor($minutos / 60);
                    $minutosRestantes = $minutos % 60;
                    $totalHoras = sprintf('%02d:%02d', $horas, $minutosRestantes);
                } else {
                    $totalHoras = '00:00';
                }

                $registrosDiarios[] = [
                    'data' => Carbon::parse($data)->format('d/m/Y'),
                    'entrada' => $entrada ? Carbon::parse($entrada->horario)->format('H:i:s') : '-',
                    'saida' => $saida ? Carbon::parse($saida->horario)->format('H:i:s') : '-',
                    'total_horas' => $totalHoras,
                    'nome_aluno' => $aluno->nome
                ];
            }

            // Calcula estatísticas do aluno
            $diasPresenca = $registrosPorDia->count();
            $porcentagemPresenca = ($diasPresenca / $totalDias) * 100;
            $primeiroRegistro = $registros->first() ? 
                Carbon::parse($registros->first()->horario)->format('d/m/Y') : '-';
            $ultimoRegistro = $registros->last() ? 
                Carbon::parse($registros->last()->horario)->format('d/m/Y') : '-';
            $horarioUltimoRegistro = $registros->last() ? 
                Carbon::parse($registros->last()->horario)->format('H:i') : '-';

            $dadosAlunos[] = [
                'nome' => $aluno->nome,
                'matricula' => $aluno->matricula,
                'dias_presenca' => $diasPresenca,
                'porcentagem_presenca' => $porcentagemPresenca,
                'primeiro_registro' => $primeiroRegistro,
                'ultimo_registro' => $ultimoRegistro,
                'horario_ultimo_registro' => $horarioUltimoRegistro,
                'ativo' => $aluno->ativo
            ];
        }

        // Ordena registros diários por data
        usort($registrosDiarios, function($a, $b) {
            return strtotime($b['data']) - strtotime($a['data']);
        });

        // Gera PDF
        $pdf = PDF::loadView('exports.registros', [
            'alunos' => $dadosAlunos,
            'registros_diarios' => $registrosDiarios,
            'periodo' => [
                'inicio' => $this->dataInicio->format('d/m/Y'),
                'fim' => $this->dataFim->format('d/m/Y')
            ],
            'user' => auth()->user()
        ]);

        return $pdf->download('relatorio_' . $this->dataInicio->format('Y-m-d') . '_a_' . $this->dataFim->format('Y-m-d') . '.pdf');
    }
}
