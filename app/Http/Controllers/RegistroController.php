<?php

namespace App\Http\Controllers;

use App\Models\Registro;
use App\Models\User;
use App\Models\AlertaEsquecimento;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegistroController extends Controller
{
    /**
     * Registra ponto do aluno (entrada ou saída)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registrarPonto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:entrada,saida'
        ], [
            'tipo.required' => 'O tipo de registro é obrigatório',
            'tipo.in' => 'O tipo deve ser entrada ou saída'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        $user = $request->user();

        if ($user->tipo !== 'aluno') {
            return $this->unauthorizedResponse('Apenas alunos podem registrar ponto');
        }

        if (!$user->ativo) {
            return $this->unauthorizedResponse('Seu acesso está bloqueado');
        }

        // Verifica o último registro do usuário
        $ultimoRegistro = Registro::where('user_id', $user->id)
            ->whereDate('horario', Carbon::today())
            ->orderBy('horario', 'desc')
            ->first();

        // Se for entrada e o último registro foi entrada, não permite
        if ($request->tipo === 'entrada' && $ultimoRegistro && $ultimoRegistro->tipo === 'entrada') {
            return $this->errorResponse('Você precisa registrar saída antes de registrar outra entrada');
        }

        // Se for saída e o último registro foi saída, não permite
        if ($request->tipo === 'saida' && $ultimoRegistro && $ultimoRegistro->tipo === 'saida') {
            return $this->errorResponse('Você precisa registrar entrada antes de registrar outra saída');
        }

        // Se for a primeira saída do dia, verifica se tem entrada
        if ($request->tipo === 'saida' && !$ultimoRegistro) {
            return $this->errorResponse('Você precisa registrar entrada antes de registrar saída');
        }

        $registro = new Registro();
        $registro->user_id = $user->id;
        $registro->tipo = $request->tipo;
        $registro->horario = now();
        $registro->save();

        return $this->successResponse($registro, 'Ponto registrado com sucesso');
    }

    /**
     * Retorna os registros do dia do aluno
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registrosHoje(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->tipo !== 'aluno') {
            return $this->unauthorizedResponse('Acesso não autorizado');
        }

        $registros = Registro::where('user_id', $user->id)
            ->whereDate('horario', Carbon::today())
            ->orderBy('horario')
            ->get();

        return $this->successResponse($registros);
    }

    /**
     * Calcula o total de horas do aluno
     *
     * @param int $userId
     * @param Carbon $dataInicio
     * @param Carbon $dataFim
     * @return float
     */
    private function calcularHoras(int $userId, Carbon $dataInicio, Carbon $dataFim): float
    {
        $registros = Registro::where('user_id', $userId)
            ->whereBetween('horario', [$dataInicio, $dataFim])
            ->orderBy('horario')
            ->get();

        $totalMinutos = 0;
        $entrada = null;

        foreach ($registros as $registro) {
            if ($registro->tipo === 'entrada') {
                $entrada = Carbon::parse($registro->horario);
            } else if ($registro->tipo === 'saida' && $entrada) {
                $saida = Carbon::parse($registro->horario);
                $totalMinutos += $entrada->diffInMinutes($saida);
                $entrada = null;
            }
        }

        return round($totalMinutos / 60, 2);
    }

    /**
     * Retorna as horas trabalhadas do aluno
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function horasTrabalhadas(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->tipo !== 'aluno') {
            return $this->unauthorizedResponse('Acesso não autorizado');
        }

        $hoje = Carbon::today();
        $inicioSemana = Carbon::today()->startOfWeek();
        $fimSemana = Carbon::today()->endOfWeek();

        $horasHoje = $this->calcularHoras($user->id, $hoje, $hoje->copy()->endOfDay());
        $horasSemana = $this->calcularHoras($user->id, $inicioSemana, $fimSemana);

        return $this->successResponse([
            'horasHoje' => $horasHoje,
            'horasSemana' => $horasSemana
        ]);
    }

    /**
     * Retorna o histórico de registros do usuário logado ou de todos os alunos (para coordenador)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function historico(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Registro::query()
            ->select(
                'registros.id',
                'registros.tipo',
                'registros.horario',
                'users.nome as nome_aluno',
                'users.matricula'
            )
            ->join('users', 'users.id', '=', 'registros.user_id')
            ->orderBy('registros.horario', 'desc');

        // Filtros
        if ($request->has('data_inicio')) {
            $query->where('registros.horario', '>=', $request->data_inicio . ' 00:00:00');
        }
        if ($request->has('data_fim')) {
            $query->where('registros.horario', '<=', $request->data_fim . ' 23:59:59');
        }
        if ($request->has('matricula')) {
            $query->where('users.matricula', 'like', '%' . $request->matricula . '%');
        }
        if ($request->has('nome')) {
            $query->where('users.nome', 'like', '%' . $request->nome . '%');
        }

        // Se for aluno, mostra apenas seus registros
        if ($user->tipo === 'aluno') {
            $query->where('registros.user_id', $user->id);
        }

        // Paginação
        $perPage = $request->input('per_page', 15);
        $registros = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Histórico recuperado com sucesso',
            'data' => [
                'registros' => $registros->items(),
                'pagination' => [
                    'total' => $registros->total(),
                    'per_page' => $registros->perPage(),
                    'current_page' => $registros->currentPage(),
                    'last_page' => $registros->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Retorna o resumo de presenças por período
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resumo(Request $request): JsonResponse
    {
        $user = $request->user();
        $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->format('Y-m-d'));

        $query = DB::table('registros')
            ->join('users', 'users.id', '=', 'registros.user_id')
            ->whereBetween('registros.horario', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
            ->select(
                'users.id',
                'users.nome',
                'users.matricula',
                DB::raw('COUNT(DISTINCT DATE(registros.horario)) as dias_presenca'),
                DB::raw('MIN(DATE(registros.horario)) as primeiro_registro'),
                DB::raw('MAX(DATE(registros.horario)) as ultimo_registro'),
                DB::raw('MAX(registros.horario) as ultimo_registro_horario')
            )
            ->groupBy('users.id', 'users.nome', 'users.matricula');

        // Se for aluno, mostra apenas seu resumo
        if ($user->tipo === 'aluno') {
            $query->where('users.id', $user->id);
        }

        $resumos = $query->get();

        // Calcular porcentagem de presença e formatar horário
        $totalDias = Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1;
        foreach ($resumos as &$resumo) {
            $resumo->porcentagem_presenca = round(($resumo->dias_presenca / $totalDias) * 100, 2);
            if ($resumo->ultimo_registro_horario) {
                $horario = Carbon::parse($resumo->ultimo_registro_horario)->setTimezone('America/Sao_Paulo');
                $resumo->ultimo_registro_horario = $horario->format('H:i:s');
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Resumo recuperado com sucesso',
            'data' => [
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim,
                    'total_dias' => $totalDias
                ],
                'resumos' => $resumos
            ]
        ]);
    }

    public function historicoPermanencia(Request $request)
    {
        $user = auth()->user();
        $dataInicio = $request->input('data_inicio', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dataFim = $request->input('data_fim', Carbon::now()->format('Y-m-d'));

        // Busca os registros agrupados por dia
        $registros = DB::table('registros')
            ->where('user_id', $user->id)
            ->whereDate('horario', '>=', $dataInicio)
            ->whereDate('horario', '<=', $dataFim)
            ->select(
                DB::raw('DATE(horario) as data'),
                DB::raw('MIN(CASE WHEN tipo = "entrada" THEN TIME(horario) END) as primeiro_registro'),
                DB::raw('MAX(CASE WHEN tipo = "saida" THEN TIME(horario) END) as ultimo_registro'),
                DB::raw('TIMESTAMPDIFF(HOUR, MIN(horario), MAX(horario)) as horas_permanencia')
            )
            ->groupBy('data')
            ->orderBy('data', 'desc')
            ->get();

        // Calcula estatísticas
        $totalDias = $registros->count();
        $totalHoras = $registros->sum('horas_permanencia');
        $mediaDiaria = $totalDias > 0 ? round($totalHoras / $totalDias, 2) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'periodo' => [
                    'inicio' => $dataInicio,
                    'fim' => $dataFim
                ],
                'estatisticas' => [
                    'total_dias' => $totalDias,
                    'total_horas' => $totalHoras,
                    'media_diaria' => $mediaDiaria
                ],
                'registros' => $registros->map(function($registro) {
                    return [
                        'data' => Carbon::parse($registro->data)->format('d/m/Y'),
                        'dia_semana' => Carbon::parse($registro->data)->locale('pt-BR')->isoFormat('dddd'),
                        'entrada' => $registro->primeiro_registro,
                        'saida' => $registro->ultimo_registro,
                        'horas_permanencia' => $registro->horas_permanencia
                    ];
                })
            ]
        ]);
    }

    /**
     * Solicita ajuste de um registro
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function solicitarAjuste(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'registro_id' => 'required|exists:registros,id',
            'novo_horario' => 'required|date',
            'justificativa' => 'required|string|min:10'
        ], [
            'registro_id.required' => 'O ID do registro é obrigatório',
            'registro_id.exists' => 'Registro não encontrado',
            'novo_horario.required' => 'O novo horário é obrigatório',
            'novo_horario.date' => 'O novo horário deve ser uma data válida',
            'justificativa.required' => 'A justificativa é obrigatória',
            'justificativa.min' => 'A justificativa deve ter pelo menos 10 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        $user = $request->user();
        $registro = Registro::find($request->registro_id);

        // Verifica se o registro pertence ao usuário
        if ($registro->user_id !== $user->id) {
            return $this->unauthorizedResponse('Você não tem permissão para ajustar este registro');
        }

        // Cria a solicitação de ajuste
        DB::table('solicitacoes_ajuste')->insert([
            'registro_id' => $registro->id,
            'user_id' => $user->id,
            'horario_atual' => $registro->horario,
            'horario_solicitado' => $request->novo_horario,
            'justificativa' => $request->justificativa,
            'status' => 'pendente',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Notifica o coordenador (implementar depois)
        // TODO: Implementar notificação

        return $this->successResponse(null, 'Solicitação de ajuste enviada com sucesso');
    }

    /**
     * Cria um alerta de esquecimento de registro
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function criarAlertaEsquecimento(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => [
                'required',
                'date',
                'after_or_equal:' . now()->subDays(7)->format('Y-m-d'),
                'before_or_equal:' . now()->addDays(1)->format('Y-m-d'),
            ],
            'horario_previsto' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$request->data) return;
                    
                    $dataHora = Carbon::createFromFormat('Y-m-d H:i', $request->data . ' ' . $value);
                    
                    if ($dataHora->isFuture() && $dataHora->diffInDays(now()) > 1) {
                        $fail('O horário não pode ser mais de 1 dia no futuro.');
                    }
                    
                    if ($dataHora->isPast() && $dataHora->diffInDays(now()) > 7) {
                        $fail('O horário não pode ser mais de 7 dias no passado.');
                    }
                }
            ],
            'tipo' => 'required|in:entrada,saida',
            'justificativa' => 'required|string|min:10'
        ], [
            'data.required' => 'A data é obrigatória',
            'data.date' => 'A data deve ser válida',
            'data.after_or_equal' => 'A data não pode ser mais de 7 dias no passado',
            'data.before_or_equal' => 'A data não pode ser mais de 1 dia no futuro',
            'horario_previsto.required' => 'O horário é obrigatório',
            'horario_previsto.date_format' => 'O horário deve estar no formato HH:mm',
            'tipo.required' => 'O tipo é obrigatório',
            'tipo.in' => 'O tipo deve ser entrada ou saída',
            'justificativa.required' => 'A justificativa é obrigatória',
            'justificativa.min' => 'A justificativa deve ter pelo menos 10 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        $user = $request->user();

        if ($user->tipo !== 'aluno') {
            return $this->unauthorizedResponse('Apenas alunos podem criar alertas de esquecimento');
        }

        // Verifica se já existe alerta para o mesmo dia e tipo
        $alertaExistente = AlertaEsquecimento::where('user_id', $user->id)
            ->where('data', $request->data)
            ->where('tipo', $request->tipo)
            ->exists();

        if ($alertaExistente) {
            return $this->errorResponse('Já existe um alerta para esta data e tipo de registro');
        }

        $alerta = new AlertaEsquecimento();
        $alerta->user_id = $user->id;
        $alerta->data = $request->data;
        $alerta->horario_previsto = Carbon::parse($request->data . ' ' . $request->horario_previsto);
        $alerta->tipo = $request->tipo;
        $alerta->justificativa = $request->justificativa;
        $alerta->save();

        return $this->successResponse($alerta, 'Alerta de esquecimento criado com sucesso');
    }

    /**
     * Lista os alertas de esquecimento do aluno
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listarAlertasEsquecimento(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->tipo !== 'aluno') {
            return $this->unauthorizedResponse('Acesso não autorizado');
        }

        $alertas = AlertaEsquecimento::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($alertas);
    }

    /**
     * Lista as solicitações de ajuste do aluno logado
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listarSolicitacoesAjuste(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->tipo !== 'aluno') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado. Apenas alunos podem acessar este recurso.'
            ], 403);
        }

        try {
            $solicitacoes = DB::table('solicitacoes_ajuste')
                ->join('registros', 'solicitacoes_ajuste.registro_id', '=', 'registros.id')
                ->join('users', 'solicitacoes_ajuste.user_id', '=', 'users.id')
                ->where('solicitacoes_ajuste.user_id', $user->id)
                ->select(
                    'solicitacoes_ajuste.*',
                    'registros.horario as horario_registro',
                    'registros.tipo as tipo_registro',
                    'users.nome as nome_aluno',
                    'users.matricula'
                )
                ->orderBy('solicitacoes_ajuste.created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Solicitações de ajuste listadas com sucesso',
                'data' => $solicitacoes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao listar solicitações de ajuste',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
