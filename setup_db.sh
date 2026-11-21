#!/bin/bash
DB_NAME='tiktok_db'
DB_USER='tiktok_user'
DB_PASS='TikTok@2026Secure!'

# Create database and user
sudo mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Core schema
sudo mysql ${DB_NAME} -e "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    referred_by VARCHAR(50),
    wallet_balance DECIMAL(10,2) DEFAULT 0.00,
    avatar VARCHAR(255),
    two_factor_secret VARCHAR(255),
    is_2fa_enabled TINYINT(1) DEFAULT 0,
    payout_address VARCHAR(255),
    payout_method VARCHAR(50),
    payout_network VARCHAR(50),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    registration_ip VARCHAR(45),
    last_login_ip VARCHAR(45),
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    buyer_identifier VARCHAR(255) NOT NULL,
    amount_total DECIMAL(10,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    order_id VARCHAR(255),
    type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_details TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referral_code VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
"

# Run additional schema files
cd /var/www/anglodutchrecruit.eu
sudo mysql ${DB_NAME} < loyalty_schema.sql
sudo mysql ${DB_NAME} < wallets_schema.sql
sudo mysql ${DB_NAME} < add_avatar_column.sql
sudo mysql ${DB_NAME} < update_users_schema.sql

# Update config.php
sudo sed -i "s/'host' => 'localhost'/'host' => 'localhost'/g" config.php
sudo sed -i "s/'name' => 'u916646072_tiktok'/'name' => '${DB_NAME}'/g" config.php
sudo sed -i "s/'user' => 'u916646072_admin'/'user' => '${DB_USER}'/g" config.php
sudo sed -i "s/'pass' => '@Idea241@'/'pass' => '${DB_PASS}'/g" config.php

echo 'Database setup complete.'
