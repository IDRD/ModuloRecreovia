<?php

namespace App\Http\Controllers\Recreovia;

use App\Http\Controllers\Controller;
use App\Modulos\Recreovia\Cronograma;
use App\Modulos\Recreovia\ProductoNoConforme;
use App\Modulos\Recreovia\CalificacionDelServicio;
use App\Modulos\Recreovia\GrupoPoblacional;
use App\Modulos\Parques\Localidad;
use App\Modulos\Recreovia\Recreopersona;
use App\Modulos\Recreovia\Sesion;
use App\Http\Requests\GuardarSesionGestor;
use App\Http\Requests\GuardarProductoNoConforme;
use App\Http\Requests\GuardarCalificacionDelServicio;
use Illuminate\Http\Request;
use Mail;

class SesionController extends Controller {

	public function __construct()
	{
		if (isset($_SESSION['Usuario']))
			$this->usuario = $_SESSION['Usuario'];
	}

	public function crearSesionesGestor(Request $request, $id_cronograma)
	{
		$profesores = collect();
		$localidades = Localidad::with('profesores', 'profesores.persona')->has('profesores')->get();
		foreach ($localidades as $localidad)
		{
			foreach ($localidad->profesores as $profesor)
			{
				$profesores->push($profesor);
			}
		}

		$filtro_profesores = $profesores->unique('Id_Recreopersona');
		$filtro_profesores = $filtro_profesores->sortBy('persona.Primer_Apellido');

		$cronograma = Cronograma::with(['punto', 'punto.localidad.profesores.persona', 'jornada', 'sesiones', 'sesiones.profesor'])
											->find($id_cronograma);

		$formulario = [
			'titulo' => 'Crear o editar sesiones',
			'cronograma' => $cronograma,
			'profesores' => $filtro_profesores, //solo se usa para el permiso gestion_global_de_sesiones
			'sesion' => null,
			'status' => session('status')
		];

		$datos = [
			'seccion' => 'Programación',
			'formulario' => view('idrd.recreovia.formulario-sesiones', $formulario)
		];

		return view('form', $datos);
	}

	public function editarSesionesGestor(Request $request, $id_cronograma, $id_sesion)
	{
		$profesores = collect();
		$localidades = Localidad::with('profesores', 'profesores.persona')->has('profesores')->get();
		foreach ($localidades as $localidad)
		{
			foreach ($localidad->profesores as $profesor)
			{
				$profesores->push($profesor);
			}
		}

		$filtro_profesores = $profesores->unique('Id_Recreopersona');
		$filtro_profesores = $filtro_profesores->sortBy('persona.Primer_Apellido');

		$cronograma = Cronograma::with(['punto', 'punto.localidad.profesores.persona', 'jornada', 'sesiones'])->find($id_cronograma);

		$sesion = Sesion::find($id_sesion);

		$formulario = [
			'titulo' => 'Crear o editar sesiones',
			'cronograma' => $cronograma,
			'profesores' => $filtro_profesores, //solo se usa para el permiso gestion_global_de_sesiones
			'sesion' => $sesion,
			'status' => session('status')
		];

		$datos = [
			'seccion' => 'Programación',
			'formulario' => view('idrd.recreovia.formulario-sesiones', $formulario)
		];

		return view('form', $datos);
	}

	public function editarSesionProfesor(Request $request, $id_sesion)
	{
		$sesion = Sesion::with('cronograma', 'cronograma.punto', 'cronograma.jornada', 'gruposPoblacionales', 'ProductoNoConforme', 'profesor', 'acompanantes')->find($id_sesion);
		$gruposPoblacionales = GrupoPoblacional::get();

		$formulario = [
			'titulo' => 'Sesion',
			'sesion' => $sesion,
			'gruposPoblacionales' => $gruposPoblacionales,
			'tipo' => 'profesor',
            'gestores' => collect(),
			'area' => session('area'),
			'status' => session('status')
		];

		$datos = [
			'seccion' => 'Sesiones profesor',
			'formulario' => view('idrd.recreovia.formulario-sesiones-detalles', $formulario)
		];

		return view('form', $datos);
	}

