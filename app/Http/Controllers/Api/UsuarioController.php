<?php
// app/Http/Controllers/Api/UsuarioController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Hash, DB, Validator, Storage};
use Illuminate\Validation\Rule;
use App\Models\Usuario;

class UsuarioController extends Controller
{
    /**
     * Listar usuarios con filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Usuario::with(['sede', 'rol', 'especialidad', 'estado']);

            // Filtro por sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            // Filtro por rol
            if ($request->filled('rol_id')) {
                $query->where('rol_id', $request->rol_id);
            }

            // Filtro por estado
            if ($request->filled('estado_id')) {
                $query->where('estado_id', $request->estado_id);
            }

            // Filtro por especialidad
            if ($request->filled('especialidad_id')) {
                $query->where('especialidad_id', $request->especialidad_id);
            }

            // Búsqueda por nombre, documento o login
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%")
                      ->orWhere('documento', 'like', "%{$search}%")
                      ->orWhere('login', 'like', "%{$search}%");
                });
            }

            // Solo activos
            if ($request->boolean('solo_activos')) {
                $query->activos();
            }

            // Solo médicos
            if ($request->boolean('solo_medicos')) {
                $query->medicos();
            }

            $perPage = $request->input('per_page', 15);
            $usuarios = $query->orderBy('nombre')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $usuarios->map(function ($usuario) {
                    return $this->formatUsuario($usuario);
                }),
                'pagination' => [
                    'total' => $usuarios->total(),
                    'per_page' => $usuarios->perPage(),
                    'current_page' => $usuarios->currentPage(),
                    'last_page' => $usuarios->lastPage(),
                    'from' => $usuarios->firstItem(),
                    'to' => $usuarios->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo usuarios',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Crear nuevo usuario
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validación
            $validator = Validator::make($request->all(), [
                'sede_id' => 'required|exists:sedes,id',
                'documento' => 'required|string|max:15|unique:usuarios,documento',
                'nombre' => 'required|string|max:50',
                'apellido' => 'required|string|max:50',
                'telefono' => 'required|string|max:10',
                'correo' => 'required|email|max:60|unique:usuarios,correo',
                'login' => 'required|string|max:50|unique:usuarios,login',
                'password' => 'required|string|min:6|confirmed',
                'rol_id' => 'required|exists:roles,id',
                'estado_id' => 'required|exists:estados,id',
                'especialidad_id' => 'nullable|exists:especialidades,id',
                'registro_profesional' => 'nullable|string|max:50',
                
                // ✅ VALIDACIÓN PARA FIRMA (base64 o archivo)
                'firma' => 'nullable|string', // Base64 de la imagen
                'firma_file' => 'nullable|file|mimes:png,jpg,jpeg|max:2048', // O archivo directo
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Preparar datos del usuario
            $userData = [
                'sede_id' => $request->sede_id,
                'documento' => $request->documento,
                'nombre' => strtoupper($request->nombre),
                'apellido' => strtoupper($request->apellido),
                'telefono' => $request->telefono,
                'correo' => $request->correo,
                'login' => $request->login,
                'password' => Hash::make($request->password),
                'rol_id' => $request->rol_id,
                'estado_id' => $request->estado_id,
                'especialidad_id' => $request->especialidad_id,
                'registro_profesional' => $request->registro_profesional,
            ];

            // ✅ PROCESAR FIRMA SI ES MÉDICO
            if ($this->esMedico($request->rol_id)) {
                $firmaData = $this->procesarFirma($request);
                if ($firmaData) {
                    $userData['firma'] = $firmaData;
                }
            }

            // Crear usuario
            $usuario = Usuario::create($userData);

            DB::commit();

            // Cargar relaciones
            $usuario->load(['sede', 'rol', 'especialidad', 'estado']);

            return response()->json([
                'success' => true,
                'data' => $this->formatUsuario($usuario),
                'message' => 'Usuario creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error creando usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Mostrar un usuario específico
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::with(['sede', 'rol', 'especialidad', 'estado'])
                ->where('uuid', $uuid)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $this->formatUsuario($usuario, true) // true = incluir firma
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
    }

    /**
     * Actualizar usuario
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            // Validación
            $validator = Validator::make($request->all(), [
                'sede_id' => 'sometimes|required|exists:sedes,id',
                'documento' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:15',
                    Rule::unique('usuarios')->ignore($usuario->id)
                ],
                'nombre' => 'sometimes|required|string|max:50',
                'apellido' => 'sometimes|required|string|max:50',
                'telefono' => 'sometimes|required|string|max:10',
                'correo' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:60',
                    Rule::unique('usuarios')->ignore($usuario->id)
                ],
                'login' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('usuarios')->ignore($usuario->id)
                ],
                'password' => 'sometimes|nullable|string|min:6|confirmed',
                'rol_id' => 'sometimes|required|exists:roles,id',
                'estado_id' => 'sometimes|required|exists:estados,id',
                'especialidad_id' => 'nullable|exists:especialidades,id',
                'registro_profesional' => 'nullable|string|max:50',
                
                // ✅ VALIDACIÓN PARA FIRMA
                'firma' => 'nullable|string',
                'firma_file' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
                'eliminar_firma' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Actualizar datos básicos
            if ($request->filled('sede_id')) $usuario->sede_id = $request->sede_id;
            if ($request->filled('documento')) $usuario->documento = $request->documento;
            if ($request->filled('nombre')) $usuario->nombre = strtoupper($request->nombre);
            if ($request->filled('apellido')) $usuario->apellido = strtoupper($request->apellido);
            if ($request->filled('telefono')) $usuario->telefono = $request->telefono;
            if ($request->filled('correo')) $usuario->correo = $request->correo;
            if ($request->filled('login')) $usuario->login = $request->login;
            if ($request->filled('rol_id')) $usuario->rol_id = $request->rol_id;
            if ($request->filled('estado_id')) $usuario->estado_id = $request->estado_id;
            if ($request->filled('especialidad_id')) $usuario->especialidad_id = $request->especialidad_id;
            if ($request->filled('registro_profesional')) $usuario->registro_profesional = $request->registro_profesional;

            // Actualizar password si se proporciona
            if ($request->filled('password')) {
                $usuario->password = Hash::make($request->password);
            }

            // ✅ PROCESAR FIRMA
            if ($request->boolean('eliminar_firma')) {
                // Eliminar firma existente
                $usuario->firma = null;
            } elseif ($this->esMedico($usuario->rol_id)) {
                $firmaData = $this->procesarFirma($request);
                if ($firmaData) {
                    $usuario->firma = $firmaData;
                }
            }

            $usuario->save();

            DB::commit();

            // Cargar relaciones
            $usuario->load(['sede', 'rol', 'especialidad', 'estado']);

            return response()->json([
                'success' => true,
                'data' => $this->formatUsuario($usuario),
                'message' => 'Usuario actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            // Verificar si tiene citas o agendas activas
            if ($usuario->agendas()->exists() || $usuario->citasCreadas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el usuario porque tiene registros asociados'
                ], 400);
            }

            $usuario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * ✅ SUBIR/ACTUALIZAR FIRMA DE MÉDICO
     */
    public function subirFirma(Request $request, string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            // Verificar que sea médico
            if (!$this->esMedico($usuario->rol_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo los médicos pueden tener firma digital'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'firma' => 'required|string', // Base64
                // O si prefieres archivo:
                // 'firma_file' => 'required|file|mimes:png,jpg,jpeg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $firmaData = $this->procesarFirma($request);
            
            if (!$firmaData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error procesando la firma'
                ], 400);
            }

            $usuario->firma = $firmaData;
            $usuario->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $usuario->uuid,
                    'tiene_firma' => !empty($usuario->firma),
                    'firma_preview' => $this->getFirmaPreview($usuario->firma)
                ],
                'message' => 'Firma actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error subiendo firma',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * ✅ ELIMINAR FIRMA DE MÉDICO
     */
    public function eliminarFirma(string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            $usuario->firma = null;
            $usuario->save();

            return response()->json([
                'success' => true,
                'message' => 'Firma eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando firma'
            ], 500);
        }
    }

    /**
     * ✅ OBTENER FIRMA DE MÉDICO
     */
    public function obtenerFirma(string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            if (empty($usuario->firma)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene firma registrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'firma' => $usuario->firma,
                    'tipo' => $this->detectarTipoFirma($usuario->firma)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo firma'
            ], 500);
        }
    }

    /**
     * Cambiar estado del usuario
     */
    public function cambiarEstado(Request $request, string $uuid): JsonResponse
    {
        try {
            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            $validator = Validator::make($request->all(), [
                'estado_id' => 'required|exists:estados,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $usuario->estado_id = $request->estado_id;
            $usuario->save();

            $usuario->load('estado');

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $usuario->uuid,
                    'estado' => [
                        'id' => $usuario->estado->id,
                        'nombre' => $usuario->estado->nombre
                    ]
                ],
                'message' => 'Estado actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error cambiando estado'
            ], 500);
        }
    }

    /**
     * ✅ MÉTODOS PRIVADOS PARA PROCESAR FIRMA
     */
    private function procesarFirma(Request $request): ?string
    {
        // Opción 1: Firma como base64 (recomendado para canvas)
        if ($request->filled('firma')) {
            $firmaBase64 = $request->firma;
            
            // Validar que sea base64 válido
            if ($this->esBase64Valido($firmaBase64)) {
                return $firmaBase64;
            }
        }

        // Opción 2: Firma como archivo subido
        if ($request->hasFile('firma_file')) {
            $file = $request->file('firma_file');
            
            if ($file->isValid()) {
                // Leer el archivo y convertir a base64
                $imageData = file_get_contents($file->getRealPath());
                $base64 = base64_encode($imageData);
                $mimeType = $file->getMimeType();
                
                return "data:{$mimeType};base64,{$base64}";
            }
        }

        return null;
    }

    private function esBase64Valido(string $data): bool
    {
        // Verificar si tiene el formato data:image/...;base64,...
        if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $data)) {
            return true;
        }

        // Verificar si es base64 puro
        if (base64_encode(base64_decode($data, true)) === $data) {
            return true;
        }

        return false;
    }

    private function detectarTipoFirma(string $firma): string
    {
        if (strpos($firma, 'data:image/png') !== false) {
            return 'image/png';
        } elseif (strpos($firma, 'data:image/jpeg') !== false || strpos($firma, 'data:image/jpg') !== false) {
            return 'image/jpeg';
        }
        
        return 'unknown';
    }

    private function getFirmaPreview(string $firma): string
    {
        // Retornar los primeros 100 caracteres para preview
        return substr($firma, 0, 100) . '...';
    }

    private function esMedico(int $rolId): bool
    {
        $rol = \App\Models\Rol::find($rolId);
        return $rol && strtoupper($rol->nombre) === 'MEDICO';
    }

    /**
     * Formatear datos del usuario para respuesta
     */
    private function formatUsuario(Usuario $usuario, bool $incluirFirma = false): array
    {
        $data = [
            'id' => $usuario->id,
            'uuid' => $usuario->uuid,
            'documento' => $usuario->documento,
            'nombre' => $usuario->nombre,
            'apellido' => $usuario->apellido,
            'nombre_completo' => $usuario->nombre_completo,
            'telefono' => $usuario->telefono,
            'correo' => $usuario->correo,
            'login' => $usuario->login,
            'registro_profesional' => $usuario->registro_profesional,
            
            'sede' => [
                'id' => $usuario->sede->id,
                'uuid' => $usuario->sede->uuid,
                'nombre' => $usuario->sede->nombre
            ],
            
            'rol' => [
                'id' => $usuario->rol->id,
                'uuid' => $usuario->rol->uuid,
                'nombre' => $usuario->rol->nombre
            ],
            
            'estado' => [
                'id' => $usuario->estado->id,
                'uuid' => $usuario->estado->uuid,
                'nombre' => $usuario->estado->nombre
            ],
            
            'especialidad' => $usuario->especialidad ? [
                'id' => $usuario->especialidad->id,
                'uuid' => $usuario->especialidad->uuid,
                'nombre' => $usuario->especialidad->nombre
            ] : null,
            
            'tiene_firma' => !empty($usuario->firma),
            'es_medico' => $usuario->esMedico(),
            'permisos' => $usuario->permisos,
            
            'created_at' => $usuario->created_at?->toISOString(),
            'updated_at' => $usuario->updated_at?->toISOString()
        ];

        // ✅ Incluir firma completa solo si se solicita explícitamente
        if ($incluirFirma && !empty($usuario->firma)) {
            $data['firma'] = $usuario->firma;
        }

        return $data;
    }
}
