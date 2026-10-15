<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/AuditLog.php';
function audit_log(string $action,?string $entityType=null,?int $entityId=null,array $metadata=[]): void { try { AuditLog::record(is_logged_in()?(int)current_user_id():null,$action,$entityType,$entityId,$metadata); } catch(Throwable $e) { error_log('CMMP audit log failed: '.$e->getMessage()); } }
