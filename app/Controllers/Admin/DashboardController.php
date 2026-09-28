<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Database;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Owner;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $roles = auth_user()['roles'] ?? [];

        if (in_array('CLIENTE', $roles, true)) {
            $this->redirect('/cliente');
        }

        if (
            (active_environment()['tipo_codigo'] ?? '') === 'ACADEMICO'
            && (
                in_array('DOCENTE', $roles, true)
                || in_array('ESTUDIANTE', $roles, true)
            )
        ) {
            $this->redirect('/academico');
        }

        $environmentId = active_environment_id();

        $db = Database::connection();

        /*
        |--------------------------------------------------------------------------
        | Hospitalizaciones activas
        |--------------------------------------------------------------------------
        */

        $statement = $db->prepare(
            "
            SELECT COUNT(*)
            FROM hospitalizaciones h
            INNER JOIN eventos_clinicos ec
                ON ec.id = h.evento_clinico_id
            INNER JOIN animales a
                ON a.id = ec.animal_id
            INNER JOIN estados_hospitalizacion eh
                ON eh.id = h.estado_hospitalizacion_id
            WHERE a.entorno_id = :e
              AND eh.codigo = 'ACTIVA'
            "
        );

        $statement->execute([
            'e' => $environmentId,
        ]);

        $hospitalized = (int) $statement->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Próximas revacunaciones
        |--------------------------------------------------------------------------
        */

        $statement = $db->prepare(
            "
            SELECT COUNT(*)
            FROM vacunaciones v
            INNER JOIN eventos_clinicos ec
                ON ec.id = v.evento_clinico_id
            INNER JOIN animales a
                ON a.id = ec.animal_id
            WHERE a.entorno_id = :e
              AND ec.anulado_at IS NULL
              AND v.fecha_revacunacion
                  BETWEEN CURDATE()
                  AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            "
        );

        $statement->execute([
            'e' => $environmentId,
        ]);

        $vaccinesDue = (int) $statement->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | Modelos
        |--------------------------------------------------------------------------
        */

        $appointments = new Appointment();
        $patients = new Patient();
        $owners = new Owner();


        /*
        |--------------------------------------------------------------------------
        | Vista
        |--------------------------------------------------------------------------
        */

        $this->view(
            'dashboard/index',
            [
                'title' => 'Dashboard clínico',

                'metrics' => [
                    'appointments' => $appointments->countToday($environmentId),
                    'vaccines_due' => $vaccinesDue,
                    'hospitalized' => $hospitalized,
                    'patients' => $patients->countByEnvironment($environmentId),
                    'owners' => $owners->countByEnvironment($environmentId),
                ],

                'appointments' => $appointments->nextToday($environmentId),

                'recentPatients' => $patients->recentByEnvironment(
                    $environmentId
                ),
            ]
        );
    }
}
