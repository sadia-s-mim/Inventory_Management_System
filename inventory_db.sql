



CREATE DATABASE IF NOT EXISTS perfect_choice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE perfect_choice;



CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);



CREATE TABLE branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    phone VARCHAR(30),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);



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




CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    parent_id INT DEFAULT NULL,
    cat_level TINYINT NOT NULL DEFAULT 1,  
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(category_id) ON DELETE CASCADE
);




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




CREATE TABLE activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);




CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255)
);




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
('Admin', 'admin@perfectchoice.com', '$2y$10$fP71sE8f.KnPU4UBwtGXIO4xP0ckofw.BJ9zyDOAUSC0mpr16e1Ma', 1, 1, '01710000001'),
('Branch Manager Gulshan', 'manager.gulshan@perfectchoice.com', '$2y$10$hZHYu.AO0Sfsos1CuaEbBuklhTyiL3Fwor2a0ohrp1GlTt3IYjxsm', 2, 1, '01710000002'),
('Sales User Uttara', 'sales.uttara@perfectchoice.com', '$2y$10$hX1KFgIm6kdlOoB1iWQXD.YGL8QCHzLQIVldw2RriiYFjlS6043P6', 3, 2, '01710000003'),
('Sales User Gulshan', 'sales.gulshan@perfectchoice.com', '$2y$10$fP71sE8f.KnPU4UBwtGXIO4xP0ckofw.BJ9zyDOAUSC0mpr16e1Ma', 3, 1, '01710000004'),
('Branch Manager Uttara', 'manager.uttara@perfectchoice.com', '$2y$10$hZHYu.AO0Sfsos1CuaEbBuklhTyiL3Fwor2a0ohrp1GlTt3IYjxsm', 2, 2, '01710000005'),
('Branch Manager Chittagong', 'manager.chittagong@perfectchoice.com', '$2y$10$hX1KFgIm6kdlOoB1iWQXD.YGL8QCHzLQIVldw2RriiYFjlS6043P6', 2, 3, '01710000006'),
('Sales User Chittagong', 'sales.chittagong@perfectchoice.com', '$2y$10$fP71sE8f.KnPU4UBwtGXIO4xP0ckofw.BJ9zyDOAUSC0mpr16e1Ma', 3, 3, '01710000007'),
('Branch Manager Sylhet', 'manager.sylhet@perfectchoice.com', '$2y$10$hZHYu.AO0Sfsos1CuaEbBuklhTyiL3Fwor2a0ohrp1GlTt3IYjxsm', 2, 4, '01710000008'),
('Sales User Sylhet', 'sales.sylhet@perfectchoice.com', '$2y$10$hX1KFgIm6kdlOoB1iWQXD.YGL8QCHzLQIVldw2RriiYFjlS6043P6', 3, 4, '01710000009');

