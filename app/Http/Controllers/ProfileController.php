<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    /**
     * Atualiza o avatar do usuário
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        try {
            $user = auth()->user();
            
            // Remove avatar antigo se existir
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Gera um nome único para o arquivo
            $fileName = Str::uuid() . '.' . $request->avatar->extension();
            
            // Salva o arquivo no storage
            $path = $request->avatar->storeAs('avatars', $fileName, 'public');
            
            // Atualiza o usuário com o caminho do avatar
            $user->avatar = $path;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Avatar atualizado com sucesso',
                'data' => [
                    'avatar_url' => Storage::url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao atualizar avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna os dados do perfil do usuário
     */
    public function show()
    {
        $user = auth()->user();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'matricula' => $user->matricula,
                'tipo' => $user->tipo,
                'avatar_url' => $user->avatar ? Storage::url($user->avatar) : null
            ]
        ]);
    }
}
