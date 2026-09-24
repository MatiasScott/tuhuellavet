<?php

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Database;
use App\Services\NotificationService;

class NotificacionController extends Controller
{
    public function index(Request $r): void
    {
        $s = Database::connection()
        ->prepare(
            '
            SELECT n.*,tn.nombre AS tipo,cn.nombre AS canal 
            FROM notificaciones n 
            JOIN tipos_notificacion tn ON tn.id=n.tipo_notificacion_id 
            JOIN canales_notificacion cn ON cn.id=n.canal_id 
            WHERE n.entorno_id=:e OR n.entorno_id IS NULL 
            ORDER BY n.created_at DESC 
            LIMIT 300
            ');
        $s->execute(['e' => active_environment_id()]);
        $this->view('notificaciones/index', ['title' => 'Notificaciones', 'notifications' => $s->fetchAll()]);
    }

    public function process(
        Request $r
    ): void {
        try {
            /*
         * Primero generamos las alertas
         * administrativas correspondientes.
         */
            $inventory =
                (new \App\Services\InventoryAlertService())
                ->generate();

            /*
         * Después procesamos la cola,
         * incluyendo las recién creadas.
         */
            $notifications =
                (new NotificationService())
                ->processBatch(200);

            \App\Core\Session::flash(
                'success',
                'Alertas inventario: '
                    . json_encode(
                        $inventory,
                        JSON_UNESCAPED_UNICODE
                    )
                    . ' | Notificaciones: '
                    . json_encode(
                        $notifications,
                        JSON_UNESCAPED_UNICODE
                    )
            );
        } catch (\Throwable $e) {
            \App\Core\Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/notificaciones'
        );
    }
}
