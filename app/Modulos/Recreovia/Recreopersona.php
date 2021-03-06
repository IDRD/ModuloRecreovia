<?php

namespace App\Modulos\Recreovia;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Iatstuti\Database\Support\CascadeSoftDeletes;
use Idrd\Usuarios\Seguridad\TraitSeguridad;

class Recreopersona extends Model
{
	protected $table = 'Recreopersonas';
    protected $primaryKey = 'Id_Recreopersona';
    protected $connection = 'mysql';
    protected $dates = ['deleted_at'];

    public function __construct()
    {
        parent::__construct();
    	$this->table = config('database.connections.mysql.database').'.Recreopersonas';
    }

    public function persona()
    {
    	return $this->belongsTo('App\Modulos\Personas\Persona', 'Id_Persona');
    }

    public function localidades()
    {
        return $this->belongsToMany('App\Modulos\Parques\Localidad', config('database.connections.mysql.database').'.LocalidadesPersonas', 'Id_Recreopersona', 'Id_Localidad')
                    ->withPivot('tipo');
    }

    public function cronogramas()
    {
        return $this->hasMany('App\Modulos\Recreovia\Cronograma', 'Id_Recreopersona');
    }

    public function sesiones()
    {
        return $this->hasMany('App\Modulos\Recreovia\Sesion', 'Id_Recreopersona');
    }

	public function sesionesDeAcompanante()
	{
		return $this->belognsToMany('App\Modulos\Recreovia\Sesion', 'Sesiones_Acompanantes', 'Id_Recreopersona', 'Id_Sesion');
	}

    public function historialCronogramas()
    {
        return $this->belongsToMany('App\Modulos\Recreovia\Cronograma', 'HistorialCronogramasGestores', 'Id_Recreopersona', 'Id_Cronograma');
    }

    public function reportes()
    {
        return $this->belongsToMany('App\Modulos\Recreovia\Reporte', 'ReportesProfesores', 'Id_Profesor', 'Id_Reporte')
                    ->withPivot('Hora_Llegada', 'Hora_Salida', 'Sesiones_Realizadas', 'Planificacion', 'Sistema_De_Datos', 'Novedades');
    }

    public function getCode()
    {
        return 'U'.str_pad($this->Id_Recreopersona, 5, '0', STR_PAD_LEFT);
    }

    use SoftDeletes, CascadeSoftDeletes, TraitSeguridad;
}
