<?php
// app/Http/Controllers/Api/CitaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Cita;
use App\Http\Resources\CitaResource;
use Illuminate\Support\Facades\Log;

class CitaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Cita::with([
            'paciente', 
            'agenda', 
            'cupsContratado',
            'usuarioCreador',
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

        if ($request->filled('paciente_uuid')) {
            $query->where('paciente_uuid', $request->paciente_uuid);
        }

        if ($request->filled('agenda_uuid')) {
            $query->where('agenda_uuid', $request->agenda_uuid);
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

    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('🩺 API CitaController@store - Datos recibidos', [
                'data' => $request->all(),
                'user_id' => $request->user()->id
            ]);

            // ✅ VALIDACIÓN USANDO UUIDs
            $validatedData = $request->validate([
                'fecha' => 'required|date',
                'fecha_inicio' => 'required|date',
                'fecha_final' => 'required|date|after:fecha_inicio',
                'fecha_deseada' => 'nullable|date',
                'motivo' => 'nullable|string|max:200',
                'nota' => 'required|string|max:200',
                'estado' => 'nullable|string|max:50',
                'patologia' => 'nullable|string|max:50',
                'paciente_uuid' => 'required|string|exists:pacientes,uuid',
                'agenda_uuid' => 'required|string|exists:agendas,uuid',
                'cups_contratado_uuid' => 'nullable|string|exists:cups_contratados,uuid',
            ]);

            $validatedData['sede_id'] = $request->user()->sede_id;
            $validatedData['usuario_creo_cita_id'] = $request->user()->id;
            $validatedData['estado'] = $validatedData['estado'] ?? 'PROGRAMADA';

            Log::info('📝 Datos validados para crear cita', [
                'data' => $validatedData
            ]);

            $cita = Cita::create($validatedData);
            
            $cita->load([
                'paciente', 
                'agenda', 
                'cupsContratado', 
                'usuarioCreador',
                'sede'
            ]);

            Log::info('✅ Cita creada exitosamente en API', [
                'cita_uuid' => $cita->uuid,
                'paciente_uuid' => $cita->paciente_uuid
            ]);

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita),
                'message' => 'Cita creada exitosamente'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('❌ Errores de validación en cita', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('💥 Error creando cita en API', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)
                ->with([
                    'paciente', 
                    'agenda', 
                    'cupsContratado',
                    'usuarioCreador',
                    'sede'
                ])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => new CitaResource($cita)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cita no encontrada'
            ], 404);
        }
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $cita = Cita::where('uuid', $uuid)->firstOrFail();
            
            $validatedData = $request->validate([
                'fecha' => 'sometimes|date',
                'fecha_inicio' => 'sometimes|date',
                'fecha_final' => 'sometimes|date|after:fecha_inicio',
                'fecha_deseada' => 'nullable|date',
                'motivo' => 'nullable|string|max:200',
                'nota' => 'sometimes|string|max:200',
                'estado' => 'sometimes|string|max:50',
                'patologia' => 'nullable|string|max:50',
                'paciente_uuid' => 'sometimes|string|exists:pacientes,uuid',
                'agenda_uuid' => 'sometimes|string|exists:agendas,uuid',
                'cups_contratado_uuid' => 'nullable|string|exists:cups_contratados,uuid',
            ]);

            $cita->update($validatedData);
            
            $cita->load([
                'paciente', 
                'agenda', 
                'cupsContratado', 
                'usuarioCreador',
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
            'usuarioCreador'
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
