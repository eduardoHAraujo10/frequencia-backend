<?php

namespace App\Exports;

use App\Models\Registro;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrosExport
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
        $fileName = 'registros_' . $this->dataInicio->format('Y-m-d') . '_a_' . $this->dataFim->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM para Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho
            fputcsv($file, [
                'Data',
                'Hora',
                'Nome do Aluno',
                'Matrícula',
                'Tipo de Registro'
            ], ';'); // Usando ; como separador para Excel

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

            // Adiciona dados
            foreach ($registros as $registro) {
                $horario = Carbon::parse($registro->horario)->setTimezone('America/Sao_Paulo');

                // Formata os dados com ; como separador
                $linha = [
                    $horario->format('d/m/Y'),
                    $horario->format('H:i:s'),
                    str_replace(';', ',', $registro->nome), // Substitui ; por , para evitar quebras
                    $registro->matricula,
                    ucfirst($registro->tipo)
                ];

                fputcsv($file, $linha, ';');
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}
