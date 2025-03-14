<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\AlertaEsquecimento;
use Illuminate\Support\Facades\Auth;

class CoordenadorController extends Controller
{
    /**
     * Lista todos os alunos cadastrados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listarAlunos(Request $request): JsonResponse
    {
        // Verifica se é um coordenador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        // Parâmetros de paginação e busca
        $busca = $request->query('busca');
        $porPagina = $request->query('por_pagina', 10);
        $status = $request->query('status'); // ativo, inativo ou todos

        $query = User::where('tipo', 'aluno')
            ->when($busca, function ($q) use ($busca) {
                return $q->where(function ($query) use ($busca) {
                    $query->where('nome', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%")
                        ->orWhere('matricula', 'like', "%{$busca}%");
                });
            })
            ->when($status !== 'todos', function ($q) use ($status) {
                if ($status === 'ativo') {
                    return $q->where('ativo', true);
                } elseif ($status === 'inativo') {
                    return $q->where('ativo', false);
                }
            })
            ->orderBy('nome');

        $alunos = $query->paginate($porPagina);

        return response()->json([
            'status' => 'success',
            'message' => 'Alunos listados com sucesso',
            'data' => [
                'alunos' => $alunos->items(),
                'paginacao' => [
                    'total' => $alunos->total(),
                    'por_pagina' => $alunos->perPage(),
                    'pagina_atual' => $alunos->currentPage(),
                    'ultima_pagina' => $alunos->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Cadastra um novo aluno
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cadastrarAluno(Request $request): JsonResponse
    {
        // Verifica se é um coordenador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'matricula' => 'required|string|unique:users'
        ], [
            'nome.required' => 'O nome é obrigatório',
            'nome.max' => 'O nome não pode ter mais que 255 caracteres',
            'email.required' => 'O email é obrigatório',
            'email.email' => 'Por favor, insira um email válido',
            'email.unique' => 'Este email já está em uso',
            'matricula.required' => 'A matrícula é obrigatória',
            'matricula.unique' => 'Esta matrícula já está em uso'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        try {
            $aluno = User::create([
                'nome' => $request->nome,
                'email' => $request->email,
                'matricula' => $request->matricula,
                'password' => Hash::make($request->matricula), // Senha inicial é a matrícula
                'tipo' => 'aluno',
                'ativo' => true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Aluno cadastrado com sucesso. A senha inicial é a matrícula do aluno.',
                'data' => [
                    'aluno' => [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                        'email' => $aluno->email,
                        'matricula' => $aluno->matricula,
                        'ativo' => $aluno->ativo,
                        'created_at' => $aluno->created_at
                    ]
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao cadastrar aluno',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Altera o status (ativo/inativo) de um aluno
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function alterarStatus(Request $request, int $id): JsonResponse
    {
        // Verifica se é um coordenador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ativo' => 'required|boolean'
        ], [
            'ativo.required' => 'O status é obrigatório',
            'ativo.boolean' => 'O status deve ser verdadeiro ou falso'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        try {
            $aluno = User::where('id', $id)->where('tipo', 'aluno')->first();

            if (!$aluno) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Aluno não encontrado'
                ], 404);
            }

            $aluno->ativo = $request->ativo;
            $aluno->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Status do aluno atualizado com sucesso',
                'data' => [
                    'aluno' => [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                        'email' => $aluno->email,
                        'matricula' => $aluno->matricula,
                        'ativo' => $aluno->ativo
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar status do aluno',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna os registros de um aluno
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function registrosAluno(Request $request, int $id): JsonResponse
    {
        if ($request->user()->tipo !== 'coordenador') {
            return $this->unauthorizedResponse('Acesso não autorizado');
        }

        $aluno = User::where('id', $id)
            ->where('tipo', 'aluno')
            ->first();

        if (!$aluno) {
            return $this->notFoundResponse('Aluno não encontrado');
        }

        // Busca registros dos últimos 30 dias
        $registros = Registro::where('user_id', $id)
            ->whereDate('horario', '>=', Carbon::now()->subDays(30))
            ->orderBy('horario', 'desc')
            ->get()
            ->groupBy(function ($registro) {
                return Carbon::parse($registro->horario)->format('Y-m-d');
            })
            ->map(function ($registrosDia) {
                $entrada = $registrosDia->firstWhere('tipo', 'entrada');
                $saida = $registrosDia->firstWhere('tipo', 'saida');

                $total = '';
                if ($entrada && $saida) {
                    $minutos = Carbon::parse($entrada->horario)->diffInMinutes(Carbon::parse($saida->horario));
                    $horas = floor($minutos / 60);
                    $minutosRestantes = $minutos % 60;
                    $total = sprintf('%02d:%02d', $horas, $minutosRestantes);
                }

                return [
                    'data' => $registrosDia->first()->horario->format('Y-m-d'),
                    'entrada' => $entrada ? $entrada->horario : null,
                    'saida' => $saida ? $saida->horario : null,
                    'total' => $total
                ];
            })
            ->values();

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

    public function listarAlertasEsquecimento(Request $request)
    {
        try {
            $alertas = AlertaEsquecimento::with('aluno')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'alertas' => $alertas->items(),
                    'total' => $alertas->total(),
                    'por_pagina' => $alertas->perPage(),
                    'pagina_atual' => $alertas->currentPage(),
                    'ultima_pagina' => $alertas->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao listar alertas de esquecimento'
            ], 500);
        }
    }

    public function responderAlertaEsquecimento(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:aprovado,rejeitado',
                'observacao_coordenador' => 'nullable|string'
            ]);

            $alerta = AlertaEsquecimento::findOrFail($id);
            
            if ($alerta->status !== 'pendente') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Este alerta já foi respondido'
                ], 400);
            }

            $alerta->status = $request->status;
            $alerta->observacao_coordenador = $request->observacao_coordenador;
            $alerta->coordenador_id = Auth::id();
            $alerta->data_aprovacao = now();
            $alerta->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Alerta respondido com sucesso',
                'data' => $alerta
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao responder alerta de esquecimento'
            ], 500);
        }
    }
}
