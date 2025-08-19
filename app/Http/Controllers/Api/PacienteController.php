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
        $query = Paciente::with([
            'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
            'departamentoNacimiento', 'departamentoResidencia',
            'municipioNacimiento', 'municipioResidencia', 'raza',
            'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
        ])->bySede($request->user()->sede_id);

        // Filtros
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
    }

    public function store(StorePacienteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['sede_id'] = $request->user()->sede_id;
        $data['fecha_registro'] = now();

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
    }

    public function show(string $uuid): JsonResponse
    {
        $paciente = Paciente::where('uuid', $uuid)
            ->with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion',
                'citas.agenda', 'historiasClinicas'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new PacienteResource($paciente)
        ]);
    }

    public function update(UpdatePacienteRequest $request, string $uuid): JsonResponse
    {
        $paciente = Paciente::where('uuid', $uuid)->firstOrFail();
        
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
    }

    public function destroy(string $uuid): JsonResponse
    {
        $paciente = Paciente::where('uuid', $uuid)->firstOrFail();
        $paciente->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paciente eliminado exitosamente'
        ]);
    }

    public function searchByDocument(Request $request): JsonResponse
    {
        $request->validate([
            'documento' => 'required|string'
        ]);

        $paciente = Paciente::where('documento', $request->documento)
            ->bySede($request->user()->sede_id)
            ->with([
                'empresa', 'regimen', 'tipoAfiliacion', 'zonaResidencia',
                'departamentoNacimiento', 'departamentoResidencia',
                'municipioNacimiento', 'municipioResidencia', 'raza',
                'escolaridad', 'tipoParentesco', 'tipoDocumento', 'ocupacion'
            ])
            ->first();

        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PacienteResource($paciente)
        ]);
    }
}
