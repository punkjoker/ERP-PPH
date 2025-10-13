CREATE TABLE groups (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    group_id INT NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(group_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
INSERT INTO groups (group_name, description) VALUES
('SuperAdmin', 'Full access to all modules'),
('HR', 'Human Resources Department'),
('Stores', 'Inventory and stores management'),
('Production', 'Production Department'),
('QualityControl', 'Quality Control Department'),
('Procurement', 'Handles supplier relations, purchases, and procurement reports'),
('Drivers', 'Handles deliveries, vehicles, and transport management'),
('Reports', 'Access to overall system reports and analytics');



CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(100) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE materials ADD quantity INT DEFAULT 0 AFTER cost;

CREATE TABLE stock_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_name VARCHAR(255),
    stock_code VARCHAR(100),
    quantity VARCHAR(100),
    unit VARCHAR(50),
    total_cost DECIMAL(10,2),
    unit_cost DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE stock_in 
ADD COLUMN original_quantity INT DEFAULT NULL 
AFTER quantity;

CREATE TABLE stock_out_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_code VARCHAR(50),
    stock_name VARCHAR(100),
    quantity_removed DECIMAL(10,2),
    unit_cost DECIMAL(10,2),
    remaining_quantity DECIMAL(10,2),
    removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE stock_out_history 
ADD COLUMN stock_date DATE AFTER remaining_quantity;
ALTER TABLE stock_out_history
ADD COLUMN reason VARCHAR(255) DEFAULT NULL AFTER stock_date,
ADD COLUMN requested_by VARCHAR(255) DEFAULT NULL AFTER reason,
ADD COLUMN approved_by VARCHAR(255) DEFAULT NULL AFTER requested_by;


CREATE TABLE material_out_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    material_name VARCHAR(100) NOT NULL,
    quantity_removed INT NOT NULL,
    remaining_quantity INT NOT NULL,
    issued_to VARCHAR(100),
    description TEXT,
    removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(id)
);
CREATE TABLE chemicals_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_name VARCHAR(150) NOT NULL,
    rm_lot_no VARCHAR(100) NOT NULL,
    std_quantity DECIMAL(10,2) NOT NULL,          -- Standard quantity
    original_quantity DECIMAL(10,2) NOT NULL,     -- Total loaded quantity
    remaining_quantity DECIMAL(10,2) NOT NULL,    -- Decreases when used
    total_cost DECIMAL(12,2) NOT NULL,            -- Total cost of lot
    unit_price DECIMAL(10,2) NOT NULL,            -- Price per kg/litre
    date_added DATE NOT NULL,                     -- Date picker input
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE chemicals_in
ADD COLUMN status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending' AFTER date_added;
ALTER TABLE chemicals_in ADD COLUMN batch_no VARCHAR(100) AFTER chemical_name;


CREATE TABLE inspected_chemicals_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chemical_id INT NOT NULL,
    rm_lot_no VARCHAR(100),
    approved_quantity DECIMAL(10,2),
    approved_by VARCHAR(100),
    approved_date DATE,
    tests JSON, -- store multiple test rows
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chemical_id) REFERENCES chemicals_in(id) ON DELETE CASCADE
);
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Main BOM
CREATE TABLE bill_of_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    bom_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- BOM items (chemicals)
CREATE TABLE bill_of_material_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bom_id INT NOT NULL,
    chemical_id INT NOT NULL,
    quantity_requested DECIMAL(10,2),
    unit VARCHAR(10),
    unit_price DECIMAL(10,2),
    total_cost DECIMAL(10,2),
    FOREIGN KEY (bom_id) REFERENCES bill_of_materials(id) ON DELETE CASCADE,
    FOREIGN KEY (chemical_id) REFERENCES chemicals_in(id) ON DELETE CASCADE
);
ALTER TABLE bill_of_materials 
    ADD requested_by VARCHAR(100) AFTER bom_date,
    ADD description TEXT AFTER requested_by;

ALTER TABLE bill_of_materials
ADD issued_by VARCHAR(100) AFTER requested_by,
ADD remarks TEXT AFTER issued_by,
ADD issue_date DATE AFTER remarks;

