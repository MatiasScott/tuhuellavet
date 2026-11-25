<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Core\Database;
use App\Services\IContaService;
use App\Services\FiscalSyncService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;
use ReflectionProperty;
use RuntimeException;

final class FiscalPersistenceTest extends TestCase
{
    private PDO $db;
    private int $env = 0, $sale = 0, $doc = 0, $remote = 0;
    protected function setUp(): void
    {
        parent::setUp();
        self::assertSame('testing', $_ENV['APP_ENV'] ?? '');
        self::assertContains($_ENV['DB_HOST'] ?? '', ['localhost','127.0.0.1']);
        $this->db = Database::connection();
        self::assertSame('tuhuellavet_qa', $this->db->query('SELECT DATABASE()')->fetchColumn());
        self::assertFalse($this->db->inTransaction());
        $seed = $this->db->query('SELECT empresa_id,tipo_entorno_id FROM entornos ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $user = (int)$this->db->query('SELECT id FROM usuarios ORDER BY id LIMIT 1')->fetchColumn();
        self::assertNotFalse($seed); self::assertGreaterThan(0,$user);
        $token = 'QA-FISCAL-'.bin2hex(random_bytes(8));
        $this->db->prepare('INSERT INTO entornos (empresa_id,tipo_entorno_id,nombre,codigo,permite_facturacion_real) VALUES (?,?,?,?,1)')->execute([$seed['empresa_id'],$seed['tipo_entorno_id'],$token,$token]);
        $this->env=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO ventas (numero,entorno_id,fecha,estado,creado_por) VALUES (?,?,NOW(),'FINALIZADA',?)")->execute([$token,$this->env,$user]);
        $this->sale=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO documentos_fiscales (venta_id,tipo_documento,estado) VALUES (?,'FACTURA','GENERADA')")->execute([$this->sale]);
        $this->doc=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO iconta_documentos (documento_fiscal_id,iconta_id_documento,estado) VALUES (?,'999999999','GENERADA')")->execute([$this->doc]);
        $this->remote=(int)$this->db->lastInsertId();
    }
    protected function tearDown(): void
    {
        if (isset($this->db) && $this->env > 0) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            foreach (['iconta_documentos'=>$this->remote,'documentos_fiscales'=>$this->doc,'ventas'=>$this->sale,'entornos'=>$this->env] as $table=>$id) {
                if ($id>0) $this->db->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
            }
        }
        parent::tearDown();
    }
    private function service(?array $response): IContaService
    {
        $mock=$this->getMockBuilder(IContaService::class)->disableOriginalConstructor()->onlyMethods(['getInvoiceSriStatus'])->getMock();
        foreach (['enabled'=>true,'apiUrl'=>'https://iconta.example.invalid','apiKey'=>'qa-fake'] as $key=>$value) {
            (new ReflectionProperty(IContaService::class,$key))->setValue($mock,$value);
        }
        $expect=$mock->expects($response===[] ? self::never() : self::once())->method('getInvoiceSriStatus');
        if ($response===null) $expect->willThrowException(new RuntimeException('QA communication failure'));
        elseif ($response!==[]) $expect->willReturn($response);
        return $mock;
    }
    private function row(): array
    {
        $s=$this->db->prepare('SELECT i.*,d.estado AS fiscal, TIMESTAMPDIFF(MINUTE,i.ultima_consulta_sri_at,i.proxima_consulta_sri_at) AS minutes FROM iconta_documentos i JOIN documentos_fiscales d ON d.id=i.documento_fiscal_id WHERE i.id=?');
        $s->execute([$this->remote]); return $s->fetch(PDO::FETCH_ASSOC);
    }
    public static function successes(): array { return [['POR_FIRMAR',5],['NEGADO',60],['ANULADO',null]]; }
    #[DataProvider('successes')]
    public function testSuccess(string $status,?int $minutes): void
    {
        $this->db->prepare("UPDATE iconta_documentos SET errores_consulta_sri=3,ultimo_error_consulta_sri='QA' WHERE id=?")->execute([$this->remote]);
        $this->service(['EstadoFactura'=>$status])->syncInvoiceSriStatus($this->remote,true);
        $r=$this->row(); self::assertSame($status,$r['estado']); self::assertSame($status,$r['fiscal']);
        self::assertSame(0,(int)$r['errores_consulta_sri']); self::assertNull($r['ultimo_error_consulta_sri']);
        self::assertNotNull($r['consulta_sri_exitosa_at']); self::assertNotNull($r['ultima_consulta_sri_at']);
        self::assertSame($minutes,$r['minutes']===null?null:(int)$r['minutes']);
        self::assertSame([], (new FiscalSyncService())->pendingDocuments(1,$this->remote));
    }
    public static function failures(): array { return [[0,5],[1,10],[5,180],[65535,180]]; }
    #[DataProvider('failures')]
    public function testFailure(int $previous,int $minutes): void
    {
        $this->db->prepare('UPDATE iconta_documentos SET errores_consulta_sri=? WHERE id=?')->execute([$previous,$this->remote]);
        try { $this->service(null)->syncInvoiceSriStatus($this->remote,true); self::fail('Expected error'); }
        catch (RuntimeException $e) { self::assertSame('QA communication failure',$e->getMessage()); }
        $r=$this->row(); self::assertSame('GENERADA',$r['estado']); self::assertSame('GENERADA',$r['fiscal']);
        self::assertSame(min(65535,$previous+1),(int)$r['errores_consulta_sri']); self::assertSame($minutes,(int)$r['minutes']);
        self::assertSame('QA communication failure',$r['ultimo_error_consulta_sri']); self::assertNull($r['consulta_sri_exitosa_at']);
    }
    public static function exclusions(): array { return [['UPDATE ventas SET es_simulada=1 WHERE id=?','sale'],['UPDATE entornos SET permite_facturacion_real=0 WHERE id=?','env'],["UPDATE documentos_fiscales SET estado='ANULADO' WHERE id=?",'doc'],['UPDATE iconta_documentos SET proxima_consulta_sri_at=TIMESTAMPADD(HOUR,1,NOW()) WHERE id=?','remote']]; }
    #[DataProvider('exclusions')]
    public function testExclusion(string $sql,string $key): void
    {
        $this->db->prepare($sql)->execute([$this->$key]);
        self::assertSame([], (new FiscalSyncService())->pendingDocuments(1,$this->remote));
        self::assertSame(['omitido'=>true],$this->service([])->syncInvoiceSriStatus($this->remote,true));
        self::assertNull($this->row()['ultima_consulta_sri_at']);
    }
}
