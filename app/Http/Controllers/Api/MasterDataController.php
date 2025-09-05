<?php
// app/Http/Controllers/Api/MasterDataController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\{
    Departamento, Municipio, Empresa, Regimen, TipoAfiliacion,
    ZonaResidencial, Raza, Escolaridad, TipoParentesco, TipoDocumento,
    Ocupacion, Especialidad, Diagnostico, Medicamento, Remision,
    Cups, CupsContratado, Contrato,Novedad, Auxiliar, Brigada, Proceso, Usuario
};

class MasterDataController extends Controller
{
    public function departamentos(): JsonResponse
    {
        $departamentos = Departamento::orderBy('nombre')->get();
        return response()->json([
            'success' => true,
            'data' => $departamentos->map(function ($depto) {
                return [
                    'uuid' => $depto->uuid,
                    'codigo' => $depto->codigo,
                    'nombre' => $depto->nombre
                ];
            })
        ]);
    }

    public function municipios(string $departamentoUuid): JsonResponse
    {
        $departamento = Departamento::where('uuid', $departamentoUuid)->firstOrFail();
        
        $municipios = Municipio::where('departamento_id', $departamento->id)
            ->orderBy('nombre')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $municipios->map(function ($municipio) {
                return [
                    'uuid' => $municipio->uuid,
                    'codigo' => $municipio->codigo,
                    'nombre' => $municipio->nombre
                ];
            })
        ]);
    }

    public function empresas(): JsonResponse
    {
        $empresas = Empresa::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $empresas->map(function ($empresa) {
                return [
                    'uuid' => $empresa->uuid,
                    'nombre' => $empresa->nombre,
                    'nit' => $empresa->nit,
                    'codigo_eapb' => $empresa->codigo_eapb,
                    'telefono' => $empresa->telefono,
                    'direccion' => $empresa->direccion
                ];
            })
        ]);
    }

    public function regimenes(): JsonResponse
    {
        $regimenes = Regimen::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $regimenes->map(function ($regimen) {
                return [
                    'uuid' => $regimen->uuid,
                    'nombre' => $regimen->nombre
                ];
            })
        ]);
    }

    public function tiposAfiliacion(): JsonResponse
    {
        $tipos = TipoAfiliacion::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $tipos->map(function ($tipo) {
                return [
                    'uuid' => $tipo->uuid,
                    'nombre' => $tipo->nombre
                ];
            })
        ]);
    }

    public function zonasResidenciales(): JsonResponse
    {
        $zonas = ZonaResidencial::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $zonas->map(function ($zona) {
                return [
                    'uuid' => $zona->uuid,
                    'nombre' => $zona->nombre,
                    'abreviacion' => $zona->abreviacion
                ];
            })
        ]);
    }

    public function razas(): JsonResponse
    {
        $razas = Raza::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $razas->map(function ($raza) {
                return [
                    'uuid' => $raza->uuid,
                    'nombre' => $raza->nombre
                ];
            })
        ]);
    }

    public function escolaridades(): JsonResponse
    {
        $escolaridades = Escolaridad::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $escolaridades->map(function ($escolaridad) {
                return [
                    'uuid' => $escolaridad->uuid,
                    'nombre' => $escolaridad->nombre
                ];
            })
        ]);
    }

    public function tiposParentesco(): JsonResponse
    {
        $tipos = TipoParentesco::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $tipos->map(function ($tipo) {
                return [
                    'uuid' => $tipo->uuid,
                    'nombre' => $tipo->nombre
                ];
            })
        ]);
    }

    public function tiposDocumento(): JsonResponse
    {
        $tipos = TipoDocumento::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $tipos->map(function ($tipo) {
                return [
                    'uuid' => $tipo->uuid,
                    'abreviacion' => $tipo->abreviacion,
                    'nombre' => $tipo->nombre
                ];
            })
        ]);
    }

    public function ocupaciones(): JsonResponse
    {
        $ocupaciones = Ocupacion::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $ocupaciones->map(function ($ocupacion) {
                return [
                    'uuid' => $ocupacion->uuid,
                    'codigo' => $ocupacion->codigo,
                    'nombre' => $ocupacion->nombre
                ];
            })
        ]);
    }

    public function especialidades(): JsonResponse
    {
        $especialidades = Especialidad::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $especialidades->map(function ($especialidad) {
                return [
                    'uuid' => $especialidad->uuid,
                    'nombre' => $especialidad->nombre
                ];
            })
        ]);
    }

    public function diagnosticos(Request $request): JsonResponse
    {
        $query = Diagnostico::query();
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                  ->orWhere('nombre', 'like', "%{$search}%");
            });
        }
        
        $diagnosticos = $query->orderBy('codigo')->limit(100)->get();
        
        return response()->json([
            'success' => true,
            'data' => $diagnosticos->map(function ($diagnostico) {
                return [
                    'uuid' => $diagnostico->uuid,
                    'codigo' => $diagnostico->codigo,
                    'nombre' => $diagnostico->nombre,
                    'categoria' => $diagnostico->categoria
                ];
            })
        ]);
    }

    public function medicamentos(Request $request): JsonResponse
    {
        $query = Medicamento::query();
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('nombre', 'like', "%{$search}%");
        }
        
        $medicamentos = $query->orderBy('nombre')->limit(100)->get();
        
        return response()->json([
            'success' => true,
            'data' => $medicamentos->map(function ($medicamento) {
                return [
                    'uuid' => $medicamento->uuid,
                    'nombre' => $medicamento->nombre
                ];
            })
        ]);
    }

    public function remisiones(): JsonResponse
    {
        $remisiones = Remision::orderBy('nombre')->get();
        
        return response()->json([
            'success' => true,
            'data' => $remisiones->map(function ($remision) {
                return [
                    'uuid' => $remision->uuid,
                    'codigo' => $remision->codigo,
                    'nombre' => $remision->nombre
                ];
            })
        ]);
    }

    public function cups(Request $request): JsonResponse
    {
        $query = Cups::query();
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                  ->orWhere('nombre', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('categoria_id')) {
            $query->where('categoria_cups_id', $request->categoria_id);
        }
        
        $cups = $query->orderBy('codigo')->limit(100)->get();
        
        return response()->json([
            'success' => true,
            'data' => $cups->map(function ($cup) {
                return [
                    'uuid' => $cup->uuid,
                    'codigo' => $cup->codigo,
                    'nombre' => $cup->nombre,
                    'categoria' => $cup->categoria?->nombre
                ];
            })
        ]);
    }

    public function cupsContratados(Request $request): JsonResponse
    {
        $query = CupsContratado::with(['cups', 'contrato']);
        
        if ($request->filled('contrato_id')) {
            $query->where('contrato_id', $request->contrato_id);
        }
        
        $cupsContratados = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $cupsContratados->map(function ($cupsContratado) {
                return [
                    'uuid' => $cupsContratado->uuid,
                    'tarifa' => $cupsContratado->tarifa,
                    'cups' => [
                        'uuid' => $cupsContratado->cups->uuid,
                        'codigo' => $cupsContratado->cups->codigo,
                        'nombre' => $cupsContratado->cups->nombre
                    ],
                    'contrato' => [
                        'uuid' => $cupsContratado->contrato->uuid,
                        'numero' => $cupsContratado->contrato->numero,
                        'empresa' => $cupsContratado->contrato->empresa->nombre
                    ]
                ];
            })
        ]);
    }

    public function contratos(): JsonResponse
    {
        $contratos = Contrato::with('empresa')->orderBy('numero')->get();
        
        return response()->json([
            'success' => true,
            'data' => $contratos->map(function ($contrato) {
                return [
                    'uuid' => $contrato->uuid,
                    'numero' => $contrato->numero,
                    'fecha_inicio' => $contrato->fecha_inicio?->format('Y-m-d'),
                    'fecha_fin' => $contrato->fecha_fin?->format('Y-m-d'),
                    'empresa' => [
                        'uuid' => $contrato->empresa->uuid,
                        'nombre' => $contrato->empresa->nombre,
                        'nit' => $contrato->empresa->nit
                    ]
                ];
            })
        ]);
    }

    public function usuariosConEspecialidad(): JsonResponse
{
    try {
        $usuarios = Usuario::with(['especialidad', 'estado', 'sede'])
            ->whereNotNull('especialidad_id')
            ->whereHas('especialidad')
            ->whereHas('estado', function ($q) {
                $q->where('nombre', 'ACTIVO');
            })
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $usuarios->map(function ($usuario) {
                return [
                    'id' => $usuario->id,
                    'uuid' => $usuario->uuid,
                    'documento' => $usuario->documento,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'nombre_completo' => $usuario->nombre_completo,
                    'login' => $usuario->login,
                    'especialidad_id' => $usuario->especialidad_id,
                    'especialidad' => [
                        'id' => $usuario->especialidad->id,
                        'uuid' => $usuario->especialidad->uuid,
                        'nombre' => $usuario->especialidad->nombre
                    ],
                    'sede_id' => $usuario->sede_id,
                    'sede' => [
                        'id' => $usuario->sede->id,
                        'nombre' => $usuario->sede->nombre
                    ]
                ];
            })
        ]);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error obteniendo usuarios con especialidad: ' . $e->getMessage()
        ], 500);
    }
}

    public function allMasterData(): JsonResponse
    {
         return response()->json([
        'success' => true,
        'data' => [
            'departamentos' => $this->getDepartamentosData(),
            'empresas' => $this->getEmpresasData(),
            'regimenes' => $this->getRegimenesData(),
            'tipos_afiliacion' => $this->getTiposAfiliacionData(),
            'zonas_residenciales' => $this->getZonasResidencialesData(),
            'razas' => $this->getRazasData(),
            'escolaridades' => $this->getEscolaridadesData(),
            'tipos_parentesco' => $this->getTiposParentescoData(),
            'tipos_documento' => $this->getTiposDocumentoData(),
            'ocupaciones' => $this->getOcupacionesData(),
            'especialidades' => $this->getEspecialidadesData(),
            'contratos' => $this->getContratosData(),
            'novedades' => $this->getNovedadesData(),
            'auxiliares' => $this->getAuxiliaresData(),
            'brigadas' => $this->getBrigadasData(),
            'procesos' => $this->getProcesosData(),
            'usuarios_con_especialidad' => $this->getUsuariosConEspecialidadData(), 
            'last_updated' => now()->toISOString()
        ]
    ]);
    }

    // Métodos privados para obtener datos
    private function getDepartamentosData()
    {
        return Departamento::with('municipios')->orderBy('nombre')->get()->map(function ($depto) {
            return [
                'uuid' => $depto->uuid,
                'codigo' => $depto->codigo,
                'nombre' => $depto->nombre,
                'municipios' => $depto->municipios->map(function ($municipio) {
                    return [
                        'uuid' => $municipio->uuid,
                        'codigo' => $municipio->codigo,
                        'nombre' => $municipio->nombre
                    ];
                })
            ];
        });
    }

       private function getEmpresasData()
    {
        return Empresa::orderBy('nombre')->get()->map(function ($empresa) {
            return [
                'uuid' => $empresa->uuid,
                'nombre' => $empresa->nombre,
                'nit' => $empresa->nit,
                'codigo_eapb' => $empresa->codigo_eapb,
                'telefono' => $empresa->telefono,
                'direccion' => $empresa->direccion
            ];
        });
    }

    private function getRegimenesData()
    {
        return Regimen::orderBy('nombre')->get()->map(function ($regimen) {
            return [
                'uuid' => $regimen->uuid,
                'nombre' => $regimen->nombre
            ];
        });
    }

    private function getTiposAfiliacionData()
    {
        return TipoAfiliacion::orderBy('nombre')->get()->map(function ($tipo) {
            return [
                'uuid' => $tipo->uuid,
                'nombre' => $tipo->nombre
            ];
        });
    }

    private function getZonasResidencialesData()
    {
        return ZonaResidencial::orderBy('nombre')->get()->map(function ($zona) {
            return [
                'uuid' => $zona->uuid,
                'nombre' => $zona->nombre,
                'abreviacion' => $zona->abreviacion
            ];
        });
    }

    private function getRazasData()
    {
        return Raza::orderBy('nombre')->get()->map(function ($raza) {
            return [
                'uuid' => $raza->uuid,
                'nombre' => $raza->nombre
            ];
        });
    }

    private function getEscolaridadesData()
    {
        return Escolaridad::orderBy('nombre')->get()->map(function ($escolaridad) {
            return [
                'uuid' => $escolaridad->uuid,
                'nombre' => $escolaridad->nombre
            ];
        });
    }

    private function getTiposParentescoData()
    {
        return TipoParentesco::orderBy('nombre')->get()->map(function ($tipo) {
            return [
                'uuid' => $tipo->uuid,
                'nombre' => $tipo->nombre
            ];
        });
    }

    private function getTiposDocumentoData()
    {
        return TipoDocumento::orderBy('nombre')->get()->map(function ($tipo) {
            return [
                'uuid' => $tipo->uuid,
                'abreviacion' => $tipo->abreviacion,
                'nombre' => $tipo->nombre
            ];
        });
    }

    private function getOcupacionesData()
    {
        return Ocupacion::orderBy('nombre')->get()->map(function ($ocupacion) {
            return [
                'uuid' => $ocupacion->uuid,
                'codigo' => $ocupacion->codigo,
                'nombre' => $ocupacion->nombre
            ];
        });
    }

    private function getEspecialidadesData()
    {
        return Especialidad::orderBy('nombre')->get()->map(function ($especialidad) {
            return [
                'uuid' => $especialidad->uuid,
                'nombre' => $especialidad->nombre
            ];
        });
    }
    // Métodos privados adicionales
