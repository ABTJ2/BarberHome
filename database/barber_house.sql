SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
DROP DATABASE IF EXISTS barber_house;
CREATE DATABASE barber_house CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE barber_house;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_users_role FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  notes VARCHAR(500) NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_clients_name(last_name,first_name), INDEX idx_clients_phone(phone)
) ENGINE=InnoDB;

CREATE TABLE barbers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NULL,
  commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (commission_percent BETWEEN 0 AND 100),
  active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE barber_schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barber_id INT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL COMMENT '1=Lunes ... 7=Domingo',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_barber_day(barber_id,day_of_week),
  CONSTRAINT fk_schedule_barber FOREIGN KEY(barber_id) REFERENCES barbers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(12,2) NOT NULL CHECK (price >= 0),
  duration_minutes SMALLINT UNSIGNED NOT NULL CHECK (duration_minutes > 0),
  active TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE barber_services (
  barber_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(barber_id,service_id),
  CONSTRAINT fk_bs_barber FOREIGN KEY(barber_id) REFERENCES barbers(id) ON DELETE CASCADE,
  CONSTRAINT fk_bs_service FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  barber_id INT UNSIGNED NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  status ENUM('reserved','attended','cancelled','no_show') NOT NULL DEFAULT 'reserved',
  notes VARCHAR(500) NULL,
  walk_in TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_appt_client FOREIGN KEY(client_id) REFERENCES clients(id),
  CONSTRAINT fk_appt_barber FOREIGN KEY(barber_id) REFERENCES barbers(id),
  CONSTRAINT fk_appt_user FOREIGN KEY(created_by) REFERENCES users(id),
  INDEX idx_appt_date(start_at), INDEX idx_appt_barber_time(barber_id,start_at,end_at)
) ENGINE=InnoDB;

CREATE TABLE appointment_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  price_snapshot DECIMAL(12,2) NOT NULL,
  duration_snapshot SMALLINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_appt_service(appointment_id,service_id),
  CONSTRAINT fk_as_appt FOREIGN KEY(appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_as_service FOREIGN KEY(service_id) REFERENCES services(id)
) ENGINE=InnoDB;

CREATE TABLE payment_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL UNIQUE,
  payment_method_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  commission_percent_snapshot DECIMAL(5,2) NOT NULL,
  commission_amount DECIMAL(12,2) NOT NULL,
  notes VARCHAR(500) NULL,
  paid_at DATETIME NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  updated_at DATETIME NULL,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  CONSTRAINT fk_payment_appt FOREIGN KEY(appointment_id) REFERENCES appointments(id),
  CONSTRAINT fk_payment_method FOREIGN KEY(payment_method_id) REFERENCES payment_methods(id),
  CONSTRAINT fk_payment_user FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_payment_void_user FOREIGN KEY(voided_by) REFERENCES users(id),
  INDEX idx_payment_date(paid_at)
) ENGINE=InnoDB;

CREATE TABLE expense_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  concept VARCHAR(180) NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount > 0),
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  CONSTRAINT fk_exp_cat FOREIGN KEY(category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_exp_user FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_exp_void_user FOREIGN KEY(voided_by) REFERENCES users(id),
  INDEX idx_expense_date(expense_date)
) ENGINE=InnoDB;

INSERT INTO roles(code,name) VALUES ('recepcionista','Recepcionista'),('encargado','Encargado');
INSERT INTO users(role_id,username,full_name,password_hash,active,created_at,updated_at) VALUES
(1,'recepcion','Recepción Barber House','$2y$10$4Am17vkMZox/2A72Ui9iA.VcQ1nhx761DAEthGhJok.4tQCyRf9Mm',1,NOW(),NOW()),
(2,'encargado','Encargado Barber House','$2y$10$4Am17vkMZox/2A72Ui9iA.VcQ1nhx761DAEthGhJok.4tQCyRf9Mm',1,NOW(),NOW());

INSERT INTO clients(first_name,last_name,phone,notes,created_at,updated_at) VALUES
('Juan','Pérez','264 555 1234','Prefiere turnos por la tarde.',NOW(),NOW()),
('Diego','Gómez','264 555 7281','',NOW(),NOW()),
('Lucas','Díaz','264 555 3345','Corte corto.',NOW(),NOW()),
('Martín','Sosa','264 555 9190','',NOW(),NOW());

INSERT INTO barbers(full_name,phone,commission_percent,active,created_at,updated_at) VALUES
('Carlos Balmaceda','264 555 1122',45,1,NOW(),NOW()),
('Jesús Funes','264 555 2233',50,1,NOW(),NOW()),
('Nahuel Vera','264 555 3344',40,1,NOW(),NOW());

INSERT INTO services(name,price,duration_minutes,active,created_at,updated_at) VALUES
('Corte',12000,45,1,NOW(),NOW()),('Barba',8000,30,1,NOW(),NOW()),('Corte + brushing',18000,60,1,NOW(),NOW()),('Color',15000,60,1,NOW(),NOW());

INSERT INTO barber_services(barber_id,service_id) SELECT b.id,s.id FROM barbers b CROSS JOIN services s;

INSERT INTO barber_schedules(barber_id,day_of_week,start_time,end_time,active) VALUES
(1,1,'09:00','18:00',1),(1,2,'09:00','18:00',1),(1,3,'09:00','18:00',1),(1,4,'09:00','18:00',1),(1,5,'09:00','18:00',1),(1,6,'09:00','14:00',1),
(2,1,'10:00','19:00',1),(2,2,'10:00','19:00',1),(2,3,'10:00','19:00',1),(2,4,'10:00','19:00',1),(2,5,'10:00','19:00',1),(2,6,'10:00','16:00',1),
(3,1,'14:00','21:00',1),(3,2,'14:00','21:00',1),(3,3,'14:00','21:00',1),(3,4,'14:00','21:00',1),(3,5,'14:00','21:00',1),(3,6,'10:00','16:00',1);

INSERT INTO payment_methods(name,active) VALUES ('Efectivo',1),('Transferencia',1),('Tarjeta',1);
INSERT INTO expense_categories(name,active) VALUES ('Servicios',1),('Limpieza',1),('Mantenimiento',1),('Otros',1);

-- La agenda de prueba tiene reservas el próximo lunes y una atención cobrada ayer.
INSERT INTO appointments(client_id,barber_id,start_at,end_at,status,notes,walk_in,created_by,created_at,updated_at) VALUES
(1,2,CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 15:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 15:45:00'),'reserved','',0,1,NOW(),NOW()),
(2,1,CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 16:00:00'),CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 17:15:00'),'attended','',0,1,NOW(),NOW()),
(3,3,CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 17:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL MOD(7-WEEKDAY(CURDATE()),7) DAY),' 17:45:00'),'reserved','',0,1,NOW(),NOW());
INSERT INTO appointment_services(appointment_id,service_id,price_snapshot,duration_snapshot) VALUES
(1,1,12000,45),(2,1,12000,45),(2,2,8000,30),(3,1,12000,45);
INSERT INTO payments(appointment_id,payment_method_id,amount,commission_percent_snapshot,commission_amount,notes,paid_at,created_by) VALUES
(2,1,18000,45,8100,'Descuento de cortesía',CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 17:20:00'),1);
INSERT INTO expenses(expense_date,category_id,concept,amount,created_by,created_at,updated_at) VALUES
(DATE_SUB(CURDATE(),INTERVAL 1 DAY),2,'Insumos de limpieza',25000,2,NOW(),NOW());

SET FOREIGN_KEY_CHECKS=1;
