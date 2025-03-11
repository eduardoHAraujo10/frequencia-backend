<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Registro;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function estatisticas(Request $request)
    {
        // Verifica se o usuário é coordenador
        if (Auth::user()->tipo !== 'coordenador') {
            return response()->json(['status' => 'error', 'message' => 'Não autorizado'], 403);
        }

        $hoje = Carbon::now()->setTimezone('America/Sao_Paulo');
        $data = $request->input('data', $hoje->format('Y-m-d'));

        // Estatísticas gerais
        $totalAlunos = User::where('tipo', 0)->where('ativo', true)->count();
        $alunosPresentes = User::where('tipo', 0)->whereHas('registros', function ($query) use ($data) {
            $query->whereDate('horario', $data);
        })->count();

        $ausentes = $totalAlunos - $alunosPresentes;
        $porcentagemPresenca = $totalAlunos > 0 ? round(($alunosPresentes / $totalAlunos) * 100, 2) : 0;

        // Estatísticas de horários
        $horariosRegistros = Registro::whereDate('horario', $data)
            ->select(DB::raw('HOUR(horario) as hora'), DB::raw('COUNT(*) as total'))
            ->groupBy('hora')
            ->orderBy('hora')
            ->get();

        // Últimos registros
        $ultimosRegistros = Registro::with('user')
            ->whereDate('horario', $data)
            ->orderBy('horario', 'desc')
            ->take(10)
            ->get()
            ->map(function ($registro) {
                return [
                    'id' => $registro->id,
                    'aluno' => [
                        'nome' => $registro->user->nome,
                        'matricula' => $registro->user->matricula
                    ],
                    'tipo' => $registro->tipo,
                    'horario' => Carbon::parse($registro->horario)
                        ->setTimezone('America/Sao_Paulo')
                        ->format('H:i:s'),
                    'data_hora_completa' => Carbon::parse($registro->horario)
                        ->setTimezone('America/Sao_Paulo')
                        ->format('d/m/Y H:i:s')
                ];
            });

        // Estatísticas por período do dia
        $periodos = [
            'manha' => [
                'inicio' => '06:00:00',
                'fim' => '11:59:59',
                'total' => 0
            ],
            'tarde' => [
                'inicio' => '12:00:00',
                'fim' => '17:59:59',
                'total' => 0
            ],
            'noite' => [
                'inicio' => '18:00:00',
                'fim' => '23:59:59',
                'total' => 0
            ]
        ];

        foreach ($periodos as $periodo => &$dados) {
            $dados['total'] = Registro::whereDate('horario', $data)
                ->whereTime('horario', '>=', $dados['inicio'])
                ->whereTime('horario', '<=', $dados['fim'])
                ->count();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $data,
                'dia_semana' => Carbon::parse($data)->locale('pt-BR')->isoFormat('dddd'),
                'estatisticas_gerais' => [
                    'total_alunos' => $totalAlunos,
                    'presentes' => $alunosPresentes,
                    'ausentes' => $ausentes,
                    'porcentagem_presenca' => $porcentagemPresenca
                ],
                'distribuicao_horarios' => $horariosRegistros,
                'distribuicao_periodos' => $periodos,
                'ultimos_registros' => $ultimosRegistros
            ]
        ]);
    }

    public function estatisticasPeriodo(Request $request)
    {
        // Verifica se o usuário é coordenador
        if (Auth::user()->tipo !== 'coordenador') {
            return response()->json(['status' => 'error', 'message' => 'Não autorizado'], 403);
        }

        $dataInicio = $request->input('data_inicio', Carbon::now()->subDays(7)->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->format('Y-m-d'));

        // Estatísticas por dia no período
        $estatisticasDiarias = DB::table('registros')
            ->join('users', 'registros.user_id', '=', 'users.id')
            ->whereDate('registros.horario', '>=', $dataInicio)
            ->whereDate('registros.horario', '<=', $dataFim)
            ->where('users.tipo', 0)
            ->where('users.ativo', true)
            ->select(
                DB::raw('DATE(horario) as data'),
                DB::raw('COUNT(DISTINCT user_id) as total_presentes')
            )
            ->groupBy('data')
            ->orderBy('data')
            ->get();

        // Total de horas registradas no período
        $horasTotais = DB::table('registros')
            ->join('users', 'registros.user_id', '=', 'users.id')
            ->where('users.tipo', 0)
            ->whereDate('horario', '>=', $dataInicio)
            ->whereDate('horario', '<=', $dataFim)
            ->select(DB::raw('SUM(TIMESTAMPDIFF(HOUR, MIN(horario), MAX(horario))) as total_horas'))
            ->first();

        // Média de permanência por aluno
        $mediaPermanencia = DB::table('registros')
            ->join('users', 'registros.user_id', '=', 'users.id')
            ->where('users.tipo', 0)
            ->whereDate('horario', '>=', $dataInicio)
            ->whereDate('horario', '<=', $dataFim)
            ->select(
                'user_id',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, MIN(horario), MAX(horario))) as media_horas')
            )
            ->groupBy('user_id')
            ->avg('media_horas');

        return response()->json([
            'status' => 'success',
            'data' => [
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ],
                'estatisticas_diarias' => $estatisticasDiarias,
                'horas_totais' => round($horasTotais->total_horas ?? 0, 2),
                'media_permanencia' => round($mediaPermanencia ?? 0, 2)
            ]
        ]);
    }
}
