<?php
declare(strict_types=1);
function current_user(): ?array { return $_SESSION['user']??null; }
function require_auth(): array { $u=current_user(); if(!$u)respond(['error'=>'Authentication required'],401); return $u; }
function require_permission(string $p): array { $u=require_auth(); if($u['role']==='Administrator')return $u; if(!in_array($p,$u['permissions']??[],true))respond(['error'=>'Permission denied'],403); return $u; }
function user_payload(int $id): array { $q=db()->prepare('SELECT u.id,u.full_name,u.email,u.phone,u.staff_position,r.name role,u.status,u.must_change_password FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?'); $q->execute([$id]); $u=$q->fetch(); if(!$u)respond(['error'=>'Account not found'],404); $q=db()->prepare('SELECT p.code FROM user_permissions up JOIN permissions p ON p.id=up.permission_id WHERE up.user_id=? ORDER BY p.code'); $q->execute([$id]); $u['permissions']=array_column($q->fetchAll(),'code'); return $u; }
function catalog_products(bool $all=false): array { $sql='SELECT p.id,p.code,p.name,p.description,p.units_per_box,p.allow_individual,p.box_price,c.name category FROM products p LEFT JOIN product_categories c ON c.id=p.category_id WHERE p.is_active=1'; if(!$all)$sql.=' AND p.is_public=1'; return db()->query($sql.' ORDER BY p.name LIMIT 250')->fetchAll(); }
