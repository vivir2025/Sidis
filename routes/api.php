<?php
// routes/api.php - VERSIÓN CORREGIDA
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    PacienteController,
    CitaController,
    HistoriaClinicaController,
    SyncController,
    MasterDataController,
    AgendaController,
    FacturaController,
    ParaclinicoController,
    VisitaController,
    UtilController,
    EspecialidadController,
    CategoriaCupsController,
    CupsController,
    ContratoController,
    CupsContratadoController,
    NovedadController,
    AuxiliarController,
    BrigadaController,
    ProcesoController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (sin autenticación)
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/health', function () {
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
    });
});

// Rutas protegidas (requieren autenticación)
Route::prefix('v1')->middleware(['auth:sanctum', 'sede.access'])->group(function () {
    
    // ================================
    // AUTENTICACIÓN
    // ================================
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ================================
    // PACIENTES
    // ================================
    Route::prefix('pacientes')->group(function () {
        Route::get('/', [PacienteController::class, 'index']);
        Route::get('/test', [PacienteController::class, 'test']);
        Route::post('/', [PacienteController::class, 'store']);
        
        // Rutas de búsqueda (ANTES de las rutas con parámetros)
        Route::get('/search', [PacienteController::class, 'search']);
        Route::get('/search/document', [PacienteController::class, 'searchByDocument']);
        Route::get('/buscar-documento', [PacienteController::class, 'searchByDocument']);
        
        // Rutas con parámetros UUID (DESPUÉS de las rutas específicas)
        Route::get('/{uuid}', [PacienteController::class, 'show']);
        Route::put('/{uuid}', [PacienteController::class, 'update']);
        Route::delete('/{uuid}', [PacienteController::class, 'destroy']);
        
        // Rutas relacionadas
        Route::get('/{uuid}/historias', [HistoriaClinicaController::class, 'historiasPaciente']);
        Route::get('/{uuid}/citas', [CitaController::class, 'citasPaciente']);
    });

    // ================================
    // CITAS
    // ================================
    Route::prefix('citas')->group(function () {
        Route::get('/', [CitaController::class, 'index']);
        Route::post('/', [CitaController::class, 'store']);
        Route::get('/del-dia', [CitaController::class, 'citasDelDia']);
        Route::get('/agenda/{agenda}', [CitaController::class, 'citasPorAgenda']);
        Route::get('/{cita}', [CitaController::class, 'show']);
        Route::put('/{cita}', [CitaController::class, 'update']);
        Route::delete('/{cita}', [CitaController::class, 'destroy']);
        Route::patch('/{cita}/estado', [CitaController::class, 'cambiarEstado']);
    });

    // ================================
    // AGENDAS
    // ================================
    Route::prefix('agendas')->group(function () {
        Route::get('/', [AgendaController::class, 'index']);
        Route::post('/', [AgendaController::class, 'store']);
        Route::get('/disponibles', [AgendaController::class, 'disponibles']);
        Route::get('/{agenda}', [AgendaController::class, 'show']);
        Route::put('/{agenda}', [AgendaController::class, 'update']);
        Route::delete('/{agenda}', [AgendaController::class, 'destroy']);
        Route::get('/{agenda}/citas', [AgendaController::class, 'citasAgenda']);
    });

    // ================================
    // ESPECIALIDADES
    // ================================
    Route::prefix('especialidades')->group(function () {
        Route::get('/', [EspecialidadController::class, 'index']);
        Route::post('/', [EspecialidadController::class, 'store']);
        Route::get('/activas', [EspecialidadController::class, 'activas']);
        Route::get('/{id}', [EspecialidadController::class, 'show']);
        Route::put('/{id}', [EspecialidadController::class, 'update']);
        Route::delete('/{id}', [EspecialidadController::class, 'destroy']);
        Route::post('/{id}/restore', [EspecialidadController::class, 'restore']);
        Route::get('/{id}/medicos', [EspecialidadController::class, 'medicos']);
        Route::patch('/{id}/estado', [EspecialidadController::class, 'cambiarEstado']);
    });
    
    // ================================
    // RECURSOS ADICIONALES
    // ================================
    Route::apiResource('novedades', NovedadController::class);
    Route::apiResource('auxiliares', AuxiliarController::class);
    Route::apiResource('brigadas', BrigadaController::class);
    Route::apiResource('procesos', ProcesoController::class);

    // ================================
    // CONTRATOS
    // ================================
    Route::prefix('contratos')->group(function () {
        Route::get('/', [ContratoController::class, 'index']);
        Route::post('/', [ContratoController::class, 'store']);
        Route::get('/vigentes', [ContratoController::class, 'vigentes']);
        Route::get('/por-vencer', [ContratoController::class, 'porVencer']);
        Route::get('/{contrato}', [ContratoController::class, 'show']);
        Route::put('/{contrato}', [ContratoController::class, 'update']);
        Route::delete('/{contrato}', [ContratoController::class, 'destroy']);
        Route::patch('/{contrato}/estado', [ContratoController::class, 'cambiarEstado']);
    });

    // ================================
    // CATEGORÍAS CUPS
    // ================================
    Route::prefix('categorias-cups')->group(function () {
        Route::get('/', [CategoriaCupsController::class, 'index']);
        Route::post('/', [CategoriaCupsController::class, 'store']);
        Route::get('/con-cups', [CategoriaCupsController::class, 'conCups']);
        Route::get('/{categoriaCup}', [CategoriaCupsController::class, 'show']);
        Route::put('/{categoriaCup}', [CategoriaCupsController::class, 'update']);
        Route::delete('/{categoriaCup}', [CategoriaCupsController::class, 'destroy']);
    });

    // ================================
    // CUPS
    // ================================
    Route::prefix('cups')->group(function () {
        Route::get('/', [CupsController::class, 'index']);
        Route::post('/', [CupsController::class, 'store']);
        Route::get('/activos', [CupsController::class, 'activos']);
        Route::get('/origen/{origen}', [CupsController::class, 'porOrigen']);
        Route::post('/buscar', [CupsController::class, 'buscar']);
        Route::get('/{cup}', [CupsController::class, 'show']);
        Route::put('/{cup}', [CupsController::class, 'update']);
        Route::delete('/{cup}', [CupsController::class, 'destroy']);
    });

    // ================================
    // CUPS CONTRATADOS
    // ================================
    Route::prefix('cups-contratados')->group(function () {
        Route::get('/', [CupsContratadoController::class, 'index']);
        Route::post('/', [CupsContratadoController::class, 'store']);
        Route::get('/contrato/{contratoId}', [CupsContratadoController::class, 'porContrato']);
        Route::get('/disponibles', [CupsContratadoController::class, 'disponibles']);
        Route::post('/masivos', [CupsContratadoController::class, 'masivos']);
        Route::get('/{cupsContratado}', [CupsContratadoController::class, 'show']);
        Route::put('/{cupsContratado}', [CupsContratadoController::class, 'update']);
        Route::delete('/{cupsContratado}', [CupsContratadoController::class, 'destroy']);
        Route::patch('/{cupsContratado}/activar-desactivar', [CupsContratadoController::class, 'activarDesactivar']);
    });

    // ================================
    // HISTORIAS CLÍNICAS
    // ================================
    Route::prefix('historias-clinicas')->group(function () {
        Route::get('/', [HistoriaClinicaController::class, 'index']);
        Route::post('/', [HistoriaClinicaController::class, 'store']);
        Route::get('/{historia}', [HistoriaClinicaController::class, 'show']);
        Route::put('/{historia}', [HistoriaClinicaController::class, 'update']);
        Route::delete('/{historia}', [HistoriaClinicaController::class, 'destroy']);
        
        // Componentes de historia clínica
        Route::post('/{historia}/diagnosticos', [HistoriaClinicaController::class, 'agregarDiagnostico']);
        Route::delete('/{historia}/diagnosticos/{diagnostico}', [HistoriaClinicaController::class, 'eliminarDiagnostico']);
        Route::post('/{historia}/medicamentos', [HistoriaClinicaController::class, 'agregarMedicamento']);
        Route::delete('/{historia}/medicamentos/{medicamento}', [HistoriaClinicaController::class, 'eliminarMedicamento']);
        Route::post('/{historia}/remisiones', [HistoriaClinicaController::class, 'agregarRemision']);
        Route::delete('/{historia}/remisiones/{remision}', [HistoriaClinicaController::class, 'eliminarRemision']);
        Route::post('/{historia}/cups', [HistoriaClinicaController::class, 'agregarCups']);
        Route::delete('/{historia}/cups/{cups}', [HistoriaClinicaController::class, 'eliminarCups']);
        
        // PDFs
        Route::post('/{historia}/pdfs', [HistoriaClinicaController::class, 'subirPdf']);
        Route::delete('/{historia}/pdfs/{pdf}', [HistoriaClinicaController::class, 'eliminarPdf']);
        Route::get('/{historia}/pdfs/{pdf}/download', [HistoriaClinicaController::class, 'descargarPdf']);
    });

    // ================================
    // PARACLINICOS
    // ================================
    Route::prefix('paraclinicos')->group(function () {
        Route::get('/', [ParaclinicoController::class, 'index']);
        Route::post('/', [ParaclinicoController::class, 'store']);
        Route::get('/{paraclinico}', [ParaclinicoController::class, 'show']);
        Route::put('/{paraclinico}', [ParaclinicoController::class, 'update']);
        Route::delete('/{paraclinico}', [ParaclinicoController::class, 'destroy']);
        Route::get('/paciente/{documento}', [ParaclinicoController::class, 'porPaciente']);
    });

    // ================================
    // VISITAS
    // ================================
    Route::prefix('visitas')->group(function () {
        Route::get('/', [VisitaController::class, 'index']);
        Route::post('/', [VisitaController::class, 'store']);
        Route::get('/{visita}', [VisitaController::class, 'show']);
        Route::put('/{visita}', [VisitaController::class, 'update']);
        Route::delete('/{visita}', [VisitaController::class, 'destroy']);
        Route::get('/paciente/{documento}', [VisitaController::class, 'porPaciente']);
    });

    // ================================
    // FACTURAS
    // ================================
    Route::prefix('facturas')->group(function () {
        Route::get('/', [FacturaController::class, 'index']);
        Route::post('/', [FacturaController::class, 'store']);
        Route::get('/{factura}', [FacturaController::class, 'show']);
        Route::put('/{factura}', [FacturaController::class, 'update']);
        Route::delete('/{factura}', [FacturaController::class, 'destroy']);
        Route::get('/{factura}/pdf', [FacturaController::class, 'generarPdf']);
    });

    // ================================
    // DATOS MAESTROS
    // ================================
    Route::prefix('master-data')->group(function () {
        Route::get('/all', [MasterDataController::class, 'allMasterData']);
        Route::get('/departamentos', [MasterDataController::class, 'departamentos']);
        Route::get('/municipios/{departamento}', [MasterDataController::class, 'municipios']);
        Route::get('/empresas', [MasterDataController::class, 'empresas']);
        Route::get('/regimenes', [MasterDataController::class, 'regimenes']);
        Route::get('/tipos-afiliacion', [MasterDataController::class, 'tiposAfiliacion']);
        Route::get('/zonas-residenciales', [MasterDataController::class, 'zonasResidenciales']);
        Route::get('/razas', [MasterDataController::class, 'razas']);
        Route::get('/escolaridades', [MasterDataController::class, 'escolaridades']);
        Route::get('/tipos-parentesco', [MasterDataController::class, 'tiposParentesco']);
        Route::get('/tipos-documento', [MasterDataController::class, 'tiposDocumento']);
        Route::get('/ocupaciones', [MasterDataController::class, 'ocupaciones']);
        Route::get('/especialidades', [MasterDataController::class, 'especialidades']);
        Route::get('/diagnosticos', [MasterDataController::class, 'diagnosticos']);
        Route::get('/medicamentos', [MasterDataController::class, 'medicamentos']);
        Route::get('/remisiones', [MasterDataController::class, 'remisiones']);
        Route::get('/cups', [MasterDataController::class, 'cups']);
        Route::get('/cups-contratados', [MasterDataController::class, 'cupsContratados']);
        Route::get('/contratos', [MasterDataController::class, 'contratos']);
        Route::get('/novedades', [MasterDataController::class, 'novedades']);
        Route::get('/auxiliares', [MasterDataController::class, 'auxiliares']);
        Route::get('/brigadas', [MasterDataController::class, 'brigadas']);
        Route::get('/procesos', [MasterDataController::class, 'procesos']);
    });

    // ================================
    // SINCRONIZACIÓN
    // ================================
    Route::prefix('sync')->group(function () {
        Route::get('/status', [SyncController::class, 'syncStatus']);
        Route::post('/pull', [SyncController::class, 'pullChanges']);
        Route::post('/push', [SyncController::class, 'pushChanges']);
        Route::post('/full-sync', [SyncController::class, 'fullSync']);
        Route::post('/retry-failed', [SyncController::class, 'retryFailedChanges']);
        Route::delete('/cleanup', [SyncController::class, 'cleanupSyncQueue']);
        Route::get('/conflicts', [SyncController::class, 'getConflicts']);
        Route::post('/resolve-conflict', [SyncController::class, 'resolveConflict']);
    });

    // ================================
    // UTILIDADES
    // ================================
    Route::prefix('utils')->group(function () {
        Route::post('/backup', [UtilController::class, 'createBackup']);
        Route::get('/system-info', [UtilController::class, 'systemInfo']);
        Route::post('/test-connection', [UtilController::class, 'testConnection']);
        Route::get('/logs', [UtilController::class, 'getLogs']);
    });
});
