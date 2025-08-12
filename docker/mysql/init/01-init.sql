-- MySQL initialization script for KibaAuth
-- This script ensures the database and user are properly set up

-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `kibaauth` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create the user if it doesn't exist and grant privileges
CREATE USER IF NOT EXISTS 'kibaauth_user'@'%' IDENTIFIED BY 'kibaauth_password';
GRANT ALL PRIVILEGES ON `kibaauth`.* TO 'kibaauth_user'@'%';

-- Grant privileges to root user as well
GRANT ALL PRIVILEGES ON `kibaauth`.* TO 'root'@'%';

-- Flush privileges to ensure they take effect
FLUSH PRIVILEGES;
