<?php
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
    ProcesoController,
    MedicamentoController,
    DiagnosticoController,
    UsuarioController,
    RemisionController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rutas públicas (sin autenticación)
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/cups-contratados/por-cups/{cupsUuid}', [CupsContratadoController::class, 'porCupsUuid']);
    // 🧪 RUTA TEMPORAL PARA DEBUG API
Route::get('/debug/historias-api', [App\Http\Controllers\Api\HistoriaClinicaController::class, 'debugHistoriasApi']);

    // 🔐 VERIFICACIÓN DE IDENTIDAD (públicas para bot de WhatsApp)
    Route::post('/pacientes/verificacion/iniciar', [PacienteController::class, 'iniciarVerificacion']);
    Route::post('/pacientes/verificacion/validar', [PacienteController::class, 'validarVerificacion']);

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
          // ✅ NUEVAS RUTAS API
        Route::get('/sedes', [AuthController::class, 'sedes']);
        Route::post('/cambiar-sede', [AuthController::class, 'cambiarSede'])->middleware('auth:sanctum');
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
        Route::get('/{pacienteUuid}/ultima-historia', [HistoriaClinicaController::class, 'obtenerUltimaHistoriaMedicinaGeneral']);
        Route::get('/{pacienteUuid}/historias-clinicas', [HistoriaClinicaController::class, 'historiasPaciente']);
        Route::get('/{pacienteUuid}/ultima-historia', [HistoriaClinicaController::class, 'obtenerUltimaHistoriaMedicinaGeneral']);
        Route::get('/{pacienteUuid}/ultima-historia-medicina-general', [HistoriaClinicaController::class, 'obtenerUltimaHistoriaMedicinaGeneral']);
        
        // Rutas con parámetros UUID (DESPUÉS de las rutas específicas)
        Route::get('/{uuid}', [PacienteController::class, 'show']);
        Route::put('/{uuid}', [PacienteController::class, 'update']);
        Route::delete('/{uuid}', [PacienteController::class, 'destroy']);
        
        // Rutas relacionadas
        Route::get('/{uuid}/historias', [HistoriaClinicaController::class, 'historiasPaciente']);
        Route::get('/{uuid}/citas', [CitaController::class, 'citasPaciente']);
        Route::get('/{pacienteUuid}/ultima-historia-medicina-general', [HistoriaClinicaController::class, 'obtenerUltimaHistoriaMedicinaGeneral']);
        
    });


    Route::prefix('usuarios')->group(function () {
        // ✅ Rutas especiales PRIMERO (antes de las rutas con parámetros)
        Route::get('/all', [UsuarioController::class, 'getAllForSync']);
        Route::get('/medicos', [UsuarioController::class, 'index'])->defaults('solo_medicos', true);
        Route::get('/activos', [UsuarioController::class, 'index'])->defaults('solo_activos', true);
        
        // ✅ Rutas CRUD básicas
        Route::get('/', [UsuarioController::class, 'index']);
        Route::post('/', [UsuarioController::class, 'store']);
        
        // ✅ Rutas con parámetros UUID AL FINAL
        Route::get('/{uuid}', [UsuarioController::class, 'show']);
        Route::put('/{uuid}', [UsuarioController::class, 'update']);
        Route::delete('/{uuid}', [UsuarioController::class, 'destroy']);
        Route::patch('/{uuid}/estado', [UsuarioController::class, 'cambiarEstado']);
        
        // ✅ Rutas para firma de médico
        Route::post('/{uuid}/firma', [UsuarioController::class, 'subirFirma']);
        Route::get('/{uuid}/firma', [UsuarioController::class, 'obtenerFirma']);
        Route::delete('/{uuid}/firma', [UsuarioController::class, 'eliminarFirma']);
    });

    
    // ================================
    // CITAS
    // ================================
