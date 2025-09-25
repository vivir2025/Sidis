<?php
// app/Http/Controllers/Api/AuthController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\Usuario;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'login' => 'required|string',
                'password' => 'required|string',
                'sede_id' => 'required|integer|exists:sedes,id'
            ]);

            $usuario = Usuario::where('login', $validated['login'])
                ->where('sede_id', $validated['sede_id'])
                ->with(['sede', 'rol', 'especialidad', 'estado'])
                ->first();

            if (!$usuario || !Hash::check($validated['password'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'login' => ['Las credenciales proporcionadas son incorrectas.'],
                ]);
            }

            // Verificar si el usuario está activo usando el método auxiliar
            if (!$usuario->estaActivo()) {
                throw ValidationException::withMessages([
                    'login' => ['El usuario está inactivo o suspendido.'],
                ]);
            }

            // Crear token con expiración de 8 horas para mayor seguridad
            $token = $usuario->createToken('api-token', ['*'], now()->addHours(8))->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => now()->addHours(8)->toISOString(),
                    'usuario' => [
                        'id' => $usuario->id,
                        'uuid' => $usuario->uuid,
                        'documento' => $usuario->documento,
                        'nombre' => $usuario->nombre,
                        'apellido' => $usuario->apellido,
                        'nombre_completo' => $usuario->nombre_completo,
                        'correo' => $usuario->correo,
                        'telefono' => $usuario->telefono,
                        'login' => $usuario->login,
                        'registro_profesional' => $usuario->registro_profesional,
                        
                        // Información de relaciones
                        'sede_id' => $usuario->sede_id,
                        'sede' => [
                            'id' => $usuario->sede?->id,
                            'nombre' => $usuario->sede?->nombre,
                        ],
                        
                        'rol_id' => $usuario->rol_id,
                        'rol' => [
                            'id' => $usuario->rol?->id,
                            'nombre' => $usuario->rol?->nombre,
                        ],
                        
                        'especialidad_id' => $usuario->especialidad_id,
                        'especialidad' => $usuario->especialidad ? [
                            'id' => $usuario->especialidad->id,
                            'nombre' => $usuario->especialidad->nombre,
                        ] : null,
                        
                        'estado_id' => $usuario->estado_id,
                        'estado' => [
                            'id' => $usuario->estado?->id,
                            'nombre' => $usuario->estado?->nombre,
                        ],
                        
                        // Permisos y roles
                        'permisos' => $usuario->permisos,
                        'tipo_usuario' => [
                            'es_administrador' => $usuario->esAdministrador(),
                            'es_medico' => $usuario->esMedico(),
                            'es_enfermero' => $usuario->esEnfermero(),
                            'es_secretaria' => $usuario->esSecretaria(),
                            'es_auxiliar' => $usuario->esAuxiliar(),
                        ]
                    ]
                ],
                'message' => 'Inicio de sesión exitoso'
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar sesión'
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user()->load(['sede', 'rol', 'especialidad', 'estado']);

            return response()->json([
                'success' => true,
                'data' => [
                    'usuario' => [
                        'id' => $usuario->id,
                        'uuid' => $usuario->uuid,
                        'documento' => $usuario->documento,
                        'nombre' => $usuario->nombre,
                        'apellido' => $usuario->apellido,
                        'nombre_completo' => $usuario->nombre_completo,
                        'correo' => $usuario->correo,
                        'telefono' => $usuario->telefono,
                        'login' => $usuario->login,
                        'registro_profesional' => $usuario->registro_profesional,
                        
                        // Información de relaciones
                        'sede' => [
                            'id' => $usuario->sede?->id,
                            'nombre' => $usuario->sede?->nombre,
                        ],
                        
                        'rol' => [
                            'id' => $usuario->rol?->id,
                            'nombre' => $usuario->rol?->nombre,
                        ],
                        
                        'especialidad' => $usuario->especialidad ? [
                            'id' => $usuario->especialidad->id,
                            'nombre' => $usuario->especialidad->nombre,
                        ] : null,
                        
                        'estado' => [
                            'id' => $usuario->estado?->id,
                            'nombre' => $usuario->estado?->nombre,
                        ],
                        
                        // Permisos y roles
                        'permisos' => $usuario->permisos,
                        'tipo_usuario' => [
                            'es_administrador' => $usuario->esAdministrador(),
                            'es_medico' => $usuario->esMedico(),
                            'es_enfermero' => $usuario->esEnfermero(),
                            'es_secretaria' => $usuario->esSecretaria(),
                            'es_auxiliar' => $usuario->esAuxiliar(),
                        ]
                    ]
                ],
                'message' => 'Información del usuario obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del usuario'
            ], 500);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user();
            
            // Eliminar el token actual
            $request->user()->currentAccessToken()->delete();
            
            // Crear nuevo token
            $token = $usuario->createToken('api-token', ['*'], now()->addHours(8))->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => now()->addHours(8)->toISOString()
                ],
                'message' => 'Token renovado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al renovar token'
            ], 500);
        }
    }

    public function checkPermissions(Request $request): JsonResponse
    {
        try {
            $usuario = $request->user()->load(['rol']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'permisos' => $usuario->permisos,
                    'rol' => $usuario->rol?->nombre,
                    'puede_acceder' => [
                        'dashboard_admin' => $usuario->esAdministrador(),
                        'gestion_citas' => $usuario->esAdministrador() || $usuario->esSecretaria(),
                        'agenda_medica' => $usuario->esMedico() || $usuario->esEnfermero(),
                        'reportes' => $usuario->esAdministrador() || $usuario->esMedico(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar permisos'
            ], 500);
        }
    }

/**
 * ✅ NUEVO: Autenticar usuario sin validar sede específica
 */
public function authenticateUser(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string'
        ]);

        // Buscar usuario por login en cualquier sede
        $usuario = Usuario::where('login', $validated['login'])
            ->with(['sede', 'rol', 'especialidad', 'estado'])
            ->first();

        if (!$usuario || !Hash::check($validated['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'login' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Verificar si el usuario está activo
        if (!$usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'login' => ['El usuario está inactivo o suspendido.'],
            ]);
        }

        // Crear token
        $token = $usuario->createToken('api-token', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addHours(8)->toISOString(),
                'usuario' => [
                    'id' => $usuario->id,
                    'uuid' => $usuario->uuid,
                    'documento' => $usuario->documento,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'nombre_completo' => $usuario->nombre_completo,
                    'correo' => $usuario->correo,
                    'telefono' => $usuario->telefono,
                    'login' => $usuario->login,
                    'registro_profesional' => $usuario->registro_profesional,
                    
                    // Información de relaciones
                    'sede_id' => $usuario->sede_id,
                    'sede' => [
                        'id' => $usuario->sede?->id,
                        'nombre' => $usuario->sede?->nombre,
                    ],
                    
                    'rol_id' => $usuario->rol_id,
                    'rol' => [
                        'id' => $usuario->rol?->id,
                        'nombre' => $usuario->rol?->nombre,
                    ],
                    
                    'especialidad_id' => $usuario->especialidad_id,
                    'especialidad' => $usuario->especialidad ? [
                        'id' => $usuario->especialidad->id,
                        'nombre' => $usuario->especialidad->nombre,
                    ] : null,
                    
                    'estado_id' => $usuario->estado_id,
                    'estado' => [
                        'id' => $usuario->estado?->id,
                        'nombre' => $usuario->estado?->nombre,
                    ],
                    
                    // Permisos y roles
                    'permisos' => $usuario->permisos,
                    'tipo_usuario' => [
                        'es_administrador' => $usuario->esAdministrador(),
                        'es_medico' => $usuario->esMedico(),
                        'es_enfermero' => $usuario->esEnfermero(),
                        'es_secretaria' => $usuario->esSecretaria(),
                        'es_auxiliar' => $usuario->esAuxiliar(),
                    ]
                ]
            ],
            'message' => 'Usuario autenticado correctamente'
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor',
            'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
        ], 500);
    }
}
}
