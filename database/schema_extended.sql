-- ============================================================
-- Extended Schema for Capstone Features
-- Smart Inventory System - ISU Cauayan Food Innovation Center
-- ============================================================

-- 16. batches
CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    lot_number VARCHAR(100),
    supplier_id INT,
    manufacturing_date DATE,
    expiration_date DATE,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    remaining_quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    purchase_reference VARCHAR(100),
    storage_location VARCHAR(100),
    status ENUM('Active','Expired','Depleted','Disposed') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_batch_number (batch_number),
    INDEX idx_batch_expiry (expiration_date),
    INDEX idx_batch_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. batch_movements
CREATE TABLE IF NOT EXISTS batch_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    movement_type ENUM('In','Out','Transfer','Adjustment','Disposed') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    previous_remaining DECIMAL(10,2),
    new_remaining DECIMAL(10,2),
    remarks TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. purchase_requests
CREATE TABLE IF NOT EXISTS purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pr_number VARCHAR(50) NOT NULL UNIQUE,
    requestor_id INT NOT NULL,
    department VARCHAR(100),
    purpose TEXT,
    status ENUM('Draft','Submitted','Department Approved','Inventory Approved','Administrator Approved','Purchased','Received','Completed','Rejected','Cancelled') DEFAULT 'Draft',
    estimated_total DECIMAL(12,2) DEFAULT 0,
    actual_total DECIMAL(12,2) DEFAULT 0,
    approved_by_dept INT,
    approved_by_inventory INT,
    approved_by_admin INT,
    approved_date_dept DATETIME,
    approved_date_inventory DATETIME,
    approved_date_admin DATETIME,
    rejection_reason TEXT,
    received_date DATETIME,
    completed_date DATETIME,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requestor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by_dept) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by_inventory) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by_admin) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pr_number (pr_number),
    INDEX idx_pr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. purchase_request_items
CREATE TABLE IF NOT EXISTS purchase_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pr_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    item_code VARCHAR(50),
    category_id INT,
    unit_id INT,
    quantity_requested DECIMAL(10,2) NOT NULL,
    quantity_received DECIMAL(10,2) DEFAULT 0,
    estimated_cost DECIMAL(10,2) DEFAULT 0,
    actual_cost DECIMAL(10,2) DEFAULT 0,
    total_estimated DECIMAL(12,2) DEFAULT 0,
    total_actual DECIMAL(12,2) DEFAULT 0,
    remarks TEXT,
    FOREIGN KEY (pr_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. purchase_request_approvals
CREATE TABLE IF NOT EXISTS purchase_request_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pr_id INT NOT NULL,
    approver_id INT NOT NULL,
    approval_level ENUM('Department','Inventory Manager','Administrator') NOT NULL,
    action ENUM('Approved','Rejected','Returned') NOT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pr_id) REFERENCES purchase_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. borrow_records
