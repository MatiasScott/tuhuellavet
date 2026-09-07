<?php
namespace App\Core;
use PDO;
class Database{private static ?PDO $connection=null;public static function connection():PDO{if(self::$connection)return self::$connection;$c=require APP_PATH.'/Config/database.php';$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',$c['host'],$c['port'],$c['database'],$c['charset']);return self::$connection=new PDO($dsn,$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}public static function transaction(callable $cb):mixed{$pdo=self::connection();try{$pdo->beginTransaction();$r=$cb($pdo);$pdo->commit();return $r;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}}
