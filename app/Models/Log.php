<?php

namespace App\Models;

use App\Helpers\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Trace d'audit (table audit.log, schéma séparé).
 */
class Log extends Model
{
    protected $primaryKey = 'id_log';

    protected $guarded = [];

    public function getTable()
    {
        return Audit::table();
    }

    public function getConnectionName()
    {
        return Audit::connection() ?? parent::getConnectionName();
    }
}
