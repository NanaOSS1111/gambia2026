CREATE DATABASE IF NOT EXISTS event_registration CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_registration;

CREATE TABLE registrations (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  representation_type VARCHAR(100) NOT NULL,
  organisation_name   VARCHAR(255) NOT NULL,
  picture             VARCHAR(255),
  title               VARCHAR(20),
  gender              VARCHAR(30) NOT NULL,
  first_name          VARCHAR(100) NOT NULL,
  last_name           VARCHAR(100) NOT NULL,
  position            VARCHAR(150),
  institution         VARCHAR(255),
  email               VARCHAR(255) NOT NULL,
  birth_date          DATE NOT NULL,
  passport_nationality VARCHAR(100) NOT NULL,
  passport_number     VARCHAR(50) NOT NULL,
  passport_expiration DATE NOT NULL,
  passport_file       VARCHAR(255),
  nomination_letter   VARCHAR(255),
  is_18_or_older      TINYINT(1) NOT NULL DEFAULT 0,
  arrival_date        DATE NOT NULL,
  departure_date      DATE NOT NULL,
  address_in_country  TEXT NOT NULL,
  contact_number      VARCHAR(50) NOT NULL,
  code_of_conduct     TINYINT(1) NOT NULL DEFAULT 0,
  data_privacy        TINYINT(1) NOT NULL DEFAULT 0,
  terms_conditions    TINYINT(1) NOT NULL DEFAULT 0,
  undertakings        TINYINT(1) NOT NULL DEFAULT 0,
  final_confirmation  TINYINT(1) NOT NULL DEFAULT 0,
  status              ENUM('pending','approved','rejected') DEFAULT 'pending',
  ip_address          VARCHAR(45),
  submitted_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Run this if the table already exists:
-- ALTER TABLE registrations ADD COLUMN undertakings TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE registrations ADD COLUMN final_confirmation TINYINT(1) NOT NULL DEFAULT 0;
