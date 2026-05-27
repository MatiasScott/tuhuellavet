<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AnimalService;
use App\Services\PropietarioService;
use App\Services\DashboardService;
use Throwable;

final class DashboardController extends Controller
{
    private DashboardService $service;
    private PropietarioService $propietarioService;
    private AnimalService $animalService;

    public function __construct()
    {
        $this->service = new DashboardService();
        $this->propietarioService = new PropietarioService();
        $this->animalService = new AnimalService();
    }

    public function index(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $roleCode = is_array($user) ? (string) ($user['rol_codigo'] ?? 'invitado') : 'invitado';

        $dashboardData = [
            'roleCode' => $roleCode,
            'rolNombre' => 'Sin rol',
            'empresaTipo' => 'global',
            'widgets' => [],
            'metrics' => [
                'animales' => 0,
                'propietarios' => 0,
                'consultas_hoy' => 0,
                'hospitalizaciones_activas' => 0,
                'vacunas_proximas_7_dias' => 0,
                'examenes_mes' => 0,
                'cirugias_mes' => 0,
                'eventos_timeline_7_dias' => 0,
            ],
            'datasets' => [
                'pacientes_recientes' => [],
                'vacunas_proximas' => [],
                'hospitalizaciones_activas' => [],
                'cirugias_recientes' => [],
                'timeline_reciente' => [],
            ],
        ];

        if (is_array($user) && isset($user['id']) && $empresaId > 0) {
            $dashboardData = $this->service->buildData((int) $user['id'], $empresaId);
        }

        $metrics = isset($dashboardData['metrics']) && is_array($dashboardData['metrics']) ? $dashboardData['metrics'] : [
            'animales' => 0,
            'propietarios' => 0,
            'consultas_hoy' => 0,
            'hospitalizaciones_activas' => 0,
            'vacunas_proximas_7_dias' => 0,
            'examenes_mes' => 0,
            'cirugias_mes' => 0,
            'eventos_timeline_7_dias' => 0,
        ];

        $datasets = isset($dashboardData['datasets']) && is_array($dashboardData['datasets']) ? $dashboardData['datasets'] : [
            'pacientes_recientes' => [],
            'vacunas_proximas' => [],
            'hospitalizaciones_activas' => [],
            'cirugias_recientes' => [],
            'timeline_reciente' => [],
        ];

        $view = match ((string) ($dashboardData['roleCode'] ?? $roleCode)) {
            'cliente' => 'dashboard/cliente',
            'invitado' => 'dashboard/invitado',
            default => 'dashboard/index',
        };

        $response->view($view, [
            'user' => $user,
            'empresaId' => $empresaId,
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'roleCode' => $dashboardData['roleCode'] ?? $roleCode,
            'rolNombre' => $dashboardData['rolNombre'],
            'empresaTipo' => $dashboardData['empresaTipo'],
            'widgets' => $dashboardData['widgets'],
            'metrics' => $metrics,
            'datasets' => $datasets,
            'client' => $dashboardData['client'] ?? [],
        ]);
    }

    public function updateProfilePhoto(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        if (!is_array($user) || $empresaId <= 0) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/portal#perfil');
        }

        try {
            $profile = $this->service->clientProfile((int) $user['id'], $empresaId);
            if (!is_array($profile)) {
                throw new \RuntimeException('No se encontro tu perfil de propietario.');
            }

            $current = [
                'nombres' => (string) ($profile['nombres'] ?? ''),
                'apellidos' => (string) ($profile['apellidos'] ?? ''),
                'identificacion' => (string) ($profile['identificacion'] ?? ''),
                'telefono' => (string) ($profile['telefono'] ?? ''),
                'celular' => (string) ($profile['celular'] ?? ''),
                'email' => (string) ($profile['email'] ?? ''),
                'direccion' => (string) ($profile['direccion'] ?? ''),
                'portal_cliente_activo' => (int) ($profile['portal_cliente_activo'] ?? 0),
                'estado' => (int) ($profile['estado'] ?? 1),
            ];

            $this->propietarioService->update((int) $profile['id'], $empresaId, $current, $request->file('foto'));
            flash_set('success', 'Tu foto de perfil se actualizo correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/portal#perfil');
    }

    public function updatePetPhoto(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $animalId = (int) $request->input('animal_id', 0);

        if (!is_array($user) || $empresaId <= 0) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/portal#mis-mascotas');
        }

        if ($animalId <= 0) {
            flash_set('error', 'Mascota invalida.');
            $response->redirect('/portal#mis-mascotas');
        }

