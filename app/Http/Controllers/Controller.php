<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Retorna uma resposta de sucesso
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Operação realizada com sucesso', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Retorna uma resposta de erro
     *
     * @param string $message
     * @param int $code
     * @param mixed $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Erro na operação', int $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    /**
     * Retorna uma resposta não autorizada
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Não autorizado'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Retorna uma resposta não encontrada
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'Recurso não encontrado'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Retorna uma resposta de validação
     *
     * @param array $errors
     * @return JsonResponse
     */
    protected function validationResponse(array $errors): JsonResponse
    {
        return $this->errorResponse('Erro de validação', 422, $errors);
    }
}
