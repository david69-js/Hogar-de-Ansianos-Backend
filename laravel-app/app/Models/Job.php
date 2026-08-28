<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Wrapper Eloquent sobre la tabla `jobs` (cola interna de Laravel). No es una
 * entidad del negocio — Laravel la usa internamente para trabajos en cola
 * (QUEUE_CONNECTION=database). JobController solo la expone por completitud del
 * CRUD administrativo; en la práctica nadie crea/edita filas aquí a mano.
 */
class Job extends Model
{
    protected $guarded = ['id'];
}
