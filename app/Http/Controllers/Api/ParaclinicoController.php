<?php
// app/Http/Controllers/Api/ParaclinicoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HcsParaclinico;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParaclinicoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = HcsParaclinico::where('sede_id', $user->sede_id)
            ->with(['sede', 'paciente']);

        // Filtros
        if ($request->filled('identificacion')) {
            $query->where('identificacion', $request->identificacion);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        if ($request->filled('mes')) {
            $query->whereMonth('fecha', $request->mes);
        }

        if ($request->filled('año')) {
            $query->whereYear('fecha', $request->año);
        }

        // Búsqueda por nombre de paciente
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('identificacion', 'like', "%{$search}%")
                  ->orWhereHas('paciente', function ($pq) use ($search) {
                      $pq->where('primer_nombre', 'like', "%{$search}%")
                        ->orWhere('segundo_nombre', 'like', "%{$search}%")
                        ->orWhere('primer_apellido', 'like', "%{$search}%")
                        ->orWhere('segundo_apellido', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'fecha');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $paraclinicos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paraclinicos,
            'message' => 'Paraclínicos obtenidos exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'fecha' => 'required|date',
            'identificacion' => 'required|string|max:30',
            
            // Perfil lipídico
            'colesterol_total' => 'nullable|string|max:50',
            'colesterol_hdl' => 'nullable|string|max:50',
            'trigliceridos' => 'nullable|string|max:50',
            'colesterol_ldl' => 'nullable|string|max:50',
            
            // Hematología
            'hemoglobina' => 'nullable|string|max:50',
            'hematocrito' => 'nullable|string|max:50',
            'plaquetas' => 'nullable|string|max:50',
            
            // Glucemia
            'hemoglobina_glicosilada' => 'nullable|string|max:50',
            'glicemia_basal' => 'nullable|string|max:50',
            'glicemia_post' => 'nullable|string|max:50',
            
            // Función renal
            'creatinina' => 'nullable|string|max:50',
            'creatinuria' => 'nullable|string|max:50',
            'microalbuminuria' => 'nullable|string|max:50',
            'albumina' => 'nullable|string|max:50',
            'relacion_albuminuria_creatinuria' => 'nullable|string|max:50',
            'parcial_orina' => 'nullable|string|max:50',
            'depuracion_creatinina' => 'nullable|string|max:50',
            'creatinina_orina_24' => 'nullable|string|max:50',
            'proteina_orina_24' => 'nullable|string|max:50',
            
            // Hormonas
            'hormona_estimulante_tiroides' => 'nullable|string|max:50',
            'hormona_paratiroidea' => 'nullable|string|max:50',
            
            // Química sanguínea
            'albumina_suero' => 'nullable|string|max:25',
            'fosforo_suero' => 'nullable|string|max:25',
            'nitrogeno_ureico' => 'nullable|string|max:25',
            'acido_urico_suero' => 'nullable|string|max:25',
            'calcio' => 'nullable|string|max:25',
            'sodio_suero' => 'nullable|string|max:25',
            'potasio_suero' => 'nullable|string|max:25',
            
            // Hierro
            'hierro_total' => 'nullable|string|max:25',
            'ferritina' => 'nullable|string|max:25',
            'transferrina' => 'nullable|string|max:25',
            
            // Enzimas
            'fosfatasa_alcalina' => 'nullable|string|max:20',
            
            // Vitaminas
            'acido_folico_suero' => 'nullable|string|max:25',
            'vitamina_b12' => 'nullable|string|max:25',
            
            'nitrogeno_ureico_orina_24' => 'nullable|string|max:25',
        ]);

        // Verificar que el paciente existe
        $paciente = Paciente::where('numero_documento', $validated['identificacion'])
            ->where('sede_id', $user->sede_id)
            ->first();

        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado en esta sede'
            ], 404);
        }

        $validated['sede_id'] = $user->sede_id;

        $paraclinico = HcsParaclinico::create($validated);
        $paraclinico->load(['sede', 'paciente']);

        return response()->json([
            'success' => true,
            'data' => $paraclinico,
            'message' => 'Paraclínico creado exitosamente'
        ], 201);
    }

    public function show(HcsParaclinico $paraclinico): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($paraclinico->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a este paraclínico'
            ], 403);
        }

        $paraclinico->load(['sede', 'paciente']);

        return response()->json([
            'success' => true,
            'data' => $paraclinico,
            'message' => 'Paraclínico obtenido exitosamente'
        ]);
    }

    public function update(Request $request, HcsParaclinico $paraclinico): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($paraclinico->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a este paraclínico'
            ], 403);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|date',
            
            // Perfil lipídico
            'colesterol_total' => 'nullable|string|max:50',
            'colesterol_hdl' => 'nullable|string|max:50',
            'trigliceridos' => 'nullable|string|max:50',
            'colesterol_ldl' => 'nullable|string|max:50',
            
            // Hematología
            'hemoglobina' => 'nullable|string|max:50',
            'hematocrito' => 'nullable|string|max:50',
            'plaquetas' => 'nullable|string|max:50',
            
            // Glucemia
            'hemoglobina_glicosilada' => 'nullable|string|max:50',
            'glicemia_basal' => 'nullable|string|max:50',
            'glicemia_post' => 'nullable|string|max:50',
            
            // Función renal
            'creatinina' => 'nullable|string|max:50',
            'creatinuria' => 'nullable|string|max:50',
            'microalbuminuria' => 'nullable|string|max:50',
            'albumina' => 'nullable|string|max:50',
            'relacion_albuminuria_creatinuria' => 'nullable|string|max:50',
            'parcial_orina' => 'nullable|string|max:50',
            'depuracion_creatinina' => 'nullable|string|max:50',
            'creatinina_orina_24' => 'nullable|string|max:50',
            'proteina_orina_24' => 'nullable|string|max:50',
            
            // Hormonas
            'hormona_estimulante_tiroides' => 'nullable|string|max:50',
            'hormona_paratiroidea' => 'nullable|string|max:50',
            
            // Química sanguínea
            'albumina_suero' => 'nullable|string|max:25',
            'fosforo_suero' => 'nullable|string|max:25',
            'nitrogeno_ureico' => 'nullable|string|max:25',
            'acido_urico_suero' => 'nullable|string|max:25',
            'calcio' => 'nullable|string|max:25',
            'sodio_suero' => 'nullable|string|max:25',
            'potasio_suero' => 'nullable|string|max:25',
            
            // Hierro
            'hierro_total' => 'nullable|string|max:25',
            'ferritina' => 'nullable|string|max:25',
            'transferrina' => 'nullable|string|max:25',
            
            // Enzimas
            'fosfatasa_alcalina' => 'nullable|string|max:20',
            
            // Vitaminas
            'acido_folico_suero' => 'nullable|string|max:25',
            'vitamina_b12' => 'nullable|string|max:25',
            
            'nitrogeno_ureico_orina_24' => 'nullable|string|max:25',
        ]);

        $paraclinico->update($validated);
        $paraclinico->load(['sede', 'paciente']);

        return response()->json([
            'success' => true,
            'data' => $paraclinico,
            'message' => 'Paraclínico actualizado exitosamente'
        ]);
    }

    public function destroy(HcsParaclinico $paraclinico): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($paraclinico->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a este paraclínico'
            ], 403);
        }

        $paraclinico->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paraclínico eliminado exitosamente'
        ]);
    }

    public function porPaciente(Request $request, string $documento): JsonResponse
    {
        $user = Auth::user();

        $paraclinicos = HcsParaclinico::where('sede_id', $user->sede_id)
            ->where('identificacion', $documento)
            ->with(['sede', 'paciente'])
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paraclinicos,
            'message' => 'Paraclínicos del paciente obtenidos exitosamente'
        ]);
    }

    public function estadisticas(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = HcsParaclinico::where('sede_id', $user->sede_id);

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $estadisticas = [
            'total_paraclinicos' => $query->count(),
            'paraclinicos_mes_actual' => (clone $query)->whereMonth('fecha', now()->month)->count(),
            'pacientes_unicos' => (clone $query)->distinct('identificacion')->count(),
            'por_mes' => (clone $query)
                ->select(DB::raw('MONTH(fecha) as mes, COUNT(*) as total'))
                ->groupBy('mes')
                ->orderBy('mes')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