Route::prefix('citas')->group(function () {
    // ================================
    // RUTAS BÁSICAS CRUD
    // ================================
    Route::get('/', [CitaController::class, 'index']);
    Route::post('/', [CitaController::class, 'store']);
    
    // ================================
    // RUTAS ESPECÍFICAS (ANTES de rutas con parámetros)
    // ================================
    Route::get('/del-dia', [CitaController::class, 'citasDelDia']);
    Route::post('/determinar-tipo-consulta', [CitaController::class, 'determinarTipoConsultaPrevio']);
    
    // ================================
    // RUTAS CON PARÁMETROS ESPECÍFICOS
    // ================================
    Route::get('/agenda/{agenda}', [CitaController::class, 'citasPorAgenda']);
    
    // ================================
    // RUTAS CON PARÁMETROS UUID GENÉRICOS (AL FINAL)
    // ================================
    Route::get('/{cita}', [CitaController::class, 'show']);
    Route::put('/{cita}', [CitaController::class, 'update']);
    Route::patch('/{cita}', [CitaController::class, 'update']); // Alternativa con PATCH
    Route::delete('/{cita}', [CitaController::class, 'destroy']);
    
    // ================================
    // CAMBIO DE ESTADO (múltiples métodos HTTP)
    // ================================
    Route::put('/{uuid}/estado', [CitaController::class, 'cambiarEstado']);
    Route::patch('/{uuid}/estado', [CitaController::class, 'cambiarEstado']);
    Route::post('/{uuid}/estado', [CitaController::class, 'cambiarEstado']);
});

Route::get('agendas/{agenda_uuid}/citas', [CitaController::class, 'citasDeAgenda']);
Route::get('/agendas/proceso-by-usuario/{usuario_uuid}', [AgendaController::class, 'getProcesoByUsuarioMedico']);
 Route::put('/citas/{uuid}/estado', [CitaController::class, 'cambiarEstado']);

 // ✅ Y ASEGÚRATE que estas líneas estén FUERA de cualquier grupo:
Route::put('/citas/{uuid}/estado', [CitaController::class, 'cambiarEstado']);
Route::patch('/citas/{uuid}/estado', [CitaController::class, 'cambiarEstado']);
Route::post('/citas/{uuid}/estado', [CitaController::class, 'cambiarEstado']);
Route::get('/pacientes/{uuid}/citas', [CitaController::class, 'citasPaciente']);
 // ================================
