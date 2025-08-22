<?php
// app/Http/Controllers/Api/PacienteController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Paciente;
use App\Http\Resources\PacienteResource;
use App\Http\Requests\{StorePacienteRequest, UpdatePacienteRequest};

class PacienteController extends Controller
{
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

    public function store(StorePacienteRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['sede_id'] = $request->user()->sede_id;
            $data['fecha_registro'] = now();
            $data['uuid'] = \Str::uuid();
            
            // ✅ Generar registro automático si no se proporciona
            if (empty($data['registro'])) {
                $data['registro'] = $this->generateRegistro($request->user()->sede_id);
            }
            
            // ✅ Estado por defecto
            if (empty($data['estado'])) {
                $data['estado'] = 'ACTIVO';
            }

            $paciente = Paciente::create($data);
            $paciente->load([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ]);

            return response()->json([
                'success' => true,
                'data' => new PacienteResource($paciente),
                'message' => 'Paciente creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error creando paciente: ' . $e->getMessage()
            ], 500);
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
        $year = date('Y');
        $lastPaciente = Paciente::where('sede_id', $sedeId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = $lastPaciente ? (intval(substr($lastPaciente->registro, -6)) + 1) : 1;
        
        return sprintf('REG%s%06d', $year, $nextNumber);
    }
}
