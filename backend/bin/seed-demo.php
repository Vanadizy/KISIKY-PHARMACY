<?php
declare(strict_types=1);
require_once __DIR__.'/../config/bootstrap.php';
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(1);}
$pdo=db();
$admin=(int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='Administrator'")->fetchColumn();
$credentials=null;
if(!$admin){$email='admin@pharmacy.local';$password='Demo-'.bin2hex(random_bytes(6)).'!';$q=$pdo->prepare('INSERT INTO users(role_id,full_name,email,password_hash,must_change_password) VALUES(1,?,?,?,1)');$q->execute(['Pharmacy Administrator',$email,password_hash($password,PASSWORD_DEFAULT)]);$credentials=['email'=>$email,'password'=>$password];}
$pdo->beginTransaction();
try{
 $cats=[];foreach(['Pain relief','First aid','Vitamins','Personal care'] as $name){$q=$pdo->prepare('INSERT INTO product_categories(name) VALUES(?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$q->execute([$name]);$cats[$name]=(int)$pdo->lastInsertId();}
 $supplier=$pdo->prepare('INSERT INTO suppliers(name,phone,email,address) SELECT ?,?,?,? WHERE NOT EXISTS(SELECT 1 FROM suppliers WHERE name=?)');$supplier->execute(['Demo Medical Supplier','+255 700 000 001','supplier@example.test','Demo data only','Demo Medical Supplier']);
 $products=[
 ['DEMO-PAR-500','Paracetamol 500 mg','Pain relief',100,12000,8000,300,true],
 ['DEMO-ORS-20','Oral Rehydration Salts','Personal care',20,10000,6500,100,false],
 ['DEMO-BND-10','Sterile Bandage Pack','First aid',10,18000,11000,60,true],
 ['DEMO-VIT-C','Vitamin C 500 mg','Vitamins',30,15000,9000,90,true],
 ['DEMO-ANT-10','Antiseptic Solution 100 ml','First aid',12,24000,15000,36,false],
 ['DEMO-ZNC-20','Zinc Tablets','Vitamins',20,14000,8500,60,true]
 ];
 foreach($products as [$code,$name,$cat,$units,$price,$cost,$stock,$individual]){$q=$pdo->prepare('SELECT id FROM products WHERE code=?');$q->execute([$code]);$id=$q->fetchColumn();if($id){continue;}$q=$pdo->prepare('INSERT INTO products(category_id,code,name,description,units_per_box,allow_individual,box_price,cost_price,reorder_level_units,is_public,is_active) VALUES(?,?,?,?,?,?,?,?,?,1,1)');$q->execute([$cats[$cat],$code,$name,'Sample catalogue item for local demo use.', $units,$individual,$price,$cost,(int)($units*2)]);$id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO inventory(product_id,stock_units) VALUES(?,?)')->execute([$id,$stock]);$pdo->prepare("INSERT INTO stock_movements(product_id,movement_type,quantity_before,quantity_in,quantity_out,quantity_after,units_per_box_snapshot,notes) VALUES(?,'opening',0,?,0,?,?, 'Demo seed opening stock')")->execute([$id,$stock,$stock,$units]);}
 $custQ=$pdo->prepare("SELECT id FROM customers WHERE email='customer@pharmacy.local'");$custQ->execute();$customerId=$custQ->fetchColumn();if(!$customerId){$pdo->prepare('INSERT INTO customers(full_name,email,password_hash) VALUES(?,?,?)')->execute(['Demo Wholesale Customer','customer@pharmacy.local',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$customerId=(int)$pdo->lastInsertId();}$exists=$pdo->prepare("SELECT id FROM orders WHERE order_reference='DEMO-ORD-001'");$exists->execute();if(!$exists->fetchColumn()){$pdo->prepare("INSERT INTO orders(order_reference,customer_id,status,subtotal,notes) VALUES('DEMO-ORD-001',?,'submitted',0,'Demo sample order; safe to process')")->execute([$customerId]);$orderId=(int)$pdo->lastInsertId();$sum=0;foreach([['DEMO-PAR-500',2],['DEMO-ORS-20',1]] as [$code,$boxes]){$q=$pdo->prepare('SELECT id,code,name,box_price FROM products WHERE code=?');$q->execute([$code]);$p=$q->fetch();$line=$boxes*(float)$p['box_price'];$sum+=$line;$pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name_snapshot,product_code_snapshot,requested_boxes,confirmed_boxes,box_price_snapshot,line_total) VALUES(?,?,?,?,?,0,?,?)')->execute([$orderId,$p['id'],$p['name'],$p['code'],$boxes,$p['box_price'],$line]);}$pdo->prepare('UPDATE orders SET subtotal=? WHERE id=?')->execute([$sum,$orderId]);}
 $pdo->commit();
}catch(Throwable $e){$pdo->rollBack();throw $e;}
echo json_encode(['seeded'=>true,'admin_created'=>$credentials!==null,'admin'=>$credentials,'demo_product_count'=>count($products)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
