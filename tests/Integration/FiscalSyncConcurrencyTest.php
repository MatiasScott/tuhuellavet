<?php
declare(strict_types=1);

use App\Core\Database;
use App\Services\IContaService;
use PHPUnit\Framework\TestCase;

final class FiscalSyncConcurrencyTest extends TestCase
{
    public function testRealDocumentLockAndReleaseOnException(): void
    {
        self::assertSame('testing', $_ENV['APP_ENV'] ?? null);
        self::assertSame('tuhuellavet_qa', $_ENV['DB_DATABASE'] ?? null);
        $a = Database::connection();
        self::assertSame('tuhuellavet_qa', $a->query('SELECT DATABASE()')->fetchColumn());
        $c = require APP_PATH . '/Config/database.php';
        self::assertContains($c['host'], ['localhost', '127.0.0.1', '::1']);
        $b = new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname=tuhuellavet_qa;charset='.$c['charset'], $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
        self::assertSame('tuhuellavet_qa', $b->query('SELECT DATABASE()')->fetchColumn());
        self::assertNotSame((string)$a->query('SELECT CONNECTION_ID()')->fetchColumn(), (string)$b->query('SELECT CONNECTION_ID()')->fetchColumn());
        $id = random_int(1000000000, 2000000000);
        $stmt = $a->prepare('SELECT COUNT(*) FROM iconta_documentos WHERE id = :id');
        $stmt->execute(['id'=>$id]);
        self::assertSame(0, (int)$stmt->fetchColumn());
        $stmt->closeCursor();
        $lock = 'thv_iconta_invoice_'.$id;
        $service = new IContaService();
        foreach (['enabled'=>true, 'apiUrl'=>'https://iconta.example.invalid', 'apiKey'=>'qa-not-a-real-key'] as $key=>$value) {
            (new ReflectionProperty(IContaService::class, $key))->setValue($service, $value);
        }
        try {
            self::assertSame(1, $this->lock($b, 'SELECT GET_LOCK(:name, 0)', $lock));
            self::assertSame(0, $this->lock($a, 'SELECT GET_LOCK(:name, 0)', $lock));
            try {
                $service->syncInvoiceSriStatus($id);
                self::fail('La sincronizacion debio rechazar el bloqueo ocupado.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('está siendo procesado', $e->getMessage());
            }
            self::assertSame(0, $this->lock($a, 'SELECT IS_FREE_LOCK(:name)', $lock));
            self::assertSame(1, $this->lock($b, 'SELECT RELEASE_LOCK(:name)', $lock));
            try {
                $service->syncInvoiceSriStatus($id);
                self::fail('El documento inexistente debio ser rechazado.');
            } catch (RuntimeException $e) {
                self::assertSame('El documento de iConta no existe.', $e->getMessage());
            }
            self::assertSame(1, $this->lock($b, 'SELECT IS_FREE_LOCK(:name)', $lock));
            self::assertSame(1, $this->lock($b, 'SELECT GET_LOCK(:name, 0)', $lock));
            self::assertSame(1, $this->lock($b, 'SELECT RELEASE_LOCK(:name)', $lock));
        } finally {
            $this->lock($a, 'SELECT RELEASE_LOCK(:name)', $lock);
            $this->lock($b, 'SELECT RELEASE_LOCK(:name)', $lock);
        }
    }

    private function lock(PDO $db, string $sql, string $name): ?int
    {
        $stmt = $db->prepare($sql);
        $stmt->execute(['name'=>$name]);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $value === null || $value === false ? null : (int)$value;
    }
}
