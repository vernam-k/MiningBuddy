-- Mining Buddy Database Schema
-- This file contains the complete database schema for the Mining Buddy application
-- Execute this file in phpMyAdmin or via MySQL command line to set up the database

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS mining_buddy
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE mining_buddy;

-- Drop tables if they exist to ensure clean installation
-- Tables are dropped in reverse order of creation to handle foreign key constraints
DROP TABLE IF EXISTS banned_users;
DROP TABLE IF EXISTS market_prices;
DROP TABLE IF EXISTS ore_types;
DROP TABLE IF EXISTS mining_ledger_snapshots;
DROP TABLE IF EXISTS operation_participants;
DROP TABLE IF EXISTS mining_operations;
DROP TABLE IF EXISTS users;

-- Users table - Stores EVE Online character information and authentication tokens
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL UNIQUE,
    character_name VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(512) DEFAULT NULL,
    corporation_id BIGINT UNSIGNED NOT NULL,
    corporation_name VARCHAR(255) NOT NULL,
    corporation_logo_url VARCHAR(512) DEFAULT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    token_expires DATETIME NOT NULL,
    active_operation_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_character_id (character_id),
    INDEX idx_corporation_id (corporation_id),
    INDEX idx_active_operation (active_operation_id)
) ENGINE=InnoDB;

-- Mining operations table - Stores information about mining operations
CREATE TABLE mining_operations (
    operation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    director_id INT UNSIGNED NOT NULL,
    join_code VARCHAR(10) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'ending', 'syncing', 'ended') NOT NULL DEFAULT 'active',
    last_mining_activity DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    termination_type ENUM('manual', 'inactivity', 'system') DEFAULT NULL,
    auto_terminate_exempt BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (director_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_join_code (join_code),
    INDEX idx_status (status),
    INDEX idx_creation_date (created_at)
) ENGINE=InnoDB;

