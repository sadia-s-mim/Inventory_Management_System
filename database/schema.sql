-- =====================================================================
-- PERFECT CHOICE — Clothing Retail Inventory Management System
-- =====================================================================

CREATE DATABASE IF NOT EXISTS perfect_choice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE perfect_choice;

-- ---------------------------------------------------------------------
-- ROLES — Admin / Branch Manager / Sales User
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);
-- ---------------------------------------------------------------------
-- BRANCHES
-- ---------------------------------------------------------------------
CREATE TABLE branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    phone VARCHAR(30),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ---------------------------------------------------------------------
-- USERS
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    branch_id INT,
    phone VARCHAR(30),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- CATEGORIES — self-referencing tree (Gender > Clothing/Shoes > Type)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    parent_id INT DEFAULT NULL,
    cat_level TINYINT NOT NULL DEFAULT 1,  -- 1=Gender, 2=Group, 3=Type
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- SUPPLIERS
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(150),
    address VARCHAR(255),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ---------------------------------------------------------------------
-- PRODUCTS
-- ---------------------------------------------------------------------
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) UNIQUE,
    product_name VARCHAR(150) NOT NULL,
    category_id INT NOT NULL,
    supplier_id INT,
    size VARCHAR(20),
    color VARCHAR(50),
    cost_price DECIMAL(10,2) DEFAULT 0,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    reorder_level INT DEFAULT 10,
    image VARCHAR(255),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- INVENTORY — live stock quantity per product per branch
