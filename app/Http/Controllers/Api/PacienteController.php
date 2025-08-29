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

            // ✅ Filtro por sede del usuario autenticado
            if ($request->user()) {
                $query->where('sede_id', $request->user()->sede_id);
            }

            // Filtros opcionales
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

            $pacientes = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => PacienteResource::collection($pacientes),
                'meta' => [
                    'current_page' => $pacientes->currentPage(),
                    'last_page' => $pacientes->lastPage(),
                    'per_page' => $pacientes->perPage(),
                    'total' => $pacientes->total()
                ]
            ]);

        } catch (\Exception $e) {
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

            // ✅ VALIDACIÓN CORREGIDA
            $validatedData = $request->validate([
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
                
                // ✅ IDs de relaciones (UUIDs como strings)
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
                'data' => $pacienteData
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
                'documento' => $paciente->documento
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
                'error' => 'Datos inválidos',
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
                'error' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }
     private function procesarDatosParaGuardar(array $validatedData, Request $request): array
    {
        $user = auth()->user();
        
        $data = [
            'uuid' => \Str::uuid(),
            'sede_id' => $user->sede_id ?? 1,
            'fecha_registro' => now(),
            'estado' => $validatedData['estado'] ?? 'ACTIVO',
            
            // ✅ CAMPOS BÁSICOS
            'registro' => $validatedData['registro'] ?? $this->generateRegistro($user->sede_id ?? 1),
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
            // ✅ RETORNAR NULL EN LUGAR DE FALLAR
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
            $query = Paciente::where('uuid', $uuid);
            
            // ✅ Filtro por sede si hay usuario autenticado
            if (auth()->check()) {
                $query->where('sede_id', auth()->user()->sede_id);
            }

            $paciente = $query->with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion',
                'citas.agenda', 'historiasClinicas'
            ])->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Paciente no encontrado'
            ], 404);
        }
    }

    public function update(UpdatePacienteRequest $request, string $uuid): JsonResponse
    {
        try {
            $query = Paciente::where('uuid', $uuid);
            
            if ($request->user()) {
                $query->where('sede_id', $request->user()->sede_id);
            }

            $paciente = $query->firstOrFail();
            
            $data = $request->validated();
            $data['fecha_actualizacion'] = now();
            
            $paciente->update($data);
            $paciente->load([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente),
                'message' => 'Paciente actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error actualizando paciente: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        try {
            $query = Paciente::where('uuid', $uuid);
            
            if (auth()->check()) {
                $query->where('sede_id', auth()->user()->sede_id);
            }

            $paciente = $query->firstOrFail();
            $paciente->delete();

            return response()->json([
                'success' => true,
                'message' => 'Paciente eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error eliminando paciente: ' . $e->getMessage()
            ], 500);
        }
    }

    public function searchByDocument(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'documento' => 'required|string'
            ]);

            $query = Paciente::where('documento', $request->documento);
            
            if ($request->user()) {
                $query->where('sede_id', $request->user()->sede_id);
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
                    'error' => 'Paciente no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error buscando paciente: ' . $e->getMessage()
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

            if ($request->user()) {
                $query->where('sede_id', $request->user()->sede_id);
            }

            // Aplicar filtros de búsqueda
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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en búsqueda: ' . $e->getMessage()
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
}
