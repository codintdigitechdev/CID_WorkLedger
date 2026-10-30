-- WorkLedger (मराठी आवृत्ती) by CodInt Digitech : डेटाबेस स्क्रिप्ट
-- XAMPP: open phpMyAdmin > Import and choose this file.
-- cPanel: create a database in the MySQL Databases tool, delete the next two lines, then import into it.
CREATE DATABASE IF NOT EXISTS workledger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE workledger;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, work_entries, tasks, staff, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(120) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager') NOT NULL DEFAULT 'manager',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  reset_token CHAR(64) NULL,
  reset_expires DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_code VARCHAR(20) NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  designation VARCHAR(80) NULL,
  joining_date DATE NULL,
  address TEXT NULL,
  bank_details TEXT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (status)
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  unit VARCHAR(20) NOT NULL DEFAULT 'Per piece',
  rate DECIMAL(10,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB;

CREATE TABLE work_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  task_id INT NOT NULL,
  work_date DATE NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  rate DECIMAL(10,2) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('Pending','Approved') NOT NULL DEFAULT 'Pending',
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (work_date), INDEX (status), INDEX (staff_id, work_date),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  pay_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  type ENUM('Wage','Advance') NOT NULL DEFAULT 'Wage',
  mode ENUM('Cash','UPI','Bank transfer','Cheque') NOT NULL DEFAULT 'Cash',
  reference VARCHAR(100) NULL,
  remarks VARCHAR(255) NULL,
  created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (pay_date), INDEX (staff_id, pay_date),
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Logins (change these passwords after first sign in)
-- admin / Admin@123     manager / Manager@123
INSERT INTO users (name, username, email, password_hash, role) VALUES
('प्रशासक','admin','admin@example.com','$2y$10$LuZijIc..A.NpWb7AQ7hJOpn2A5oOHObsNI9Pyj6BpY6FF2Nx4ntW','admin'),
('व्यवस्थापक','manager','manager@example.com','$2y$10$zfcSqQHQVBHF7wutWlCGUe26sPn8MCnxuctGKx9AgPk/jp3x22g7S','manager');

-- ---------- नमुना माहिती (स्वच्छ सुरुवातीसाठी हा भाग हटवा) ----------
INSERT INTO staff (staff_code, name, phone, designation, joining_date, status) VALUES
('ST001','सुनीता पाटील','9800000001','शिंपी',CURDATE() - INTERVAL 300 DAY,'Active'),
('ST002','राहुल शिंदे','9800000002','डिलिव्हरी',CURDATE() - INTERVAL 220 DAY,'Active'),
('ST003','महेश जाधव','9800000003','मदतनीस',CURDATE() - INTERVAL 150 DAY,'Active'),
('ST004','पूजा मोरे','9800000004','डेटा ऑपरेटर',CURDATE() - INTERVAL 90 DAY,'Active'),
('ST005','विकास काळे','9800000005','पॅकर',CURDATE() - INTERVAL 40 DAY,'Active');
INSERT INTO tasks (name, unit, rate, description) VALUES
('शिलाई','Per piece',45.00,'कपड्यांची शिलाई'),
('पॅकिंग','Per piece',12.50,'तयार माल पॅक करणे'),
('डिलिव्हरी','Per job',150.00,'स्थानिक डिलिव्हरी'),
('चढवणे / उतरवणे','Per hour',120.00,NULL),
('साफसफाई','Per day',400.00,NULL),
('डेटा एंट्री','Per hour',90.00,NULL);
INSERT INTO work_entries (staff_id, task_id, work_date, quantity, rate, amount, status) VALUES
(1,1,CURDATE() - INTERVAL 12 DAY,40,45.00,1800.00,'Approved'),
(1,2,CURDATE() - INTERVAL 10 DAY,120,12.50,1500.00,'Approved'),
(1,1,CURDATE() - INTERVAL 3 DAY,35,45.00,1575.00,'Pending'),
(2,3,CURDATE() - INTERVAL 9 DAY,6,150.00,900.00,'Approved'),
(2,3,CURDATE() - INTERVAL 5 DAY,8,150.00,1200.00,'Approved'),
(2,4,CURDATE() - INTERVAL 2 DAY,5,120.00,600.00,'Pending'),
(3,5,CURDATE() - INTERVAL 11 DAY,2,400.00,800.00,'Approved'),
(3,5,CURDATE() - INTERVAL 6 DAY,3,400.00,1200.00,'Approved'),
(3,4,CURDATE() - INTERVAL 4 DAY,6,120.00,720.00,'Approved'),
(4,6,CURDATE() - INTERVAL 8 DAY,10,90.00,900.00,'Approved'),
(4,6,CURDATE() - INTERVAL 7 DAY,12,90.00,1080.00,'Approved'),
(4,6,CURDATE() - INTERVAL 1 DAY,8,90.00,720.00,'Pending'),
(5,2,CURDATE() - INTERVAL 6 DAY,200,12.50,2500.00,'Approved'),
(5,1,CURDATE() - INTERVAL 2 DAY,20,45.00,900.00,'Approved');
INSERT INTO payments (staff_id, pay_date, amount, type, mode, reference) VALUES
(1,CURDATE() - INTERVAL 5 DAY,2000.00,'Wage','UPI','UPI-84213'),
(2,CURDATE() - INTERVAL 4 DAY,1500.00,'Wage','Cash',NULL),
(3,CURDATE() - INTERVAL 9 DAY,500.00,'Advance','Cash',NULL),
(4,CURDATE() - INTERVAL 3 DAY,1000.00,'Wage','Bank transfer','NEFT-55120');
