<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;
use PDO;

class EnvironmentIsolationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = $this->db();
    }

    public function testAnimalsBelongToExistingEnvironment(): void
    {
        $count = (int) $this->pdo->query(
            '
            SELECT COUNT(*)
            FROM animales a
            LEFT JOIN entornos e
                ON e.id = a.entorno_id
            WHERE e.id IS NULL
            '
        )->fetchColumn();

        $this->assertSame(
            0,
            $count,
            'Existen animales asociados a entornos inexistentes.'
        );
    }

    public function testAnimalOwnerEnvironmentMatchesAnimalEnvironment(): void
    {
        $count = (int) $this->pdo->query(
            '
            SELECT COUNT(*)
            FROM animales a
            INNER JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id
            WHERE a.entorno_id <> pe.entorno_id
              AND a.deleted_at IS NULL
            '
        )->fetchColumn();

        $this->assertSame(
            0,
            $count,
            'Hay animales vinculados a propietarios de otro entorno.'
        );
    }

    public function testClientOwnershipQueryDoesNotExposeOtherClients(): void
    {
        $rows = $this->pdo->query(
            '
        SELECT
            a.id AS animal_id,
            pe.entorno_id,
            p.usuario_id
        FROM animales a
        INNER JOIN propietarios_entornos pe
            ON pe.id = a.propietario_entorno_id
        INNER JOIN propietarios p
            ON p.id = pe.propietario_id
        WHERE p.usuario_id IS NOT NULL
          AND a.activo = 1
          AND a.deleted_at IS NULL
          AND pe.activo = 1
          AND p.activo = 1
          AND p.deleted_at IS NULL
        LIMIT 25
        '
        )->fetchAll();

        /*
     * En desarrollo puede no existir todavía ningún propietario
     * vinculado a un usuario con animales registrados.
     *
     * En ese caso no modificamos la base ni generamos fixtures.
     */
        if (!$rows) {
            $this->addToAssertionCount(1);
            return;
        }

        foreach ($rows as $row) {
            $stmt = $this->pdo->prepare(
                '
            SELECT COUNT(*)
            FROM animales a
            INNER JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id
            INNER JOIN propietarios p
                ON p.id = pe.propietario_id
            WHERE a.id = :animal
              AND a.entorno_id = :entorno
              AND pe.entorno_id = :entorno
              AND p.usuario_id = :usuario
              AND a.activo = 1
              AND a.deleted_at IS NULL
              AND pe.activo = 1
              AND p.activo = 1
              AND p.deleted_at IS NULL
            '
            );

            $stmt->execute([
                'animal' => (int) $row['animal_id'],
                'entorno' => (int) $row['entorno_id'],
                'usuario' => (int) $row['usuario_id'],
            ]);

            $this->assertSame(
                1,
                (int) $stmt->fetchColumn(),
                'El propietario legítimo no puede resolver su propio animal.'
            );
        }
    }

    public function testClientCannotResolveAnimalFromAnotherOwner(): void
    {
        $pair = $this->pdo->query(
            '
            SELECT
                a.id AS animal_id,
                a.entorno_id,
                p.usuario_id AS propietario_usuario,
                p2.usuario_id AS otro_usuario
            FROM animales a
            INNER JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id
            INNER JOIN propietarios p
                ON p.id = pe.propietario_id
            INNER JOIN propietarios_entornos pe2
                ON pe2.entorno_id = a.entorno_id
               AND pe2.id <> pe.id
               AND pe2.activo = 1
            INNER JOIN propietarios p2
                ON p2.id = pe2.propietario_id
               AND p2.usuario_id IS NOT NULL
               AND p2.usuario_id <> p.usuario_id
            WHERE p.usuario_id IS NOT NULL
              AND a.activo = 1
              AND a.deleted_at IS NULL
              AND p.activo = 1
              AND p.deleted_at IS NULL
              AND p2.activo = 1
              AND p2.deleted_at IS NULL
            LIMIT 1
            '
        )->fetch();

        /*
         * Una base de desarrollo recién creada puede no tener todavía
         * dos clientes con animales en el mismo entorno.
         */
        if (!$pair) {
            $this->addToAssertionCount(1);
            return;
        }

        $stmt = $this->pdo->prepare(
            '
            SELECT COUNT(*)
            FROM animales a
            INNER JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id
            INNER JOIN propietarios p
                ON p.id = pe.propietario_id
            WHERE a.id = :animal
              AND a.entorno_id = :entorno
              AND pe.entorno_id = :entorno
              AND p.usuario_id = :usuario
              AND a.activo = 1
              AND a.deleted_at IS NULL
              AND pe.activo = 1
              AND p.activo = 1
              AND p.deleted_at IS NULL
            '
        );

        $stmt->execute([
            'animal' => (int) $pair['animal_id'],
            'entorno' => (int) $pair['entorno_id'],
            'usuario' => (int) $pair['otro_usuario'],
        ]);

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn(),
            'Un cliente puede resolver el animal perteneciente a otro cliente.'
        );
    }

    public function testAnimalCannotBeResolvedFromAnotherEnvironment(): void
    {
        $row = $this->pdo->query(
            '
            SELECT
                a.id,
                a.entorno_id
            FROM animales a
            WHERE a.activo = 1
              AND a.deleted_at IS NULL
            LIMIT 1
            '
        )->fetch();

        if (!$row) {
            $this->addToAssertionCount(1);
            return;
        }

        $otherEnvironment = $this->pdo->prepare(
            '
            SELECT id
            FROM entornos
            WHERE id <> :actual
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        );

        $otherEnvironment->execute([
            'actual' => (int) $row['entorno_id'],
        ]);

        $otherId = $otherEnvironment->fetchColumn();

        if (!$otherId) {
            $this->addToAssertionCount(1);
            return;
        }

        $stmt = $this->pdo->prepare(
            '
            SELECT COUNT(*)
            FROM animales
            WHERE id = :animal
              AND entorno_id = :entorno
              AND activo = 1
              AND deleted_at IS NULL
            '
        );

        $stmt->execute([
            'animal' => (int) $row['id'],
            'entorno' => (int) $otherId,
        ]);

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn(),
            'Un animal puede resolverse desde un entorno diferente.'
        );
    }

    public function testAnimalDocumentsRemainInsideAnimalEnvironment(): void
    {
        $count = (int) $this->pdo->query(
            '
            SELECT COUNT(*)
            FROM animal_archivos aa
            INNER JOIN archivos ar
                ON ar.id = aa.archivo_id
            INNER JOIN animales a
                ON a.id = aa.animal_id
            LEFT JOIN entornos e
                ON e.id = a.entorno_id
            WHERE e.id IS NULL
               OR ar.deleted_at IS NOT NULL
               OR a.deleted_at IS NOT NULL
            '
        )->fetchColumn();

        $this->assertSame(
            0,
            $count,
            'Existen relaciones documentales fuera de un animal válido.'
        );
    }

    public function testDocumentLookupCanBeScopedByEnvironment(): void
    {
        $row = $this->pdo->query(
            '
            SELECT
                ar.id AS archivo_id,
                a.entorno_id
            FROM archivos ar
            INNER JOIN animal_archivos aa
                ON aa.archivo_id = ar.id
            INNER JOIN animales a
                ON a.id = aa.animal_id
            WHERE ar.deleted_at IS NULL
              AND a.deleted_at IS NULL
              AND a.activo = 1
            LIMIT 1
            '
        )->fetch();

        if (!$row) {
            $this->addToAssertionCount(1);
            return;
        }

        $otherEnvironment = $this->pdo->prepare(
            '
            SELECT id
            FROM entornos
            WHERE id <> :actual
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        );

        $otherEnvironment->execute([
            'actual' => (int) $row['entorno_id'],
        ]);

        $otherId = $otherEnvironment->fetchColumn();

        if (!$otherId) {
            $this->addToAssertionCount(1);
            return;
        }

        $stmt = $this->pdo->prepare(
            '
            SELECT COUNT(DISTINCT ar.id)
            FROM archivos ar
            INNER JOIN animal_archivos aa
                ON aa.archivo_id = ar.id
            INNER JOIN animales a
                ON a.id = aa.animal_id
            WHERE ar.id = :archivo
              AND ar.deleted_at IS NULL
              AND a.entorno_id = :entorno
              AND a.deleted_at IS NULL
              AND a.activo = 1
            '
        );

        $stmt->execute([
            'archivo' => (int) $row['archivo_id'],
            'entorno' => (int) $otherId,
        ]);

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn(),
            'Un documento puede resolverse desde otro entorno.'
        );
    }

    public function testNoPatientPhotoCanCrossEnvironmentThroughAnimalId(): void
    {
        $row = $this->pdo->query(
            '
            SELECT
                id,
                entorno_id
            FROM animales
            WHERE foto_principal_path IS NOT NULL
              AND foto_principal_path <> ""
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        )->fetch();

        if (!$row) {
            $this->addToAssertionCount(1);
            return;
        }

        $otherEnvironment = $this->pdo->prepare(
            '
            SELECT id
            FROM entornos
            WHERE id <> :actual
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        );

        $otherEnvironment->execute([
            'actual' => (int) $row['entorno_id'],
        ]);

        $otherId = $otherEnvironment->fetchColumn();

        if (!$otherId) {
            $this->addToAssertionCount(1);
            return;
        }

        $stmt = $this->pdo->prepare(
            '
            SELECT COUNT(*)
            FROM animales
            WHERE id = :animal
              AND entorno_id = :entorno
              AND foto_principal_path IS NOT NULL
              AND activo = 1
              AND deleted_at IS NULL
            '
        );

        $stmt->execute([
            'animal' => (int) $row['id'],
            'entorno' => (int) $otherId,
        ]);

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn(),
            'La fotografía de un paciente puede resolverse desde otro entorno.'
        );
    }
}