private function getNovedadesData()
{
    return Novedad::orderBy('tipo_novedad')->get()->map(function ($novedad) {
        return [
            'uuid' => $novedad->uuid,
            'tipo_novedad' => $novedad->tipo_novedad
        ];
    });
}

private function getAuxiliaresData()
{
    return Auxiliar::orderBy('nombre')->get()->map(function ($auxiliar) {
        return [
            'uuid' => $auxiliar->uuid,
            'nombre' => $auxiliar->nombre
        ];
    });
}

private function getBrigadasData()
{
    return Brigada::orderBy('nombre')->get()->map(function ($brigada) {
        return [
            'uuid' => $brigada->uuid,
            'nombre' => $brigada->nombre
        ];
    });
}

    private function getProcesosData()
    {
        return Proceso::orderBy('nombre')->get()->map(function ($proceso) {
            return [
                'uuid' => $proceso->uuid,
                'nombre' => $proceso->nombre,
                'n_cups' => $proceso->n_cups
            ];
        });
    }

    private function getContratosData()
    {
        return Contrato::with('empresa')->orderBy('numero')->get()->map(function ($contrato) {
            return [
                'uuid' => $contrato->uuid,
                'numero' => $contrato->numero,
                'fecha_inicio' => $contrato->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $contrato->fecha_fin?->format('Y-m-d'),
                'empresa' => [
                    'uuid' => $contrato->empresa->uuid,
                    'nombre' => $contrato->empresa->nombre,
                    'nit' => $contrato->empresa->nit
                ]
            ];
        });
    }

    private function getUsuariosConEspecialidadData()
{
    return Usuario::with(['especialidad', 'estado', 'sede'])
        ->whereNotNull('especialidad_id')
        ->whereHas('especialidad')
        ->whereHas('estado', function ($q) {
            $q->where('nombre', 'ACTIVO');
        })
        ->orderBy('nombre')
        ->get()
        ->map(function ($usuario) {
            return [
                'id' => $usuario->id,
                'uuid' => $usuario->uuid,
                'documento' => $usuario->documento,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'nombre_completo' => $usuario->nombre_completo,
                'login' => $usuario->login,
                'especialidad_id' => $usuario->especialidad_id,
                'especialidad' => [
                    'id' => $usuario->especialidad->id,
                    'uuid' => $usuario->especialidad->uuid,
                    'nombre' => $usuario->especialidad->nombre
                ],
                'sede_id' => $usuario->sede_id,
                'sede' => [
                    'id' => $usuario->sede->id,
                    'nombre' => $usuario->sede->nombre
                ]
            ];
        });
}
    
    public function novedades(): JsonResponse
{
    $novedades = Novedad::orderBy('tipo_novedad')->get();
    
    return response()->json([
        'success' => true,
        'data' => $novedades->map(function ($novedad) {
            return [
                'uuid' => $novedad->uuid,
                'tipo_novedad' => $novedad->tipo_novedad
            ];
        })
    ]);
}

public function auxiliares(): JsonResponse
{
    $auxiliares = Auxiliar::orderBy('nombre')->get();
    
    return response()->json([
        'success' => true,
        'data' => $auxiliares->map(function ($auxiliar) {
            return [
                'uuid' => $auxiliar->uuid,
                'nombre' => $auxiliar->nombre
            ];
        })
    ]);
}

public function brigadas(): JsonResponse
{
    $brigadas = Brigada::orderBy('nombre')->get();
    
    return response()->json([
        'success' => true,
        'data' => $brigadas->map(function ($brigada) {
            return [
                'uuid' => $brigada->uuid,
                'nombre' => $brigada->nombre
            ];
        })
    ]);
}
public function procesos(): JsonResponse
{
    $procesos = Proceso::orderBy('nombre')->get();
    
    return response()->json([
        'success' => true,
        'data' => $procesos->map(function ($proceso) {
            return [
                'uuid' => $proceso->uuid,
                'nombre' => $proceso->nombre,
                'n_cups' => $proceso->n_cups
            ];
        })
    ]);

}
}