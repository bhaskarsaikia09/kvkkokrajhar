-- KVK Kokrajhar - Training Farmer Self Registration Module
-- EXISTING DATABASE EDITION
-- Run this while your OLD/CURRENT KVK website database is selected.
-- This script DOES NOT create a database and DOES NOT switch databases.
-- It only adds the two module tables below to the current database.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS training_programmes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_code VARCHAR(32) NOT NULL UNIQUE,
    registration_token CHAR(64) NOT NULL UNIQUE,
    training_name VARCHAR(255) NOT NULL,
    training_type VARCHAR(100) NOT NULL,
    training_date DATE NOT NULL,
    venue VARCHAR(255) NOT NULL,
    registration_deadline DATETIME NULL,
    registration_status ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_by BIGINT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_training_date (training_date),
    INDEX idx_training_status (registration_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_farmer_registrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_id BIGINT UNSIGNED NOT NULL,
    registration_no VARCHAR(50) NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    village VARCHAR(150) NOT NULL,
    district VARCHAR(120) NOT NULL,
    pincode CHAR(6) NOT NULL,
    gender ENUM('Male','Female') NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    caste ENUM('Gen','OBC','SC','ST(P)','ST(H)') NOT NULL,
    category ENUM('Farmer','Farm Women','Rural Youth','Students','Entrepreneur','Others') NOT NULL,
    aadhaar_last4 CHAR(4) NULL,
    aadhaar_hash CHAR(64) NULL,
    mobile_no VARCHAR(15) NOT NULL,
    photo_path VARCHAR(255) NULL,
    photo_mime VARCHAR(50) NULL,
    registration_source ENUM('online','qr','admin') NOT NULL DEFAULT 'online',
    consent_given TINYINT(1) NOT NULL DEFAULT 0,
    guardian_name VARCHAR(150) NULL,
    guardian_consent TINYINT(1) NOT NULL DEFAULT 0,
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_training_farmer_training
        FOREIGN KEY (training_id) REFERENCES training_programmes(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uq_training_aadhaar_hash (training_id, aadhaar_hash),
    INDEX idx_training_farmer_training (training_id),
    INDEX idx_training_farmer_mobile (mobile_no),
    INDEX idx_training_farmer_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
