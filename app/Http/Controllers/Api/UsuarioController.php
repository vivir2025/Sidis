<?php
// app/Http/Controllers/Api/UsuarioController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Hash, DB, Validator, Storage, Log};
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
            Log::info('📋 [API] Listando usuarios', [
                'filtros' => $request->all()
            ]);

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

            // ✅ Filtro por especialidad (aceptar UUID)
            if ($request->filled('especialidad_id')) {
                $especialidadId = $this->obtenerIdDesdeUuid(
                    'especialidades', 
                    $request->especialidad_id
                );
                
                if ($especialidadId) {
                    $query->where('especialidad_id', $especialidadId);
                }
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

            // ✅ NUEVO: Verificar si se solicitan TODOS los registros
            if ($request->get('all') === 'true' || $request->get('all') === true || $request->boolean('all')) {
                // Devolver todos los registros sin paginación
                $usuarios = $query->orderBy('nombre')->get();
                
                Log::info('✅ [API] Todos los usuarios obtenidos exitosamente', [
                    'total' => $usuarios->count()
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $usuarios->map(function ($usuario) {
                        return $this->formatUsuario($usuario);
                    }),
                    'message' => 'Usuarios obtenidos exitosamente',
                    'total' => $usuarios->count()
                ]);
            }

            $perPage = $request->input('per_page', 15);
            $usuarios = $query->orderBy('nombre')->paginate($perPage);

            Log::info('✅ [API] Usuarios listados exitosamente', [
                'total' => $usuarios->total(),
                'pagina' => $usuarios->currentPage()
            ]);

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
            Log::error('❌ [API] Error listando usuarios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
            Log::info('📝 [API] Iniciando creación de usuario', [
                'login' => $request->login,
                'documento' => $request->documento,
                'tiene_firma' => $request->filled('firma'),
                'longitud_firma' => $request->filled('firma') ? strlen($request->firma) : 0,
                'tiene_archivo_firma' => $request->hasFile('firma_file')
            ]);

            // ✅ VALIDACIÓN ACTUALIZADA PARA ACEPTAR UUID
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
                'especialidad_id' => 'nullable|string|exists:especialidades,uuid',
                'registro_profesional' => 'nullable|string|max:50',
                'firma' => 'nullable|string',
                'firma_file' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
            ]);

            if ($validator->fails()) {
                Log::warning('⚠️ [API] Errores de validación al crear usuario', [
                    'errores' => $validator->errors()->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // ✅ CONVERTIR UUID A ID SI SE PROPORCIONA ESPECIALIDAD
            $especialidadId = null;
            if ($request->filled('especialidad_id')) {
                Log::info('🔄 [API] Convirtiendo UUID de especialidad a ID', [
                    'uuid' => $request->especialidad_id
                ]);

                $especialidadId = $this->obtenerIdDesdeUuid(
                    'especialidades', 
                    $request->especialidad_id
                );
                
                if (!$especialidadId) {
                    Log::error('❌ [API] Especialidad no encontrada', [
                        'uuid' => $request->especialidad_id
                    ]);

                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Especialidad no encontrada'
                    ], 404);
                }

                Log::info('✅ [API] UUID convertido exitosamente', [
                    'uuid' => $request->especialidad_id,
                    'id' => $especialidadId
                ]);
            }

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
                'especialidad_id' => $especialidadId,
                'registro_profesional' => $request->registro_profesional,
            ];

            // Procesar firma si es médico
            if ($this->esMedico($request->rol_id)) {
                Log::info('👨‍⚕️ [API] Usuario es médico, procesando firma', [
                    'rol_id' => $request->rol_id
                ]);

                $firmaData = $this->procesarFirma($request);
                
                if ($firmaData) {
                    $userData['firma'] = $firmaData;
                    Log::info('✅ [API] Firma procesada exitosamente', [
                        'longitud' => strlen($firmaData),
                        'tipo' => $this->detectarTipoFirma($firmaData)
                    ]);
                } else {
                    Log::warning('⚠️ [API] No se pudo procesar la firma');
                }
            } else {
                Log::info('ℹ️ [API] Usuario no es médico, omitiendo firma', [
                    'rol_id' => $request->rol_id
                ]);
            }

            // Crear usuario
            $usuario = Usuario::create($userData);

            Log::info('✅ [API] Usuario creado exitosamente', [
                'usuario_id' => $usuario->id,
                'uuid' => $usuario->uuid,
                'login' => $usuario->login,
                'tiene_firma' => !empty($usuario->firma)
            ]);

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
            
            Log::error('❌ [API] Error creando usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
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
        Log::info('🔍 [API] Buscando usuario', ['uuid' => $uuid]);

        $usuario = Usuario::with(['sede', 'rol', 'especialidad', 'estado'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        Log::info('✅ [API] Usuario encontrado', [
            'uuid' => $uuid,
            'login' => $usuario->login,
            'tiene_firma' => !empty($usuario->firma),
            'longitud_firma' => !empty($usuario->firma) ? strlen($usuario->firma) : 0
        ]);

        // ✅ SIEMPRE INCLUIR LA FIRMA EN LA VISTA DE DETALLE
        return response()->json([
            'success' => true,
            'data' => $this->formatUsuario($usuario, true) // 👈 true para incluir firma
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        Log::warning('⚠️ [API] Usuario no encontrado', ['uuid' => $uuid]);

        return response()->json([
            'success' => false,
            'message' => 'Usuario no encontrado'
        ], 404);

    } catch (\Exception $e) {
        Log::error('❌ [API] Error al mostrar usuario', [
            'uuid' => $uuid,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
}


    /**
     * Actualizar usuario
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            Log::info('📝 [API] Iniciando actualización de usuario', [
                'uuid' => $uuid,
                'campos_a_actualizar' => array_keys($request->except(['_token', '_method'])),
                'tiene_firma' => $request->filled('firma'),
                'eliminar_firma' => $request->boolean('eliminar_firma')
            ]);

            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            // ✅ VALIDACIÓN ACTUALIZADA PARA ACEPTAR UUID
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
                'especialidad_id' => 'nullable|string|exists:especialidades,uuid',
                'registro_profesional' => 'nullable|string|max:50',
                'firma' => 'nullable|string',
                'firma_file' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
                'eliminar_firma' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                Log::warning('⚠️ [API] Errores de validación al actualizar usuario', [
                    'uuid' => $uuid,
                    'errores' => $validator->errors()->toArray()
                ]);

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
            if ($request->filled('registro_profesional')) $usuario->registro_profesional = $request->registro_profesional;

            // ✅ CONVERTIR UUID A ID SI SE ACTUALIZA ESPECIALIDAD
            if ($request->filled('especialidad_id')) {
                Log::info('🔄 [API] Actualizando especialidad', [
                    'uuid_especialidad' => $request->especialidad_id
                ]);

                $especialidadId = $this->obtenerIdDesdeUuid(
                    'especialidades', 
                    $request->especialidad_id
                );
                
                if (!$especialidadId) {
                    Log::error('❌ [API] Especialidad no encontrada en actualización', [
                        'uuid' => $request->especialidad_id
                    ]);

                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Especialidad no encontrada'
                    ], 404);
                }
                
                $usuario->especialidad_id = $especialidadId;
                Log::info('✅ [API] Especialidad actualizada', ['id' => $especialidadId]);
            }

            // Actualizar password si se proporciona
            if ($request->filled('password')) {
                $usuario->password = Hash::make($request->password);
                Log::info('🔐 [API] Contraseña actualizada');
            }

            // Procesar firma
            if ($request->boolean('eliminar_firma')) {
                Log::info('🗑️ [API] Eliminando firma del usuario', ['uuid' => $uuid]);
                $usuario->firma = null;
            } elseif ($this->esMedico($usuario->rol_id)) {
                Log::info('👨‍⚕️ [API] Usuario es médico, procesando firma en actualización');
                
                $firmaData = $this->procesarFirma($request);
                if ($firmaData) {
                    $usuario->firma = $firmaData;
                    Log::info('✅ [API] Firma actualizada exitosamente', [
                        'longitud' => strlen($firmaData)
                    ]);
                }
            }

            $usuario->save();

            Log::info('✅ [API] Usuario actualizado exitosamente', [
                'uuid' => $uuid,
                'tiene_firma' => !empty($usuario->firma)
            ]);

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
            
            Log::error('❌ [API] Error actualizando usuario', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando usuario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO MÉTODO: Convertir UUID a ID
     */
    private function obtenerIdDesdeUuid(string $tabla, string $uuid): ?int
    {
        try {
            Log::info('🔄 [API] Convirtiendo UUID a ID', [
                'tabla' => $tabla,
                'uuid' => $uuid
            ]);

            $resultado = DB::table($tabla)
                ->where('uuid', $uuid)
                ->first(['id']);
            
            if ($resultado) {
                Log::info('✅ [API] UUID convertido exitosamente', [
                    'tabla' => $tabla,
                    'uuid' => $uuid,
                    'id' => $resultado->id
                ]);
                return $resultado->id;
            }

            Log::warning('⚠️ [API] UUID no encontrado en tabla', [
                'tabla' => $tabla,
                'uuid' => $uuid
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('❌ [API] Error convirtiendo UUID a ID', [
                'tabla' => $tabla,
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            Log::info('🗑️ [API] Eliminando usuario', ['uuid' => $uuid]);

            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            if ($usuario->agendas()->exists() || $usuario->citasCreadas()->exists()) {
                Log::warning('⚠️ [API] No se puede eliminar usuario con registros asociados', [
                    'uuid' => $uuid
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el usuario porque tiene registros asociados'
                ], 400);
            }

            $usuario->delete();

            Log::info('✅ [API] Usuario eliminado exitosamente', ['uuid' => $uuid]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [API] Error eliminando usuario', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

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
            Log::info('📝 [API] Subiendo firma para usuario', [
                'uuid' => $uuid,
                'tiene_firma' => $request->filled('firma'),
                'longitud' => $request->filled('firma') ? strlen($request->firma) : 0
            ]);

            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            if (!$this->esMedico($usuario->rol_id)) {
                Log::warning('⚠️ [API] Usuario no es médico', [
                    'uuid' => $uuid,
                    'rol_id' => $usuario->rol_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Solo los médicos pueden tener firma digital'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'firma' => 'required|string',
            ]);

            if ($validator->fails()) {
                Log::warning('⚠️ [API] Validación fallida al subir firma', [
                    'errores' => $validator->errors()->toArray()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $firmaData = $this->procesarFirma($request);
            
            if (!$firmaData) {
                Log::error('❌ [API] Error procesando firma');
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error procesando la firma'
                ], 400);
            }

            $usuario->firma = $firmaData;
            $usuario->save();

            Log::info('✅ [API] Firma subida exitosamente', [
                'uuid' => $uuid,
                'longitud_firma' => strlen($firmaData)
            ]);

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
            Log::error('❌ [API] Error subiendo firma', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
            Log::info('🗑️ [API] Eliminando firma', ['uuid' => $uuid]);

            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            $usuario->firma = null;
            $usuario->save();

            Log::info('✅ [API] Firma eliminada exitosamente', ['uuid' => $uuid]);

            return response()->json([
                'success' => true,
                'message' => 'Firma eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [API] Error eliminando firma', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

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
            Log::info('🔍 [API] Obteniendo firma', ['uuid' => $uuid]);

            $usuario = Usuario::where('uuid', $uuid)->firstOrFail();

            if (empty($usuario->firma)) {
                Log::warning('⚠️ [API] Usuario sin firma', ['uuid' => $uuid]);

                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene firma registrada'
                ], 404);
            }

            Log::info('✅ [API] Firma obtenida exitosamente', [
                'uuid' => $uuid,
                'longitud' => strlen($usuario->firma)
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'firma' => $usuario->firma,
                    'tipo' => $this->detectarTipoFirma($usuario->firma)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [API] Error obteniendo firma', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

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
            Log::info('🔄 [API] Cambiando estado de usuario', [
                'uuid' => $uuid,
                'nuevo_estado_id' => $request->estado_id
            ]);

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

            Log::info('✅ [API] Estado cambiado exitosamente', [
                'uuid' => $uuid,
                'nuevo_estado' => $usuario->estado->nombre
            ]);

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
            Log::error('❌ [API] Error cambiando estado', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

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
        Log::info('🖼️ [API] Procesando firma', [
            'tiene_firma_base64' => $request->filled('firma'),
            'tiene_archivo' => $request->hasFile('firma_file')
        ]);

        if ($request->filled('firma')) {
            $firmaBase64 = $request->firma;
            
            Log::info('📊 [API] Analizando firma base64', [
                'longitud_original' => strlen($firmaBase64),
                'primeros_50_chars' => substr($firmaBase64, 0, 50)
            ]);
            
            if ($this->esBase64Valido($firmaBase64)) {
                Log::info('✅ [API] Firma base64 válida');
                return $firmaBase64;
            } else {
                Log::warning('⚠️ [API] Firma base64 no válida');
            }
        }

        if ($request->hasFile('firma_file')) {
            $file = $request->file('firma_file');
            
            Log::info('📁 [API] Procesando archivo de firma', [
                'nombre' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'tamaño' => $file->getSize()
            ]);
            
            if ($file->isValid()) {
                $imageData = file_get_contents($file->getRealPath());
                $base64 = base64_encode($imageData);
                $mimeType = $file->getMimeType();
                
                $firmaCompleta = "data:{$mimeType};base64,{$base64}";
                
                Log::info('✅ [API] Archivo de firma procesado exitosamente', [
                    'longitud_final' => strlen($firmaCompleta)
                ]);
                
                return $firmaCompleta;
            } else {
                Log::error('❌ [API] Archivo de firma no válido');
            }
        }

        Log::warning('⚠️ [API] No se pudo procesar ninguna firma');
        return null;
    }

        private function esBase64Valido(string $data): bool
    {
        Log::info('🔍 [API] Validando base64', [
            'longitud' => strlen($data),
            'tiene_prefijo_data' => strpos($data, 'data:image/') === 0
        ]);

        // Verificar si tiene el prefijo data:image/
        if (preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $data)) {
            Log::info('✅ [API] Base64 válido con prefijo data:image');
            return true;
        }

        // Verificar si es base64 puro
        if (base64_encode(base64_decode($data, true)) === $data) {
            Log::info('✅ [API] Base64 puro válido');
            return true;
        }

        Log::warning('⚠️ [API] Base64 no válido');
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
        return substr($firma, 0, 100) . '...';
    }

    private function esMedico(int $rolId): bool
    {
        $rol = \App\Models\Rol::find($rolId);
        $esMedico = $rol && strtoupper($rol->nombre) === 'PROFESIONAL EN SALUD';
        
        Log::info('👨‍⚕️ [API] Verificando si es médico', [
            'rol_id' => $rolId,
            'rol_nombre' => $rol ? $rol->nombre : 'no encontrado',
            'es_medico' => $esMedico
        ]);
        
        return $esMedico;
    }
    

    /**
     * Formatear datos del usuario para respuesta
     */
    /**
 * Formatear datos del usuario para la respuesta
 */
private function formatUsuario(Usuario $usuario, bool $incluirFirma = false): array
{
    $data = [
        'uuid' => $usuario->uuid,
        'documento' => $usuario->documento,
        'nombre' => $usuario->nombre,
        'apellido' => $usuario->apellido,
        'nombre_completo' => trim($usuario->nombre . ' ' . $usuario->apellido),
        'telefono' => $usuario->telefono,
        'correo' => $usuario->correo,
        'login' => $usuario->login,
        'ultimo_acceso' => $usuario->ultimo_acceso 
            ? \Carbon\Carbon::parse($usuario->ultimo_acceso)->format('d/m/Y H:i:s')
            : null,
        
        // Relaciones
        'sede' => $usuario->sede ? [
            'uuid' => $usuario->sede->uuid,
            'nombre' => $usuario->sede->nombre,
        ] : null,
        
        'rol' => $usuario->rol ? [
            'id' => $usuario->rol->id,
            'nombre' => $usuario->rol->nombre,
        ] : null,
        
        'estado' => $usuario->estado ? [
            'id' => $usuario->estado->id,
            'nombre' => $usuario->estado->nombre,
        ] : null,
        
        'especialidad' => $usuario->especialidad ? [
            'uuid' => $usuario->especialidad->uuid,
            'nombre' => $usuario->especialidad->nombre,
        ] : null,
        
        // Datos profesionales
        'es_medico' => $usuario->rol_id == 2, // Ajusta según tu lógica
        'registro_profesional' => $usuario->registro_profesional,
        
        // Firma digital
        'tiene_firma' => !empty($usuario->firma),
        
        // Timestamps
        'created_at' => $usuario->created_at?->format('Y-m-d H:i:s'),
        'updated_at' => $usuario->updated_at?->format('Y-m-d H:i:s'),
    ];

    // ✅ INCLUIR LA FIRMA SOLO SI SE SOLICITA
    if ($incluirFirma && !empty($usuario->firma)) {
        // Verificar si la firma ya tiene el prefijo data:image
        if (strpos($usuario->firma, 'data:image/') === 0) {
            // Ya tiene el prefijo, usar tal cual
            $data['firma'] = $usuario->firma;
            
            Log::info('✅ [API] Firma con prefijo existente', [
                'uuid' => $usuario->uuid,
                'longitud' => strlen($usuario->firma),
                'prefijo' => substr($usuario->firma, 0, 30)
            ]);
        } else {
            // No tiene prefijo, agregarlo
            $data['firma'] = 'data:image/png;base64,' . $usuario->firma;
            
            Log::info('✅ [API] Prefijo agregado a la firma', [
                'uuid' => $usuario->uuid,
                'longitud_original' => strlen($usuario->firma),
                'longitud_con_prefijo' => strlen($data['firma'])
            ]);
        }
    } else {
        Log::info('ℹ️ [API] Firma no incluida', [
            'uuid' => $usuario->uuid,
            'incluir_firma' => $incluirFirma,
            'tiene_firma' => !empty($usuario->firma)
        ]);
    }

    return $data;
}

/**
 * Obtener todos los usuarios para sincronización
 */
public function getAllForSync(Request $request)
{
    try {
        $query = Usuario::with(['sede', 'rol', 'especialidad', 'estado']);

        // Filtrar por sede si se especifica
        if ($request->has('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        $usuarios = $query->get()->map(function ($usuario) {
            return [
                'id' => $usuario->id,
                'uuid' => $usuario->uuid,
                'documento' => $usuario->documento,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'nombre_completo' => $usuario->nombre_completo,
                'correo' => $usuario->correo,
                'telefono' => $usuario->telefono,
                'login' => $usuario->login,
                'sede_id' => $usuario->sede_id,
                'sede' => $usuario->sede,
                'rol_id' => $usuario->rol_id,
                'rol' => $usuario->rol,
                'especialidad_id' => $usuario->especialidad_id,
                'especialidad' => $usuario->especialidad,
                'estado_id' => $usuario->estado_id,
                'estado' => $usuario->estado,
                'permisos' => $usuario->permisos ?? [],
                'tipo_usuario' => $usuario->tipo_usuario ?? [],
                'es_medico' => $usuario->es_medico ?? false,
                'registro_profesional' => $usuario->registro_profesional,
                'firma' => $usuario->firma,              // ✅ INCLUIR FIRMA
                'tiene_firma' => $usuario->tiene_firma ?? 0,
                'created_at' => $usuario->created_at,
                'updated_at' => $usuario->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $usuarios,
            'total' => $usuarios->count()
        ]);

    } catch (\Exception $e) {
        Log::error('Error obteniendo usuarios para sincronización', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Error obteniendo usuarios'
        ], 500);
    }
}


    
}
