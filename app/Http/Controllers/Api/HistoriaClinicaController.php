<?php
// app/Http/Controllers/Api/HistoriaClinicaController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\HistoriaClinica;
use App\Http\Resources\HistoriaClinicaResource;
use App\Http\Requests\{StoreHistoriaClinicaRequest, UpdateHistoriaClinicaRequest};

class HistoriaClinicaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HistoriaClinica::with([
            'cita.paciente', 'cita.agenda.usuario', 'historiaComplementaria',
            'historiaDiagnosticos.diagnostico', 'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision', 'historiaCups.cups'
        ])->bySede($request->user()->sede_id);

        // Filtros
        if ($request->filled('paciente_documento')) {
            $query->whereHas('cita.paciente', function ($q) use ($request) {
                $q->where('documento', $request->paciente_documento);
            });
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereHas('cita', function ($q) use ($request) {
                $q->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
            });
        }

        $historias = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => HistoriaClinicaResource::collection($historias),
            'meta' => [
                'current_page' => $historias->currentPage(),
                'last_page' => $historias->lastPage(),
                'per_page' => $historias->perPage(),
                'total' => $historias->total()
            ]
        ]);
    }

    public function store(StoreHistoriaClinicaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['sede_id'] = $request->user()->sede_id;

        $historia = HistoriaClinica::create($data);
        
        // Crear historia complementaria si se proporciona
        if ($request->filled('historia_complementaria')) {
            $historia->historiaComplementaria()->create($request->historia_complementaria);
        }

        // Agregar diagnósticos
        if ($request->filled('diagnosticos')) {
            foreach ($request->diagnosticos as $diagnostico) {
                $historia->historiaDiagnosticos()->create($diagnostico);
            }
        }

        // Agregar medicamentos
        if ($request->filled('medicamentos')) {
            foreach ($request->medicamentos as $medicamento) {
                $historia->historiaMedicamentos()->create($medicamento);
            }
        }

        // Agregar remisiones
        if ($request->filled('remisiones')) {
            foreach ($request->remisiones as $remision) {
                $historia->historiaRemisiones()->create($remision);
            }
        }

        // Agregar CUPS
        if ($request->filled('cups')) {
            foreach ($request->cups as $cup) {
                $historia->historiaCups()->create($cup);
            }
        }

        $historia->load([
            'cita.paciente', 'cita.agenda.usuario', 'historiaComplementaria',
            'historiaDiagnosticos.diagnostico', 'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision', 'historiaCups.cups'
        ]);

        return response()->json([
            'success' => true,
            'data' => new HistoriaClinicaResource($historia),
            'message' => 'Historia clínica creada exitosamente'
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $historia = HistoriaClinica::where('uuid', $uuid)
            ->with([
                'cita.paciente', 'cita.agenda.usuario', 'historiaComplementaria',
                'historiaDiagnosticos.diagnostico', 'historiaMedicamentos.medicamento',
                'historiaRemisiones.remision', 'historiaCups.cups', 'pdfs'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new HistoriaClinicaResource($historia)
        ]);
    }

    public function update(UpdateHistoriaClinicaRequest $request, string $uuid): JsonResponse
    {
        $historia = HistoriaClinica::where('uuid', $uuid)->firstOrFail();
        $historia->update($request->validated());

        // Actualizar historia complementaria
        if ($request->filled('historia_complementaria')) {
            $historia->historiaComplementaria()->updateOrCreate(
                ['historia_clinica_id' => $historia->id],
                $request->historia_complementaria
            );
        }

        $historia->load([
            'cita.paciente', 'cita.agenda.usuario', 'historiaComplementaria',
            'historiaDiagnosticos.diagnostico', 'historiaMedicamentos.medicamento',
            'historiaRemisiones.remision', 'historiaCups.cups'
        ]);

        return response()->json([
            'success' => true,
            'data' => new HistoriaClinicaResource($historia),
            'message' => 'Historia clínica actualizada exitosamente'
        ]);
    }

    public function historiasPaciente(Request $request, string $pacienteUuid): JsonResponse
    {
        $historias = HistoriaClinica::whereHas('cita.paciente', function ($q) use ($pacienteUuid) {
            $q->where('uuid', $pacienteUuid);
        })
        ->with([
            'cita.paciente', 'cita.agenda.usuario',
            'historiaDiagnosticos.diagnostico'
        ])
        ->bySede($request->user()->sede_id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'data' => HistoriaClinicaResource::collection($historias)
        ]);
    }
}
