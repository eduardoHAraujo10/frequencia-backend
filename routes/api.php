<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\GerenciadorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CoordenadorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Rotas públicas
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);

    // Rotas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Usuário
        Route::get('/usuarios/perfil', [UserController::class, 'perfil']);
        Route::put('/usuarios/perfil', [UserController::class, 'atualizar']);

        // Aluno
        Route::get('/aluno/info', [RegistroController::class, 'info']);
        Route::get('/alunos', [GerenciadorController::class, 'listarAlunos']);
        Route::get('/registros/hoje', [RegistroController::class, 'registrosHoje']);
        Route::post('/registros', [RegistroController::class, 'registrarPonto']);
        Route::get('/registros', [GerenciadorController::class, 'listarTodosRegistros']);
        Route::get('/registros/permanencia', [RegistroController::class, 'historicoPermanencia']);
        Route::get('/registros/horas', [RegistroController::class, 'horasTrabalhadas']);
        Route::get('/registros/historico', [RegistroController::class, 'historico']);
        Route::get('/registros/resumo', [RegistroController::class, 'resumo']);
        Route::post('/registros/ajuste', [RegistroController::class, 'solicitarAjuste']);
        Route::put('/registros/{id}', [GerenciadorController::class, 'editarRegistro']);
        Route::delete('/registros/{id}', [GerenciadorController::class, 'excluirRegistro']);

        // Gerenciador
        Route::prefix('gerenciador')->group(function () {
            Route::get('/alunos', [GerenciadorController::class, 'listarAlunos']);
            Route::post('/alunos', [GerenciadorController::class, 'cadastrarAluno']);
            Route::put('/alunos/{id}/status', [GerenciadorController::class, 'alterarStatus']);
            Route::get('/alunos/{id}/registros', [GerenciadorController::class, 'registrosAluno']);
            Route::get('/alunos/{id}/frequencia', [GerenciadorController::class, 'frequenciaAluno']);
            Route::get('/presencas', [GerenciadorController::class, 'totalPresencas']);
            Route::get('/exportar-registros', [GerenciadorController::class, 'exportarRegistros']);
            Route::post('/registros', [GerenciadorController::class, 'adicionarRegistro']);
            Route::put('/registros/{id}', [GerenciadorController::class, 'editarRegistro']);
            Route::delete('/registros/{id}', [GerenciadorController::class, 'excluirRegistro']);
            Route::get('/dashboard', [DashboardController::class, 'estatisticas']);
            Route::get('/dashboard/periodo', [DashboardController::class, 'estatisticasPeriodo']);
        });

        // Rotas de alerta de esquecimento
        Route::post('/registros/alerta-esquecimento', [RegistroController::class, 'criarAlertaEsquecimento']);
        Route::get('/registros/alerta-esquecimento', [RegistroController::class, 'listarAlertasEsquecimento']);

        // Rotas de registro de ponto
        Route::post('/registros/registrar-ponto', [RegistroController::class, 'registrarPonto']);
        Route::get('/registros/horas-trabalhadas', [RegistroController::class, 'horasTrabalhadas']);
        Route::get('/registros/solicitacoes-ajuste', [RegistroController::class, 'listarSolicitacoesAjuste']);
    });

    // Rotas do coordenador
    Route::middleware(['auth:sanctum', \App\Http\Middleware\CoordenadorMiddleware::class])->group(function () {
        // Alertas de esquecimento
        Route::get('/coordenador/alertas-esquecimento', [CoordenadorController::class, 'listarAlertasEsquecimento']);
        Route::post('/coordenador/alertas-esquecimento/{id}/responder', [CoordenadorController::class, 'responderAlertaEsquecimento']);

        // Solicitações de ajuste
        Route::get('/coordenador/solicitacoes-ajuste', [CoordenadorController::class, 'listarSolicitacoesAjuste']);
        Route::post('/coordenador/solicitacoes-ajuste/{id}/responder', [CoordenadorController::class, 'responderSolicitacaoAjuste']);
    });
});