	public function editarSesionGestor(Request $request, $id_sesion)
	{
		$sesion = Sesion::with('cronograma', 'cronograma.punto', 'cronograma.jornada', 'gruposPoblacionales', 'ProductoNoConforme', 'profesor', 'acompanantes')->find($id_sesion);
		$gruposPoblacionales = GrupoPoblacional::get();

        $gestores = collect();
		$localidades = Localidad::with('gestores.persona')->has('gestores')->get();
        foreach ($localidades as $localidad)
        {
            foreach ($localidad->gestores as $gestor)
            {
                $gestores->push($gestor);
            }
        }

		$formulario = [
			'titulo' => 'Sesion',
			'sesion' => $sesion,
			'gruposPoblacionales' => $gruposPoblacionales,
			'tipo' => 'gestor',
            'gestores' => $gestores,
			'area' => session('area'),
			'status' => session('status')
		];

		$datos = [
			'seccion' => 'Sesiones gestor',
			'formulario' => view('idrd.recreovia.formulario-sesiones-detalles', $formulario)
		];

		return view('form', $datos);
	}

	public function eliminarSesionesGestor(Request $request, $id_cronograma, $id_sesion)
	{
		$sesion = Sesion::find($id_sesion);
		$sesion->delete();

		return redirect('/gestores/'.$id_cronograma.'/sesiones')->with(['status' => 'success']);
	}

	public function procesarGestor(GuardarSesionGestor $request)
	{
		$notificar = false;

		if ($request->input('Id') == 0)
		{
			$sesion = new Sesion;
			$nuevo = true;
			$notificar = true;
		} else {
			$sesion = Sesion::find($request->input('Id'));
			if ($sesion->Id_Recreopersona != $request->input('Id_Recreopersona'))
			{
				$notificar = true;
				//si el estado de ejecucion es realizado no es necesario cambiar el estado de ejecución
				$sesion->Estado_Ejecucion = $sesion->Estado_Ejecucion != 'Realizado' ? 'Reasignado' : $sesion->Estado_Ejecucion;
			}

			$nuevo = false;
		}

		$sesion->Id_Cronograma = $request->input('Id_Cronograma');
		$sesion->Id_Recreopersona = $request->input('Id_Recreopersona') == '' ? null : $request->input('Id_Recreopersona');
		$sesion->Objetivo_General = $request->input('Objetivo_General');
		$sesion->Observaciones = $request->input('Observaciones');
		$sesion->Fecha = $request->input('Fecha');
		$sesion->Inicio = $request->input('Inicio');
		$sesion->Fin = $request->input('Fin');
		$sesion->Estado = !$nuevo ? $sesion->Estado : 'Pendiente';
		$sesion->Asumida_Por_El_Gestor = !$nuevo ? $sesion->Asumida_Por_El_Gestor : null;
		$sesion->save();

		if(array_key_exists('Acompanantes', $request->input()))
			$sesion->acompanantes()->sync($request->input('Acompanantes'));

		if ($notificar && $sesion->profesor)
			$this->notificar($sesion, 'profesor');

		return redirect('/gestores/'.$request->input('Id_Cronograma').'/sesiones')->with(['status' => 'success']);
	}

