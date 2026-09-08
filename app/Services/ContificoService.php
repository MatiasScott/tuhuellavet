<?php
namespace App\Services;
use App\Core\Database;
class ContificoService
{
 public function isConfigured():bool{return !empty($_ENV['CONTIFICO_API_URL'])&&!empty($_ENV['CONTIFICO_API_TOKEN']);}
 public function processPending(int $limit=20):array{$db=Database::connection();$limit=max(1,min($limit,100));$rows=$db->query("SELECT cd.*,df.venta_id FROM contifico_documentos cd JOIN documentos_fiscales df ON df.id=cd.documento_fiscal_id WHERE cd.estado='PENDIENTE' ORDER BY cd.id LIMIT {$limit}")->fetchAll();$ok=0;$fail=0;foreach($rows as $r){try{if(!$this->isConfigured())throw new \RuntimeException('Contífico no está configurado.');/* Adaptador HTTP real se activa con credenciales y endpoint del cliente. */$db->prepare("UPDATE contifico_documentos SET intentos=intentos+1,ultimo_error='Pendiente de habilitar endpoint productivo' WHERE id=:id")->execute(['id'=>$r['id']]);$fail++;}catch(\Throwable $e){$db->prepare('UPDATE contifico_documentos SET intentos=intentos+1,ultimo_error=:e WHERE id=:id')->execute(['e'=>$e->getMessage(),'id'=>$r['id']]);$fail++;}}return['processed'=>count($rows),'sent'=>$ok,'failed'=>$fail];}
}
