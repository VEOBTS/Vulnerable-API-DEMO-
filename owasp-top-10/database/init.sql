-- database/init.sql
USE labdb;

-- USERS TABLE
-- 'password' column uses MD5 (intentionally weak)
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('user','admin') DEFAULT 'user',
    balance     DECIMAL(10,2) DEFAULT 100.00,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SESSIONS TABLE
CREATE TABLE IF NOT EXISTS sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token       VARCHAR(500) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ORDERS TABLE
CREATE TABLE IF NOT EXISTS orders (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    product     VARCHAR(100) NOT NULL,
    quantity    INT DEFAULT 1,
    total       DECIMAL(10,2),
    status      ENUM('pending','complete','cancelled') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- PRODUCTS TABLE
CREATE TABLE IF NOT EXISTS products (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0
);

-- SEED DATA
-- MD5('password123') = '482c811da5d5b4bc6d497ffa98491e38'
-- MD5('adminpass')   = '46f94c8de14fb36680850768ff1b7f2a'
INSERT INTO users (username, email, password, role, balance) VALUES
  ('alice',   'alice@lab.com',   MD5('password123'), 'user',  250.00),
  ('bob',     'bob@lab.com',     MD5('password123'), 'user',   75.00),
  ('charlie', 'charlie@lab.com', MD5('password123'), 'user',  500.00),
  ('admin',   'admin@lab.com',   MD5('adminpass'),   'admin', 9999.99);

INSERT INTO products (name, price, stock) VALUES
  ('Widget A', 9.99,  100),
  ('Widget B', 24.99,  50),
  ('Widget C', 4.99,  200);

INSERT INTO orders (user_id, product, quantity, total) VALUES
  (1, 'Widget A', 2, 19.98),
  (2, 'Widget B', 1, 24.99),
  (1, 'Widget C', 5, 24.95);