-- Operation participants table - Tracks users participating in mining operations
CREATE TABLE operation_participants (
    participant_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'left', 'kicked', 'banned') NOT NULL DEFAULT 'active',
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,
    join_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    leave_time DATETIME DEFAULT NULL,
    FOREIGN KEY (operation_id) REFERENCES mining_operations(operation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_participant (user_id, status), -- Ensures a user can only be active in one operation
                                                           -- Note: This needs additional application logic to enforce properly
    INDEX idx_operation_user (operation_id, user_id),
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB;

-- Mining ledger snapshots table - Stores snapshots of mining activity from EVE API
CREATE TABLE mining_ledger_snapshots (
    snapshot_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type_id INT UNSIGNED NOT NULL, -- EVE Online type ID for the ore
    quantity BIGINT UNSIGNED NOT NULL,
    snapshot_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    snapshot_type ENUM('start', 'update', 'end') NOT NULL,
    FOREIGN KEY (operation_id) REFERENCES mining_operations(operation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_operation_user_type (operation_id, user_id, type_id),
    INDEX idx_snapshot_time (snapshot_time),
    INDEX idx_snapshot_type (snapshot_type)
) ENGINE=InnoDB;

-- Ore types table - Stores information about ore types from EVE API
CREATE TABLE ore_types (
    type_id INT UNSIGNED PRIMARY KEY, -- EVE Online type ID
    name VARCHAR(255) NOT NULL,
    icon_url VARCHAR(512) DEFAULT NULL,
    volume FLOAT NOT NULL DEFAULT 0,
    description TEXT,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- Market prices table - Stores current Jita market prices for ore types
CREATE TABLE market_prices (
    price_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_id INT UNSIGNED NOT NULL,
    jita_best_buy DECIMAL(20,2) NOT NULL,
    jita_best_sell DECIMAL(20,2) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES ore_types(type_id) ON DELETE CASCADE,
    INDEX idx_type_id (type_id),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB;

-- Banned users table - Stores users banned from specific operations
CREATE TABLE banned_users (
    ban_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    banned_by INT UNSIGNED NOT NULL,
    banned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    FOREIGN KEY (operation_id) REFERENCES mining_operations(operation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_ban (operation_id, user_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- User statistics table - Stores aggregated statistics for users
CREATE TABLE user_statistics (
    user_id INT UNSIGNED PRIMARY KEY,
    total_isk_mined DECIMAL(20,2) NOT NULL DEFAULT 0,
    total_operations_joined INT UNSIGNED NOT NULL DEFAULT 0,
    total_active_time INT UNSIGNED NOT NULL DEFAULT 0, -- Stored in seconds
    average_isk_per_operation DECIMAL(20,2) NOT NULL DEFAULT 0,
    average_isk_per_hour DECIMAL(20,2) NOT NULL DEFAULT 0,
    highest_isk_operation_id INT UNSIGNED DEFAULT NULL,
    highest_isk_operation_value DECIMAL(20,2) NOT NULL DEFAULT 0,
    current_rank INT UNSIGNED DEFAULT NULL,
    last_week_rank INT UNSIGNED DEFAULT NULL,
    last_month_rank INT UNSIGNED DEFAULT NULL,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (highest_isk_operation_id) REFERENCES mining_operations(operation_id) ON DELETE SET NULL,
    INDEX idx_rank (current_rank)
) ENGINE=InnoDB;

-- Corporation statistics table - Stores aggregated statistics for corporations
CREATE TABLE corporation_statistics (
    corporation_id BIGINT UNSIGNED PRIMARY KEY,
    corporation_name VARCHAR(255) NOT NULL,
    total_isk_mined DECIMAL(20,2) NOT NULL DEFAULT 0,
    total_members_participating INT UNSIGNED NOT NULL DEFAULT 0,
    total_operations_directed INT UNSIGNED NOT NULL DEFAULT 0,
    average_isk_per_operation DECIMAL(20,2) NOT NULL DEFAULT 0,
    most_active_director_id INT UNSIGNED DEFAULT NULL,
    top_contributor_id INT UNSIGNED DEFAULT NULL,
    current_rank INT UNSIGNED DEFAULT NULL,
    last_week_rank INT UNSIGNED DEFAULT NULL,
    last_month_rank INT UNSIGNED DEFAULT NULL,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (most_active_director_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (top_contributor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_name (corporation_name),
    INDEX idx_rank (current_rank)
) ENGINE=InnoDB;

-- Operation statistics table - Stores statistics for completed operations
CREATE TABLE operation_statistics (
    operation_id INT UNSIGNED PRIMARY KEY,
    total_isk_generated DECIMAL(20,2) NOT NULL DEFAULT 0,
    operation_duration INT UNSIGNED NOT NULL DEFAULT 0, -- Stored in seconds
    participant_count INT UNSIGNED NOT NULL DEFAULT 0,
    peak_concurrent_participants INT UNSIGNED NOT NULL DEFAULT 0,
    most_valuable_contributor_id INT UNSIGNED DEFAULT NULL,
    most_active_contributor_id INT UNSIGNED DEFAULT NULL,
    average_isk_per_participant DECIMAL(20,2) NOT NULL DEFAULT 0,
    most_mined_ore_type_id INT UNSIGNED DEFAULT NULL,
    operation_efficiency_score FLOAT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (operation_id) REFERENCES mining_operations(operation_id) ON DELETE CASCADE,
    FOREIGN KEY (most_valuable_contributor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (most_active_contributor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (most_mined_ore_type_id) REFERENCES ore_types(type_id) ON DELETE SET NULL,
    INDEX idx_isk_generated (total_isk_generated),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Time period statistics table - Stores aggregated statistics for specific time periods
CREATE TABLE time_period_statistics (
    period_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_operations INT UNSIGNED NOT NULL DEFAULT 0,
    total_isk_generated DECIMAL(20,2) NOT NULL DEFAULT 0,
    total_participants INT UNSIGNED NOT NULL DEFAULT 0,
    top_operation_id INT UNSIGNED DEFAULT NULL,
    top_user_id INT UNSIGNED DEFAULT NULL,
    top_corporation_id BIGINT UNSIGNED DEFAULT NULL,
    total_mining_time BIGINT UNSIGNED NOT NULL DEFAULT 0, -- Stored in seconds
    FOREIGN KEY (top_operation_id) REFERENCES mining_operations(operation_id) ON DELETE SET NULL,
    FOREIGN KEY (top_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY unique_period (period_type, start_date),
    INDEX idx_period_dates (period_type, start_date, end_date)
) ENGINE=InnoDB;

-- Alter the users table to add the foreign key for active_operation_id
-- This is done after table creation to avoid circular references
ALTER TABLE users
ADD CONSTRAINT fk_user_active_operation
FOREIGN KEY (active_operation_id) REFERENCES mining_operations(operation_id) ON DELETE SET NULL;

-- No initial data is inserted, the application will fetch this data from the EVE ESI API