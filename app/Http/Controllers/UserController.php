<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Registro;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Retorna informações do perfil do usuário
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function perfil(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuário não autenticado'
            ], 401);
        }

        // Se for aluno, inclui informações de presença do mês atual
        $dadosAdicionais = [];
        if ($user->tipo === 'aluno') {
            $inicioMes = Carbon::now()->startOfMonth();
            $hoje = Carbon::now();

            $presencas = Registro::where('user_id', $user->id)
                ->whereBetween('horario', [$inicioMes, $hoje])
                ->select(
                    DB::raw('COUNT(DISTINCT DATE(horario)) as dias_presenca'),
                    DB::raw('SUM(CASE WHEN tipo = "entrada" THEN 1 ELSE 0 END) as total_entradas'),
                    DB::raw('SUM(CASE WHEN tipo = "saida" THEN 1 ELSE 0 END) as total_saidas')
                )
                ->first();

            $totalDias = $inicioMes->diffInDays($hoje) + 1;

            $dadosAdicionais = [
                'estatisticas' => [
                    'dias_presenca' => $presencas->dias_presenca ?? 0,
                    'total_entradas' => $presencas->total_entradas ?? 0,
                    'total_saidas' => $presencas->total_saidas ?? 0,
                    'porcentagem_presenca' => $totalDias > 0 ? round((($presencas->dias_presenca ?? 0) / $totalDias) * 100, 2) : 0,
                    'periodo' => [
                        'inicio' => $inicioMes->format('Y-m-d'),
                        'fim' => $hoje->format('Y-m-d'),
                        'total_dias' => $totalDias
                    ]
                ]
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Perfil recuperado com sucesso',
            'data' => [
                'usuario' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'tipo' => $user->tipo,
                    'matricula' => $user->matricula,
                    'ativo' => $user->ativo,
                    'ultimo_acesso' => $user->ultimo_acesso,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'avatar_url' => $user->avatar ? Storage::url($user->avatar) : null
                ],
                ...$dadosAdicionais
            ]
        ]);
    }

    /**
     * Atualiza informações do perfil do usuário
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function atualizar(Request $request): JsonResponse
    {
        $user = $request->user();

        // Regras de validação
        $rules = [
            'nome' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'senha_atual' => 'required_with:nova_senha',
            'nova_senha' => 'sometimes|min:6',
            'confirmar_senha' => 'required_with:nova_senha|same:nova_senha'
        ];

        $messages = [
            'nome.max' => 'O nome não pode ter mais que 255 caracteres',
            'email.email' => 'Por favor, insira um email válido',
            'email.unique' => 'Este email já está em uso',
            'senha_atual.required_with' => 'A senha atual é obrigatória para alterar a senha',
            'nova_senha.min' => 'A nova senha deve ter pelo menos 6 caracteres',
            'confirmar_senha.required_with' => 'Por favor, confirme a nova senha',
            'confirmar_senha.same' => 'A confirmação da senha não corresponde à nova senha'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro de validação',
                'errors' => $validator->errors()->toArray()
            ], 422);
        }

        // Verifica a senha atual se estiver tentando alterar a senha
        if ($request->has('nova_senha')) {
            if (!Hash::check($request->senha_atual, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Senha atual incorreta'
                ], 401);
            }
        }

        try {
            // Atualiza os dados básicos
            if ($request->has('nome')) {
                $user->nome = $request->nome;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('nova_senha')) {
                $user->password = Hash::make($request->nova_senha);
            }

            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Perfil atualizado com sucesso',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nome' => $user->nome,
                        'email' => $user->email,
                        'tipo' => $user->tipo,
                        'matricula' => $user->matricula,
                        'ativo' => $user->ativo,
                        'ultimo_acesso' => $user->ultimo_acesso
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
