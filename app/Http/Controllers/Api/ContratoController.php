<?php
// app/Http/Controllers/Api/ContratoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ContratoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contrato::with(['empresa']);

        // Filtros
        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->empresa_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('vigentes')) {
            $query->vigentes();
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('poliza', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'fecha_registro');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $contratos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contratos,
            'message' => 'Contratos obtenidos exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'numero' => 'required|string|max:50|unique:contratos,numero',
            'descripcion' => 'required|string|max:50',
            'plan_beneficio' => 'required|in:POS,NO POS',
            'poliza' => 'required|string|max:50',
            'por_descuento' => 'required|string|max:50',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'valor' => 'required|string|max:50',
            'tipo' => 'required|in:PGP,EVENTO',
            'copago' => 'required|in:SI,NO',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $contrato = Contrato::create($validated);
        $contrato->load('empresa');

        return response()->json([
            'success' => true,
            'data' => $contrato,
            'message' => 'Contrato creado exitosamente'
        ], 201);
    }

    public function show(Contrato $contrato): JsonResponse
    {
        $contrato->load(['empresa', 'facturas.paciente']);

        return response()->json([
            'success' => true,
            'data' => $contrato,
            'message' => 'Contrato obtenido exitosamente'
        ]);
    }

    public function update(Request $request, Contrato $contrato): JsonResponse
    {
        $validated = $request->validate([
            'empresa_id' => 'sometimes|exists:empresas,id',
            'numero' => 'sometimes|string|max:50|unique:contratos,numero,' . $contrato->id,
            'descripcion' => 'sometimes|string|max:50',
            'plan_beneficio' => 'sometimes|in:POS,NO POS',
            'poliza' => 'sometimes|string|max:50',
            'por_descuento' => 'sometimes|string|max:50',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after:fecha_inicio',
            'valor' => 'sometimes|string|max:50',
            'tipo' => 'sometimes|in:PGP,EVENTO',
            'copago' => 'sometimes|in:SI,NO',
            'estado' => 'sometimes|in:ACTIVO,INACTIVO'
        ]);

        $contrato->update($validated);
        $contrato->load('empresa');

        return response()->json([
            'success' => true,
            'data' => $contrato,
            'message' => 'Contrato actualizado exitosamente'
        ]);
    }

    public function destroy(Contrato $contrato): JsonResponse
    {
        // Verificar si tiene facturas asociadas
        if ($contrato->facturas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el contrato porque tiene facturas asociadas'
            ], 422);
        }

        $contrato->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contrato eliminado exitosamente'
        ]);
    }

    public function vigentes(): JsonResponse
    {
        $contratos = Contrato::vigentes()
            ->activos()
            ->with('empresa')
            ->orderBy('fecha_fin')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contratos,
            'message' => 'Contratos vigentes obtenidos exitosamente'
        ]);
    }

    public function porVencer(Request $request): JsonResponse
    {
        $dias = $request->get('dias', 30); // Por defecto 30 días
        
        $contratos = Contrato::vigentes()
            ->activos()
            ->where('fecha_fin', '<=', now()->addDays($dias))
            ->with('empresa')
            ->orderBy('fecha_fin')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contratos,
            'message' => 'Contratos por vencer obtenidos exitosamente'
        ]);
    }
}
