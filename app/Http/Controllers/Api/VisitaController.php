<?php
// app/Http/Controllers/Api/VisitaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HcsVisita;
use App\Models\Paciente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VisitaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = HcsVisita::where('sede_id', $user->sede_id)
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

        if ($request->filled('zona')) {
            $query->where('zona', 'like', "%{$request->zona}%");
        }

        if ($request->filled('hta')) {
            $query->where('hta', $request->hta);
        }

        if ($request->filled('dm')) {
            $query->where('dm', $request->dm);
        }

        if ($request->filled('abandono_social')) {
            $query->where('abandono_social', $request->abandono_social);
        }

        if ($request->filled('mes')) {
            $query->whereMonth('fecha', $request->mes);
        }

        if ($request->filled('año')) {
            $query->whereYear('fecha', $request->año);
        }

        // Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('identificacion', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%")
                  ->orWhere('zona', 'like', "%{$search}%")
                  ->orWhere('familiar', 'like', "%{$search}%")
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
        $visitas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $visitas,
            'message' => 'Visitas obtenidas exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'fecha' => 'required|date',
            'identificacion' => 'required|string|max:30',
            'edad' => 'required|string|max:12',
            'hta' => 'required|string|max:12',
            'dm' => 'required|string|max:12',
            'telefono' => 'required|string|max:20',
            'zona' => 'required|string|max:50',
            
            // Medidas antropométricas
            'peso' => 'nullable|string|max:20',
            'talla' => 'nullable|string|max:20',
            'imc' => 'nullable|string|max:20',
            'perimetro_abdominal' => 'nullable|integer',
            
            // Signos vitales
            'frecuencia_cardiaca' => 'nullable|string|max:20',
            'frecuencia_respiratoria' => 'nullable|string|max:20',
            'tension_arterial' => 'nullable|string|max:20',
            'glucometria' => 'nullable|string|max:30',
            'temperatura' => 'nullable|string|max:20',
            
            // Información social
            'familiar' => 'nullable|string|max:50',
            'abandono_social' => 'nullable|string|max:11',
            
            // Evaluación
            'motivo' => 'nullable|string',
            'medicamentos' => 'nullable|string',
            'riesgos' => 'nullable|string|max:500',
            'conductas' => 'nullable|string|max:1000',
            'novedades' => 'nullable|string',
            'encargado' => 'nullable|string',
            'fecha_control' => 'nullable|date',
            
            // Documentación
            'foto' => 'nullable|image|max:2048', // 2MB máximo
            'firma' => 'nullable|string',
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

        // Procesar foto si existe
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('visitas/fotos', 'public');
            $validated['foto'] = $fotoPath;
        }

        // Calcular IMC si no se proporcionó pero hay peso y talla
        if (!$validated['imc'] && $validated['peso'] && $validated['talla']) {
            $peso = floatval($validated['peso']);
            $talla = floatval($validated['talla']) / 100;
            if ($talla > 0) {
                $validated['imc'] = round($peso / ($talla * $talla), 2);
            }
        }

        $validated['sede_id'] = $user->sede_id;

        $visita = HcsVisita::create($validated);
        $visita->load(['sede', 'paciente']);

        return response()->json([
            'success' => true,
            'data' => $visita,
            'message' => 'Visita creada exitosamente'
        ], 201);
    }

    public function show(HcsVisita $visita): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($visita->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a esta visita'
            ], 403);
        }

        $visita->load(['sede', 'paciente']);

        return response()->json([
            'success' => true,
            'data' => $visita,
            'message' => 'Visita obtenida exitosamente'
        ]);
    }

    public function update(Request $request, HcsVisita $visita): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($visita->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a esta visita'
            ], 403);
        }

        $validated = $request->validate([
            'fecha' => 'sometimes|date',
            'edad' => 'sometimes|string|max:12',
            'hta' => 'sometimes|string|max:12',
            'dm' => 'sometimes|string|max:12',
            'telefono' => 'sometimes|string|max:20',
            'zona' => 'sometimes|string|max:50',
            
            // Medidas antropométricas
            'peso' => 'nullable|string|max:20',
            'talla' => 'nullable|string|max:20',
            'imc' => 'nullable|string|max:20',
            'perimetro_abdominal' => 'nullable|integer',
            
            // Signos vitales
            'frecuencia_cardiaca' => 'nullable|string|max:20',
            'frecuencia_respiratoria' => 'nullable|string|max:20',
            'tension_arterial' => 'nullable|string|max:20',
            'glucometria' => 'nullable|string|max:30',
            'temperatura' => 'nullable|string|max:20',
            
            // Información social
            'familiar' => 'nullable|string|max:50',
            'abandono_social' => 'nullable|string|max:11',
            
            // Evaluación
            'motivo' => 'nullable|string',
            'medicamentos' => 'nullable|string',
            'riesgos' => 'nullable|string|max:500',
            'conductas' => 'nullable|string|max:1000',
            'novedades' => 'nullable|string',
            'encargado' => 'nullable|string',
            'fecha_control' => 'nullable|date',
            
            // Documentación
            'foto' => 'nullable|image|max:2048',
            'firma' => 'nullable|string',
        ]);

        // Procesar nueva foto si existe
        if ($request->hasFile('foto')) {
            // Eliminar foto anterior si existe
            if ($visita->foto) {
                Storage::disk('public')->delete($visita->foto);
            }
            $fotoPath = $request->file('foto')->store('visitas/fotos', 'public');
            $validated['foto'] = $fotoPath;
        }

        // Recalcular IMC si se actualizó peso o talla
        if ((isset($validated['peso']) || isset($validated['talla'])) && !isset($validated['imc'])) {
            $peso = floatval($validated['peso'] ?? $visita->peso);
            $talla = floatval($validated['talla'] ?? $visita->talla) / 100;
            if ($peso > 0 && $talla > 0) {
                $validated['imc'] = round($peso / ($talla * $talla), 2);
            }
        }

        $visita->update($validated);
        $visita->load(['sede', 'paciente']);

        return response()->json([
            'success' => true,
            'data' => $visita,
            'message' => 'Visita actualizada exitosamente'
        ]);
    }

    public function destroy(HcsVisita $visita): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($visita->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a esta visita'
            ], 403);
        }

        // Eliminar foto si existe
        if ($visita->foto) {
            Storage::disk('public')->delete($visita->foto);
        }

        $visita->delete();

        return response()->json([
            'success' => true,
            'message' => 'Visita eliminada exitosamente'
        ]);
    }

    public function porPaciente(Request $request, string $documento): JsonResponse
    {
        $user = Auth::user();

        $visitas = HcsVisita::where('sede_id', $user->sede_id)
            ->where('identificacion', $documento)
            ->with(['sede', 'paciente'])
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitas,
            'message' => 'Visitas del paciente obtenidas exitosamente'
        ]);
    }

    public function proximosControles(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $dias = $request->get('dias', 7); // Por defecto próximos 7 días
        
        $visitas = HcsVisita::where('sede_id', $user->sede_id)
            ->whereNotNull('fecha_control')
            ->whereBetween('fecha_control', [now(), now()->addDays($dias)])
            ->with(['sede', 'paciente'])
            ->orderBy('fecha_control', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitas,
            'message' => 'Próximos controles obtenidos exitosamente'
        ]);
    }

    public function estadisticas(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = HcsVisita::where('sede_id', $user->sede_id);

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $estadisticas = [
            'total_visitas' => $query->count(),
            'visitas_mes_actual' => (clone $query)->whereMonth('fecha', now()->month)->count(),
            'pacientes_hta' => (clone $query)->where('hta', 'SI')->count(),
            'pacientes_dm' => (clone $query)->where('dm', 'SI')->count(),
            'abandono_social' => (clone $query)->where('abandono_social', 'SI')->count(),
            'proximos_controles' => HcsVisita::where('sede_id', $user->sede_id)
                ->whereNotNull('fecha_control')
                ->whereBetween('fecha_control', [now(), now()->addDays(7)])
                ->count(),
            'por_zona' => (clone $query)
                ->select('zona', DB::raw('COUNT(*) as total'))
                ->groupBy('zona')
                ->orderBy('total', 'desc')
                ->get(),
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

    public function reporteRiesgo(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $visitas = HcsVisita::where('sede_id', $user->sede_id)
            ->with(['paciente'])
            ->get()
            ->map(function ($visita) {
                return [
                    'id' => $visita->id,
                    'identificacion' => $visita->identificacion,
                    'paciente' => $visita->paciente ? $visita->paciente->nombre_completo : null,
                    'fecha' => $visita->fecha,
                    'hta' => $visita->hta,
                    'dm' => $visita->dm,
                    'imc' => $visita->imc,
                    'clasificacion_imc' => $visita->clasificacion_imc,
                    'riesgo_cardiovascular' => $visita->riesgo_cardiovascular,
                    'abandono_social' => $visita->abandono_social,
                    'necesita_control' => $visita->necesita_control,
                    'dias_hasta_control' => $visita->dias_hasta_control,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $visitas,
            'message' => 'Reporte de riesgo generado exitosamente'
        ]);
    }
}