// AGENDAS
// ================================
Route::prefix('agendas')->group(function () {
    // ✅ Rutas sin parámetros (van primero)
    Route::get('/', [AgendaController::class, 'index']);
    Route::post('/', [AgendaController::class, 'store']);
    Route::get('/disponibles', [AgendaController::class, 'disponibles']);
    
    // ✅ MANTENGO AMBAS RUTAS (agregando validación a la primera)
    Route::get('/{uuid}/citas/count', [AgendaController::class, 'contarCitas'])
        ->where('uuid', '[0-9a-f-]{36}');  // ⬅️ SOLO AGREGUÉ ESTO
    
    Route::get('/{uuid}/citas', [AgendaController::class, 'getCitas'])
        ->where('uuid', '[0-9a-f-]{36}');

    Route::get('/{uuid}/citas/count', [AgendaController::class, 'getCitasCount'])
        ->where('uuid', '[0-9a-f-]{36}');
    
    Route::get('/{uuid}', [AgendaController::class, 'show'])
        ->where('uuid', '[0-9a-f-]{36}');
    
    // ✅ Solo agregué validación UUID
    Route::put('/{agenda}', [AgendaController::class, 'update'])
        ->where('agenda', '[0-9a-f-]{36}');  // ⬅️ AGREGADO
    
    Route::delete('/{agenda}', [AgendaController::class, 'destroy'])
        ->where('agenda', '[0-9a-f-]{36}');  // ⬅️ AGREGADO
    
    Route::get('/{agenda}/citas', [AgendaController::class, 'citasAgenda'])
        ->where('agenda', '[0-9a-f-]{36}');  // ⬅️ AGREGADO
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

      Route::get('/historias-clinicas/determinar-vista/{citaUuid}', 
        [HistoriaClinicaController::class, 'determinarVistaHistoriaClinica'])
        ->name('api.historias-clinicas.determinar-vista');
        Route::get('/historias-clinicas/debug/paciente/{paciente}/historias', [HistoriaClinicaController::class, 'debugPacienteHistorias']); // ✅ ESTA LÍNEA

    // ================================
    // HISTORIAS CLÍNICAS - VERSIÓN ACTUALIZADA
    // ================================
    Route::prefix('historias-clinicas')->group(function () {
            Route::get('/determinar-vista/{citaUuid}', [HistoriaClinicaController::class, 'determinarVistaHistoriaClinica']);
        // ================================
        // RUTAS BÁSICAS CRUD
        // ================================
        Route::get('/', [HistoriaClinicaController::class, 'index']);
        Route::post('/', [HistoriaClinicaController::class, 'store']);
        Route::get('/{historia}', [HistoriaClinicaController::class, 'show']);
        Route::put('/{historia}', [HistoriaClinicaController::class, 'update']);
        Route::delete('/{historia}', [HistoriaClinicaController::class, 'destroy']);
        
        
        // ================================
        // RUTAS DE BÚSQUEDA Y FILTROS
        // ================================
        Route::get('/search/paciente', [HistoriaClinicaController::class, 'buscarPorPaciente']);
        Route::get('/search/documento/{documento}', [HistoriaClinicaController::class, 'porDocumentoPaciente']);
        Route::get('/search/fecha', [HistoriaClinicaController::class, 'porFecha']);
        Route::get('/search/especialidad/{especialidad}', [HistoriaClinicaController::class, 'porEspecialidad']);
        Route::get('/search/medico/{medicoUuid}', [HistoriaClinicaController::class, 'porMedico']);
        Route::get('/search/diagnostico/{codigo}', [HistoriaClinicaController::class, 'porDiagnostico']);
        
        // ================================
        // RUTAS DE ESTADÍSTICAS
        // ================================
        Route::get('/stats/resumen', [HistoriaClinicaController::class, 'resumenEstadisticas']);
        Route::get('/stats/por-especialidad', [HistoriaClinicaController::class, 'estadisticasPorEspecialidad']);
        Route::get('/stats/por-medico', [HistoriaClinicaController::class, 'estadisticasPorMedico']);
        Route::get('/stats/diagnosticos-frecuentes', [HistoriaClinicaController::class, 'diagnosticosFrecuentes']);
        
        // ================================
        // RUTAS DE TIPOS DE HISTORIA
        // ================================
        Route::get('/primera-vez', [HistoriaClinicaController::class, 'primeraVez']);
        Route::get('/controles', [HistoriaClinicaController::class, 'controles']);
        Route::get('/urgencias', [HistoriaClinicaController::class, 'urgencias']);
        
        // ================================
        // COMPONENTES DE HISTORIA CLÍNICA
        // ================================
        
        // DIAGNÓSTICOS
        Route::prefix('{historia}/diagnosticos')->group(function () {
            Route::get('/', [HistoriaClinicaController::class, 'listarDiagnosticos']);
            Route::post('/', [HistoriaClinicaController::class, 'agregarDiagnostico']);
            Route::put('/{diagnostico}', [HistoriaClinicaController::class, 'actualizarDiagnostico']);
            Route::delete('/{diagnostico}', [HistoriaClinicaController::class, 'eliminarDiagnostico']);
            Route::patch('/{diagnostico}/tipo', [HistoriaClinicaController::class, 'cambiarTipoDiagnostico']);
        });
      
        // MEDICAMENTOS
        Route::prefix('{historia}/medicamentos')->group(function () {
            Route::get('/', [HistoriaClinicaController::class, 'listarMedicamentos']);
            Route::post('/', [HistoriaClinicaController::class, 'agregarMedicamento']);
            Route::put('/{medicamento}', [HistoriaClinicaController::class, 'actualizarMedicamento']);
            Route::delete('/{medicamento}', [HistoriaClinicaController::class, 'eliminarMedicamento']);
            Route::patch('/{medicamento}/estado', [HistoriaClinicaController::class, 'cambiarEstadoMedicamento']);
        });
        
        // REMISIONES
        Route::prefix('{historia}/remisiones')->group(function () {
            Route::get('/', [HistoriaClinicaController::class, 'listarRemisiones']);
            Route::post('/', [HistoriaClinicaController::class, 'agregarRemision']);
            Route::put('/{remision}', [HistoriaClinicaController::class, 'actualizarRemision']);
            Route::delete('/{remision}', [HistoriaClinicaController::class, 'eliminarRemision']);
            Route::patch('/{remision}/estado', [HistoriaClinicaController::class, 'cambiarEstadoRemision']);
        });
        
        // PROCEDIMIENTOS CUPS
        Route::prefix('{historia}/cups')->group(function () {
            Route::get('/', [HistoriaClinicaController::class, 'listarCups']);
            Route::post('/', [HistoriaClinicaController::class, 'agregarCups']);
            Route::put('/{cups}', [HistoriaClinicaController::class, 'actualizarCups']);
            Route::delete('/{cups}', [HistoriaClinicaController::class, 'eliminarCups']);
            Route::patch('/{cups}/estado', [HistoriaClinicaController::class, 'cambiarEstadoCups']);
        });
          // ✅ AGREGAR ESTA RUTA FALTANTE
    Route::get('/pacientes/{uuid}/historias-clinicas', [HistoriaClinicaController::class, 'historiasPaciente']);
        // ================================
        // ARCHIVOS Y DOCUMENTOS
        // ================================
        Route::prefix('{historia}/archivos')->group(function () {
            Route::get('/', [HistoriaClinicaController::class, 'listarArchivos']);
            Route::post('/pdf', [HistoriaClinicaController::class, 'subirPdf']);
            Route::post('/imagen', [HistoriaClinicaController::class, 'subirImagen']);
            Route::post('/documento', [HistoriaClinicaController::class, 'subirDocumento']);
            Route::get('/{archivo}/download', [HistoriaClinicaController::class, 'descargarArchivo']);
            Route::delete('/{archivo}', [HistoriaClinicaController::class, 'eliminarArchivo']);
        });
        
        // ================================
        // GENERACIÓN DE DOCUMENTOS
        // ================================
        Route::prefix('{historia}/generar')->group(function () {
            Route::get('/pdf', [HistoriaClinicaController::class, 'generarPdf']);
            Route::get('/receta', [HistoriaClinicaController::class, 'generarReceta']);
            Route::get('/orden-laboratorio', [HistoriaClinicaController::class, 'generarOrdenLaboratorio']);
            Route::get('/incapacidad', [HistoriaClinicaController::class, 'generarIncapacidad']);
            Route::get('/remision-pdf', [HistoriaClinicaController::class, 'generarRemisionPdf']);
        });
        
        // ================================
        // PLANTILLAS Y FORMATOS
        // ================================
        Route::prefix('{historia}/plantillas')->group(function () {
            Route::get('/soap', [HistoriaClinicaController::class, 'plantillaSOAP']);
            Route::get('/evolucion', [HistoriaClinicaController::class, 'plantillaEvolucion']);
            Route::get('/interconsulta', [HistoriaClinicaController::class, 'plantillaInterconsulta']);
            Route::post('/guardar-plantilla', [HistoriaClinicaController::class, 'guardarPlantilla']);
        });
        
        // ================================
        // VALIDACIONES Y AUDITORÍA
        // ================================
        Route::prefix('{historia}/auditoria')->group(function () {
            Route::get('/historial-cambios', [HistoriaClinicaController::class, 'historialCambios']);
            Route::get('/validaciones', [HistoriaClinicaController::class, 'validarHistoria']);
            Route::post('/firmar', [HistoriaClinicaController::class, 'firmarHistoria']);
            Route::post('/cerrar', [HistoriaClinicaController::class, 'cerrarHistoria']);
        });
        
        // ================================
        // SINCRONIZACIÓN OFFLINE
        // ================================
        Route::prefix('{historia}/sync')->group(function () {
            Route::get('/status', [HistoriaClinicaController::class, 'estadoSincronizacion']);
            Route::post('/offline', [HistoriaClinicaController::class, 'marcarOffline']);
            Route::post('/sincronizar', [HistoriaClinicaController::class, 'sincronizarHistoria']);
            Route::get('/conflictos', [HistoriaClinicaController::class, 'conflictosSincronizacion']);
        });
        
        // ================================
        // RUTAS ESPECIALES PARA CITAS
        // ================================
        Route::get('/cita/{citaUuid}', [HistoriaClinicaController::class, 'porCita']);
        // ✅ NUEVA RUTA
       
        Route::post('/cita/{citaUuid}/crear', [HistoriaClinicaController::class, 'crearDesdeCita']);
        Route::get('/agenda/{agendaUuid}/historias', [HistoriaClinicaController::class, 'porAgenda']);
        
        // ================================
        // REPORTES Y EXPORTACIÓN
        // ================================
        Route::prefix('reportes')->group(function () {
            Route::get('/excel', [HistoriaClinicaController::class, 'exportarExcel']);
            Route::get('/csv', [HistoriaClinicaController::class, 'exportarCsv']);
            Route::get('/estadisticas-periodo', [HistoriaClinicaController::class, 'reportePeriodo']);
            Route::get('/medicamentos-formulados', [HistoriaClinicaController::class, 'reporteMedicamentos']);
            Route::get('/diagnosticos-periodo', [HistoriaClinicaController::class, 'reporteDiagnosticos']);
        });
    });
    // ================================
// MEDICAMENTOS - ✅ AGREGAR ESTA SECCIÓN COMPLETA
// ================================
Route::prefix('medicamentos')->group(function () {
    Route::get('/', [MedicamentoController::class, 'index']);
    Route::get('/buscar', [MedicamentoController::class, 'buscar']);
    Route::post('/', [MedicamentoController::class, 'store']);
    Route::get('/activos', [MedicamentoController::class, 'activos']);
    Route::get('/{medicamento}', [MedicamentoController::class, 'show']);
    Route::put('/{medicamento}', [MedicamentoController::class, 'update']);
    Route::delete('/{medicamento}', [MedicamentoController::class, 'destroy']);
});

// ================================
// DIAGNÓSTICOS - ✅ AGREGAR ESTA SECCIÓN COMPLETA
// ================================
Route::prefix('diagnosticos')->group(function () {
    Route::get('/', [DiagnosticoController::class, 'index']);
    Route::get('/buscar', [DiagnosticoController::class, 'buscar']);
    Route::post('/', [DiagnosticoController::class, 'store']);
    Route::get('/activos', [DiagnosticoController::class, 'activos']);
    Route::get('/categoria/{categoria}', [DiagnosticoController::class, 'porCategoria']);
    Route::get('/{diagnostico}', [DiagnosticoController::class, 'show']);
    Route::put('/{diagnostico}', [DiagnosticoController::class, 'update']);
    Route::delete('/{diagnostico}', [DiagnosticoController::class, 'destroy']);
});

// ================================
// REMISIONES - ✅ AGREGAR ESTA SECCIÓN COMPLETA
// ================================
Route::prefix('remisiones')->group(function () {
    Route::get('/', [RemisionController::class, 'index']);
    Route::get('/buscar', [RemisionController::class, 'buscar']);
    Route::post('/', [RemisionController::class, 'store']);
    Route::get('/activas', [RemisionController::class, 'activas']);
    Route::get('/tipo/{tipo}', [RemisionController::class, 'porTipo']);
    Route::get('/{remision}', [RemisionController::class, 'show']);
    Route::put('/{remision}', [RemisionController::class, 'update']);
    Route::delete('/{remision}', [RemisionController::class, 'destroy']);
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
        Route::get('/sedes', [MasterDataController::class, 'sedes']);
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
        Route::get('/master-data/roles', [MasterDataController::class, 'roles']);
        Route::get('/medicamentos', [MasterDataController::class, 'medicamentos']);
        Route::get('/remisiones', [MasterDataController::class, 'remisiones']);
        Route::get('/cups', [MasterDataController::class, 'cups']);
        Route::get('/cups-contratados', [MasterDataController::class, 'cupsContratados']);
        Route::get('/contratos', [MasterDataController::class, 'contratos']);
        Route::get('/novedades', [MasterDataController::class, 'novedades']);
        Route::get('/auxiliares', [MasterDataController::class, 'auxiliares']);
        Route::get('/brigadas', [MasterDataController::class, 'brigadas']);
        Route::get('/procesos', [MasterDataController::class, 'procesos']);
        Route::get('/roles', [MasterDataController::class, 'roles']);
        Route::get('/usuarios-con-especialidad', [MasterDataController::class, 'usuariosConEspecialidad']);
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
