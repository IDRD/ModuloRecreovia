<?php

namespace App\Http\Controllers\Recreovia;

use App\Modulos\Parques\Localidad;
use App\Modulos\Recreovia\GrupoPoblacional;
use App\Modulos\Recreovia\Jornada;
use App\Http\Controllers\Controller;
use App\Modulos\Recreovia\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReporteAsistenciaController extends Controller
{
    public function index(Request $request) {
        $request->flash();

        if($request->isMethod('get')) {
            $sesiones = null;
        } else {
            $qb = Reporte::with('sesiones');
            $qb = $this->aplicarFiltros($qb, $request);

            $elementos = $qb->where('Estado', 'Finalizado')
                ->whereNull('deleted_at')
                ->orderBy('Id', 'DESC')
                ->get();

            $sesiones = collect([]);

            foreach ($elementos as $reporte)
            {
                foreach ($reporte->sesiones as $sesion)
                {
                    $exists = $sesiones->search(function($item, $key) use ($sesion)
                    {
                        return $item->Id == $sesion->Id;
                    }, true);

                    if (is_bool($exists))
                        $sesiones->push($sesion);
                }
            }
        }

        $data = [
            'localidades' => Localidad::with('upz.puntos')->get(),
            'jornadas' => Jornada::all(),
            'gruposPoblacionales' => GrupoPoblacional::all(),
            'seccion' => 'Reporte asistencia y participación',
            'sesiones' => $sesiones
        ];

        return view('idrd.recreovia.reporte-asistencia', $data);
    }

    private function aplicarFiltros(Builder $qb, $request) {
        if($request->has('id_jornada'))
        {
            $qb->whereHas('cronograma', function($query) use ($request) {
                $query->whereIn('Id_Jornada', $request->input('id_jornada'));
            });
        }

        if($request->has('id_localidad'))
        {
            $qb->whereHas('punto', function($query) use ($request) {
                $query->whereIn('Id_Localidad', $request->input('id_localidad'));
            });
        }

        if($request->has('id_upz'))
        {
            $qb->whereHas('punto', function($query) use ($request) {
                $query->whereIn('Id_Upz', $request->input('id_upz'));
            });
        }

        if($request->has('id_punto'))
        {
            $qb->whereHas('punto', function($query) use ($request) {
                $query->whereIn('Id_Punto', $request->input('id_punto'));
            });
        }

        if($request->input('fecha_inicio') || $request->input('fecha_fin'))
        {
            $qb->whereHas('sesiones', function($query) use ($request) {
                if ($request->input('fecha_inicio'))
                    $query->where('Fecha', '>=', $request->input('fecha_inicio'));

                if ($request->input('fecha_fin'))
                    $query->where('Fecha', '<=', $request->input('fecha_fin'));
            });
        }

        return $qb;
    }
}
