<?php
// app/Http/Controllers/Api/FacturaController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\Paciente;
use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Factura::where('sede_id', $user->sede_id)
            ->with(['sede', 'cita', 'paciente', 'contrato.empresa']);

        // Filtros
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        if ($request->filled('paciente_id')) {
            $query->where('paciente_id', $request->paciente_id);
        }

        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->contrato_id);
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
                $q->where('autorizacion', 'like', "%{$search}%")
                  ->orWhereHas('paciente', function ($pq) use ($search) {
                      $pq->where('documento', 'like', "%{$search}%")
                        ->orWhere('primer_nombre', 'like', "%{$search}%")
                        ->orWhere('primer_apellido', 'like', "%{$search}%");
                  })
                  ->orWhereHas('contrato', function ($cq) use ($search) {
                      $cq->where('numero', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'fecha');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $facturas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $facturas,
            'message' => 'Facturas obtenidas exitosamente'
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'cita_id' => 'nullable|exists:citas,id',
            'paciente_id' => 'nullable|exists:pacientes,id',
            'contrato_id' => 'required|exists:contratos,id',
            'fecha' => 'required|date',
            'copago' => 'required|string|max:50',
            'comision' => 'required|string|max:50',
            'descuento' => 'required|string|max:50',
            'valor_consulta' => 'required|string|max:50',
            'sub_total' => 'required|string|max:50',
            'autorizacion' => 'required|string|max:50',
            'cantidad' => 'nullable|integer|min:1'
        ]);

        // Verificar que la cita pertenece a la sede del usuario
        if ($validated['cita_id']) {
            $cita = Cita::find($validated['cita_id']);
            if ($cita->sede_id !== $user->sede_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cita no pertenece a su sede'
                ], 422);
            }
        }

        // Verificar que el paciente pertenece a la sede del usuario
        if ($validated['paciente_id']) {
            $paciente = Paciente::find($validated['paciente_id']);
            if ($paciente->sede_id !== $user->sede_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El paciente no pertenece a su sede'
                ], 422);
            }
        }

        $validated['sede_id'] = $user->sede_id;

        $factura = Factura::create($validated);
        $factura->load(['sede', 'cita', 'paciente', 'contrato.empresa']);

        return response()->json([
            'success' => true,
            'data' => $factura,
            'message' => 'Factura creada exitosamente'
        ], 201);
    }

    public function show(Factura $factura): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($factura->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a esta factura'
            ], 403);
        }

        $factura->load(['sede', 'cita', 'paciente', 'contrato.empresa']);

        return response()->json([
            'success' => true,
            'data' => $factura,
            'message' => 'Factura obtenida exitosamente'
        ]);
    }

    public function update(Request $request, Factura $factura): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($factura->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene acceso a esta factura'
            ], 403);
        }

        $validated = $request->validate([
            'copago' => 'sometimes|string|max:50',
            'comision' => 'sometimes|string|max:50',
            'descuento' => 'sometimes|string|max:50',
            'valor_consulta' => 'sometimes|string|max:50',
            'sub_total' => 'sometimes|string|max:50',
            'autorizacion' => 'sometimes|string|max:50',
            'cantidad' => 'nullable|integer|min:1'
        ]);

        $factura->update($validated);
        $factura->load(['sede', 'cita', 'paciente', 'contrato.empresa']);

        return response()->json([
            'success' => true,
            'data' => $factura,
            'message' => 'Factura actualizada exitosamente'
        ]);
    }

    public function destroy(Factura $factura): JsonResponse
    {
        $user = Auth::user();

        // Verificar acceso por sede
        if ($factura->sede_id !== $user->sede_id) {
            return response()->json([
                                'success' => false,
                'message' => 'No tiene acceso a esta factura'
            ], 403);
        }

        $factura->delete();

        return response()->json([
            'success' => true,
            'message' => 'Factura eliminada exitosamente'
        ]);
    }

    public function porPaciente(Request $request, string $documento): JsonResponse
    {
        $user = Auth::user();

        $paciente = Paciente::where('documento', $documento)
            ->where('sede_id', $user->sede_id)
            ->first();

        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Paciente no encontrado'
            ], 404);
        }

        $facturas = Factura::where('sede_id', $user->sede_id)
            ->where('paciente_id', $paciente->id)
            ->with(['sede', 'cita', 'contrato.empresa'])
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $facturas,
            'message' => 'Facturas del paciente obtenidas exitosamente'
        ]);
    }

    public function resumenMensual(Request $request): JsonResponse
    {
        $user = Auth::user();
        $mes = $request->get('mes', now()->month);
        $año = $request->get('año', now()->year);

        $facturas = Factura::where('sede_id', $user->sede_id)
            ->delMes($mes, $año)
            ->with(['contrato.empresa'])
            ->get();

        $resumen = [
            'periodo' => [
                'mes' => $mes,
                'año' => $año,
                'nombre_mes' => now()->month($mes)->translatedFormat('F')
            ],
            'totales' => [
                'cantidad_facturas' => $facturas->count(),
                'total_copago' => $facturas->sum('copago_numerico'),
                'total_comision' => $facturas->sum('comision_numerica'),
                'total_descuento' => $facturas->sum('descuento_numerico'),
                'total_valor_consulta' => $facturas->sum('valor_consulta_numerico'),
                'total_sub_total' => $facturas->sum('sub_total_numerico'),
                'total_final' => $facturas->sum('total_final')
            ],
            'por_empresa' => $facturas->groupBy('contrato.empresa.nombre')
                ->map(function ($facturasPorEmpresa) {
                    return [
                        'cantidad' => $facturasPorEmpresa->count(),
                        'total_facturado' => $facturasPorEmpresa->sum('sub_total_numerico'),
                        'total_copago' => $facturasPorEmpresa->sum('copago_numerico'),
                        'total_descuento' => $facturasPorEmpresa->sum('descuento_numerico')
                    ];
                }),
            'por_contrato' => $facturas->groupBy('contrato.numero')
                ->map(function ($facturasPorContrato) {
                    return [
                        'cantidad' => $facturasPorContrato->count(),
                        'total_facturado' => $facturasPorContrato->sum('sub_total_numerico'),
                        'empresa' => $facturasPorContrato->first()->contrato->empresa->nombre ?? 'N/A'
                    ];
                })
        ];

        return response()->json([
            'success' => true,
            'data' => $resumen,
            'message' => 'Resumen mensual obtenido exitosamente'
        ]);
    }

    public function estadisticas(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $query = Factura::where('sede_id', $user->sede_id);

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $estadisticas = [
            'resumen_general' => [
                'total_facturas' => (clone $query)->count(),
                'total_facturado' => (clone $query)->get()->sum('sub_total_numerico'),
                'total_copago' => (clone $query)->get()->sum('copago_numerico'),
                'total_descuentos' => (clone $query)->get()->sum('descuento_numerico'),
                'promedio_factura' => (clone $query)->get()->avg('sub_total_numerico')
            ],
            'facturas_mes_actual' => Factura::where('sede_id', $user->sede_id)
                ->delMes()
                ->count(),
            'top_contratos' => (clone $query)
                ->select('contrato_id', DB::raw('COUNT(*) as total_facturas'), DB::raw('SUM(CAST(REPLACE(REPLACE(sub_total, ",", ""), "$", "") AS DECIMAL(10,2))) as total_monto'))
                ->with('contrato.empresa')
                ->groupBy('contrato_id')
                ->orderBy('total_monto', 'desc')
                ->limit(5)
                ->get(),
            'por_mes' => (clone $query)
                ->select(DB::raw('MONTH(fecha) as mes, YEAR(fecha) as año, COUNT(*) as total_facturas, SUM(CAST(REPLACE(REPLACE(sub_total, ",", ""), "$", "") AS DECIMAL(10,2))) as total_monto'))
                ->groupBy('mes', 'año')
                ->orderBy('año', 'desc')
                ->orderBy('mes', 'desc')
                ->limit(12)
                ->get(),
            'facturas_con_autorizacion' => (clone $query)
                ->whereNotNull('autorizacion')
                ->where('autorizacion', '!=', '')
                ->where('autorizacion', '!=', '0')
                ->count(),
            'facturas_sin_autorizacion' => (clone $query)
                ->where(function ($q) {
                    $q->whereNull('autorizacion')
                      ->orWhere('autorizacion', '')
                      ->orWhere('autorizacion', '0');
                })
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }

    public function reporteFacturacion(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'contrato_id' => 'nullable|exists:contratos,id',
            'empresa_id' => 'nullable|exists:empresas,id'
        ]);

        $query = Factura::where('sede_id', $user->sede_id)
            ->whereBetween('fecha', [$validated['fecha_desde'], $validated['fecha_hasta']])
            ->with(['paciente', 'contrato.empresa', 'cita']);

        if (isset($validated['contrato_id'])) {
            $query->where('contrato_id', $validated['contrato_id']);
        }

        if (isset($validated['empresa_id'])) {
            $query->whereHas('contrato', function ($q) use ($validated) {
                $q->where('empresa_id', $validated['empresa_id']);
            });
        }

        $facturas = $query->orderBy('fecha')->get();

        $reporte = [
            'periodo' => [
                'fecha_desde' => $validated['fecha_desde'],
                'fecha_hasta' => $validated['fecha_hasta']
            ],
            'facturas' => $facturas->map(function ($factura) {
                return [
                    'id' => $factura->id,
                    'uuid' => $factura->uuid,
                    'fecha' => $factura->fecha,
                    'paciente' => [
                        'documento' => $factura->paciente->documento ?? null,
                        'nombre' => $factura->paciente->nombre_completo ?? null,
                    ],
                    'empresa' => $factura->contrato->empresa->nombre ?? null,
                    'contrato' => $factura->contrato->numero ?? null,
                    'autorizacion' => $factura->autorizacion,
                    'valores' => [
                        'valor_consulta' => $factura->valor_consulta_numerico,
                        'copago' => $factura->copago_numerico,
                        'descuento' => $factura->descuento_numerico,
                        'sub_total' => $factura->sub_total_numerico,
                        'total_final' => $factura->total_final
                    ]
                ];
            }),
            'totales' => [
                'cantidad_facturas' => $facturas->count(),
                'total_valor_consulta' => $facturas->sum('valor_consulta_numerico'),
                'total_copago' => $facturas->sum('copago_numerico'),
                'total_descuento' => $facturas->sum('descuento_numerico'),
                'total_sub_total' => $facturas->sum('sub_total_numerico'),
                'total_final' => $facturas->sum('total_final')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $reporte,
            'message' => 'Reporte de facturación generado exitosamente'
        ]);
    }

    public function facturarCita(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'cita_id' => 'required|exists:citas,id',
            'contrato_id' => 'required|exists:contratos,id',
            'copago' => 'required|string|max:50',
            'comision' => 'required|string|max:50',
            'descuento' => 'required|string|max:50',
            'valor_consulta' => 'required|string|max:50',
            'sub_total' => 'required|string|max:50',
            'autorizacion' => 'required|string|max:50',
            'cantidad' => 'nullable|integer|min:1'
        ]);

        // Verificar que la cita existe y pertenece a la sede
        $cita = Cita::with('paciente')->find($validated['cita_id']);
        
        if ($cita->sede_id !== $user->sede_id) {
            return response()->json([
                'success' => false,
                'message' => 'La cita no pertenece a su sede'
            ], 422);
        }

        // Verificar que la cita no esté ya facturada
        $facturaExistente = Factura::where('cita_id', $validated['cita_id'])->first();
        if ($facturaExistente) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cita ya ha sido facturada'
            ], 422);
        }

        // Crear la factura
        $facturaData = $validated;
        $facturaData['sede_id'] = $user->sede_id;
        $facturaData['paciente_id'] = $cita->paciente_id;
        $facturaData['fecha'] = now()->toDateString();

        $factura = Factura::create($facturaData);
        $factura->load(['sede', 'cita', 'paciente', 'contrato.empresa']);

        return response()->json([
            'success' => true,
            'data' => $factura,
            'message' => 'Cita facturada exitosamente'
        ], 201);
    }
}

