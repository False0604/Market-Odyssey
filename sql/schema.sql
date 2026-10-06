-- ============================================================
--  Market Odyssey — reference database schema
-- ============================================================
--  You normally do NOT need to run this by hand: config/db.php
--  creates the database, the tables and the sample data
--  automatically the first time you open the site.
--
--  This file is here so you can see the structure at a glance,
--  or recreate it manually in phpMyAdmin if you want.
-- ============================================================

CREATE DATABASE IF NOT EXISTS market_odyssey
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE market_odyssey;

-- Students who can buy / sell / chat -------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(80)  NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,   -- hashed with password_hash()
    hostel     VARCHAR(40)  DEFAULT 'Hostel A',
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- Items for sale --------------------------------------------
CREATE TABLE IF NOT EXISTS listings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    title          VARCHAR(120) NOT NULL,
    category       VARCHAR(40)  NOT NULL,
    price          INT NOT NULL DEFAULT 0,      -- 0 = free giveaway
    item_condition VARCHAR(20)  NOT NULL DEFAULT 'Good',
    description    TEXT,
    pickup         VARCHAR(40)  DEFAULT 'Hostel A',
    photo          VARCHAR(255) DEFAULT '',     -- file name in /uploads
    pay_cod        TINYINT(1)   DEFAULT 1,
    pay_upi        TINYINT(1)   DEFAULT 1,
    status         VARCHAR(20)  DEFAULT 'pending',  -- pending | approved
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- Buyer <-> seller chat messages ----------------------------
CREATE TABLE IF NOT EXISTS messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    listing_id  INT NOT NULL,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
