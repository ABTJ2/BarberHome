-- Ejecutar UNA VEZ en instalaciones anteriores. Conserva todos los datos.
USE barber_house;
ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL AFTER active;
ALTER TABLE clients ADD COLUMN deleted_at DATETIME NULL AFTER notes;
ALTER TABLE barbers ADD COLUMN deleted_at DATETIME NULL AFTER active;
ALTER TABLE services ADD COLUMN deleted_at DATETIME NULL AFTER active;
ALTER TABLE payments ADD COLUMN voided_at DATETIME NULL AFTER updated_at,
  ADD COLUMN voided_by INT UNSIGNED NULL AFTER voided_at,
  ADD CONSTRAINT fk_payment_void_user FOREIGN KEY (voided_by) REFERENCES users(id);
