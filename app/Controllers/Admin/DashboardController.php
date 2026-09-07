<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Owner;
use App\Core\Database;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $environmentId = active_environment_id();
        if (!$environmentId) $this->redirect('/seleccionar-entorno');

        $appointments = new Appointment();
        $patients = new Patient();
        $owners = new Owner();
        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM hospitalizaciones h
             INNER JOIN eventos_clinicos ec ON ec.id=h.evento_clinico_id
             INNER JOIN animales a ON a.id=ec.animal_id
             INNER JOIN estados_hospitalizacion eh ON eh.id=h.estado_hospitalizacion_id
             WHERE a.entorno_id=:entorno AND eh.codigo='ACTIVA'"
        );
        $stmt->execute(['entorno'=>$environmentId]);
        $hospitalized = (int)$stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM vacunaciones v
             INNER JOIN eventos_clinicos ec ON ec.id=v.evento_clinico_id
             INNER JOIN animales a ON a.id=ec.animal_id
             WHERE a.entorno_id=:entorno
               AND v.fecha_revacunacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        );
        $stmt->execute(['entorno'=>$environmentId]);
        $vaccinesDue = (int)$stmt->fetchColumn();

        $this->view('dashboard/index',[
            'title'=>'Dashboard clínico',
            'metrics'=>[
                'appointments'=>$appointments->countToday($environmentId),
                'vaccines_due'=>$vaccinesDue,
                'hospitalized'=>$hospitalized,
                'patients'=>$patients->countByEnvironment($environmentId),
                'owners'=>$owners->countByEnvironment($environmentId),
            ],
            'appointments'=>$appointments->nextToday($environmentId),
            'recentPatients'=>$patients->recentByEnvironment($environmentId),
        ]);
    }
}