-- ---------------------------------------------------------------------
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    branch_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_branch (product_id, branch_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- STOCK_IN 
-- ---------------------------------------------------------------------
CREATE TABLE stock_in (
    stock_in_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    reference_no VARCHAR(50),
    stock_in_date DATE NOT NULL,
    total_cost DECIMAL(12,2) DEFAULT 0,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);
-- ---------------------------------------------------------------------
-- STOCK_IN_DETAILS 
-- ---------------------------------------------------------------------
CREATE TABLE stock_in_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_in_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    FOREIGN KEY (stock_in_id) REFERENCES stock_in(stock_in_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- ---------------------------------------------------------------------
-- STOCK_OUT 
-- ---------------------------------------------------------------------
CREATE TABLE stock_out (
    stock_out_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    reference_no VARCHAR(50),
    stock_out_date DATE NOT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ---------------------------------------------------------------------
-- STOCK_OUT_DETAILS 
-- ---------------------------------------------------------------------
CREATE TABLE stock_out_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_out_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    FOREIGN KEY (stock_out_id) REFERENCES stock_out(stock_out_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- ---------------------------------------------------------------------
-- NOTIFICATIONS — low stock alerts
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    branch_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- ACTIVITY_LOGS — audit trail
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- SETTINGS 
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255)
);

-- ---------------------------------------------------------------------
-- PASSWORD_RESETS — single-use, expiring OTP codes for "Forgot Password"
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    used TINYINT(1) DEFAULT 0,
    attempts TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- STOCK_TRANSFERS — moving inventory from one branch to another
-- ---------------------------------------------------------------------
CREATE TABLE stock_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    from_branch_id INT NOT NULL,
    to_branch_id INT NOT NULL,
    user_id INT NOT NULL,
    reference_no VARCHAR(50),
    transfer_date DATE NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (to_branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE stock_transfer_details (
    detail_id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(transfer_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- =====================================================================
-- SAMPLE DATA
-- =====================================================================

INSERT INTO roles (role_name, description) VALUES
('Admin', 'Full system access'),
('Branch Manager', 'Manages branch inventory and approves stock movement'),
('Sales User', 'Records sales and checks product availability');


INSERT INTO branches (branch_name, location, phone) VALUES
('Perfect Choice - Gulshan', 'Gulshan Avenue, Dhaka', '01700000001'),
('Perfect Choice - Uttara', 'Sector 7, Uttara, Dhaka', '01700000002'),
('Perfect Choice - Chittagong', 'Agrabad Access Road, Chattogram', '01700000004'),
('Perfect Choice - Sylhet', 'Zindabazar, Sylhet', '01700000005');


INSERT INTO users (full_name, email, password, role_id, branch_id, phone) VALUES
('Admin', 'admin.connect@gmail.com', '$2y$10$fP71sE8f.KnPU4UBwtGXIO4xP0ckofw.BJ9zyDOAUSC0mpr16e1Ma', 1, 1, '01710000001'),
('Branch Manager Gulshan', 'manager.gulshan@gmail.com', '$2y$10$hZHYu.AO0Sfsos1CuaEbBuklhTyiL3Fwor2a0ohrp1GlTt3IYjxsm', 2, 1, '01710000002'),
('Sales User Uttara', 'sales.uttara@gmail.com', '$2y$10$hX1KFgIm6kdlOoB1iWQXD.YGL8QCHzLQIVldw2RriiYFjlS6043P6', 3, 2, '01710000003'),
('Sales User Gulshan', 'sales.gulshan@perfectchoice.com', '$2y$10$fP71sE8f.KnPU4UBwtGXIO4xP0ckofw.BJ9zyDOAUSC0mpr16e1Ma', 3, 1, '01710000004'),
('Branch Manager Uttara', 'manager.uttara@perfectchoice.com', '$2y$10$hZHYu.AO0Sfsos1CuaEbBuklhTyiL3Fwor2a0ohrp1GlTt3IYjxsm', 2, 2, '01710000005'),
('Branch Manager Chittagong', 'manager.chittagong@perfectchoice.com', '$2y$10$hX1KFgIm6kdlOoB1iWQXD.YGL8QCHzLQIVldw2RriiYFjlS6043P6', 2, 3, '01710000006'),
('Sales User Chittagong', 'sales.chittagong@perfectchoice.com', '$2y$10$fP71sE8f.KnPU4UBwtGXIO4xP0ckofw.BJ9zyDOAUSC0mpr16e1Ma', 3, 3, '01710000007'),
('Branch Manager Sylhet', 'manager.sylhet@perfectchoice.com', '$2y$10$hZHYu.AO0Sfsos1CuaEbBuklhTyiL3Fwor2a0ohrp1GlTt3IYjxsm', 2, 4, '01710000008'),
('Sales User Sylhet', 'sales.sylhet@perfectchoice.com', '$2y$10$hX1KFgIm6kdlOoB1iWQXD.YGL8QCHzLQIVldw2RriiYFjlS6043P6', 3, 4, '01710000009');


-- Categories: Level 1 Gender, Level 2 Group, Level 3 Type
INSERT INTO categories (category_name, parent_id, cat_level) VALUES
('Female', NULL, 1),
('Male', NULL, 1);

INSERT INTO categories (category_name, parent_id, cat_level) VALUES
('Clothing', 1, 2),
('Shoes', 1, 2),
('Clothing', 2, 2),
('Shoes', 2, 2);

INSERT INTO categories (category_name, parent_id, cat_level) VALUES
('Abaya', 3, 3),
('Hijab', 3, 3),
('Burqa', 3, 3),
('Kurti', 3, 3),
('Tops', 3, 3),
('Heels', 4, 3),
('Flats', 4, 3),
('Shirt', 5, 3),
('Panjabi', 5, 3),
('Loafers', 6, 3),
('Sandals', 6, 3);

-- Kids section
INSERT INTO categories (category_name, parent_id, cat_level) VALUES
('Kids', NULL, 1);

INSERT INTO categories (category_name, parent_id, cat_level) VALUES
('Clothing', 18, 2),
('Shoes', 18, 2);

INSERT INTO categories (category_name, parent_id, cat_level) VALUES
('T-Shirts', 19, 3),
('Frocks', 19, 3),
('Shorts', 19, 3),
('Sleepwear', 19, 3),
('Sneakers', 20, 3),
('Sandals', 20, 3);

-- suppliers
INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES
('Dhaka Textile House', 'Karim Rahman', '01811111111', 'contact@dhakatextile.com', 'Old Dhaka, Dhaka'),
('Northern Footwear Ltd.', 'Fatima Begum', '01822222222', 'sales@northernfootwear.com', 'Tongi, Gazipur'),
('Chattogram Garments Supply', 'Nasir Uddin', '01833333333', 'info@ctggarments.com', 'Bahaddarhat, Chattogram'),
('Sylhet Fabric & Textiles', 'Rehana Akter', '01844444444', 'orders@sylhetfabric.com', 'Zindabazar, Sylhet'),
('Bengal Kids Wear Ltd.', 'Shahin Alam', '01855555555', 'contact@bengalkidswear.com', 'Mirpur, Dhaka'),
('Padma Leather & Shoes', 'Nazrul Islam', '01866666666', 'sales@padmaleather.com', 'Hazaribagh, Dhaka');

-- Products 
INSERT INTO products (sku, product_name, category_id, supplier_id, size, color, cost_price, selling_price, reorder_level) VALUES
('PC-ABY-001', 'Classic Black Abaya', 7, 1, 'M', 'Black', 1200.00, 2200.00, 10),
('PC-ABY-002', 'Embellished Abaya', 7, 1, 'L', 'Navy', 1500.00, 2800.00, 8),
('PC-HIJ-001', 'Chiffon Hijab', 8, 1, 'Free Size', 'Maroon', 250.00, 550.00, 20),
('PC-HIJ-002', 'Jersey Hijab', 8, 1, 'Free Size', 'Black', 200.00, 450.00, 25),
('PC-BUR-001', 'Classic Burqa', 9, 1, 'L', 'Black', 1400.00, 2600.00, 10),
('PC-KUR-001', 'Embroidered Kurti', 10, 1, 'L', 'Mustard Yellow', 700.00, 1450.00, 15),
('PC-KUR-002', 'Printed Kurti', 10, 1, 'M', 'Teal', 600.00, 1250.00, 15),
('PC-TOP-001', 'Casual Top', 11, 1, 'M', 'White', 400.00, 900.00, 20),
('PC-HEL-001', 'Block Heel Sandal', 12, 2, '38', 'Beige', 800.00, 1750.00, 8),
('PC-FLT-001', 'Ballet Flats', 13, 2, '37', 'Black', 650.00, 1400.00, 10),
('PC-SHR-001', 'Formal Cotton Shirt', 14, 1, 'M', 'White', 600.00, 1300.00, 15),
('PC-SHR-002', 'Casual Check Shirt', 14, 1, 'L', 'Blue', 550.00, 1150.00, 15),
('PC-PNJ-001', 'Eid Panjabi', 15, 1, 'XL', 'Off-White', 900.00, 1900.00, 10),
('PC-LOA-001', 'Leather Loafers', 16, 2, '42', 'Brown', 1100.00, 2200.00, 8),
('PC-SAN-001', 'Mens Sandals', 17, 2, '41', 'Brown', 500.00, 1100.00, 10),
('PC-KID-001', 'Kids Cartoon T-Shirt', 21, 1, '5-6Y', 'Multicolor', 250.00, 550.00, 15),
('PC-KID-002', 'Kids Canvas Sneakers', 25, 2, '30', 'Red', 450.00, 950.00, 10);

-- Stock In:
INSERT INTO stock_in (supplier_id, branch_id, user_id, reference_no, stock_in_date, total_cost, notes) VALUES
(1, 1, 1, 'SI-OPEN-B1', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 283700.00, 'Opening balance stock'),
(1, 2, 1, 'SI-OPEN-B2', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 158150.00, 'Opening balance stock');

-- Stock In:
INSERT INTO stock_in (supplier_id, branch_id, user_id, reference_no, stock_in_date, total_cost, notes) VALUES
(1, 1, 1, 'SI-20260625-B1', DATE_SUB(CURDATE(), INTERVAL 9 DAY), 5000.00, 'Restock delivery'),
(1, 2, 2, 'SI-20260625-B2', DATE_SUB(CURDATE(), INTERVAL 9 DAY), 3000.00, 'Restock delivery'),
(1, 1, 1, 'SI-20260628-B1', DATE_SUB(CURDATE(), INTERVAL 6 DAY), 7000.00, 'Restock delivery'),
(1, 2, 2, 'SI-20260630-B2', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 9600.00, 'Restock delivery'),
(1, 1, 1, 'SI-20260701-B1', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 6000.00, 'Restock delivery'),
(1, 2, 2, 'SI-20260702-B2', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 7200.00, 'Restock delivery'),
(2, 1, 1, 'SI-20260703-B1', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 6500.00, 'Restock delivery'),
(1, 2, 2, 'SI-20260703-B2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 2500.00, 'Restock delivery'),
(2, 1, 1, 'SI-20260704-B1', CURDATE(), 5000.00, 'Restock delivery');

INSERT INTO stock_in_details (stock_in_id, product_id, quantity, unit_cost) VALUES
(1, 1, 30, 1200.00),
(1, 2, 18, 1500.00),
(1, 3, 70, 250.00),
(1, 4, 55, 200.00),
(1, 5, 20, 1400.00),
(1, 6, 25, 700.00),
(1, 7, 28, 600.00),
(1, 8, 40, 400.00),
(1, 9, 20, 800.00),
(1, 10, 24, 650.00),
(1, 11, 35, 600.00),
(1, 12, 30, 550.00),
(1, 13, 18, 900.00),
(1, 14, 16, 1100.00),
(1, 15, 22, 500.00),
(2, 1, 15, 1200.00),
(2, 2, 10, 1500.00),
(2, 3, 45, 250.00),
(2, 4, 35, 200.00),
(2, 5, 12, 1400.00),
(2, 6, 14, 700.00),
(2, 7, 16, 600.00),
(2, 8, 22, 400.00),
(2, 9, 10, 800.00),
(2, 10, 14, 650.00),
(2, 11, 20, 600.00),
(2, 12, 18, 550.00),
(2, 13, 9, 900.00),
(2, 14, 8, 1100.00),
(2, 15, 12, 500.00),
(3, 3, 20, 250.00),
(4, 4, 15, 200.00),
(5, 6, 10, 700.00),
(6, 1, 8, 1200.00),
(7, 8, 15, 400.00),
(8, 11, 12, 600.00),
(9, 10, 10, 650.00),
(10, 3, 10, 250.00),
(11, 15, 10, 500.00);



-- Stock Out: 
INSERT INTO stock_out (branch_id, user_id, reference_no, stock_out_date, total_amount, notes) VALUES
(1, 2, 'SO-20260621-B1', DATE_SUB(CURDATE(), INTERVAL 13 DAY), 4400.00, 'Daily sales'),
(2, 3, 'SO-20260621-B2', DATE_SUB(CURDATE(), INTERVAL 13 DAY), 2700.00, 'Daily sales'),
(1, 2, 'SO-20260622-B1', DATE_SUB(CURDATE(), INTERVAL 12 DAY), 6600.00, 'Daily sales'),
(2, 3, 'SO-20260622-B2', DATE_SUB(CURDATE(), INTERVAL 12 DAY), 5800.00, 'Daily sales'),
(1, 2, 'SO-20260623-B1', DATE_SUB(CURDATE(), INTERVAL 11 DAY), 4500.00, 'Daily sales'),
(2, 3, 'SO-20260623-B2', DATE_SUB(CURDATE(), INTERVAL 11 DAY), 5200.00, 'Daily sales'),
(1, 2, 'SO-20260624-B1', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 5500.00, 'Daily sales'),
(2, 3, 'SO-20260624-B2', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 4200.00, 'Daily sales'),
(1, 2, 'SO-20260625-B1', DATE_SUB(CURDATE(), INTERVAL 9 DAY), 10650.00, 'Daily sales'),
(1, 2, 'SO-20260626-B1', DATE_SUB(CURDATE(), INTERVAL 8 DAY), 11400.00, 'Daily sales'),
(2, 3, 'SO-20260626-B2', DATE_SUB(CURDATE(), INTERVAL 8 DAY), 6600.00, 'Daily sales'),
(1, 2, 'SO-20260627-B1', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 11200.00, 'Daily sales'),
(2, 3, 'SO-20260627-B2', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 5250.00, 'Daily sales'),
(1, 2, 'SO-20260628-B1', DATE_SUB(CURDATE(), INTERVAL 6 DAY), 6900.00, 'Daily sales'),
(2, 3, 'SO-20260628-B2', DATE_SUB(CURDATE(), INTERVAL 6 DAY), 4050.00, 'Daily sales'),
(1, 2, 'SO-20260629-B1', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 14750.00, 'Daily sales'),
(2, 3, 'SO-20260630-B2', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 3600.00, 'Daily sales'),
(1, 2, 'SO-20260630-B1', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 4950.00, 'Daily sales'),
(1, 2, 'SO-20260701-B1', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 13300.00, 'Daily sales'),
(2, 3, 'SO-20260701-B2', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 3300.00, 'Daily sales'),
(1, 2, 'SO-20260702-B1', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 17500.00, 'Daily sales'),
(1, 2, 'SO-20260703-B1', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 16500.00, 'Daily sales'),
(2, 3, 'SO-20260703-B2', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 3750.00, 'Daily sales'),
(2, 3, 'SO-20260704-B2', CURDATE(), 12100.00, 'Daily sales'),
(1, 2, 'SO-20260704-B1', CURDATE(), 4400.00, 'Daily sales');

-- stock out details
INSERT INTO stock_out_details (stock_out_id, product_id, quantity, unit_price) VALUES
(1, 3, 8, 550.00),
(2, 4, 6, 450.00),
(3, 1, 3, 2200.00),
(4, 6, 4, 1450.00),
(5, 8, 5, 900.00),
(6, 11, 4, 1300.00),
(7, 3, 10, 550.00),
(8, 10, 3, 1400.00),
(9, 15, 4, 1100.00),
(9, 7, 5, 1250.00),
(10, 13, 6, 1900.00),
(11, 14, 3, 2200.00),
(12, 2, 4, 2800.00),
(13, 9, 3, 1750.00),
(14, 12, 6, 1150.00),
(15, 4, 9, 450.00),
(16, 5, 4, 2600.00),
(16, 6, 3, 1450.00),
(17, 8, 4, 900.00),
(18, 3, 9, 550.00),
(19, 13, 7, 1900.00),
(20, 15, 3, 1100.00),
(21, 14, 5, 2200.00),
(21, 11, 5, 1300.00),
(22, 9, 4, 1750.00),
(22, 13, 5, 1900.00),
(23, 7, 3, 1250.00),
(24, 3, 6, 550.00),
(24, 14, 4, 2200.00),
(25, 1, 2, 2200.00);


-- Final inventory snapshot 
INSERT INTO inventory (product_id, branch_id, quantity) VALUES
(1, 1, 25),
(1, 2, 23),
(2, 1, 14),
(2, 2, 10),
(3, 1, 63),
(3, 2, 49),
(4, 1, 55),
(4, 2, 35),
(5, 1, 16),
(5, 2, 12),
(6, 1, 32),
(6, 2, 10),
(7, 1, 23),
(7, 2, 13),
(8, 1, 50),
(8, 2, 18),
(9, 1, 16),
(9, 2, 7),
(10, 1, 34),
(10, 2, 11),
(11, 1, 30),
(11, 2, 28),
(12, 1, 24),
(12, 2, 18),
(13, 1, 0),
(13, 2, 9),
(14, 1, 11),
(14, 2, 1),
(15, 1, 28),
(15, 2, 9),
(16, 1, 20),
(16, 2, 14),
(17, 1, 9),
(17, 2, 12);


-- Chittagong and Sylhet branches: inventory + stock movement history
INSERT INTO inventory (product_id, branch_id, quantity) VALUES
(1, 3, 15),
(2, 3, 9),
(3, 3, 25),
(4, 3, 23),
(5, 3, 22),
(6, 3, 16),
(7, 3, 14),
(8, 3, 42),
(9, 3, 13),
(10, 3, 45),
(11, 3, 35),
(12, 3, 10),
(13, 3, 9),
(14, 3, 13),
(15, 3, 21),
(16, 3, 22),
(17, 3, 40),
(1, 4, 9),
(2, 4, 43),
(3, 4, 20),
(4, 4, 42),
(5, 4, 34),
(6, 4, 22),
(7, 4, 36),
(8, 4, 45),
(9, 4, 25),
(10, 4, 8),
(11, 4, 18),
(12, 4, 35),
(13, 4, 29),
(14, 4, 25),
(15, 4, 17),
(16, 4, 21),
(17, 4, 29);

INSERT INTO stock_in (supplier_id, branch_id, user_id, reference_no, stock_in_date, total_cost, notes) VALUES
(1, 3, 1, 'SI-OPEN-B3', DATE_SUB(CURDATE(), INTERVAL 18 DAY), 317700.00, 'Opening balance stock'),
(2, 4, 1, 'SI-OPEN-B4', DATE_SUB(CURDATE(), INTERVAL 18 DAY), 428000.00, 'Opening balance stock');

INSERT INTO stock_in_details (stock_in_id, product_id, quantity, unit_cost) VALUES
(12, 1, 19, 1200.00),
(12, 2, 13, 1500.00),
(12, 3, 34, 250.00),
(12, 4, 27, 200.00),
(12, 5, 30, 1400.00),
(12, 6, 24, 700.00),
(12, 7, 26, 600.00),
(12, 8, 49, 400.00),
(12, 9, 16, 800.00),
(12, 10, 59, 650.00),
(12, 11, 45, 600.00),
(12, 12, 21, 550.00),
(12, 13, 13, 900.00),
(12, 14, 22, 1100.00),
(12, 15, 25, 500.00),
(12, 16, 33, 250.00),
(12, 17, 47, 450.00),
(13, 1, 22, 1200.00),
(13, 2, 55, 1500.00),
(13, 3, 28, 250.00),
(13, 4, 54, 200.00),
(13, 5, 40, 1400.00),
(13, 6, 36, 700.00),
(13, 7, 40, 600.00),
(13, 8, 48, 400.00),
(13, 9, 38, 800.00),
(13, 10, 14, 650.00),
(13, 11, 25, 600.00),
(13, 12, 39, 550.00),
(13, 13, 35, 900.00),
(13, 14, 29, 1100.00),
(13, 15, 26, 500.00),
(13, 16, 28, 250.00),
(13, 17, 39, 450.00);

INSERT INTO stock_out (branch_id, user_id, reference_no, stock_out_date, total_amount, notes) VALUES
(3, 7, 'SO-B3-1', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 36400.00, 'Daily sales'),
(3, 7, 'SO-B3-2', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 20800.00, 'Daily sales'),
(3, 7, 'SO-B3-3', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 17650.00, 'Daily sales'),
(3, 7, 'SO-B3-4', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 28050.00, 'Daily sales'),
(3, 7, 'SO-B3-5', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 53250.00, 'Daily sales'),
(3, 7, 'SO-B3-6', DATE_SUB(CURDATE(), INTERVAL 14 DAY), 19300.00, 'Daily sales'),
(4, 9, 'SO-B4-1', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 48400.00, 'Daily sales'),
(4, 9, 'SO-B4-2', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 21400.00, 'Daily sales'),
(4, 9, 'SO-B4-3', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 18750.00, 'Daily sales'),
(4, 9, 'SO-B4-4', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 71950.00, 'Daily sales'),
(4, 9, 'SO-B4-5', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 18500.00, 'Daily sales'),
(4, 9, 'SO-B4-6', DATE_SUB(CURDATE(), INTERVAL 14 DAY), 24900.00, 'Daily sales');

INSERT INTO stock_out_details (stock_out_id, product_id, quantity, unit_price) VALUES
(26, 7, 12, 1250.00),
(26, 10, 14, 1400.00),
(26, 4, 4, 450.00),
(27, 1, 4, 2200.00),
(27, 13, 4, 1900.00),
(27, 15, 4, 1100.00),
(28, 17, 7, 950.00),
(28, 3, 9, 550.00),
(28, 16, 11, 550.00),
(29, 9, 3, 1750.00),
(29, 2, 4, 2800.00),
(29, 6, 8, 1450.00),
(30, 14, 9, 2200.00),
(30, 5, 8, 2600.00),
(30, 12, 11, 1150.00),
(31, 8, 7, 900.00),
(31, 11, 10, 1300.00),
(32, 13, 6, 1900.00),
(32, 10, 6, 1400.00),
(32, 1, 13, 2200.00),
(33, 8, 3, 900.00),
(33, 15, 9, 1100.00),
(33, 14, 4, 2200.00),
(34, 17, 10, 950.00),
(34, 16, 7, 550.00),
(34, 4, 12, 450.00),
(35, 9, 13, 1750.00),
(35, 5, 6, 2600.00),
(35, 2, 12, 2800.00),
(36, 3, 8, 550.00),
(36, 7, 4, 1250.00),
(36, 11, 7, 1300.00),
(37, 12, 4, 1150.00),
(37, 6, 14, 1450.00);


INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Perfect Choice'),
('currency', 'BDT'),
('low_stock_threshold_default', '10');
