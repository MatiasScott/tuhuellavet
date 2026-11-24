<?php
declare(strict_types=1);
use App\Core\Database;
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$limit = filter_var($argv[1] ?? '200', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2000]]);
if (!$limit || $argc > 2) { exit("Uso: php scripts/audit_iconta_fiscal_rounding.php [1-2000]\n"); }
require dirname(__DIR__).'/bootstrap/app.php';
function cents(float $n): int { return (int) round($n*100,0,PHP_ROUND_HALF_UP); }
function xmlRead(string $s): SimpleXMLElement {
    if (stripos($s,'<!DOCTYPE') !== false || stripos($s,'<!ENTITY') !== false) throw new RuntimeException('XML con DTD');
    $x = simplexml_load_string($s, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    if ($x === false) throw new RuntimeException('XML invalido');
    return $x;
}
function num($x): float { $s=trim((string)$x); if (!is_numeric($s) || !is_finite((float)$s)) throw new RuntimeException('Importe invalido'); return (float)$s; }
function predict(array $p, bool $precise): array {
    $q=num($p['cantidad']??''); $u=num($p['valor_unitario']??''); $d=num($p['porcentaje_descuento']??0)/100; $r=num($p['iva_aplicado']??'')/100;
    if ($q<=0 || $u<=0 || $d<0 || $d>=1 || !in_array($r,[0.0,0.15],true) || !empty($p['aplica_porcentaje_servicio'])) throw new RuntimeException('Payload no comparable');
    $gross=$q*$u;
    $base=$precise ? $gross*(1-$d) : (cents($gross)-cents(cents($gross)/100*$d))/100;
    $b=cents($base); $t=cents(($precise?$base:$b/100)*$r);
    return [$b,$t,$b+$t];
}
$db=null;
try {
    $db=Database::connection(); $db->exec('START TRANSACTION READ ONLY');
    $columns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='iconta_documentos'")->fetchAll(PDO::FETCH_COLUMN);
    $hasPayload=!array_diff(['documento_fiscal_id','request_payload'],$columns);
    $join=$hasPayload?' INNER JOIN iconta_documentos ic ON ic.documento_fiscal_id=df.id ':'';
    $payload=$hasPayload?', ic.request_payload':', NULL AS request_payload';
    $rows=$db->query("SELECT df.id,df.venta_id,v.total,v.impuestos,a.ruta_storage,a.hash_sha256 $payload FROM documentos_fiscales df JOIN ventas v ON v.id=df.venta_id LEFT JOIN archivos a ON a.id=df.archivo_xml_id AND a.deleted_at IS NULL $join WHERE df.estado='AUTORIZADO' AND df.tipo_documento='FACTURA' AND v.es_simulada=0 ORDER BY df.id DESC LIMIT ".(int)$limit)->fetchAll(PDO::FETCH_ASSOC);
    $root=realpath(STORAGE_PATH.'/uploads');
    $detail=$db->prepare('SELECT base_imponible, impuesto, total FROM venta_detalles WHERE venta_id=:id');
    $stats=array_fill_keys(['documentos','xml_validos','xml_omitidos','venta_coincide','venta_difiere','payload_no_comparable','lineas_comparables','ambos','solo_actual','solo_precision','ninguno'],0);
    $samples=[];
    foreach ($rows as $row) {
        $stats['documentos']++;
        try {
            $path=$root===false?false:realpath($root.'/'.($row['ruta_storage']??''));
            if (!$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path) || filesize($path)>10485760) throw new RuntimeException('Archivo no disponible');
            $raw=file_get_contents($path); if ($raw===false) throw new RuntimeException('Lectura fallida');
            if (!empty($row['hash_sha256']) && !hash_equals(strtolower($row['hash_sha256']),hash('sha256',$raw))) throw new RuntimeException('Hash diferente');
            $x=xmlRead($raw);
            if ($x->getName()==='autorizacion') { if (trim((string)$x->estado)!=='AUTORIZADO') throw new RuntimeException('Estado XML'); $x=xmlRead((string)$x->comprobante); }
            if ($x->getName()!=='factura') throw new RuntimeException('No es factura');
            $lines=[]; $sum=[0,0,0];
            foreach ($x->detalles->detalle as $line) {
                $b=cents(num($line->precioTotalSinImpuesto)); $t=0; $rates=[];
                foreach ($line->impuestos->impuesto as $tax) { if ((string)$tax->codigo!=='2') throw new RuntimeException('Impuesto no comparable'); $t+=cents(num($tax->valor)); $rates[]=num($tax->tarifa); }
                $a=[$b,$t,$b+$t]; foreach ($a as $i=>$n) $sum[$i]+=$n;
                $lines[]=['code'=>(string)$line->codigoPrincipal,'qty'=>num($line->cantidad),'rate'=>count($rates)===1?$rates[0]:null,'amounts'=>$a];
            }
            if (!$lines) throw new RuntimeException('Sin detalles');
            $header=cents(num($x->infoFactura->importeTotal));
            $detail->execute(['id'=>$row['venta_id']]); $local=[0,0,0];
            foreach ($detail->fetchAll(PDO::FETCH_ASSOC) as $d) { $local[0]+=cents(num($d['base_imponible'])); $local[1]+=cents(num($d['impuesto'])); $local[2]+=cents(num($d['total'])); }
            $stats['xml_validos']++;
            $ok=$sum===$local && $header===$local[2] && cents(num($row['total']))===$header && cents(num($row['impuestos']))===$sum[1];
            $stats[$ok?'venta_coincide':'venta_difiere']++;
            if (!$ok && count($samples)<20) $samples[]=['documento_id'=>(int)$row['id'],'local_centavos'=>$local,'xml_centavos'=>$sum,'xml_total_cabecera'=>$header];
        } catch (Throwable $e) { $stats['xml_omitidos']++; continue; }
        try {
            $p=json_decode($row['request_payload']??'',true,512,JSON_THROW_ON_ERROR)['detalles']??null;
            if (!is_array($p) || count($p)!==count($lines)) throw new RuntimeException('Sin correspondencia');
            // Match only unique code/quantity/rate combinations; never assume line order.
            $matched=[];
            foreach ($p as $item) {
                $hits=[]; foreach ($lines as $i=>$line) if ($line['code']===(string)($item['codigo_producto']??'') && abs($line['qty']-num($item['cantidad']??''))<0.000001 && $line['rate']!==null && abs($line['rate']-num($item['iva_aplicado']??''))<0.000001) $hits[]=$i;
                if (count($hits)!==1 || isset($matched[$hits[0]])) throw new RuntimeException('Correspondencia ambigua');
                $i=$hits[0]; $a=predict($item,false)===$lines[$i]['amounts']; $b=predict($item,true)===$lines[$i]['amounts'];
                $matched[$i]=$a?($b?'ambos':'solo_actual'):($b?'solo_precision':'ninguno');
            }
            foreach ($matched as $result) { $stats['lineas_comparables']++; $stats[$result]++; }
        } catch (Throwable $e) { $stats['payload_no_comparable']++; }
    }
    echo json_encode(['modo'=>'SOLO LECTURA','payload_disponible'=>$hasPayload,'resultados'=>$stats,'muestra_diferencias'=>$samples],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,"Auditoria interrumpida: ".get_class($e).". Revise esquema y configuracion localmente.\n"); exit(1); }
finally { if ($db instanceof PDO && $db->inTransaction()) $db->rollBack(); }
