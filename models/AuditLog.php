<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class AuditLog
{
    public static function record(?int $userId,string $action,?string $entityType=null,?int $entityId=null,array $metadata=[]): void { database()->prepare('INSERT INTO activity_logs (user_id,action,entity_type,entity_id,ip_address,user_agent,metadata) VALUES (:user_id,:action,:entity_type,:entity_id,:ip,:agent,:metadata)')->execute(['user_id'=>$userId,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'ip'=>$_SERVER['REMOTE_ADDR']??null,'agent'=>isset($_SERVER['HTTP_USER_AGENT'])?substr($_SERVER['HTTP_USER_AGENT'],0,500):null,'metadata'=>$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES):null]); }
    public static function list(array $filters): array { $w=[];$p=[];if(!empty($filters['user_id'])){$w[]='a.user_id=:user_id';$p['user_id']=(int)$filters['user_id'];}if($filters['action']!==''){$w[]='a.action=:action';$p['action']=$filters['action'];}if($filters['module']!==''){$w[]='a.entity_type=:module';$p['module']=$filters['module'];}if($filters['from']!==''){$w[]='a.created_at>=:from';$p['from']=$filters['from'].' 00:00:00';}if($filters['to']!==''){$w[]='a.created_at<=:to';$p['to']=$filters['to'].' 23:59:59';}$where=$w?'WHERE '.implode(' AND ',$w):'';$q=database()->prepare("SELECT a.*,u.username FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id $where ORDER BY a.created_at DESC LIMIT 500");$q->execute($p);return $q->fetchAll();}
    public static function actions(): array { return database()->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN); }
    public static function modules(): array { return database()->query('SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN); }
}
