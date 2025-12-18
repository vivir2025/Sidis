<?php
// app/Http/Controllers/Api/PacienteController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Paciente;
use App\Http\Resources\PacienteResource;
use App\Http\Requests\{StorePacienteRequest, UpdatePacienteRequest};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PacienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Paciente::with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            // ✅ CAMBIO PRINCIPAL: Filtro opcional por sede (igual que AgendaController)
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            // Filtros opcionales existentes
            if ($request->filled('documento')) {
                $query->where('documento', 'like', '%' . $request->documento . '%');
            }

            if ($request->filled('nombre')) {
                $query->where(function ($q) use ($request) {
                    $q->where('primer_nombre', 'like', '%' . $request->nombre . '%')
                      ->orWhere('segundo_nombre', 'like', '%' . $request->nombre . '%')
                      ->orWhere('primer_apellido', 'like', '%' . $request->nombre . '%')
                      ->orWhere('segundo_apellido', 'like', '%' . $request->nombre . '%');
                });
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('sexo')) {
                $query->where('sexo', $request->sexo);
            }

            // ✅ BÚSQUEDA GENERAL MEJORADA (similar a AgendaController)
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('documento', 'like', "%{$search}%")
                      ->orWhere('primer_nombre', 'like', "%{$search}%")
                      ->orWhere('segundo_nombre', 'like', "%{$search}%")
                      ->orWhere('primer_apellido', 'like', "%{$search}%")
                      ->orWhere('segundo_apellido', 'like', "%{$search}%")
                      ->orWhere('telefono', 'like', "%{$search}%")
                      ->orWhere('correo', 'like', "%{$search}%");
                });
            }

            // ✅ ORDENAMIENTO MEJORADO (similar a AgendaController)
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSortFields = ['primer_nombre', 'primer_apellido', 'documento', 'fecha_nacimiento', 'created_at', 'fecha_registro'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            
            $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
            
            if ($sortBy === 'primer_nombre') {
                $query->orderBy('primer_nombre', $sortOrder)
                      ->orderBy('primer_apellido', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder)
                      ->orderBy('created_at', 'desc');
            }

            // ✅ NUEVO: Verificar si se solicitan TODOS los registros
            if ($request->get('all') === 'true' || $request->get('all') === true || $request->boolean('all')) {
                // Devolver todos los registros sin paginación
                $pacientes = $query->get();
                
                return response()->json([
                    'success' => true,
                    'data' => PacienteResource::collection($pacientes),
                    'message' => 'Pacientes obtenidos exitosamente',
                    'total' => $pacientes->count()
                ]);
            }

            // ✅ PAGINACIÓN MEJORADA
            $perPage = $request->get('per_page', 15);
            $perPage = max(5, min(100, (int) $perPage));
            
            $pacientes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => PacienteResource::collection($pacientes),
                'meta' => [
                    'current_page' => $pacientes->currentPage(),
                    'last_page' => $pacientes->lastPage(),
                    'per_page' => $pacientes->perPage(),
                    'total' => $pacientes->total()
                ],
                'message' => 'Pacientes obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo pacientes', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error obteniendo pacientes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('🏥 PacienteController@store - Datos recibidos', [
                'data' => $request->all(),
                'user_id' => auth()->id(),
                'user_sede' => auth()->user()->sede_id ?? 'NO_SEDE'
            ]);

            // ✅ VALIDACIÓN MEJORADA - Permitir sede_id opcional
            $validatedData = $request->validate([
                // ✅ NUEVO: Permitir especificar sede_id
                'sede_id' => 'nullable|exists:sedes,id',
                
                // Campos obligatorios
                'primer_nombre' => 'required|string|max:50',
                'primer_apellido' => 'required|string|max:50',
                'documento' => 'required|string|max:20',
                'fecha_nacimiento' => 'required|date|before:today',
                'sexo' => 'required|in:M,F',
                
                // Campos opcionales
                'segundo_nombre' => 'nullable|string|max:50',
                'segundo_apellido' => 'nullable|string|max:50',
                'direccion' => 'nullable|string|max:255',
                'telefono' => 'nullable|string|max:50',
                'correo' => 'nullable|email|max:100',
                'estado_civil' => 'nullable|string|max:50',
                'observacion' => 'nullable|string',
                'registro' => 'nullable|string|max:50',
                'estado' => 'nullable|in:ACTIVO,INACTIVO',
                
                // IDs de relaciones (UUIDs como strings)
                'tipo_documento_id' => 'nullable|string',
                'empresa_id' => 'nullable|string',
                'regimen_id' => 'nullable|string',
                'tipo_afiliacion_id' => 'nullable|string',
                'zona_residencia_id' => 'nullable|string',
                'depto_nacimiento_id' => 'nullable|string',
                'depto_residencia_id' => 'nullable|string',
                'municipio_nacimiento_id' => 'nullable|string',
                'municipio_residencia_id' => 'nullable|string',
                'raza_id' => 'nullable|string',
                'escolaridad_id' => 'nullable|string',
                'parentesco_id' => 'nullable|string',
                'ocupacion_id' => 'nullable|string',
                'novedad_id' => 'nullable|string',
                'auxiliar_id' => 'nullable|string',
                'brigada_id' => 'nullable|string',
                
                // Campos adicionales
                'nombre_acudiente' => 'nullable|string|max:100',
                'parentesco_acudiente' => 'nullable|string|max:50',
                'telefono_acudiente' => 'nullable|string|max:50',
                'direccion_acudiente' => 'nullable|string|max:255',
                'acompanante_nombre' => 'nullable|string|max:100',
                'acompanante_telefono' => 'nullable|string|max:50'
            ]);

            // ✅ PROCESAR DATOS PARA GUARDAR
            $pacienteData = $this->procesarDatosParaGuardar($validatedData, $request);

            Log::info('📝 Datos procesados para guardar', [
                'data' => $pacienteData,
                'sede_id_final' => $pacienteData['sede_id']
            ]);

            // ✅ CREAR PACIENTE
            $paciente = Paciente::create($pacienteData);

            // ✅ CARGAR RELACIONES
            $paciente->load([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            Log::info('✅ Paciente creado exitosamente', [
                'id' => $paciente->id,
                'uuid' => $paciente->uuid,
                'documento' => $paciente->documento,
                'sede_id' => $paciente->sede_id
            ]);

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente),
                'message' => 'Paciente creado exitosamente'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('❌ Errores de validación', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('💥 Error creando paciente', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    private function procesarDatosParaGuardar(array $validatedData, Request $request): array
    {
        $user = auth()->user();
        
        // ✅ CAMBIO: Usar sede del request o del usuario como fallback
        $sedeId = $validatedData['sede_id'] ?? $user->sede_id ?? 1;
        
        $data = [
            'uuid' => \Str::uuid(),
            'sede_id' => $sedeId, // ✅ CAMBIO AQUÍ
            'fecha_registro' => now(),
            'estado' => $validatedData['estado'] ?? 'ACTIVO',
            
            // ✅ CAMPOS BÁSICOS
            'registro' => $validatedData['registro'] ?? $this->generateRegistro($sedeId),
            'primer_nombre' => $validatedData['primer_nombre'],
            'segundo_nombre' => $validatedData['segundo_nombre'],
            'primer_apellido' => $validatedData['primer_apellido'],
            'segundo_apellido' => $validatedData['segundo_apellido'],
            'documento' => $validatedData['documento'],
            'fecha_nacimiento' => $validatedData['fecha_nacimiento'],
            'sexo' => $validatedData['sexo'],
            'direccion' => $validatedData['direccion'] ?? '',
            'telefono' => $validatedData['telefono'] ?? '',
            'correo' => $validatedData['correo'],
            'observacion' => $validatedData['observacion'],
            'estado_civil' => $validatedData['estado_civil'] ?? '',
            
            // ✅ CAMPOS ADICIONALES
            'nombre_acudiente' => $validatedData['nombre_acudiente'],
            'parentesco_acudiente' => $validatedData['parentesco_acudiente'],
            'telefono_acudiente' => $validatedData['telefono_acudiente'],
            'direccion_acudiente' => $validatedData['direccion_acudiente'],
            'acompanante_nombre' => $validatedData['acompanante_nombre'],
            'acompanante_telefono' => $validatedData['acompanante_telefono']
        ];

        // ✅ CONVERTIR UUIDs A IDs USANDO MÉTODO HELPER
        $relationMappings = [
            'tipo_documento_id' => 'tipos_documento',
            'empresa_id' => 'empresas',
            'regimen_id' => 'regimenes',
            'tipo_afiliacion_id' => 'tipos_afiliacion',
            'zona_residencia_id' => 'zonas_residenciales',
            'depto_nacimiento_id' => 'departamentos',
            'depto_residencia_id' => 'departamentos',
            'municipio_nacimiento_id' => 'municipios',
            'municipio_residencia_id' => 'municipios',
            'raza_id' => 'razas',
            'escolaridad_id' => 'escolaridades',
            'parentesco_id' => 'tipos_parentesco',
            'ocupacion_id' => 'ocupaciones',
            'novedad_id' => 'novedades',
            'auxiliar_id' => 'auxiliares',
            'brigada_id' => 'brigadas'
        ];

        foreach ($relationMappings as $field => $table) {
            if (!empty($validatedData[$field])) {
                $id = $this->convertUuidToId($validatedData[$field], $table);
                $data[$field] = $id;
                
                Log::info("🔄 Conversión UUID->ID", [
                    'field' => $field,
                    'uuid' => $validatedData[$field],
                    'id' => $id,
                    'table' => $table
                ]);
            }
        }

        return $data;
    }

    private function convertUuidToId(?string $uuid, string $table): ?int
    {
        if (!$uuid) return null;

        try {
            // ✅ VERIFICAR SI YA ES UN ID NUMÉRICO
            if (is_numeric($uuid)) {
                return (int) $uuid;
            }

            // ✅ BUSCAR POR UUID
            $id = DB::table($table)->where('uuid', $uuid)->value('id');
            
            if (!$id) {
                Log::warning("⚠️ UUID no encontrado en tabla", [
                    'uuid' => $uuid,
                    'table' => $table
                ]);
                return null;
            }

            return (int) $id;
        } catch (\Exception $e) {
            Log::error("❌ Error convirtiendo UUID a ID", [
                'uuid' => $uuid,
                'table' => $table,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            Log::info('🔍 PacienteController@show - Debug', [
                'uuid' => $uuid,
                'user_authenticated' => auth()->check(),
                'user_id' => auth()->id(),
                'user_sede' => auth()->user()->sede_id ?? 'NO_SEDE',
            ]);

            // ✅ CAMBIO: Sin filtro automático de sede
            $query = Paciente::where('uuid', $uuid);
            
            // ✅ OPCIONAL: Filtrar por sede solo si se especifica en la request
            // if ($request->filled('sede_id')) {
            //     $query->where('sede_id', $request->sede_id);
            // }

            $paciente = $query->with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion','novedad','auxiliar','brigada'
            ])->first();

            if (!$paciente) {
                Log::warning('❌ Paciente no encontrado', [
                    'uuid' => $uuid
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado'
                ], 404);
            }

            Log::info('✅ Paciente encontrado exitosamente', [
                'uuid' => $uuid,
                'documento' => $paciente->documento
            ]);

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente),
                'message' => 'Paciente obtenido exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Error en show()', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdatePacienteRequest $request, string $uuid): JsonResponse
    {
        try {
            Log::info('🔄 PacienteController@update - Iniciando', [
                'uuid' => $uuid,
                'data_received' => $request->all(),
                'user_id' => auth()->id()
            ]);

            // ✅ CAMBIO: Sin filtro automático de sede
            $query = Paciente::where('uuid', $uuid);
            
            $paciente = $query->first();
            
            if (!$paciente) {
                Log::warning('❌ Paciente no encontrado para actualizar', [
                    'uuid' => $uuid
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado'
                ], 404);
            }
            
            $validatedData = $request->validated();
            $processedData = $this->procesarDatosParaActualizar($validatedData, $request);
            
            Log::info('📝 Datos procesados para actualizar', [
                'uuid' => $uuid,
                'original_data_sample' => [
                    'primer_nombre' => $paciente->primer_nombre,
                    'documento' => $paciente->documento
                ],
                'processed_data_sample' => [
                    'primer_nombre' => $processedData['primer_nombre'] ?? 'no-change',
                    'documento' => $processedData['documento'] ?? 'no-change'
                ]
            ]);
            
            $paciente->update($processedData);
            
            $paciente->load([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            Log::info('✅ Paciente actualizado exitosamente', [
                'uuid' => $uuid,
                'id' => $paciente->id,
                'documento' => $paciente->documento
            ]);

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente),
                'message' => 'Paciente actualizado exitosamente'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Errores de validación en update', [
                'uuid' => $uuid,
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('💥 Error actualizando paciente', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error actualizando paciente: ' . $e->getMessage()
            ], 500);
        }
    }

    private function procesarDatosParaActualizar(array $validatedData, Request $request): array
    {
        $data = [
            'fecha_actualizacion' => now(),
            
            // ✅ CAMPOS BÁSICOS (solo los que vienen en la request)
            'sede_id' => $validatedData['sede_id'] ?? null, // ✅ NUEVO: Permitir cambiar sede
            'primer_nombre' => $validatedData['primer_nombre'] ?? null,
            'segundo_nombre' => $validatedData['segundo_nombre'] ?? null,
            'primer_apellido' => $validatedData['primer_apellido'] ?? null,
            'segundo_apellido' => $validatedData['segundo_apellido'] ?? null,
            'documento' => $validatedData['documento'] ?? null,
            'fecha_nacimiento' => $validatedData['fecha_nacimiento'] ?? null,
            'sexo' => $validatedData['sexo'] ?? null,
            'direccion' => $validatedData['direccion'] ?? null,
            'telefono' => $validatedData['telefono'] ?? null,
            'correo' => $validatedData['correo'] ?? null,
            'observacion' => $validatedData['observacion'] ?? null,
            'estado_civil' => $validatedData['estado_civil'] ?? null,
            'estado' => $validatedData['estado'] ?? null,
            'registro' => $validatedData['registro'] ?? null,
            
            // ✅ CAMPOS ADICIONALES
            'nombre_acudiente' => $validatedData['nombre_acudiente'] ?? null,
            'parentesco_acudiente' => $validatedData['parentesco_acudiente'] ?? null,
            'telefono_acudiente' => $validatedData['telefono_acudiente'] ?? null,
            'direccion_acudiente' => $validatedData['direccion_acudiente'] ?? null,
            'acompanante_nombre' => $validatedData['acompanante_nombre'] ?? null,
            'acompanante_telefono' => $validatedData['acompanante_telefono'] ?? null
        ];

        // ✅ LIMPIAR CAMPOS NULL
        $data = array_filter($data, function($value) {
            return $value !== null;
        });

        // ✅ CONVERTIR UUIDs A IDs (IGUAL QUE EN STORE)
        $relationMappings = [
            'tipo_documento_id' => 'tipos_documento',
            'empresa_id' => 'empresas',
            'regimen_id' => 'regimenes',
            'tipo_afiliacion_id' => 'tipos_afiliacion',
            'zona_residencia_id' => 'zonas_residenciales',
            'depto_nacimiento_id' => 'departamentos',
            'depto_residencia_id' => 'departamentos',
            'municipio_nacimiento_id' => 'municipios',
            'municipio_residencia_id' => 'municipios',
            'raza_id' => 'razas',
            'escolaridad_id' => 'escolaridades',
            'parentesco_id' => 'tipos_parentesco',
            'ocupacion_id' => 'ocupaciones',
            'novedad_id' => 'novedades',
            'auxiliar_id' => 'auxiliares',
            'brigada_id' => 'brigadas'
        ];

        foreach ($relationMappings as $field => $table) {
            if (isset($validatedData[$field]) && !empty($validatedData[$field])) {
                $id = $this->convertUuidToId($validatedData[$field], $table);
                if ($id !== null) {
                    $data[$field] = $id;
                    
                    Log::info("🔄 Conversión UUID->ID (update)", [
                        'field' => $field,
                        'uuid' => $validatedData[$field],
                        'id' => $id,
                        'table' => $table
                    ]);
                }
            }
        }

        return $data;
    }

    public function destroy(string $uuid): JsonResponse
    {
        try {
            // ✅ CAMBIO: Sin filtro automático de sede
            $query = Paciente::where('uuid', $uuid);
            
            $paciente = $query->firstOrFail();
            $paciente->delete();

            return response()->json([
                'success' => true,
                'message' => 'Paciente eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando paciente: ' . $e->getMessage()
            ], 500);
        }
    }

    public function searchByDocument(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'documento' => 'required|string',
                'sede_id' => 'nullable|exists:sedes,id' // ✅ NUEVO: Permitir filtro opcional
            ]);

            $query = Paciente::where('documento', $request->documento);
            
            // ✅ CAMBIO: Filtro opcional de sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            $paciente = $query->with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ])->first();

            if (!$paciente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente),
                'message' => 'Paciente encontrado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error buscando paciente: ' . $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $query = Paciente::with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            // ✅ CAMBIO: Filtro opcional de sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            // Aplicar filtros de búsqueda existentes
            if ($request->filled('documento')) {
                $query->where('documento', 'like', '%' . $request->documento . '%');
            }

            if ($request->filled('nombre')) {
                $query->where(function ($q) use ($request) {
                    $q->where('primer_nombre', 'like', '%' . $request->nombre . '%')
                      ->orWhere('segundo_nombre', 'like', '%' . $request->nombre . '%')
                      ->orWhere('primer_apellido', 'like', '%' . $request->nombre . '%')
                      ->orWhere('segundo_apellido', 'like', '%' . $request->nombre . '%');
                });
            }

            if ($request->filled('telefono')) {
                $query->where('telefono', 'like', '%' . $request->telefono . '%');
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('sexo')) {
                $query->where('sexo', $request->sexo);
            }

            $pacientes = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => PacienteResource::collection($pacientes),
                'meta' => [
                    'current_page' => $pacientes->currentPage(),
                    'last_page' => $pacientes->lastPage(),
                    'per_page' => $pacientes->perPage(),
                    'total' => $pacientes->total()
                ],
                'message' => 'Búsqueda completada exitosamente'
                            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en búsqueda: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function test(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            return response()->json([
                'success' => true,
                'message' => 'Endpoint de pacientes funcionando correctamente',
                'timestamp' => now(),
                'user_info' => $user ? [
                    'id' => $user->id,
                    'sede_id' => $user->sede_id ?? 'NO_SEDE',
                    'email' => $user->email ?? 'NO_EMAIL'
                ] : 'NO_AUTH',
                'database_info' => [
                    'pacientes_count' => \App\Models\Paciente::count(),
                    'connection' => 'OK'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * ✅ Generar número de registro automático
     */
    private function generateRegistro(int $sedeId): string
    {
        try {
            $year = date('Y');
            $lastPaciente = Paciente::where('sede_id', $sedeId)
                ->whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->first();
            
            $nextNumber = $lastPaciente ? (intval(substr($lastPaciente->registro, -6)) + 1) : 1;
            
            return sprintf('REG%s%06d', $year, $nextNumber);
        } catch (\Exception $e) {
            Log::error('Error generando registro', ['error' => $e->getMessage()]);
            return 'REG' . $year . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        }
    }

    public function health(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'ok',
                    'timestamp' => now()->toISOString(),
                    'service' => 'SIDIS API',
                    'version' => '1.0.0',
                    'database' => 'connected'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    // ✅ NUEVOS MÉTODOS ÚTILES (similares a AgendaController)
    
    /**
     * Obtener pacientes por empresa
     */
    public function pacientesPorEmpresa(Request $request, string $empresaUuid): JsonResponse
    {
        try {
            $query = Paciente::with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            // Buscar empresa por UUID y filtrar pacientes
            $empresaId = $this->convertUuidToId($empresaUuid, 'empresas');
            if (!$empresaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada'
                ], 404);
            }

            $query->where('empresa_id', $empresaId);

            // ✅ Filtro opcional de sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            $pacientes = $query->orderBy('primer_nombre')
                             ->orderBy('primer_apellido')
                             ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => PacienteResource::collection($pacientes),
                'meta' => [
                    'current_page' => $pacientes->currentPage(),
                    'last_page' => $pacientes->lastPage(),
                    'per_page' => $pacientes->perPage(),
                    'total' => $pacientes->total()
                ],
                'message' => 'Pacientes de la empresa obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo pacientes por empresa', [
                'empresa_uuid' => $empresaUuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo pacientes de la empresa'
            ], 500);
        }
    }

    /**
     * Estadísticas de pacientes
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $query = Paciente::query();

            // ✅ Filtro opcional de sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            $stats = [
                'total_pacientes' => $query->count(),
                'pacientes_activos' => $query->where('estado', 'ACTIVO')->count(),
                'pacientes_inactivos' => $query->where('estado', 'INACTIVO')->count(),
                'por_sexo' => [
                    'masculino' => $query->where('sexo', 'M')->count(),
                    'femenino' => $query->where('sexo', 'F')->count()
                ],
                'registros_hoy' => $query->whereDate('created_at', today())->count(),
                'registros_este_mes' => $query->whereMonth('created_at', now()->month)
                                             ->whereYear('created_at', now()->year)
                                             ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de pacientes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo estadísticas'
            ], 500);
        }
    }

    /**
     * Buscar pacientes por múltiples criterios
     */
    public function busquedaAvanzada(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'documento' => 'nullable|string',
                'primer_nombre' => 'nullable|string',
                'primer_apellido' => 'nullable|string',
                'telefono' => 'nullable|string',
                'correo' => 'nullable|email',
                'sexo' => 'nullable|in:M,F',
                'estado' => 'nullable|in:ACTIVO,INACTIVO',
                'fecha_nacimiento_desde' => 'nullable|date',
                'fecha_nacimiento_hasta' => 'nullable|date',
                'sede_id' => 'nullable|exists:sedes,id'
            ]);

            $query = Paciente::with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            // ✅ Filtro opcional de sede
            if ($request->filled('sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }

            // Aplicar filtros específicos
            if ($request->filled('documento')) {
                $query->where('documento', 'like', '%' . $request->documento . '%');
            }

            if ($request->filled('primer_nombre')) {
                $query->where('primer_nombre', 'like', '%' . $request->primer_nombre . '%');
            }

            if ($request->filled('primer_apellido')) {
                $query->where('primer_apellido', 'like', '%' . $request->primer_apellido . '%');
            }

            if ($request->filled('telefono')) {
                $query->where('telefono', 'like', '%' . $request->telefono . '%');
            }

            if ($request->filled('correo')) {
                $query->where('correo', 'like', '%' . $request->correo . '%');
            }

            if ($request->filled('sexo')) {
                $query->where('sexo', $request->sexo);
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('fecha_nacimiento_desde')) {
                $query->whereDate('fecha_nacimiento', '>=', $request->fecha_nacimiento_desde);
            }

            if ($request->filled('fecha_nacimiento_hasta')) {
                $query->whereDate('fecha_nacimiento', '<=', $request->fecha_nacimiento_hasta);
            }

            $pacientes = $query->orderBy('primer_nombre')
                             ->orderBy('primer_apellido')
                             ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => PacienteResource::collection($pacientes),
                'meta' => [
                    'current_page' => $pacientes->currentPage(),
                    'last_page' => $pacientes->lastPage(),
                    'per_page' => $pacientes->perPage(),
                    'total' => $pacientes->total()
                ],
                'message' => 'Búsqueda avanzada completada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en búsqueda avanzada de pacientes', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en búsqueda avanzada'
            ], 500);
        }
    }
}

                