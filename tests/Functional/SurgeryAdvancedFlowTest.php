<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\SurgeryService;
use RuntimeException;

class SurgeryAdvancedFlowTest extends ClinicalTestCase
{
    private function createProcedure(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO procedimientos_quirurgicos
            (
                nombre,
                descripcion,
                activo
            )
            VALUES
            (
                :nombre,
                :descripcion,
                1
            )
            '
        );

        $stmt->execute([
            'nombre' =>
            'PROC_ADV_QA_' . uniqid(),
            'descripcion' =>
            'Procedimiento temporal QA',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function createSurgery(
        ?int $environmentId = null
    ): int {
        $environmentId ??=
            $this->vetEnvironment;

        $patientId =
            $this->createPatient(
                $environmentId
            );

        return (new SurgeryService())->create(
            [
                'animal_id' => $patientId,
                'procedimiento_quirurgico_id' =>
                $this->createProcedure(),
                'fecha_inicio' =>
                '2026-09-14 10:00:00',
            ],
            [],
            $environmentId,
            $this->superAdminId
        );
    }

    private function functionId(
        string $code
    ): int {
        return (int) $this->scalar(
            '
            SELECT id
            FROM funciones_equipo_quirurgico
            WHERE codigo = :codigo
            LIMIT 1
            ',
            [
                'codigo' => $code,
            ]
        );
    }

    private function otherEnvironmentId(): int
    {
        $id = (int) $this->scalar(
            '
        SELECT id
        FROM entornos
        WHERE id <> :actual
        ORDER BY id
        LIMIT 1
        ',
            [
                'actual' => $this->vetEnvironment,
            ]
        );

        if ($id <= 0) {
            throw new RuntimeException(
                'No existe un segundo entorno para probar aislamiento.'
            );
        }

        return $id;
    }

    public function testTeamMemberCanBeAdded(): void
    {
        $eventId = $this->createSurgery();

        $functionId =
            $this->functionId('CIRUJANO');

        (new SurgeryService())->addTeamMember(
            $eventId,
            [
                'usuario_id' =>
                $this->superAdminId,
                'funcion_id' =>
                $functionId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugia_equipo
            WHERE cirugia_evento_id = :cirugia
              AND usuario_id = :usuario
              AND funcion_id = :funcion
            ',
            [
                'cirugia' => $eventId,
                'usuario' =>
                $this->superAdminId,
                'funcion' => $functionId,
            ]
        );

        $this->assertSame(1, $count);
    }

    public function testSameUserCanHaveDifferentSurgicalFunctions(): void
    {
        $eventId = $this->createSurgery();

        $surgeon =
            $this->functionId('CIRUJANO');

        $assistant =
            $this->functionId('AYUDANTE');

        $service = new SurgeryService();

        $service->addTeamMember(
            $eventId,
            [
                'usuario_id' =>
                $this->superAdminId,
                'funcion_id' => $surgeon,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $service->addTeamMember(
            $eventId,
            [
                'usuario_id' =>
                $this->superAdminId,
                'funcion_id' => $assistant,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugia_equipo
            WHERE cirugia_evento_id = :cirugia
              AND usuario_id = :usuario
            ',
            [
                'cirugia' => $eventId,
                'usuario' =>
                $this->superAdminId,
            ]
        );

        $this->assertSame(2, $count);
    }

    public function testDuplicateTeamFunctionIsRejected(): void
    {
        $eventId = $this->createSurgery();

        $functionId =
            $this->functionId('CIRUJANO');

        $service = new SurgeryService();

        $data = [
            'usuario_id' =>
            $this->superAdminId,
            'funcion_id' =>
            $functionId,
        ];

        $service->addTeamMember(
            $eventId,
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El integrante ya está registrado con esa función.'
        );

        $service->addTeamMember(
            $eventId,
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidSurgicalFunctionIsRejected(): void
    {
        $eventId = $this->createSurgery();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Función quirúrgica no válida.'
        );

        (new SurgeryService())->addTeamMember(
            $eventId,
            [
                'usuario_id' =>
                $this->superAdminId,
                'funcion_id' => 999999999,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidUserIsRejectedFromTeam(): void
    {
        $eventId = $this->createSurgery();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Usuario no válido.'
        );

        (new SurgeryService())->addTeamMember(
            $eventId,
            [
                'usuario_id' => 999999999,
                'funcion_id' =>
                $this->functionId(
                    'CIRUJANO'
                ),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testTeamCannotCrossEnvironment(): void
    {
        $eventId = $this->createSurgery();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cirugía no válida para el entorno actual.'
        );

        (new SurgeryService())->addTeamMember(
            $eventId,
            [
                'usuario_id' =>
                $this->superAdminId,
                'funcion_id' =>
                $this->functionId(
                    'CIRUJANO'
                ),
            ],
            $this->otherEnvironmentId(),
            $this->superAdminId
        );
    }

    public function testTeamMemberCanBeRemoved(): void
    {
        $eventId = $this->createSurgery();

        $functionId =
            $this->functionId('ANESTESISTA');

        $service = new SurgeryService();

        $service->addTeamMember(
            $eventId,
            [
                'usuario_id' =>
                $this->superAdminId,
                'funcion_id' =>
                $functionId,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $service->removeTeamMember(
            $eventId,
            $this->superAdminId,
            $functionId,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM cirugia_equipo
            WHERE cirugia_evento_id = :cirugia
              AND usuario_id = :usuario
              AND funcion_id = :funcion
            ',
            [
                'cirugia' => $eventId,
                'usuario' =>
                $this->superAdminId,
                'funcion' => $functionId,
            ]
        );

        $this->assertSame(0, $count);
    }

    public function testRemovingUnknownTeamMemberIsRejected(): void
    {
        $eventId = $this->createSurgery();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El integrante del equipo quirúrgico no existe.'
        );

        (new SurgeryService())->removeTeamMember(
            $eventId,
            $this->superAdminId,
            $this->functionId('CIRUJANO'),
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testSurgicalEvolutionCanBeCreated(): void
    {
        $eventId = $this->createSurgery();

        $id = (
            new SurgeryService()
        )->addEvolution(
            $eventId,
            [
                'fecha_hora' =>
                '2026-09-14 12:00:00',
                'evolucion' =>
                'Paciente despierto y estable.',
                'observaciones' =>
                'Continuar monitoreo.',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertGreaterThan(0, $id);

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM cirugia_evoluciones
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        $this->assertIsArray($row);

        $this->assertSame(
            $eventId,
            (int) $row['cirugia_evento_id']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $row['registrado_por']
        );

        $this->assertSame(
            'Paciente despierto y estable.',
            $row['evolucion']
        );

        $this->assertSame(
            'Continuar monitoreo.',
            $row['observaciones']
        );
    }

    public function testSurgicalEvolutionRequiresText(): void
    {
        $eventId = $this->createSurgery();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La evolución es obligatoria.'
        );

        (new SurgeryService())->addEvolution(
            $eventId,
            [
                'evolucion' => '   ',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testSurgicalEvolutionCannotCrossEnvironment(): void
    {
        $eventId = $this->createSurgery();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Cirugía no válida para el entorno actual.'
        );

        (new SurgeryService())->addEvolution(
            $eventId,
            [
                'evolucion' =>
                'No debe registrarse.',
            ],
            $this->otherEnvironmentId(),
            $this->superAdminId
        );
    }

    public function testEvolutionDateCanBeStored(): void
    {
        $eventId = $this->createSurgery();

        $id = (
            new SurgeryService()
        )->addEvolution(
            $eventId,
            [
                'fecha_hora' =>
                '2026-09-14T15:37',
                'evolucion' =>
                'Control postoperatorio.',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $date = $this->scalar(
            '
            SELECT fecha_hora
            FROM cirugia_evoluciones
            WHERE id = :id
            ',
            [
                'id' => $id,
            ]
        );

        $this->assertSame(
            '2026-09-14 15:37:00',
            $date
        );
    }
}