ALTER TABLE bill_of_material_items
ADD COLUMN chemical_name VARCHAR(100) AFTER chemical_id,
ADD COLUMN rm_lot_no VARCHAR(50) AFTER chemical_name;


CREATE TABLE production_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT, -- link to production request
    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50) DEFAULT '209024',
    requested_by VARCHAR(100),
    description TEXT,
    expected_yield VARCHAR(50), -- e.g. "100 Kg"
    obtained_yield VARCHAR(50),
    status ENUM('In production','Completed') DEFAULT 'In production',
    qc_status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE production_runs ADD COLUMN completed_at DATETIME NULL AFTER obtained_yield;

CREATE TABLE production_procedures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_run_id INT NOT NULL,
    procedure_name VARCHAR(255),
    done_by VARCHAR(100),
    checked_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_run_id) REFERENCES production_runs(id) ON DELETE CASCADE
);
ALTER TABLE production_runs ADD UNIQUE (request_id);

CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    national_id VARCHAR(20) UNIQUE,
    kra_pin VARCHAR(20) UNIQUE,          -- KRA PIN
    nssf_number VARCHAR(30) UNIQUE,      -- NSSF number
    nhif_number VARCHAR(30) UNIQUE,      -- NHIF/SHA number
    passport_path VARCHAR(255),          -- File path to uploaded passport photo
    
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20),
    department VARCHAR(100),
    position VARCHAR(100),
    date_of_hire DATE,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE employees
ADD COLUMN employment_type VARCHAR(20) DEFAULT 'Permanent',
ADD COLUMN contract_start DATE NULL,
ADD COLUMN contract_end DATE NULL;


