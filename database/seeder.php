<?php
require_once __DIR__ . '/../config/database.php';

echo "============================================\n";
echo "Smart Inventory System - Database Seeder\n";
echo "ISU Cauayan Food Innovation Center\n";
echo "============================================\n\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    if ($userCount > 0) {
        echo "Database already has data. Skipping seeding.\n";
        exit;
    }

    // 1. Roles
    echo "Inserting roles...\n";
    $roles = [
        ['Administrator', 'Full system access'],
        ['Inventory Manager', 'Manages inventory, stock in/out, and reports'],
        ['Production Staff', 'Creates production requests and views inventory'],
        ['Faculty', 'Read-only access to inventory data'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (role_name, description) VALUES (?, ?)");
    foreach ($roles as $role) { $stmt->execute($role); echo "  - {$role[0]}\n"; }

    // 2. Users (unchanged)
    echo "\nInserting users...\n";
    $users = [
        ['admin', 'admin123', 'admin@isu.edu.ph', 'Administrator', 1],
        ['inventory', 'inventory123', 'inventory@isu.edu.ph', 'Inventory Manager', 2],
        ['production', 'production123', 'production@isu.edu.ph', 'Production Staff', 3],
        ['faculty', 'faculty123', 'faculty@isu.edu.ph', 'Faculty Member', 4],
    ];
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role_id, status) VALUES (?, ?, ?, ?, ?, 'Active')");
    foreach ($users as $user) {
        $stmt->execute([$user[0], password_hash($user[1], PASSWORD_DEFAULT), $user[2], $user[3], $user[4]]);
        echo "  - {$user[0]} ({$user[3]})\n";
    }

    // 3. Units
    echo "\nInserting units...\n";
    $units = [
        ['Kilogram','kg'],['Gram','g'],['Liter','L'],['Milliliter','ml'],['Pieces','pcs'],
        ['Box','box'],['Pack','pack'],['Bag','bag'],['Sack','sack'],['Roll','roll'],
        ['Bottle','bottle'],['Can','can'],['Jar','jar'],['Case','case'],['Tray','tray'],
    ];
    $stmt = $pdo->prepare("INSERT INTO units (unit_name, abbreviation) VALUES (?, ?)");
    foreach ($units as $u) { $stmt->execute($u); }
    echo "  " . count($units) . " units inserted.\n";

    // 4. Categories
    echo "\nInserting categories...\n";
    $cats = [
        ['Raw Materials','Raw Materials','Basic ingredients for food production'],
        ['Production Supplies','Production Supplies','Supplies used in processing'],
        ['Packaging','Packaging','Materials for packaging finished products'],
        ['Chemicals','Chemicals','Processing and laboratory chemicals'],
        ['Cleaning Materials','Cleaning Materials','Sanitation and cleaning supplies'],
        ['Office Supplies','Office Supplies','Administrative supplies'],
        ['Equipment','Equipment','Production and lab equipment'],
    ];
    $stmt = $pdo->prepare("INSERT INTO categories (category_name, category_type, description, status) VALUES (?, ?, ?, 'Active')");
    foreach ($cats as $c) { $stmt->execute($c); }
    echo "  " . count($cats) . " categories inserted.\n";

    // 5. Suppliers (realistic for ISU Cauayan FIC)
    echo "\nInserting suppliers...\n";
    $suppliers = [
        ['Cauayan Rice Mill & Grains Trading', 'Roberto C. Miranda', 'Brgy. Turayong, Cauayan City, Isabela', '0918-765-4321', 'rcmiranda@cauayanrice.ph', 'Rice, Corn, Flour, Grains, Feeds'],
        ['Isabela Agri-Products Trading', 'Mercy Ann D. Fernandez', 'National Highway, Brgy. Dist. II, Cauayan City', '0917-555-1234', 'mafernandez@isabelaagri.ph', 'Fresh Vegetables, Fruits, Herbs, Spices'],
        ['NorthEast Packaging Supply', 'Engr. Karlito A. Reyes', 'Mallig Plains, Roxas, Isabela', '0922-888-7654', 'kareyes@northeastpack.ph', 'Plastic Containers, Bottles, Labels, Boxes'],
        ['MegaCauayan Chemical Solutions', 'Dr. Shirley M. Lazo', 'Brgy. Minante, Cauayan City, Isabela', '0933-444-5678', 'smlazo@megachem.ph', 'Food Preservatives, Sanitizers, Lab Reagents'],
        ['Cagayan Valley Equipment Services', 'Fernando G. Castro', 'Brgy. Alicaocao, Cauayan City', '0920-777-8901', 'fgcastro@cvequip.ph', 'Processing Equipment, Spare Parts, Tools'],
        ['Santiago Food Grade Supplies', 'Lorna P. Ramirez', 'Maharlika Highway, Santiago City, Isabela', '0945-222-3344', 'lpramirez@sfgs.ph', 'Packing Materials, Gloves, Aprons, Hairnets'],
    ];
    $stmt = $pdo->prepare("INSERT INTO suppliers (company_name, contact_person, address, phone, email, products, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
    foreach ($suppliers as $s) { $stmt->execute($s); }
    echo "  " . count($suppliers) . " suppliers inserted.\n";

    // 6. Inventory Items (realistic food innovation center items)
    echo "\nInserting inventory items...\n";
    $items = [
        // Raw Materials (category 1, units: kg=1, L=3)
        ['ITM010001','Premium Rice - Dinorado','High quality Dinorado rice from Cauayan farms',1,1,1200,100,2500,55.00,1,'2026-08-15','Dry Goods Storage - Rack A1'],
        ['ITM010002','Glutinous Rice','Malagkit rice for native delicacies',1,1,800,50,1500,65.00,1,'2026-07-20','Dry Goods Storage - Rack A2'],
        ['ITM010003','Corn Grits','Coarse ground corn for snack food production',1,1,450,60,1000,38.00,1,'2026-09-01','Dry Goods Storage - Rack B1'],
        ['ITM010004','Wheat Flour - All Purpose','Premium APF for baking and pastries',1,1,600,80,1200,42.50,2,'2026-06-30','Dry Goods Storage - Rack B2'],
        ['ITM010005','Coconut Milk - Pure','Pure extracted coconut milk, no additives',1,3,200,30,500,95.00,2,'2026-05-15','Cold Storage - Section 1'],
        ['ITM010006','Brown Sugar','Local muscovado sugar from Isabela',1,1,350,50,800,58.00,2,'2026-10-01','Dry Goods Storage - Rack C1'],
        ['ITM010007','Cooking Oil - Palm','Refined palm oil for deep frying',1,3,400,40,700,85.00,2,'2026-04-30','Dry Goods Storage - Rack C2'],
        // Packaging (category 3, units: pcs=5, box=6, roll=10)
        ['ITM030001','Plastic Tub 250ml','Round PP plastic tub with lid',3,5,5000,500,10000,8.50,3,'2027-01-01','Packaging Storage - Shelf 1'],
        ['ITM030002','Plastic Tub 500ml','Square PP plastic tub with lid',3,5,3500,400,8000,12.00,3,'2027-01-01','Packaging Storage - Shelf 2'],
        ['ITM030003','Stand-Up Pouch 100g','Aluminum foil stand-up pouch with resealable zipper',3,6,20000,2000,50000,3.50,3,'2026-12-01','Packaging Storage - Shelf 3'],
        ['ITM030004','Label Sticker - Waterproof 2x2','Glossy waterproof adhesive label',3,10,15000,1000,30000,1.25,3,'2027-03-01','Packaging Storage - Shelf 4'],
        ['ITM030005','Shrink Wrap Roll 18in','PVC shrink wrap for bundling',3,10,25,5,60,350.00,3,'2026-11-01','Packaging Storage - Shelf 5'],
        // Chemicals (category 4, units: L=3, kg=1, bottle=11)
        ['ITM040001','Food Grade Sanitizer - Chlorine Base','Approved food contact surface sanitizer',4,3,80,15,200,275.00,4,'2025-12-31','Chemical Cabinet - Left'],
        ['ITM040002','Citric Acid - Food Grade','Acidity regulator for food processing',4,1,50,10,150,160.00,4,'2026-06-30','Chemical Cabinet - Right'],
        ['ITM040003','Sodium Metabisulfite','Preservative for dried fruits and vegetables',4,1,30,5,80,420.00,4,'2025-11-30','Chemical Cabinet - Right'],
        // Equipment (category 7, units: pcs=5)
        ['ITM070001','Digital Food Scale 5kg','Stainless steel platform, LCD display',7,5,8,2,15,2500.00,5,'2028-01-01','Equipment Room - Shelf A'],
        ['ITM070002','Stainless Mixing Bowl 20L','Heavy gauge 304 stainless steel',7,5,15,3,30,850.00,5,'2027-06-01','Equipment Room - Shelf B'],
        ['ITM070003','Food Thermometer - Digital','Probe type, -50 to 300°C range',7,5,12,3,20,1200.00,5,'2027-09-01','Equipment Room - Shelf C'],
        // Cleaning Materials (category 5, units: L=3, bottle=11, pack=7)
        ['ITM050001','Commercial Dishwashing Liquid','Concentrated, food safe formula',5,11,25,5,60,180.00,4,'2026-08-15','Cleaning Supplies Cabinet'],
        ['ITM050002','Disposable Gloves - Nitrile','Box of 100, food grade, powder-free',5,6,30,5,50,320.00,5,'2027-04-01','Cleaning Supplies Cabinet'],
    ];
    $stmt = $pdo->prepare("INSERT INTO inventory_items (item_code, name, description, category_id, unit_id, current_stock, min_stock, max_stock, unit_cost, supplier_id, expiration_date, storage_location, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'Active')");
    foreach ($items as $it) { $stmt->execute($it); echo "  - {$it[1]}\n"; }

    // 7. Batches with FIFO data
    echo "\nInserting batches...\n";
    $batches = [
        [2,'BATCH-RICE-2025-01','LOT-R01',1,'2025-01-10','2026-08-15',500,120,50.00,'PO-2025-001','Dry Goods - Rack A1','Active'],
        [2,'BATCH-RICE-2025-02','LOT-R02',1,'2025-03-15','2026-10-01',700,680,52.00,'PO-2025-008','Dry Goods - Rack A1','Active'],
        [5,'BATCH-FLOUR-2025-01','LOT-F01',2,'2025-02-01','2026-06-30',600,580,42.50,'PO-2025-003','Dry Goods - Rack B2','Active'],
        [8,'BATCH-TUB250-2025-01','LOT-P01',3,'2025-01-20','2027-01-01',5000,4800,8.50,'PO-2025-005','Packaging - Shelf 1','Active'],
        [13,'BATCH-SANITIZER-2025-01','LOT-C01',4,'2025-01-05','2025-12-31',80,65,275.00,'PO-2025-007','Chemical Cabinet','Active'],
    ];
    $stmt = $pdo->prepare("INSERT INTO batches (item_id, batch_number, lot_number, supplier_id, manufacturing_date, expiration_date, quantity, remaining_quantity, unit_cost, purchase_reference, storage_location, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($batches as $b) { $stmt->execute($b); }
    echo "  " . count($batches) . " batches inserted.\n";

    // 8. Stock In Records
    echo "\nInserting stock in records...\n";
    $stm = $pdo->prepare("INSERT INTO stock_in (item_id, transaction_type, supplier_id, quantity, unit_cost, total_cost, batch_number, reference_number, received_by, date_received) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $sis = [
        [1,'Purchase',1,500,55.00,27500.00,'BATCH-RICE-2025-01','PO-2025-001',1,'2025-01-15 08:30:00'],
        [1,'Purchase',1,700,52.00,36400.00,'BATCH-RICE-2025-02','PO-2025-008',1,'2025-03-20 09:00:00'],
        [4,'Purchase',2,600,42.50,25500.00,'BATCH-FLOUR-2025-01','PO-2025-003',2,'2025-02-05 10:00:00'],
        [8,'Purchase',3,5000,8.50,42500.00,'BATCH-TUB250-2025-01','PO-2025-005',2,'2025-01-25 11:00:00'],
        [13,'Purchase',4,80,275.00,22000.00,'BATCH-SANITIZER-2025-01','PO-2025-007',1,'2025-01-10 08:00:00'],
        [18,'Purchase',5,15,850.00,12750.00,NULL,'PO-2025-010',1,'2025-02-15 13:00:00'],
        [7,'Purchase',2,400,85.00,34000.00,NULL,'PO-2025-012',2,'2025-03-01 14:30:00'],
        [20,'Purchase',5,30,320.00,9600.00,NULL,'PO-2025-015',1,'2025-03-10 09:00:00'],
    ];
    foreach ($sis as $si) { $stm->execute($si); }
    echo "  " . count($sis) . " stock in records inserted.\n";

    // 9. Stock Out Records
    echo "\nInserting stock out records...\n";
    $stmo = $pdo->prepare("INSERT INTO stock_out (item_id, transaction_type, quantity, unit_cost, total_cost, reference_number, purpose, requested_by, date_released) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $sos = [
        [1,'Production Request',80,55.00,4400.00,'PR-2025-001','Rice Cookie Production Batch 1',3,'2025-02-10 10:00:00'],
        [2,'Production Request',30,65.00,1950.00,'PR-2025-001','Rice Cookie Production Batch 1',3,'2025-02-10 10:00:00'],
        [7,'Production Request',20,85.00,1700.00,'PR-2025-001','Rice Cookie Production Batch 1',3,'2025-02-10 10:00:00'],
        [1,'Production Request',100,55.00,5500.00,'PR-2025-002','Rice Cookie Production Batch 2',3,'2025-03-05 09:30:00'],
        [5,'Production Request',15,42.50,637.50,'PR-2025-002','Rice Cookie Production Batch 2',3,'2025-03-05 09:30:00'],
        [8,'Production Request',200,8.50,1700.00,'PR-2025-002','Packaging for Batch 2',3,'2025-03-06 08:00:00'],
        [10,'Production Request',500,3.50,1750.00,'PR-2025-003','Pouch Packaging Trial',3,'2025-03-15 11:00:00'],
        [13,'Consumption',15,275.00,4125.00,'ADJ-2025-001','Monthly sanitation consumption',2,'2025-03-31 16:00:00'],
    ];
    foreach ($sos as $so) { $stmo->execute($so); }
    echo "  " . count($sos) . " stock out records inserted.\n";

    // 10. Production Requests
    echo "\nInserting production requests...\n";
    $st = $pdo->prepare("INSERT INTO production_requests (request_number, requested_by, department, request_date, purpose, status, approved_by, approved_date, completed_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $prs = [
        ['PR-2025-001',3,'Food Processing','2025-02-08','Rice Cookie Production - Batch 1 (500 pcs)','Completed',1,'2025-02-09 09:00:00','2025-02-10 16:00:00'],
        ['PR-2025-002',3,'Food Processing','2025-03-03','Rice Cookie Production - Batch 2 (1000 pcs)','Completed',1,'2025-03-04 10:00:00','2025-03-06 15:00:00'],
        ['PR-2025-003',3,'Food Processing','2025-03-12','New Product Development - Corn Snacks Trial','Approved',2,'2025-03-13 08:00:00',NULL],
        ['PR-2025-004',3,'Food Processing','2025-04-01','Coconut Delicacies Production','Pending',NULL,NULL,NULL],
    ];
    foreach ($prs as $p) { $st->execute($p); }
    echo "  " . count($prs) . " production requests inserted.\n";

    // PR Items
    $st2 = $pdo->prepare("INSERT INTO production_request_items (request_id, item_id, quantity_requested, quantity_released) VALUES (?, ?, ?, ?)");
    $st2->execute([1,1,80,80]); $st2->execute([1,2,30,30]); $st2->execute([1,7,20,20]);
    $st2->execute([2,1,100,100]); $st2->execute([2,5,15,15]); $st2->execute([2,8,200,200]);
    $st2->execute([3,3,50,0]); $st2->execute([3,10,200,0]); $st2->execute([3,9,100,0]);
    $st2->execute([4,6,40,0]); $st2->execute([4,5,10,0]);

    // 11. Purchase Requests
    echo "\nInserting purchase requests...\n";
    $stpr = $pdo->prepare("INSERT INTO purchase_requests (pr_number, requestor_id, department, purpose, status, estimated_total, approved_by_dept, approved_by_inventory, approved_by_admin, approved_date_dept, approved_date_inventory, approved_date_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stpr->execute(['PR-2025-001',2,'Inventory Management','Monthly rice supply restock - Q1 2025','Completed',36400.00,1,1,1,'2025-01-10 09:00:00','2025-01-11 10:00:00','2025-01-12 08:00:00']);
    $stpr->execute(['PR-2025-002',2,'Inventory Management','Q1 Packaging materials order','Completed',42500.00,1,1,1,'2025-01-20 09:00:00','2025-01-21 10:00:00','2025-01-22 08:00:00']);
    $stpr->execute(['PR-2025-003',3,'Food Processing','Equipment: Mixing bowls and thermometers','Completed',22500.00,1,1,1,'2025-02-10 09:00:00','2025-02-11 10:00:00','2025-02-12 08:00:00']);
    $stpr->execute(['PR-2025-004',2,'Inventory Management','Q2 Chemical and cleaning supplies','Inventory Approved',25000.00,1,2,NULL,'2025-03-20 09:00:00','2025-03-21 14:00:00',NULL]);
    echo "  4 purchase requests inserted.\n";

    // 12. Borrow Records
    echo "\nInserting borrow records...\n";
    $stbr = $pdo->prepare("INSERT INTO borrow_records (item_id, borrower_name, department, borrow_date, expected_return_date, actual_return_date, quantity_borrowed, quantity_returned, condition_before, condition_after, returned_by, received_by, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stbr->execute([17,'Maria Santos','Food Processing','2025-02-01 09:00:00','2025-02-15','2025-02-14 16:00:00',1,1,'Good','Good',1,1,'Returned',1]);
    $stbr->execute([16,'Roberto Castro','Food Processing','2025-02-10 10:00:00','2025-02-28','2025-02-27 15:00:00',3,3,'Excellent','Good',2,1,'Returned',1]);
    $stbr->execute([18,'Angela Reyes','Research & Development','2025-03-01 08:00:00','2025-03-20','2025-03-18 11:00:00',2,2,'Good','Good',2,2,'Returned',2]);
    $stbr->execute([16,'John Mercado','Food Processing','2025-03-22 09:00:00','2025-04-05',NULL,1,0,'Good',NULL,NULL,NULL,'Borrowed',3]);
    $stbr->execute([17,'Pedro Cruz','Production','2025-03-15 10:00:00','2025-03-30',NULL,2,0,'Good',NULL,NULL,NULL,'Overdue',3]);
    echo "  5 borrow records inserted.\n";

    // 13. Notifications
    echo "\nInserting notifications...\n";
    $stn = $pdo->prepare("INSERT INTO notifications (type, title, message, related_module, related_id, is_read) VALUES (?, ?, ?, ?, ?, ?)");
    $stn->execute(['Low Stock','Low Stock Alert: Food Grade Sanitizer','Current stock (80) is below minimum level (15).','inventory',13,0]);
    $stn->execute(['Near Expiry','Near Expiry: Premium Rice - Local','Premium Rice - Local will expire in 60 days.','inventory',1,0]);
    $stn->execute(['Pending Request','Pending Production Request','New product development request awaiting approval.','production',3,1]);
    $stn->execute(['Expired','Item Expired: Sodium Metabisulfite','This item has expired on 2025-11-30.','inventory',15,0]);
    echo "  4 notifications inserted.\n";

    // 14. Inventory Transactions
    echo "\nInserting inventory transactions...\n";
    $stt = $pdo->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, reference_type, reference_id, quantity, previous_stock, new_stock, unit_cost, total_value, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $txns = [
        [1,'Stock In','Stock In',1,500,0,500,55.00,27500.00,'Initial stock in - PO-2025-001',1],
        [1,'Stock In','Stock In',2,700,500,1200,52.00,36400.00,'Restock - PO-2025-008',1],
        [1,'Stock Out','Production',1,80,1200,1120,55.00,4400.00,'Rice Cookie Batch 1',3],
        [1,'Stock Out','Production',2,100,1120,1020,55.00,5500.00,'Rice Cookie Batch 2',3],
        [4,'Stock In','Stock In',1,600,0,600,42.50,25500.00,'Initial stock in - PO-2025-003',2],
        [8,'Stock In','Stock In',1,5000,0,5000,8.50,42500.00,'Initial stock in - PO-2025-005',2],
        [8,'Stock Out','Production',2,200,5000,4800,8.50,1700.00,'Packaging Batch 2',3],
        [13,'Stock In','Stock In',1,80,0,80,275.00,22000.00,'Initial stock in - PO-2025-007',1],
        [13,'Stock Out','Consumption',1,15,80,65,275.00,4125.00,'Monthly sanitation',2],
    ];
    foreach ($txns as $t) { $stt->execute($t); }
    echo "  " . count($txns) . " transactions inserted.\n";

    // 15. System Settings
    echo "\nInserting system settings...\n";
    $settings = [
        ['system_name','Smart Inventory System'],
        ['school_name','ISU Cauayan Food Innovation Center'],
        ['theme','light'],
        ['timezone','Asia/Manila'],
    ];
    $stset = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $s) { $stset->execute($s); }
    echo "  " . count($settings) . " settings inserted.\n";

    // 16. Backup dir
    $backupDir = __DIR__ . '/../uploads/backup/';
    if (!is_dir($backupDir)) { mkdir($backupDir, 0777, true); echo "\nBackup directory created.\n"; }

    echo "\n============================================\n";
    echo "Seeding completed successfully!\n";
    echo "============================================\n";
    echo "\nLogin Credentials:\n";
    echo "  Administrator  - admin / admin123\n";
    echo "  Inventory Mgr  - inventory / inventory123\n";
    echo "  Production     - production / production123\n";
    echo "  Faculty        - faculty / faculty123\n";
    echo "\nSample Data:\n";
    echo "  - 20 inventory items across all categories\n";
    echo "  - 5 batches with expiration tracking (FIFO ready)\n";
    echo "  - 8 stock in / 8 stock out records\n";
    echo "  - 4 production requests with different stages\n";
    echo "  - 4 purchase requests with multi-level approvals\n";
    echo "  - 5 borrow records (some returned, some overdue)\n";
    echo "  - 4 notifications\n";
    echo "  - 9 inventory transactions\n";
    echo "\nAccess URL: http://localhost/smart-inventory/\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
