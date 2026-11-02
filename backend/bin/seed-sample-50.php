<?php
declare(strict_types=1);
require __DIR__.'/../config/bootstrap.php';

$pdo = db();
$counts = $pdo->query('SELECT (SELECT COUNT(*) FROM products) products,(SELECT COUNT(*) FROM restocks) restocks')->fetch();
if ((int)$counts['products'] !== 0 || (int)$counts['restocks'] !== 0) {
    fwrite(STDERR, "Seed stopped: products or restocks already exist; no rows changed.\n");
    exit(2);
}

$names = [
    'Paracetamol 500 mg Tablets','Ibuprofen 200 mg Tablets','Aspirin 75 mg Tablets','Diclofenac Gel 1%','Paracetamol Suspension 120 mg/5 ml','Ibuprofen Suspension 100 mg/5 ml','Menthol Balm','Heat Patch','Oral Rehydration Salts','Muscle Rub',
    'Vitamin C 500 mg Tablets','Vitamin D3 1000 IU Capsules','Zinc 20 mg Tablets','Multivitamin Tablets','B Complex Tablets','Iron Supplement Tablets','Calcium Tablets','Folic Acid 5 mg Tablets','Vitamin C Chewable Tablets','Electrolyte Powder',
    'Adhesive Bandages','Sterile Gauze Pads','Crepe Bandage','Medical Tape','Antiseptic Wipes','Cotton Wool Roll','Digital Thermometer','Disposable Gloves','First Aid Scissors','Saline Wound Wash',
    'Hand Sanitizer 100 ml','Hand Sanitizer 500 ml','Sunscreen SPF 30','Moisturizing Lotion','Petroleum Jelly','Lip Balm','Antibacterial Soap','Baby Powder','Insect Repellent','After Sun Lotion',
    'Cetirizine 10 mg Tablets','Loratadine 10 mg Tablets','Saline Nasal Spray','Throat Lozenges','Cough Syrup Herbal','Menthol Chest Rub','Face Masks','Steam Inhalation Balm','Allergy Eye Drops','Honey Lemon Lozenges',
];
$unitsPerBox = [10,20,30,50,100,12,24,6,15,25];
$adminId = (int)$pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();

$pdo->beginTransaction();
try {
    $supplierInsert = $pdo->prepare('INSERT INTO suppliers(name,phone,email,address,active) VALUES(?,?,?,?,1)');
    $supplierInsert->execute(['Sample Medical Supplies','+255 700 000 050','samples@example.invalid','Sample data only']);
    $supplierId = (int)$pdo->lastInsertId();

    $productInsert = $pdo->prepare('INSERT INTO products(code,name,description,units_per_box,allow_individual,box_price,cost_price,reorder_level_units,is_public,is_active) VALUES(?,?,?,?,0,?,?,?,0,1)');
    $inventoryInsert = $pdo->prepare('INSERT INTO inventory(product_id,stock_units) VALUES(?,0)');
    $products = [];
    foreach ($names as $index => $name) {
        $number = $index + 1;
        $packSize = $unitsPerBox[$index % count($unitsPerBox)];
        $buying = round((850 + $number * 175) / 50) * 50;
        $selling = round($buying * 1.4 / 50) * 50;
        $productInsert->execute(['TMP-SAMPLE-'.str_pad((string)$number,3,'0',STR_PAD_LEFT),$name,'Sample inventory data for system testing.',$packSize,$selling,$buying,$packSize * 5]);
        $productId = (int)$pdo->lastInsertId();
        $code = 'MED-'.str_pad((string)$productId,6,'0',STR_PAD_LEFT);
        $pdo->prepare('UPDATE products SET code=? WHERE id=?')->execute([$code,$productId]);
        $inventoryInsert->execute([$productId]);
        $products[] = ['id'=>$productId,'code'=>$code,'pack'=>$packSize,'buying'=>$buying,'selling'=>$selling];
    }

    $today = new DateTimeImmutable('today',new DateTimeZone('Africa/Dar_es_Salaam'));
    $restockInsert = $pdo->prepare("INSERT INTO restocks(restock_reference,supplier_id,supplier_invoice_reference,performed_by,restock_date,total_amount,payment_status,notes,status) VALUES(?,?,?,?,?,?,'unpaid',?,'confirmed')");
    $itemInsert = $pdo->prepare('INSERT INTO restock_items(restock_id,product_id,incoming_boxes,incoming_loose_units,incoming_stock_units,units_per_box_snapshot,previous_cost_price,new_cost_price,previous_selling_price,new_selling_price,batch_number,expiry_date,purchase_total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $movementInsert = $pdo->prepare("INSERT INTO stock_movements(product_id,movement_type,reference_type,reference_id,quantity_before,quantity_in,quantity_out,quantity_after,units_per_box_snapshot,performed_by,notes) VALUES(?,'restock','restock',?,0,?,0,?,?,?,'Sample restock record for testing')");

    foreach ($products as $index => $product) {
        $number = $index + 1;
        $boxes = 5 + ($number % 11);
        $looseUnits = $number % 4 === 0 ? $number % $product['pack'] : 0;
        $receivedUnits = $boxes * $product['pack'] + $looseUnits;
        $newBuying = $number % 5 === 0 ? round($product['buying'] * 1.04 / 50) * 50 : $product['buying'];
        $newSelling = $number % 5 === 0 ? round($product['selling'] * 1.05 / 50) * 50 : $product['selling'];
        $lineCost = round($boxes * $newBuying + $looseUnits * $newBuying / $product['pack'],2);
        $date = $today->sub(new DateInterval('P'.(49 - $index).'D'));
        $dateText = $date->format('Y-m-d');
        $reference = 'RST-SAMPLE-'.str_replace('-','',$dateText).'-'.str_pad((string)$number,3,'0',STR_PAD_LEFT);
        $restockInsert->execute([$reference,$supplierId,'SUP-SAMPLE-'.str_pad((string)$number,3,'0',STR_PAD_LEFT),$adminId,$dateText,$lineCost,'Sample restock data for workflow and invoice testing.']);
        $restockId = (int)$pdo->lastInsertId();
        $expiry = $date->add(new DateInterval('P18M'))->format('Y-m-d');
        $itemInsert->execute([$restockId,$product['id'],$boxes,$looseUnits,$receivedUnits,$product['pack'],$product['buying'],$newBuying,$product['selling'],$newSelling,'SAMPLE-BATCH-'.str_pad((string)$number,3,'0',STR_PAD_LEFT),$expiry,$lineCost]);
        $movementInsert->execute([$product['id'],$restockId,$receivedUnits,$receivedUnits,$product['pack'],$adminId]);
        $pdo->prepare('UPDATE inventory SET stock_units=? WHERE product_id=?')->execute([$receivedUnits,$product['id']]);
        $pdo->prepare('UPDATE products SET cost_price=?,box_price=? WHERE id=?')->execute([$newBuying,$newSelling,$product['id']]);
    }

    $pdo->commit();
    echo json_encode([
        'products_created'=>count($products),
        'restocks_created'=>count($products),
        'restock_lines_created'=>count($products),
        'public_products'=>0,
        'sample_supplier_created'=>1,
        'users_preserved'=>(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'settings_preserved'=>(int)$pdo->query('SELECT COUNT(*) FROM pharmacy_settings')->fetchColumn(),
    ],JSON_PRETTY_PRINT),"\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$error->getMessage()."\n");
    exit(1);
}