CREATE TABLE leaves (
    leave_id INT(11) NOT NULL AUTO_INCREMENT,
    employee_id INT(11) NOT NULL,
    description VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (leave_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);
CREATE TABLE expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    expense_name VARCHAR(100) NOT NULL,
    expense_date DATE NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('Paid', 'Not Paid') DEFAULT 'Not Paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE lunch_expense (
    lunch_id INT(11) NOT NULL AUTO_INCREMENT,
    week_no INT(2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    items TEXT NOT NULL, -- store JSON like [{"item":"Rice","cost":500},...]
    total_amount DECIMAL(10,2) NOT NULL,
    items_bought_by VARCHAR(100),
    transport_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (lunch_id)
);

CREATE TABLE trainings (
    training_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    training_name VARCHAR(200) NOT NULL,
    training_date DATE NOT NULL,
    status ENUM('Pending','Done') DEFAULT 'Pending',
    done_by VARCHAR(100),
    approved_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

CREATE TABLE procurement_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    requested_by VARCHAR(255) DEFAULT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,  -- if linked to procurement_products
    manual_product VARCHAR(255) NULL, -- if entered manually
    type_model_brand VARCHAR(255),
    quantity INT NOT NULL,
    user_of_product VARCHAR(255), -- who will be using it
    
    supplier_name VARCHAR(255) NOT NULL,
    supplier_contact VARCHAR(255),
    price DECIMAL(12,2) NOT NULL,
    payment_terms VARCHAR(255),

    status ENUM('available', 'unavailable', 'initial') DEFAULT 'initial',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_supplier_product FOREIGN KEY (product_id) REFERENCES procurement_products(id)
);

ALTER TABLE suppliers 
MODIFY price DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE suppliers MODIFY quantity INT NULL;

CREATE TABLE po_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    po_no VARCHAR(50) UNIQUE,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_percentage DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    status TINYINT DEFAULT 0 COMMENT '0=Pending,1=Approved,2=Denied',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    product_id INT DEFAULT NULL, -- links to procurement_products if available
    manual_name VARCHAR(255) DEFAULT NULL, -- if entered manually
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit VARCHAR(50) DEFAULT NULL,
    unit_price DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    FOREIGN KEY (po_id) REFERENCES po_list(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES procurement_products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    company_name VARCHAR(255) NULL, -- Delivery company or person responsible
    delivery_contact VARCHAR(100) NULL, -- Contact phone/email
    expected_delivery DATE NULL,
    delivered_date DATE NULL,
    status TINYINT(1) NOT NULL DEFAULT 0, -- 0 = Pending, 1 = Delivered
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_po FOREIGN KEY (po_id) REFERENCES po_list(id) ON DELETE CASCADE
);

CREATE TABLE vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_name VARCHAR(100),
  vehicle_number VARCHAR(50),
  model VARCHAR(100),
  license_expiry DATE,
  vehicle_name VARCHAR(100)
);
CREATE TABLE vehicle_maintenance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_name VARCHAR(100),
  vehicle_number VARCHAR(50),
  maintenance_name VARCHAR(100),
  maintenance_company VARCHAR(100),
  maintenance_cost DECIMAL(10,2),
  approved_by VARCHAR(100),
  receipt_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fuel_cost (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_number VARCHAR(50),
  amount_refueled DECIMAL(10,2),
  refueled_by VARCHAR(100),
  approved_by VARCHAR(100),
  receipt_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE trips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_name VARCHAR(100),
  vehicle_id VARCHAR(100),
  destination_from VARCHAR(100),
  destination_to VARCHAR(100),
  distance_km DECIMAL(10,2),
  delivery_name VARCHAR(100),
  delivery_date DATETIME,
  route_name VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE breakfast_expense (
  breakfast_id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  items JSON NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  items_bought_by VARCHAR(100),
  transport_cost DECIMAL(10,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE department_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(150),
  item_used_by VARCHAR(100),
  requested_qty INT,
  requested_by VARCHAR(100),
  status ENUM('Pending Approval', 'Approved', 'Rejected') DEFAULT 'Pending Approval',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE department_requests 
ADD COLUMN department VARCHAR(100) AFTER requested_by;


ALTER TABLE department_requests 
ADD COLUMN approved_qty INT DEFAULT 0;

CREATE TABLE qc_inspections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  production_run_id INT NOT NULL,
  test_name VARCHAR(100),
  specification TEXT,
  procedure_done TEXT,
  qc_status ENUM('Approved Product', 'Not Approved') DEFAULT 'Not Approved',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (production_run_id) REFERENCES production_runs(id) ON DELETE CASCADE
);

CREATE TABLE packaging_reconciliation (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qc_inspection_id INT NOT NULL,
  item_name VARCHAR(100),
  issued DECIMAL(10,2),
  used DECIMAL(10,2),
  wasted DECIMAL(10,2),
  balance DECIMAL(10,2),
  quantity_achieved DECIMAL(10,2),
  yield_percent DECIMAL(5,2),
  units VARCHAR(50),
  cost_per_unit DECIMAL(10,2),
  total_cost DECIMAL(10,2),
  FOREIGN KEY (qc_inspection_id) REFERENCES qc_inspections(id) ON DELETE CASCADE
);

CREATE TABLE quality_manager_review (
  id INT AUTO_INCREMENT PRIMARY KEY,
  qc_inspection_id INT NOT NULL,
  checklist_no INT,
  checklist_item TEXT,
  response ENUM('Yes', 'No') DEFAULT 'No',
  FOREIGN KEY (qc_inspection_id) REFERENCES qc_inspections(id) ON DELETE CASCADE
);

CREATE TABLE order_deliveries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_day VARCHAR(100) NOT NULL,
  delivery_date DATE NOT NULL,
  status ENUM('Pending', 'Completed') DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_delivery_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  delivery_id INT NOT NULL,
  destination VARCHAR(255) NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity VARCHAR(50) NOT NULL,
  FOREIGN KEY (delivery_id) REFERENCES order_deliveries(id) ON DELETE CASCADE
);

CREATE TABLE disposables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_name VARCHAR(255) DEFAULT NULL,
  asset_id VARCHAR(255) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  disposal_method VARCHAR(100) DEFAULT NULL,
  disposal_date VARCHAR(100) DEFAULT NULL,
  disposal_location VARCHAR(255) DEFAULT NULL,
  quantity VARCHAR(50) DEFAULT NULL,
  condition_before VARCHAR(100) DEFAULT NULL,
  authorized_by VARCHAR(255) DEFAULT NULL,
  handled_by VARCHAR(255) DEFAULT NULL,
  regulatory_ref VARCHAR(255) DEFAULT NULL,
  certificate_ref VARCHAR(255) DEFAULT NULL,
  reason_for_disposal VARCHAR(255) DEFAULT NULL,
  remarks TEXT DEFAULT NULL
);
