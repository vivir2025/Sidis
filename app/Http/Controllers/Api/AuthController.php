<?php
// app/Http/Controllers/Api/AuthController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\{Usuario, Sede};
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'login' => 'required|string',
                'password' => 'required|string',
                'sede_id' => 'required|integer'
            ]);

            // ✅ CAMBIO PRINCIPAL: Buscar usuario SIN restricción de sede
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

            // ✅ OBTENER LA SEDE SELECCIONADA (no la del usuario) - optimizado con where
            $sedeSeleccionada = Sede::where('id', $validated['sede_id'])
                ->where('activo', true)
                ->first(['id', 'nombre']);
            
            if (!$sedeSeleccionada) {
                throw ValidationException::withMessages([
                    'sede_id' => ['La sede seleccionada no está disponible.'],
                ]);
            }

            // Calcular permisos una sola vez
            $esAdmin = $usuario->esAdministrador();
            $esMedico = $usuario->esMedico();
            $esEnfermero = $usuario->esEnfermero();
            $esSecretaria = $usuario->esSecretaria();
            $esAuxiliar = $usuario->esAuxiliar();

            // Crear token con expiración de 8 horas
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
                        
                        // ✅ INFORMACIÓN DE LA SEDE ORIGINAL (para referencia)
                        'sede_original_id' => $usuario->sede_id,
                        'sede_original' => [
                            'id' => $usuario->sede?->id,
                            'nombre' => $usuario->sede?->nombre,
                        ],
                        
                        // ✅ INFORMACIÓN DE LA SEDE SELECCIONADA (la que usará en la sesión)
                        'sede_id' => $sedeSeleccionada->id,
                        'sede' => [
                            'id' => $sedeSeleccionada->id,
                            'nombre' => $sedeSeleccionada->nombre,
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
                        
                        // Permisos optimizados
                        'permisos' => [
                            'es_administrador' => $esAdmin,
                            'es_medico' => $esMedico,
                            'es_enfermero' => $esEnfermero,
                            'es_secretaria' => $esSecretaria,
                            'es_auxiliar' => $esAuxiliar,
                            'puede_crear_citas' => $esAdmin || $esSecretaria,
                            'puede_ver_agenda' => $esAdmin || $esMedico || $esEnfermero,
                            'puede_gestionar_usuarios' => $esAdmin,
                        ],
                        'tipo_usuario' => [
                            'es_administrador' => $esAdmin,
                            'es_medico' => $esMedico,
                            'es_enfermero' => $esEnfermero,
                            'es_secretaria' => $esSecretaria,
                            'es_auxiliar' => $esAuxiliar,
                        ]
                    ]
                ],
                'message' => "Bienvenido a {$sedeSeleccionada->nombre} - {$usuario->nombre_completo}"
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

    // ✅ NUEVO: Endpoint para obtener todas las sedes disponibles
    public function sedes(): JsonResponse
    {
        try {
            $sedes = Sede::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'direccion', 'telefono']);

            return response()->json([
                'success' => true,
                'data' => $sedes->map(function ($sede) {
                    return [
                        'id' => $sede->id,
                        'nombre' => $sede->nombre,
                        'direccion' => $sede->direccion,
                        'telefono' => $sede->telefono
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo sedes',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    // ✅ NUEVO: Cambiar de sede sin cerrar sesión
    public function cambiarSede(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sede_id' => 'required|integer'
            ]);

            $usuario = $request->user()->load(['rol', 'especialidad']);
            $nuevaSede = Sede::where('id', $validated['sede_id'])
                ->where('activo', true)
                ->first(['id', 'nombre']);
            
            if (!$nuevaSede) {
                return response()->json([
                    'success' => false,
                    'message' => 'La sede seleccionada no está disponible'
                ], 400);
            }

            // Calcular permisos una sola vez
            $esAdmin = $usuario->esAdministrador();
            $esMedico = $usuario->esMedico();
            $esEnfermero = $usuario->esEnfermero();
            $esSecretaria = $usuario->esSecretaria();
            $esAuxiliar = $usuario->esAuxiliar();

            return response()->json([
                'success' => true,
                'data' => [
                    'usuario' => [
                        'id' => $usuario->id,
                        'uuid' => $usuario->uuid,
                        'nombre_completo' => $usuario->nombre_completo,
                        'login' => $usuario->login,
                        
                        // ✅ NUEVA SEDE SELECCIONADA
                        'sede_id' => $nuevaSede->id,
                        'sede' => [
                            'id' => $nuevaSede->id,
                            'nombre' => $nuevaSede->nombre,
                        ],
                        
                        // Mantener otros datos
                        'rol' => [
                            'id' => $usuario->rol?->id,
                            'nombre' => $usuario->rol?->nombre,
                        ],
                        'especialidad' => $usuario->especialidad ? [
                            'id' => $usuario->especialidad->id,
                            'nombre' => $usuario->especialidad->nombre,
                        ] : null,
                        'permisos' => [
                            'es_administrador' => $esAdmin,
                            'es_medico' => $esMedico,
                            'es_enfermero' => $esEnfermero,
                            'es_secretaria' => $esSecretaria,
                            'es_auxiliar' => $esAuxiliar,
                            'puede_crear_citas' => $esAdmin || $esSecretaria,
                            'puede_ver_agenda' => $esAdmin || $esMedico || $esEnfermero,
                            'puede_gestionar_usuarios' => $esAdmin,
                        ],
                        'tipo_usuario' => [
                            'es_administrador' => $esAdmin,
                            'es_medico' => $esMedico,
                            'es_enfermero' => $esEnfermero,
                            'es_secretaria' => $esSecretaria,
                            'es_auxiliar' => $esAuxiliar,
                        ]
                    ]
                ],
                'message' => "Cambiado a sede: {$nuevaSede->nombre}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cambiando de sede',
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

            // Calcular permisos una sola vez
            $esAdmin = $usuario->esAdministrador();
            $esMedico = $usuario->esMedico();
            $esEnfermero = $usuario->esEnfermero();
            $esSecretaria = $usuario->esSecretaria();
            $esAuxiliar = $usuario->esAuxiliar();

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
                        
                        // Permisos optimizados
                        'permisos' => [
                            'es_administrador' => $esAdmin,
                            'es_medico' => $esMedico,
                            'es_enfermero' => $esEnfermero,
                            'es_secretaria' => $esSecretaria,
                            'es_auxiliar' => $esAuxiliar,
                            'puede_crear_citas' => $esAdmin || $esSecretaria,
                            'puede_ver_agenda' => $esAdmin || $esMedico || $esEnfermero,
                            'puede_gestionar_usuarios' => $esAdmin,
                        ],
                        'tipo_usuario' => [
                            'es_administrador' => $esAdmin,
                            'es_medico' => $esMedico,
                            'es_enfermero' => $esEnfermero,
                            'es_secretaria' => $esSecretaria,
                            'es_auxiliar' => $esAuxiliar,
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
            
            // Calcular permisos una sola vez
            $esAdmin = $usuario->esAdministrador();
            $esMedico = $usuario->esMedico();
            $esEnfermero = $usuario->esEnfermero();
            $esSecretaria = $usuario->esSecretaria();
            $esAuxiliar = $usuario->esAuxiliar();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'permisos' => [
                        'es_administrador' => $esAdmin,
                        'es_medico' => $esMedico,
                        'es_enfermero' => $esEnfermero,
                        'es_secretaria' => $esSecretaria,
                        'es_auxiliar' => $esAuxiliar,
                        'puede_crear_citas' => $esAdmin || $esSecretaria,
                        'puede_ver_agenda' => $esAdmin || $esMedico || $esEnfermero,
                        'puede_gestionar_usuarios' => $esAdmin,
                    ],
                    'rol' => $usuario->rol?->nombre,
                    'puede_acceder' => [
                        'dashboard_admin' => $esAdmin,
                        'gestion_citas' => $esAdmin || $esSecretaria,
                        'agenda_medica' => $esMedico || $esEnfermero,
                        'reportes' => $esAdmin || $esMedico,
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
}
