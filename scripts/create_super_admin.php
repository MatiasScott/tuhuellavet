<?php
declare(strict_types=1);

use App\Core\Database;

define('BASE_PATH',dirname(__DIR__));
define('APP_PATH',BASE_PATH.'/app');
define('STORAGE_PATH',BASE_PATH.'/storage');
define('PUBLIC_PATH',BASE_PATH.'/public');

require BASE_PATH.'/vendor/autoload.php';

if (file_exists(BASE_PATH.'/.env')) Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
if (PHP_SAPI !== 'cli') exit("Este script solo puede ejecutarse desde CLI.\n");

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$nombres = $argv[3] ?? 'Super';
$apellidos = $argv[4] ?? 'Administrador';

if (!$email || !$password) {
    exit("Uso: php scripts/create_super_admin.php correo@dominio.com 'PasswordSegura' [Nombres] [Apellidos]\n");
}

$db = Database::connection();
$db->beginTransaction();

try {
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE LOWER(email)=LOWER(:email) LIMIT 1");
    $stmt->execute(['email'=>$email]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $stmt = $db->prepare(
            "INSERT INTO usuarios (nombres,apellidos,email,password_hash,activo,email_verificado_at)
             VALUES (:nombres,:apellidos,:email,:hash,1,NOW())"
        );
        $stmt->execute([
            'nombres'=>$nombres,'apellidos'=>$apellidos,'email'=>$email,
            'hash'=>password_hash($password,PASSWORD_DEFAULT)
        ]);
        $userId = (int)$db->lastInsertId();
    }

    $roleId = (int)$db->query("SELECT id FROM roles WHERE codigo='SUPER_ADMINISTRADOR' LIMIT 1")->fetchColumn();
    if (!$roleId) throw new RuntimeException('No existe el rol SUPER_ADMINISTRADOR.');

    $stmt = $db->prepare("INSERT IGNORE INTO usuarios_roles_globales (usuario_id,rol_id) VALUES (:usuario,:rol)");
    $stmt->execute(['usuario'=>$userId,'rol'=>$roleId]);

    $db->commit();
    echo "Super Administrador listo. Usuario ID: {$userId}\n";
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
