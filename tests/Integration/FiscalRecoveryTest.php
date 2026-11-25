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

final class FiscalRecoveryTest extends TestCase
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
    private array $files = [];
    protected function tearDown(): void
    {
        if (isset($this->db) && $this->env > 0) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            foreach (['iconta_documentos'=>$this->remote,'documentos_fiscales'=>$this->doc,'ventas'=>$this->sale,'entornos'=>$this->env] as $table=>$id) {
                if ($id>0) $this->db->prepare("DELETE FROM `$table` WHERE id=?")->execute([$id]);
            }
        }
        foreach ($this->files as $id => $path) {
            $this->db->prepare("DELETE FROM archivos WHERE id=?")->execute([$id]);
            if (is_file($path)) unlink($path);
        }
        parent::tearDown();
    }
    private function mock(array $responses, array $flags): IContaService
    {
        $mock=$this->getMockBuilder(IContaService::class)->disableOriginalConstructor()->onlyMethods(['getInvoiceSriStatus'])->getMock();
        foreach (['enabled'=>true,'apiUrl'=>'https://iconta.example.invalid','apiKey'=>'qa-fake'] as $key=>$value) {
            (new ReflectionProperty(IContaService::class,$key))->setValue($mock,$value);
        }
        $n=0;
        $mock->expects(self::exactly(count($responses)))->method('getInvoiceSriStatus')->willReturnCallback(function(int $id,bool $ride=false,bool $xml=false) use (&$n,$responses,$flags): array {
            self::assertSame($this->remote,$id); self::assertSame($flags[$n],[$ride,$xml]); return $responses[$n++];
        });
        return $mock;
    }
    private function mockSriStatus(string $auth='QA-AUTH'): array { return ['EstadoFactura'=>'AUTORIZADO','Autorizacion'=>$auth]; }
    private function payload(string $xml='<?xml version="1.0"?><factura><qa>test</qa></factura>'): array
    {
        return $this->mockSriStatus()+['factura_ride'=>['nombre_archivo'=>'qa.pdf','archivo'=>base64_encode("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n")], 'factura_xml'=>['nombre_archivo'=>'qa.xml','archivo'=>base64_encode($xml)]];
    }
    private function row(): array
    {
        $s=$this->db->prepare('SELECT d.*,i.estado AS remote_state,i.errores_consulta_sri,TIMESTAMPDIFF(MINUTE,i.ultima_consulta_sri_at,i.proxima_consulta_sri_at) AS minutes FROM documentos_fiscales d JOIN iconta_documentos i ON i.documento_fiscal_id=d.id WHERE d.id=?'); $s->execute([$this->doc]); return $s->fetch(PDO::FETCH_ASSOC);
    }
    private function captureFiles(): array
    {
        $r=$this->row();
        foreach (['archivo_pdf_id','archivo_xml_id'] as $key) {
            if (!$r[$key]) continue;
            $s=$this->db->prepare('SELECT ruta_storage FROM archivos WHERE id=?'); $s->execute([$r[$key]]);
            $relative=$s->fetchColumn(); self::assertIsString($relative);
            $root=realpath(STORAGE_PATH.'/uploads'); $path=realpath(STORAGE_PATH.'/uploads/'.$relative);
            self::assertNotFalse($root); self::assertNotFalse($path); self::assertStringStartsWith($root.DIRECTORY_SEPARATOR,$path);
            $this->files[(int)$r[$key]]=$path;
        }
        return $r;
    }
    public function testRecoveryAndNoDuplicateAndCancellation(): void
    {
        $this->mock([$this->mockSriStatus(),$this->payload()],[[false,false],[true,true]])->syncInvoiceSriStatus($this->remote,true);
        $r=$this->captureFiles(); self::assertSame('AUTORIZADO',$r['estado']); self::assertSame('QA-AUTH',$r['autorizacion']); self::assertSame(1440,(int)$r['minutes']); self::assertCount(2,$this->files);
        $this->mock([$this->mockSriStatus()],[[false,false]])->syncInvoiceSriStatus($this->remote);
        $after=$this->captureFiles(); self::assertSame($r['archivo_pdf_id'],$after['archivo_pdf_id']); self::assertSame($r['archivo_xml_id'],$after['archivo_xml_id']);
        $this->mock([['EstadoFactura'=>'ANULADO']],[[false,false]])->syncInvoiceSriStatus($this->remote);
        $after=$this->captureFiles(); self::assertSame('ANULADO',$after['estado']); self::assertSame('QA-AUTH',$after['autorizacion']); self::assertSame($r['archivo_pdf_id'],$after['archivo_pdf_id']); self::assertSame($r['archivo_xml_id'],$after['archivo_xml_id']);
    }
    public static function invalidResponses(): array { return [['auth'],['base64'],['state'],['mime']]; }
    #[DataProvider('invalidResponses')]
    public function testRejectAndRollback(string $kind): void
    {
        $payload=$this->payload();
        if ($kind==='auth') $payload['Autorizacion']='QA-OTHER';
        if ($kind==='base64') $payload['factura_xml']['archivo']='%%%';
        if ($kind==='state') $payload['EstadoFactura']='ANULADO';
        if ($kind==='mime') $payload['factura_xml']['archivo']=base64_encode('plain text not XML');
        $before=(int)$this->db->query('SELECT COUNT(*) FROM archivos')->fetchColumn();
        try { $this->mock([$this->mockSriStatus(),$payload],[[false,false],[true,true]])->syncInvoiceSriStatus($this->remote,true); self::fail('Expected rejection'); }
        catch (RuntimeException $e) { self::assertNotSame('',$e->getMessage()); }
        $r=$this->captureFiles(); self::assertSame('GENERADA',$r['estado']); self::assertSame('GENERADA',$r['remote_state']); self::assertNull($r['autorizacion']); self::assertNull($r['archivo_pdf_id']); self::assertNull($r['archivo_xml_id']); self::assertSame(1,(int)$r['errores_consulta_sri']); self::assertSame($before,(int)$this->db->query('SELECT COUNT(*) FROM archivos')->fetchColumn());
    }
}
