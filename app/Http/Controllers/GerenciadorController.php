<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class GerenciadorController extends Controller
{
    /**
     * Lista todos os alunos cadastrados
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listarAlunos(Request $request): JsonResponse
    {
        $user = $request->user();
        $isCoordenador = $user->tipo === 'coordenador';

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
            ->when($status !== 'todos' && $isCoordenador, function ($q) use ($status) {
                if ($status === 'ativo') {
                    return $q->where('ativo', true);
                } elseif ($status === 'inativo') {
                    return $q->where('ativo', false);
                }
            });

        // Se não for coordenador, mostrar apenas alunos ativos
        if (!$isCoordenador) {
            $query->where('ativo', true);
        }

        $query->orderBy('nome');

        $alunos = $query->paginate($porPagina);

        // Se não for coordenador, limitar os campos retornados
        if (!$isCoordenador) {
            $alunos->through(function ($aluno) {
                return [
                    'id' => $aluno->id,
                    'nome' => $aluno->nome,
                    'matricula' => $aluno->matricula
                ];
            });
        }

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
        // Verifica se é um gerenciador
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
        // Verifica se é um gerenciador
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
        // Verifica se é um gerenciador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        try {
            $aluno = User::where('id', $id)
                ->where('tipo', 'aluno')
                ->first();

            if (!$aluno) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Aluno não encontrado'
                ], 404);
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
                        'entrada' => $entrada ? $entrada->horario->format('H:i:s') : null,
                        'saida' => $saida ? $saida->horario->format('H:i:s') : null,
                        'total' => $total
                    ];
                })
                ->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Registros do aluno recuperados com sucesso',
                'data' => [
                    'aluno' => [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                        'matricula' => $aluno->matricula
                    ],
                    'registros' => $registros
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar registros do aluno',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna detalhes da frequência de um aluno específico
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function frequenciaAluno(Request $request, int $id): JsonResponse
    {
        // Verifica se é um gerenciador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        try {
            $aluno = User::where('id', $id)
                ->where('tipo', 'aluno')
                ->first();

            if (!$aluno) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Aluno não encontrado'
                ], 404);
            }

            // Período padrão: mês atual
            $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dataFim = $request->input('data_fim', Carbon::now()->format('Y-m-d'));

            // Busca registros do período
            $registros = Registro::where('user_id', $id)
                ->whereBetween('horario', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
                ->orderBy('horario', 'desc')
                ->get()
                ->groupBy(function ($registro) {
                    return Carbon::parse($registro->horario)->format('Y-m-d');
                });

            // Calcula estatísticas
            $totalDias = Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1;
            $diasPresenca = $registros->count();
            $diasAusencia = $totalDias - $diasPresenca;
            $porcentagemPresenca = round(($diasPresenca / $totalDias) * 100, 2);

            // Calcula total de horas no período
            $totalMinutos = 0;
            foreach ($registros as $data => $registrosDia) {
                $entrada = $registrosDia->firstWhere('tipo', 'entrada');
                $saida = $registrosDia->firstWhere('tipo', 'saida');

                if ($entrada && $saida) {
                    $totalMinutos += Carbon::parse($entrada->horario)
                        ->diffInMinutes(Carbon::parse($saida->horario));
                }
            }

            $horasTrabalhadas = round($totalMinutos / 60, 2);

            // Formata registros
            $registrosFormatados = $registros->map(function ($registrosDia) {
                $entrada = $registrosDia->firstWhere('tipo', 'entrada');
                $saida = $registrosDia->firstWhere('tipo', 'saida');

                // Calcula total de horas do dia
                $totalHoras = '';
                if ($entrada && $saida) {
                    $minutos = Carbon::parse($entrada->horario)
                        ->diffInMinutes(Carbon::parse($saida->horario));
                    $horas = floor($minutos / 60);
                    $minutosRestantes = $minutos % 60;
                    $totalHoras = sprintf('%02d:%02d', $horas, $minutosRestantes);
                }

                $primeiroRegistro = Carbon::parse($registrosDia->first()->horario)->setTimezone('America/Sao_Paulo');

                return [
                    'data' => $primeiroRegistro->format('Y-m-d'),
                    'dia_semana' => $primeiroRegistro->locale('pt_BR')->isoFormat('dddd'),
                    'entrada' => $entrada ? Carbon::parse($entrada->horario)->setTimezone('America/Sao_Paulo')->format('H:i:s') : null,
                    'saida' => $saida ? Carbon::parse($saida->horario)->setTimezone('America/Sao_Paulo')->format('H:i:s') : null,
                    'total_horas' => $totalHoras
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Frequência do aluno recuperada com sucesso',
                'data' => [
                    'aluno' => [
                        'id' => $aluno->id,
                        'nome' => $aluno->nome,
                        'matricula' => $aluno->matricula,
                        'email' => $aluno->email,
                        'ativo' => $aluno->ativo
                    ],
                    'periodo' => [
                        'inicio' => $dataInicio,
                        'fim' => $dataFim,
                        'total_dias' => $totalDias
                    ],
                    'estatisticas' => [
                        'dias_presenca' => $diasPresenca,
                        'dias_ausencia' => $diasAusencia,
                        'porcentagem_presenca' => $porcentagemPresenca,
                        'total_horas_trabalhadas' => $horasTrabalhadas
                    ],
                    'registros' => $registrosFormatados
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar frequência do aluno',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o total de alunos presentes e estatísticas gerais
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function totalPresencas(Request $request): JsonResponse
    {
        // Verifica se é um gerenciador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        try {
            // Período padrão: dia atual
            $data = $request->input('data', Carbon::now()->format('Y-m-d'));
            $dataCarbon = Carbon::parse($data)->setTimezone('America/Sao_Paulo');

            // Busca todos os alunos ativos
            $totalAlunos = User::where('tipo', 'aluno')
                ->where('ativo', true)
                ->count();

            // Busca alunos presentes (que registraram pelo menos entrada)
            $alunosPresentes = Registro::join('users', 'users.id', '=', 'registros.user_id')
                ->whereDate('horario', $dataCarbon->format('Y-m-d'))
                ->where('users.tipo', 'aluno')
                ->where('users.ativo', true)
                ->where('registros.tipo', 'entrada')
                ->select('users.id', 'users.nome', 'users.matricula')
                ->distinct()
                ->get();

            $totalPresentes = $alunosPresentes->count();
            $totalAusentes = $totalAlunos - $totalPresentes;
            $porcentagemPresenca = $totalAlunos > 0 ? round(($totalPresentes / $totalAlunos) * 100, 2) : 0;

            // Lista detalhada dos presentes com horários
            $presencasDetalhadas = [];
            foreach ($alunosPresentes as $aluno) {
                $registros = Registro::where('user_id', $aluno->id)
                    ->whereDate('horario', $dataCarbon->format('Y-m-d'))
                    ->orderBy('horario')
                    ->get();

                $entrada = $registros->firstWhere('tipo', 'entrada');
                $saida = $registros->firstWhere('tipo', 'saida');

                $presencasDetalhadas[] = [
                    'id' => $aluno->id,
                    'nome' => $aluno->nome,
                    'matricula' => $aluno->matricula,
                    'entrada' => $entrada ? Carbon::parse($entrada->horario)->setTimezone('America/Sao_Paulo')->format('H:i:s') : null,
                    'saida' => $saida ? Carbon::parse($saida->horario)->setTimezone('America/Sao_Paulo')->format('H:i:s') : null,
                    'ultimo_registro' => $registros->last() ? [
                        'data' => Carbon::parse($registros->last()->horario)->setTimezone('America/Sao_Paulo')->format('Y-m-d'),
                        'hora' => Carbon::parse($registros->last()->horario)->setTimezone('America/Sao_Paulo')->format('H:i:s'),
                        'data_hora_completa' => Carbon::parse($registros->last()->horario)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s'),
                        'tipo' => $registros->last()->tipo
                    ] : null
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Total de presenças recuperado com sucesso',
                'data' => [
                    'data' => $dataCarbon->format('Y-m-d'),
                    'dia_semana' => $dataCarbon->locale('pt_BR')->isoFormat('dddd'),
                    'estatisticas' => [
                        'total_alunos' => $totalAlunos,
                        'presentes' => $totalPresentes,
                        'ausentes' => $totalAusentes,
                        'porcentagem_presenca' => $porcentagemPresenca
                    ],
                    'alunos_presentes' => $presencasDetalhadas
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao buscar total de presenças',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporta registros para Excel ou PDF
     *
     * @param Request $request
     * @return mixed
     */
    public function exportarRegistros(Request $request)
    {
        // Verifica se é um gerenciador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');
        $filtros = [
            'matricula' => $request->input('matricula'),
            'nome' => $request->input('nome')
        ];

        $formato = strtolower($request->input('formato', 'excel'));

        if ($formato === 'pdf') {
            return (new \App\Exports\RegistrosPdfExport($dataInicio, $dataFim, $filtros))->export();
        }

        return (new \App\Exports\RegistrosExport($dataInicio, $dataFim, $filtros))->export();
    }

    /**
     * Lista todos os registros de ponto de todos os alunos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listarTodosRegistros(Request $request): JsonResponse
    {
        $user = $request->user();
        $isCoordenador = $user->tipo === 'coordenador';

        // Parâmetros de paginação e filtros
        $dataInicio = $request->query('data_inicio');
        $dataFim = $request->query('data_fim');
        $alunoId = $request->query('aluno_id');
        $tipo = $request->query('tipo'); // entrada, saida ou todos
        $porPagina = $request->query('por_pagina', 15);
        $ordenacao = $request->query('ordenacao', 'desc'); // asc ou desc

        $query = Registro::with(['user' => function ($query) {
            $query->select('id', 'nome', 'matricula', 'email');
        }]);

        // Se não for coordenador, limita aos registros do próprio usuário
        if (!$isCoordenador) {
            $query->where('user_id', $user->id);
        } else if ($alunoId) {
            // Se for coordenador e especificou um aluno, filtra por esse aluno
            $query->where('user_id', $alunoId);
        }

        $query->when($dataInicio, function ($q) use ($dataInicio) {
            return $q->whereDate('horario', '>=', $dataInicio);
        })
        ->when($dataFim, function ($q) use ($dataFim) {
            return $q->whereDate('horario', '<=', $dataFim);
        })
        ->when($tipo && $tipo !== 'todos', function ($q) use ($tipo) {
            return $q->where('tipo', $tipo);
        })
        ->orderBy('horario', $ordenacao);

        $registros = $query->paginate($porPagina);

        return response()->json([
            'status' => 'success',
            'message' => 'Registros listados com sucesso',
            'data' => [
                'registros' => $registros->items(),
                'paginacao' => [
                    'total' => $registros->total(),
                    'por_pagina' => $registros->perPage(),
                    'pagina_atual' => $registros->currentPage(),
                    'ultima_pagina' => $registros->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Edita um registro de ponto
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function editarRegistro(Request $request, int $id): JsonResponse
    {
        // Verifica se é um coordenador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'horario' => 'required|date',
            'tipo' => 'required|in:entrada,saida'
        ], [
            'horario.required' => 'O horário é obrigatório',
            'horario.date' => 'O horário deve ser uma data válida',
            'tipo.required' => 'O tipo é obrigatório',
            'tipo.in' => 'O tipo deve ser entrada ou saída'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        try {
            $registro = Registro::findOrFail($id);

            $registro->horario = $request->horario;
            $registro->tipo = $request->tipo;
            $registro->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Registro atualizado com sucesso',
                'data' => [
                    'registro' => $registro
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exclui um registro de ponto
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function excluirRegistro(Request $request, int $id): JsonResponse
    {
        // Verifica se é um coordenador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        try {
            $registro = Registro::findOrFail($id);
            $registro->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Registro excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao excluir registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Adiciona um novo registro de ponto para um aluno
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function adicionarRegistro(Request $request): JsonResponse
    {
        // Verifica se é um coordenador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'horario' => 'required|date',
            'tipo' => 'required|in:entrada,saida'
        ], [
            'user_id.required' => 'O ID do aluno é obrigatório',
            'user_id.exists' => 'Aluno não encontrado',
            'horario.required' => 'O horário é obrigatório',
            'horario.date' => 'O horário deve ser uma data válida',
            'tipo.required' => 'O tipo é obrigatório',
            'tipo.in' => 'O tipo deve ser entrada ou saída'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        try {
            // Verifica se o usuário é um aluno
            $aluno = User::findOrFail($request->user_id);
            if ($aluno->tipo !== 'aluno') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'O usuário selecionado não é um aluno'
                ], 400);
            }

            $registro = Registro::create([
                'user_id' => $request->user_id,
                'horario' => $request->horario,
                'tipo' => $request->tipo
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Registro adicionado com sucesso',
                'data' => [
                    'registro' => $registro
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao adicionar registro',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna um relatório geral com estatísticas de todos os alunos
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function relatorio(Request $request): JsonResponse
    {
        // Verifica se é um gerenciador
        if ($request->user()->tipo !== 'coordenador') {
            return response()->json([
                'status' => 'error',
                'message' => 'Acesso não autorizado'
            ], 403);
        }

        try {
            // Período padrão: mês atual
            $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $dataFim = $request->input('data_fim', Carbon::now()->format('Y-m-d'));
            $nomeAluno = $request->input('nome');
            $matricula = $request->input('matricula');
            $status = $request->input('status'); // todos, presente, ausente, justificado

            // Busca todos os alunos ativos com filtros
            $query = User::where('tipo', 'aluno')
                ->where('ativo', true);

            if ($nomeAluno) {
                $query->where('nome', 'like', "%{$nomeAluno}%");
            }

            if ($matricula) {
                $query->where('matricula', 'like', "%{$matricula}%");
            }

            $alunos = $query->get();

            $totalAlunos = $alunos->count();
            $totalPresentes = 0;
            $totalHoras = 0;
            $alunosRelatorio = [];
            $todosRegistros = [];

            foreach ($alunos as $aluno) {
                // Busca registros do período
                $registros = Registro::where('user_id', $aluno->id)
                    ->whereBetween('horario', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
                    ->orderBy('horario', 'desc')
                    ->get();

                if ($registros->count() > 0) {
                    $totalPresentes++;
                }

                // Adiciona cada registro à lista completa
                foreach ($registros as $registro) {
                    $horario = Carbon::parse($registro->horario)->setTimezone('America/Sao_Paulo');
                    $todosRegistros[] = [
                        'data' => $horario->format('d/m/Y'),
                        'hora' => $horario->format('H:i:s'),
                        'nome' => $aluno->nome,
                        'matricula' => $aluno->matricula,
                        'tipo' => ucfirst($registro->tipo)
                    ];
                }

                // Calcula total de horas
                $horasTrabalhadas = 0;
                $registrosPorDia = $registros->groupBy(function ($registro) {
                    return Carbon::parse($registro->horario)->format('Y-m-d');
                });

                // Formata registros por dia com entrada, saída e total de horas
                $registrosDiarios = [];
                foreach ($registrosPorDia as $data => $registrosDia) {
                    $entrada = $registrosDia->firstWhere('tipo', 'entrada');
                    $saida = $registrosDia->firstWhere('tipo', 'saida');
                    
                    // Calcula total de horas do dia
                    $totalHorasDia = '';
                    if ($entrada && $saida) {
                        $minutos = Carbon::parse($entrada->horario)
                            ->diffInMinutes(Carbon::parse($saida->horario));
                        $horas = floor($minutos / 60);
                        $minutosRestantes = $minutos % 60;
                        $totalHorasDia = sprintf('%02d:%02d', $horas, $minutosRestantes);
                        $horasTrabalhadas += $minutos / 60;
                    }

                    $registrosDiarios[] = [
                        'data' => Carbon::parse($data)->format('d/m/Y'),
                        'entrada' => $entrada ? Carbon::parse($entrada->horario)->format('H:i') : null,
                        'saida' => $saida ? Carbon::parse($saida->horario)->format('H:i') : null,
                        'total_horas' => $totalHorasDia
                    ];
                }

                $totalHoras += $horasTrabalhadas;

                // Encontra primeiro e último registro
                $primeiroRegistro = $registros->last() ? Carbon::parse($registros->last()->horario)->format('d/m/Y') : null;
                $ultimoRegistro = $registros->first() ? Carbon::parse($registros->first()->horario)->format('d/m/Y') : null;
                $horarioUltimoRegistro = $registros->first() ? Carbon::parse($registros->first()->horario)->format('H:i') : null;

                // Calcula estatísticas
                $totalDias = Carbon::parse($dataInicio)->diffInDays(Carbon::parse($dataFim)) + 1;
                $diasPresenca = $registrosPorDia->count();
                $diasAusencia = $totalDias - $diasPresenca;
                $porcentagemPresenca = round(($diasPresenca / $totalDias) * 100, 2);

                // Define o status do aluno
                $statusAluno = 'ausente';
                if ($diasPresenca > 0) {
                    $statusAluno = 'presente';
                }

                // Filtra por status se especificado
                if ($status && $status !== 'todos' && $status !== $statusAluno) {
                    continue;
                }

                $alunosRelatorio[] = [
                    'id' => $aluno->id,
                    'nome' => $aluno->nome,
                    'matricula' => $aluno->matricula,
                    'ativo' => $aluno->ativo,
                    'status' => $statusAluno,
                    'primeiro_registro' => $primeiroRegistro,
                    'ultimo_registro' => $ultimoRegistro,
                    'horario_ultimo_registro' => $horarioUltimoRegistro,
                    'estatisticas' => [
                        'dias_presenca' => $diasPresenca,
                        'dias_ausencia' => $diasAusencia,
                        'porcentagem_presenca' => $porcentagemPresenca,
                        'total_horas_trabalhadas' => round($horasTrabalhadas, 2)
                    ],
                    'registros_diarios' => $registrosDiarios
                ];
            }

            // Ordena todos os registros por data e hora (mais recente primeiro)
            usort($todosRegistros, function($a, $b) {
                $dateA = Carbon::createFromFormat('d/m/Y H:i:s', $a['data'] . ' ' . $a['hora']);
                $dateB = Carbon::createFromFormat('d/m/Y H:i:s', $b['data'] . ' ' . $b['hora']);
                return $dateB->timestamp - $dateA->timestamp;
            });

            $mediaPresenca = $totalAlunos > 0 ? round(($totalPresentes / $totalAlunos) * 100, 2) : 0;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'periodo' => [
                        'inicio' => Carbon::parse($dataInicio)->format('d/m/Y'),
                        'fim' => Carbon::parse($dataFim)->format('d/m/Y')
                    ],
                    'resumo' => [
                        'total_alunos' => $totalAlunos,
                        'presentes' => $totalPresentes,
                        'media_presenca' => $mediaPresenca,
                        'total_horas' => $totalHoras
                    ],
                    'alunos' => $alunosRelatorio,
                    'registros' => $todosRegistros
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao gerar relatório',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
