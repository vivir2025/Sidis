<?php
// app/Http/Controllers/Api/CitaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Cita;
use App\Http\Resources\CitaResource;
use App\Http\Requests\{StoreCitaRequest, UpdateCitaRequest};

class CitaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador', // ✅ NOMBRE CORRECTO
            'sede'
        ])->bySede($request->user()->sede_id);

        // Filtros
        if ($request->filled('fecha')) {
            $query->whereDate('fecha', $request->fecha);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('paciente_documento')) {
            $query->whereHas('paciente', function ($q) use ($request) {
                $q->where('documento', $request->paciente_documento);
            });
        }

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
        }

        $citas = $query->orderBy('fecha_inicio', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'meta' => [
                'current_page' => $citas->currentPage(),
                'last_page' => $citas->lastPage(),
                'per_page' => $citas->perPage(),
                'total' => $citas->total()
            ]
        ]);
    }

    public function store(StoreCitaRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['sede_id'] = $request->user()->sede_id;
            $data['usuario_creo_cita_id'] = $request->user()->id;

            $cita = Cita::create($data);
            
            // ✅ CARGAR RELACIONES CORRECTAS
            $cita->load([
                'paciente', 
                'agenda', 
                'cupsContratado', 
                'usuarioCreador', // ✅ NOMBRE CORRECTO
                'sede'
            ]);

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita),
                'message' => 'Cita creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        $cita = Cita::where('uuid', $uuid)
            ->with([
                'paciente', 
                'agenda', 
                'cupsContratado',
                'usuarioCreador', // ✅ NOMBRE CORRECTO
                'sede'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new CitaResource($cita)
        ]);
    }

    public function update(UpdateCitaRequest $request, string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)->firstOrFail();
            $cita->update($request->validated());
            
            $cita->load([
                'paciente', 
                'agenda', 
                'cupsContratado', 
                'usuarioCreador', // ✅ NOMBRE CORRECTO
                'sede'
            ]);

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita),
                'message' => 'Cita actualizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)->firstOrFail();
            $cita->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cita eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function citasDelDia(Request $request): JsonResponse
    {
        $fecha = $request->get('fecha', now()->format('Y-m-d'));
        
        $citas = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador' // ✅ NOMBRE CORRECTO
        ])
        ->bySede($request->user()->sede_id)
        ->whereDate('fecha', $fecha)
        ->orderBy('fecha_inicio')
        ->get();

        return response()->json([
            'success' => true,
            'data' => CitaResource::collection($citas),
            'meta' => [
                'fecha' => $fecha,
                'total_citas' => $citas->count()
            ]
        ]);
    }
}
