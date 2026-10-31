<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/Announcement.php'; require_once __DIR__ . '/../models/Notification.php';
$pdo=database(); $suffix=strtoupper(bin2hex(random_bytes(4))); $userId=null;$announcementId=null;$notificationId=null;$otherId=null;
try{$pdo->prepare('INSERT INTO users (username,email,password_hash,status) VALUES (:u,:e,:p,"ACTIVE")')->execute(['u'=>'COMM-'.$suffix,'e'=>'comm-'.$suffix.'@test','p'=>password_hash('test-password',PASSWORD_DEFAULT)]);$userId=(int)$pdo->lastInsertId();$announcementId=Announcement::save(['user_id'=>$userId,'title'=>'Test announcement','body'=>'Test body','audience_role'=>'MEMBERS','published_at'=>date('Y-m-d H:i:s'),'expires_at'=>null,'status'=>'PUBLISHED']);Notification::create($userId,'Test notification','Test message','TEST');$items=Notification::forUser($userId);$notificationId=(int)$items[0]['id'];if(count($items)!==1||$items[0]['read_at']!==null)throw new RuntimeException('Notification creation failed.');Notification::markRead($notificationId,$userId);if(Notification::forUser($userId)[0]['read_at']===null)throw new RuntimeException('Read state failed.');
Notification::create($userId,'Unread delete guard','Should stay','TEST');
$unreadId=null;foreach(Notification::forUser($userId) as $row){if($row['read_at']===null)$unreadId=(int)$row['id'];}
if(!$unreadId)throw new RuntimeException('Unread notification fixture missing.');
try{Notification::deleteReadForUser($unreadId,$userId);throw new RuntimeException('Unread notification delete must be blocked.');}catch(InvalidArgumentException $expected){if(!str_contains($expected->getMessage(),'Mark this notification as read'))throw $expected;}
Notification::deleteReadForUser($notificationId,$userId);
if(count(Notification::forUser($userId))!==1)throw new RuntimeException('Read notification delete failed.');
$otherId=null;
$pdo->prepare('INSERT INTO users (username,email,password_hash,status) VALUES (:u,:e,:p,"ACTIVE")')->execute(['u'=>'COMM2-'.$suffix,'e'=>'comm2-'.$suffix.'@test','p'=>password_hash('test-password',PASSWORD_DEFAULT)]);
$otherId=(int)$pdo->lastInsertId();
Notification::create($otherId,'Other user notice','Must stay','TEST');
$otherNotice=(int)$pdo->query('SELECT id FROM notifications WHERE user_id='.$otherId.' LIMIT 1')->fetchColumn();
Notification::markRead($otherNotice,$otherId);
try{Notification::deleteReadForUser($otherNotice,$userId);throw new RuntimeException('Cross-user notification delete must be blocked.');}catch(InvalidArgumentException $expected){if(!str_contains($expected->getMessage(),'Notification not found'))throw $expected;}
if((int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE id='.$otherNotice)->fetchColumn()!==1)throw new RuntimeException('Other user notification was deleted.');
$search=Notification::filtered($userId,['q'=>'stay','status'=>'all','type'=>'','since'=>'']);
if(!is_array($search)||count($search)!==1)throw new RuntimeException('Notification search failed after unique placeholders.');
echo "Communication smoke test passed.\n";}finally{$pdo->prepare('DELETE FROM notifications WHERE user_id=:id')->execute(['id'=>$userId??0]);if(!empty($otherId)){$pdo->prepare('DELETE FROM notifications WHERE user_id=:id')->execute(['id'=>$otherId]);$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$otherId]);}if($announcementId)$pdo->prepare('DELETE FROM announcements WHERE id=:id')->execute(['id'=>$announcementId]);if($userId)$pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$userId]);}