        try {
            $profile = $this->service->clientProfile((int) $user['id'], $empresaId);
            if (!is_array($profile)) {
                throw new \RuntimeException('No se encontro tu perfil de propietario.');
            }

            $animal = $this->service->clientAnimal($animalId, $empresaId);
            if (!is_array($animal)) {
                throw new \RuntimeException('Mascota no encontrada.');
            }

            if ((int) ($animal['propietario_id'] ?? 0) !== (int) ($profile['id'] ?? 0)) {
                throw new \RuntimeException('No tienes permiso para actualizar esta mascota.');
            }

            $current = [
                'propietario_id' => (int) ($animal['propietario_id'] ?? 0),
                'especie_id' => (int) ($animal['especie_id'] ?? 0),
                'raza_id' => (int) ($animal['raza_id'] ?? 0),
                'codigo' => (string) ($animal['codigo'] ?? ''),
                'nombre' => (string) ($animal['nombre'] ?? ''),
                'sexo' => (string) ($animal['sexo'] ?? ''),
                'fecha_nacimiento' => (string) ($animal['fecha_nacimiento'] ?? ''),
                'peso_actual' => $animal['peso_actual'] ?? null,
                'color' => (string) ($animal['color'] ?? ''),
                'microchip' => (string) ($animal['microchip'] ?? ''),
                'foto' => (string) ($animal['foto'] ?? ''),
                'observaciones' => (string) ($animal['observaciones'] ?? ''),
                'estado' => (int) ($animal['estado'] ?? 1),
            ];

            $this->animalService->update($animalId, $empresaId, (int) $user['id'], $current, $request->file('foto'));
            flash_set('success', 'La foto de la mascota se actualizo correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/portal#mis-mascotas');
    }

    public function showPetDetail(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $animalId = (int) $request->input('id', 0);

        if (!is_array($user) || $empresaId <= 0 || $animalId <= 0) {
            $response->redirect('/portal');
        }

        $dashboardData = $this->service->buildData((int) $user['id'], $empresaId);
        $clientData = isset($dashboardData['client']) && is_array($dashboardData['client']) ? $dashboardData['client'] : [];
        $animals = isset($clientData['animals']) && is_array($clientData['animals']) ? $clientData['animals'] : [];
        $targetAnimal = null;
        $targetIndex = null;

        foreach ($animals as $index => $animalItem) {
            if (!is_array($animalItem)) {
                continue;
            }

            if ((int) ($animalItem['id'] ?? 0) === $animalId) {
                $targetAnimal = $animalItem;
                $targetIndex = (int) $index;
                break;
            }
        }

        if (!is_array($targetAnimal)) {
            flash_set('error', 'Mascota no encontrada.');
            $response->redirect('/portal');
        }

        $animalName = trim((string) (($targetAnimal['datos']['nombre'] ?? $targetAnimal['nombre'] ?? 'Mascota')));
        $detail = [
            'profile' => $clientData['propietario'] ?? [],
            'animal' => is_array($targetAnimal['datos'] ?? null) ? $targetAnimal['datos'] : $targetAnimal,
            'consultas' => [],
            'vacunas' => [],
            'desparasitaciones' => [],
            'cirugias' => [],
            'examenes' => [],
            'timeline' => [],
            'documents' => [],
        ];

        foreach (['consultas', 'vacunas', 'desparasitaciones', 'cirugias', 'examenes', 'timeline', 'documents'] as $bucket) {
            $records = isset($targetAnimal[$bucket]) && is_array($targetAnimal[$bucket]) ? $targetAnimal[$bucket] : [];
            $detail[$bucket] = array_values(array_filter($records, static function ($record) use ($animalName): bool {
                if (!is_array($record)) {
                    return false;
                }

                $recordAnimal = trim((string) ($record['animal'] ?? ''));
                return $recordAnimal === '' || strcasecmp($recordAnimal, $animalName) === 0;
            }));
        }

        $petNavigation = [
            'previous' => null,
            'next' => null,
            'current_index' => $targetIndex,
            'total' => count($animals),
        ];

        if ($targetIndex !== null) {
            $previousIndex = $targetIndex - 1;
            $nextIndex = $targetIndex + 1;

            if (isset($animals[$previousIndex]) && is_array($animals[$previousIndex])) {
                $previousAnimal = $animals[$previousIndex];
                $previousDatos = isset($previousAnimal['datos']) && is_array($previousAnimal['datos']) ? $previousAnimal['datos'] : $previousAnimal;
                $petNavigation['previous'] = [
                    'id' => (int) ($previousAnimal['id'] ?? 0),
                    'nombre' => trim((string) ($previousAnimal['nombre'] ?? $previousDatos['nombre'] ?? 'Mascota')),
                ];
            }

            if (isset($animals[$nextIndex]) && is_array($animals[$nextIndex])) {
                $nextAnimal = $animals[$nextIndex];
                $nextDatos = isset($nextAnimal['datos']) && is_array($nextAnimal['datos']) ? $nextAnimal['datos'] : $nextAnimal;
                $petNavigation['next'] = [
                    'id' => (int) ($nextAnimal['id'] ?? 0),
                    'nombre' => trim((string) ($nextAnimal['nombre'] ?? $nextDatos['nombre'] ?? 'Mascota')),
                ];
            }
        }

        $response->view('dashboard/mascota', [
            'user' => $user,
            'empresaId' => $empresaId,
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'roleCode' => 'cliente',
            'rolNombre' => 'Cliente',
            'empresaTipo' => 'portal',
            'detail' => $detail,
            'petNavigation' => $petNavigation,
        ]);
    }
}