	public function procesar(Request $request)
	{
		$notificar = false;
		$sesion = Sesion::find($request->input('Id'));

		if ($sesion->Estado != $request->input('Estado'))
			$notificar = true;

		$sesion->Objetivos_Especificos = $request->input('Objetivos_Especificos');
		$sesion->Objetivos_Especificos_1 = $request->input('Objetivos_Especificos_1');
		$sesion->Objetivos_Especificos_2 = $request->input('Objetivos_Especificos_2');
		$sesion->Objetivos_Especificos_3 = $request->input('Objetivos_Especificos_3');
		$sesion->Metodologia_Aplicar = $request->input('Metodologia_Aplicar');
		$sesion->Recursos = $request->input('Recursos');
		$sesion->Fase_Inicial = $request->input('Fase_Inicial');
		$sesion->Tiempo_Inicial = $request->input('Tiempo_Inicial');
		$sesion->Fase_Central = $request->input('Fase_Central');
		$sesion->Tiempo_Central = $request->input('Tiempo_Central');
		$sesion->Fase_Final = $request->input('Fase_Final');
		$sesion->Tiempo_Final = $request->input('Tiempo_Final');
		$sesion->Observaciones = $request->input('Observaciones');
		$sesion->Estado = $request->has('Estado') ? $request->input('Estado') : $sesion->Estado;
		$sesion->Asumida_Por_El_Gestor = $request->has('Asumida_Por_El_Gestor') ? $request->input('Asumida_Por_El_Gestor') : null;

		if ($request->input('origen') == 'profesor')
		{
			switch ($sesion->Estado)
			{
				case 'Aprobado':
				case 'Finalizado':
				break;

				case 'Pendiente':
				case 'Diligenciado':
				case 'Rechazado':
				case 'Corregir':
					$sesion->Estado = 'Diligenciado';
				default:
				break;
			}
		}

		if ($request->input('origen') == 'gestor')
		{
			switch ($sesion->Estado)
			{
				case 'Diligenciado':
				case 'Finalizado':
				case 'Pendiente':
				case 'Corregir':
				break;

				case 'Aprobado':
					$sesion->Estado_Ejecucion = 'Pendiente';
				break;

				case 'Rechazado':
					$sesion->Metodologia_Aplicar = '';
					$sesion->Recursos = '';
					$sesion->Fase_Inicial = '';
					$sesion->Fase_Central = '';
					$sesion->Fase_Final = '';
				default:
				break;
			}
		}

		$sesion->save();

		if($request->input('origen') == 'profesor')
		{
			if ($notificar)
				$this->notificar($sesion, 'gestor');

			return redirect('/profesores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Detalles']);

		} else if($request->input('origen') == 'gestor') {

			if ($notificar)
				$this->notificar($sesion, 'profesor');

			return redirect('/gestores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Detalles']);
		}
	}

	public function asistencia(Request $request)
	{
		$gruposPoblacionales = GrupoPoblacional::get();
		$sesion = Sesion::find($request->input('Id'));
		$sesion->gruposPoblacionales()->detach();

		foreach ($gruposPoblacionales as $grupo)
		{
			//participantes masculinos
			$sesion->gruposPoblacionales()->save($grupo, [
				'Genero' => 'M',
				'Grupo_Asistencia' => 'Participantes',
				'Cantidad' => $request->has('participantes-m-'.$grupo['Id']) ? $request->input('participantes-m-'.$grupo['Id']) : 0
			]);

			//participantes femeninos
			$sesion->gruposPoblacionales()->save($grupo, [
				'Genero' => 'F',
				'Grupo_Asistencia' => 'Participantes',
				'Cantidad' => $request->has('participantes-f-'.$grupo['Id']) ? $request->input('participantes-f-'.$grupo['Id']) : 0
			]);

			//asistentes masculinos
			$sesion->gruposPoblacionales()->save($grupo, [
				'Genero' => 'M',
				'Grupo_Asistencia' => 'Asistentes',
				'Cantidad' => $request->has('asistentes-m-'.$grupo['Id']) ? $request->input('asistentes-m-'.$grupo['Id']) : 0
			]);

			//asistentes femeninos
			$sesion->gruposPoblacionales()->save($grupo, [
				'Genero' => 'F',
				'Grupo_Asistencia' => 'Asistentes',
				'Cantidad' => $request->has('asistentes-f-'.$grupo['Id']) ? $request->input('asistentes-f-'.$grupo['Id']) : 0
			]);
		}
		$sesion->save();

		if($request->input('origen') == 'profesor')
		{
			return redirect('/profesores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Asistencia']);
		} else if($request->input('origen') == 'gestor') {
			return redirect('/gestores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Asistencia']);
		}
	}

	public function productoNoConforme(GuardarProductoNoConforme $request)
	{
		$sesion = Sesion::with('productoNoConforme')->find($request->input('Id'));
		$Requisito_Detalle = '';

		if ($sesion->productoNoConforme)
			$productoNoConforme = $sesion->productoNoConforme;
		else
			$productoNoConforme = new ProductoNoConforme;

		$productoNoConforme['Id_Sesion'] = $request->input('Id');
		$productoNoConforme['Requisito_1'] = $request->input('Requisito_1');
		$productoNoConforme['Requisito_2'] = $request->input('Requisito_2');
		$productoNoConforme['Requisito_3'] = $request->input('Requisito_3');
		$productoNoConforme['Requisito_4'] = $request->input('Requisito_4');
		$productoNoConforme['Requisito_5'] = $request->input('Requisito_5');
		$productoNoConforme['Requisito_6'] = $request->input('Requisito_6');
		$productoNoConforme['Requisito_7'] = $request->input('Requisito_7');
		$productoNoConforme['Requisito_8'] = $request->input('Requisito_8');
		$productoNoConforme['Requisito_9'] = $request->input('Requisito_9');
		$productoNoConforme['Requisito_10'] = $request->input('Requisito_10');
		$productoNoConforme['Requisito_11'] = $request->input('Requisito_11');
		$productoNoConforme['Requisito_12'] = $request->input('Requisito_12');
		$productoNoConforme['Requisito_13'] = $request->input('Requisito_13');
		$productoNoConforme['Descripcion_De_La_No_Conformidad'] = $request->input('Descripcion_De_La_No_Conformidad');
		$productoNoConforme['Descripcion_De_La_Accion_Tomada'] = $request->input('Descripcion_De_La_Accion_Tomada');
		$productoNoConforme['Tratamiento'] = $request->input('Tratamiento');

		$productoNoConforme->save();

		if($request->input('origen') == 'profesor')
		{
			return redirect('/profesores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Producto_No_Conforme']);
		} else if($request->input('origen') == 'gestor') {
			return redirect('/gestores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Producto_No_Conforme']);
		}
	}

	public function calificacionDelServicio(GuardarCalificacionDelServicio $request)
	{
		$sesion = Sesion::with('calificacionDelServicio')->find($request->input('Id'));

		if ($sesion->calificacionDelServicio)
			$calificacion = $sesion->calificacionDelServicio;
		else
			$calificacion = new CalificacionDelServicio;

		$calificacion['Id_Sesion'] = $request->input('Id');
		$calificacion['Puntualidad_PAF'] = $request->input('Puntualidad_PAF');
		$calificacion['Tiempo_De_La_Sesion'] = $request->input('Tiempo_De_La_Sesion');
		$calificacion['Escenario_Y_Montaje'] = $request->input('Escenario_Y_Montaje');
		$calificacion['Cumplimiento_Del_Objetivo'] = $request->input('Cumplimiento_Del_Objetivo');
		$calificacion['Variedad_Y_Creatividad'] = $request->input('Variedad_Y_Creatividad');
		$calificacion['Imagen_Institucional'] = $request->input('Imagen_Institucional');
		$calificacion['Divulgacion'] = $request->input('Divulgacion');
		$calificacion['Seguridad'] = $request->input('Seguridad');
		$calificacion['Nombre'] = $request->input('Nombre');
		$calificacion['Telefono'] = $request->input('Telefono');
		$calificacion['Correo'] = $request->input('Correo');

		$calificacion->save();

		if($request->input('origen') == 'profesor')
		{
			return redirect('/profesores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Calificacion_Del_Servicio']);
		} else if($request->input('origen') == 'gestor') {
			return redirect('/gestores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Calificacion_Del_Servicio']);
		}
	}

	public function eliminarProductoNoConforme(Request $request, $id, $tipo)
	{
		$productoNoConforme = ProductoNoConforme::with('sesion')->find($id);
		$sesion = $productoNoConforme->sesion;

		$productoNoConforme->delete();

		if($tipo == 'profesor')
		{
			return redirect('/profesores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Producto_No_Conforme']);
		} else if($tipo == 'gestor') {
			return redirect('/gestores/sesiones/'.$sesion['Id'].'/editar')->with(['status' => 'success', 'area' => 'Producto_No_Conforme']);
		}
	}

	public function sesionesProfesor(Request $request)
	{
		$request->flash();

		if ($request->isMethod('get'))
		{
			$qb = null;
			$elementos = $qb;
		} else {
			$qb = Sesion::with('cronograma', 'cronograma.punto', 'cronograma.jornada', 'profesor.persona')
                                ->whereHas('cronograma', function ($query) {
                                    $query->whereNull('deleted_at')
                                        ->whereHas('punto', function($query_inner_punto) {
                                            $query_inner_punto->whereNull('deleted_at');
                                        });
                                })
								->where('Id_Recreopersona', $this->usuario['Recreopersona']->Id_Recreopersona);
			$qb = $this->aplicarFiltro($qb, $request);


			$elementos = $qb->whereNull('deleted_at')
							->orderBy('Id', 'DESC')
							->get();
		}

		$lista = [
			'titulo' => 'Sesiones profesor',
	        'elementos' => $elementos,
	        'status' => session('status')
		];

		$datos = [
			'seccion' => 'Sesiones profesor',
			'lista'	=> view('idrd.recreovia.lista-sesiones-profesor', $lista)
		];

		return view('list', $datos);
	}

	public function sesionesGestor(Request $request)
	{
		$request->flash();

		if ($request->isMethod('get'))
		{
			$qb = null;
			$elementos = $qb;
		} else {
			$qb = Sesion::with('cronograma', 'cronograma.punto', 'cronograma.jornada', 'profesor.persona')
						->whereHas('cronograma', function($query)
						{
							$query->where('Id_Recreopersona', $this->usuario['Recreopersona']->Id_Recreopersona)
                                ->whereNull('deleted_at')
                                ->whereHas('punto', function($query_inner_punto) {
                                    $query_inner_punto->whereNull('deleted_at');
                                });
						});
			$qb = $this->aplicarFiltro($qb, $request);

			$elementos = $qb->whereNull('deleted_at')
							->orderBy('Id', 'DESC')
							->get();
		}

		$lista = [
			'titulo' => 'Sesiones gestor',
	        'elementos' => $elementos,
	        'status' => session('status')
		];

		$datos = [
			'seccion' => 'Sesiones gestor',
			'lista'	=> view('idrd.recreovia.lista-sesiones-gestor', $lista)
		];

		return view('list', $datos);
	}

	public function buscar(Request $request)
	{
		$request->flash();
		$codigos = collect(array_filter(explode(',', $request->input('codigos'))));
		$codigos_preparados = $codigos->map(function($item, $key){
			return sprintf("'%s'", strtoupper(trim($item)));
		});

		if(!$codigos_preparados->isEmpty())
		{
			$elementos = Sesion::with('cronograma', 'cronograma.punto', 'cronograma.jornada', 'profesor.persona')
								->whereRaw('concat("S", LPAD(Id, 5, "0")) IN ('.$codigos_preparados->implode(',').')')
								->whereNull('deleted_at')
								->orderBy('Id', 'DESC')
								->get();
		} else {
			$elementos = null;
		}

		$lista = [
			'titulo' => 'Buscador de sesiones',
			'elementos' => $elementos,
			'status' => session('status')
		];

		$datos = [
			'seccion' => 'Buscador de sesiones',
			'lista'	=> view('idrd.recreovia.buscador-sesiones', $lista)
		];

		return view('list', $datos);
	}

	private function notificar($sesion, $to)
	{
		try {
			$sesion = Sesion::with('cronograma', 'cronograma.gestor', 'cronograma.gestor.persona', 'cronograma.punto', 'cronograma.jornada', 'profesor', 'profesor.persona')
							->whereNull('deleted_at')
							->find($sesion['Id']);

			$profesor = $sesion->profesor;
			$gestor = $sesion->cronograma->gestor;

			switch ($to) {
				case 'gestor':
						$destinatario = $gestor;
						$notificacion = 'La sesión de '.$sesion->toString().' la cual le fue asignada al profesor '.$profesor->persona->toString().' tiene actualmente el estado: '.$sesion->Estado.'. Le recomendamos ingresar al modulo de Recreovía del sistema de información misional (SIM) para continuar el proceso.';
					break;
				case 'profesor':
						$destinatario = $profesor;
						$notificacion = 'La sesión de '.$sesion->toString().' la cual le fue asignada tiene actualmente el estado: '.$sesion->Estado.'. Le recomendamos ingresar al modulo de Recreovía del sistema de información misional (SIM) para continuar el proceso.';
					break;
				default:
					# code...
					break;
			}

			if($destinatario)
			{
				$datos = [
					'titulo' => 'Notificación',
					'destinatario' => $destinatario->persona->toFriendlyString(),
					'notificacion' => $notificacion,
					'link' => [
						'url' => 'http://www.idrd.gov.co/SIM/Presentacion/',
						'texto' => 'Ingresar al SIM'
					],
					'pie' => 'Gracias.',
				];

				Mail::send('email.notificacion', $datos, function($m) use ($destinatario)
				{
					$m->from('mails@idrd.gov.co', 'Recreovía');
					$m->to($destinatario->correo, $destinatario->persona->toFriendlyString())->subject('Notificación');
				});
			}
		} catch (Exception $e) {

		}
	}

	public function actualizarEstado(Request $request)
	{
		$sesion = Sesion::find($request->input('id'));

		$sesion->Estado = $request->input('estado');
		$sesion->save();

		return response()->json(true);
	}

	private function aplicarFiltro($qb, $request)
	{
		if($request->input('estado') && $request->input('estado') != 'Todos')
		{
			$qb->where('Estado', $request->input('estado'));
		}

		if($request->input('desde'))
		{
			$qb->where('Fecha', '>=', $request->input('desde'));
		}

		if($request->input('hasta'))
		{
			$qb->where('Fecha', '<=', $request->input('hasta'));
		}

		return $qb;
	}
}