CREATE TABLE IF NOT EXISTS borrow_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    borrower_name VARCHAR(150) NOT NULL,
    department VARCHAR(100),
    borrow_date DATETIME NOT NULL,
    expected_return_date DATE NOT NULL,
    actual_return_date DATETIME,
    quantity_borrowed DECIMAL(10,2) NOT NULL,
    quantity_returned DECIMAL(10,2) DEFAULT 0,
    condition_before VARCHAR(100),
    condition_after VARCHAR(100),
    returned_by INT,
    received_by INT,
    penalty DECIMAL(10,2) DEFAULT 0,
    penalty_reason TEXT,
    status ENUM('Borrowed','Returned','Overdue','Lost','Damaged') DEFAULT 'Borrowed',
    remarks TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (returned_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_borrow_status (status),
    INDEX idx_borrow_date (borrow_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. roles (modify - add protected column)
-- Use ALTER if table exists, but for schema we'll include it properly
-- We'll add a proper RBAC system

-- permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    permission_name VARCHAR(150) NOT NULL,
    module VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- role_permissions
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY uk_role_permission (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. email_logs
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    status ENUM('Sent','Failed','Pending') DEFAULT 'Pending',
    error_message TEXT,
    module VARCHAR(50),
    reference_id INT,
    sent_by INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. backup_history
CREATE TABLE IF NOT EXISTS backup_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('Database','Files','Full') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_size BIGINT DEFAULT 0,
    file_path VARCHAR(500),
    status ENUM('Success','Failed','In Progress') DEFAULT 'In Progress',
    error_message TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 25. activity_timeline
CREATE TABLE IF NOT EXISTS activity_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    batch_id INT,
    pr_id INT,
    borrow_id INT,
    action_type VARCHAR(50) NOT NULL,
    action_label VARCHAR(200) NOT NULL,
    previous_value TEXT,
    new_value TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_timeline_item (item_id),
    INDEX idx_timeline_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 26. forecast_data
CREATE TABLE IF NOT EXISTS forecast_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    forecast_date DATE NOT NULL,
    forecast_type ENUM('SMA','WMA') NOT NULL,
    predicted_demand DECIMAL(10,2),
    suggested_reorder_qty DECIMAL(10,2),
    predicted_reorder_date DATE,
    confidence DECIMAL(5,2),
    actual_demand DECIMAL(10,2),
    accuracy DECIMAL(5,2),
    period_months INT DEFAULT 3,
    status ENUM('Active','Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_forecast_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 27. supplier_performance
CREATE TABLE IF NOT EXISTS supplier_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    evaluation_date DATE NOT NULL,
    delivery_ontime_count INT DEFAULT 0,
    delivery_late_count INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    defective_items DECIMAL(10,2) DEFAULT 0,
    total_items_received DECIMAL(10,2) DEFAULT 0,
    average_delivery_days DECIMAL(5,2),
    quality_rating DECIMAL(3,2),
    cost_rating DECIMAL(3,2),
    overall_rating DECIMAL(3,2),
    period VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    INDEX idx_supplier_perf (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 28. recommendations
CREATE TABLE IF NOT EXISTS recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    recommendation_type ENUM('Reorder','Overstock','SlowMoving','DeadStock','Expiring','SupplierChange','UnusualPattern') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    priority ENUM('Critical','Warning','Normal') DEFAULT 'Normal',
    suggested_action TEXT,
    is_dismissed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    INDEX idx_recommendation_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Default Permissions
-- ============================================================

INSERT INTO permissions (permission_key, permission_name, module) VALUES
('dashboard.view', 'View Dashboard', 'Dashboard'),
('inventory.view', 'View Inventory', 'Inventory'),
('inventory.add', 'Add Inventory Items', 'Inventory'),
('inventory.edit', 'Edit Inventory Items', 'Inventory'),
('inventory.delete', 'Delete Inventory Items', 'Inventory'),
('stock.in', 'Process Stock In', 'Inventory'),
('stock.out', 'Process Stock Out', 'Inventory'),
('categories.manage', 'Manage Categories', 'Inventory'),
('units.manage', 'Manage Units', 'Inventory'),
('batches.view', 'View Batches', 'Inventory'),
('batches.manage', 'Manage Batches', 'Inventory'),
('production.view', 'View Production Requests', 'Production'),
('production.create', 'Create Production Requests', 'Production'),
('production.approve', 'Approve Production Requests', 'Production'),
('suppliers.view', 'View Suppliers', 'Suppliers'),
('suppliers.manage', 'Manage Suppliers', 'Suppliers'),
('purchasereq.view', 'View Purchase Requests', 'Purchasing'),
('purchasereq.create', 'Create Purchase Requests', 'Purchasing'),
('purchasereq.approve.dept', 'Department Approval', 'Purchasing'),
('purchasereq.approve.inventory', 'Inventory Manager Approval', 'Purchasing'),
('purchasereq.approve.admin', 'Administrator Approval', 'Purchasing'),
('borrow.view', 'View Borrow Records', 'Borrow'),
('borrow.create', 'Create Borrow Records', 'Borrow'),
('borrow.return', 'Process Returns', 'Borrow'),
('forecast.view', 'View Forecasts', 'Analytics'),
('forecast.generate', 'Generate Forecasts', 'Analytics'),
('reports.view', 'View Reports', 'Reports'),
('reports.export', 'Export Reports', 'Reports'),
('users.manage', 'Manage Users', 'Administration'),
('roles.manage', 'Manage Roles & Permissions', 'Administration'),
('settings.manage', 'Manage Settings', 'Administration'),
('audit.view', 'View Audit Logs', 'Administration'),
('backup.manage', 'Manage Backups', 'Administration'),
('email.manage', 'Manage Email Settings', 'Administration'),
('timeline.view', 'View Activity Timeline', 'Analytics'),
('recommendations.view', 'View Recommendations', 'Analytics');

-- ============================================================
-- Role-Permission Assignments
-- ============================================================

-- Administrator (role_id=1): ALL permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Inventory Manager (role_id=2)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE permission_key IN (
    'dashboard.view',
    'inventory.view', 'inventory.add', 'inventory.edit', 'inventory.delete',
    'stock.in',
    'stock.out',
    'categories.manage',
    'units.manage',
    'batches.view', 'batches.manage',
    'production.view',
    'production.approve',
    'suppliers.view', 'suppliers.manage',
    'purchasereq.view', 'purchasereq.create',
    'purchasereq.approve.inventory',
    'borrow.view', 'borrow.create', 'borrow.return',
    'forecast.view',
    'reports.view', 'reports.export',
    'timeline.view',
    'recommendations.view'
);

-- Production Staff (role_id=3)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE permission_key IN (
    'dashboard.view',
    'inventory.view',
    'stock.out',
    'production.view', 'production.create', 'production.approve',
    'borrow.view',
    'reports.view'
);

-- Faculty (role_id=4)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE permission_key IN (
    'dashboard.view',
    'inventory.view',
    'reports.view'
);
