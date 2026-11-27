<?php
declare(strict_types=1);
namespace Tests\Integration;
use App\Services\BillingService;
use App\Services\IContaService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
final class ConsumerFinalModeTest extends TestCase
{
    public function testModes(): void
    {
        self::assertFalse(BillingService::parseConsumerFinal('0'));
        self::assertTrue(BillingService::parseConsumerFinal('1'));
        self::assertTrue(BillingService::parseConsumerFinal(1));
        $this->expectException(RuntimeException::class);
        BillingService::parseConsumerFinal('invalid');
    }
    private function record(): array
    {
        return ['sF_ExisteError'=>false,'persona'=>['idPersona'=>1,'identificacion'=>'9999999999999','codigoTipoIdentificacion'=>'X','activo'=>true,'esNatural'=>false], 'esCliente'=>true,'cliente'=>['id'=>2,'codigoEstado'=>'A'],'tipoIdentificacion'=>['codigo'=>'X','codigoSRI'=>'07','activo'=>true,'esConsumidorFinal'=>true,'idConsumidorFinal'=>'9999999999999']];
    }
    public function testActiveGenericCustomer(): void
    {
        self::assertSame(2,IContaService::validateConsumerFinalRecord($this->record())['idCliente']);
    }
    public function testInactiveGenericCustomerRejected(): void
    {
        $record=$this->record(); $record['cliente']['codigoEstado']='I';
        $this->expectException(RuntimeException::class);
        IContaService::validateConsumerFinalRecord($record);
    }
    public function testMissingGenericCustomerRejected(): void
    {
        $this->expectException(RuntimeException::class);
        IContaService::validateConsumerFinalRecord(null);
    }
}
