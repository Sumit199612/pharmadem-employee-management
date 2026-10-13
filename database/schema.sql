CREATE DATABASE IF NOT EXISTS `employee_management_db`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `employee_management_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- Departments
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'Manager', 'Employee') NOT NULL DEFAULT 'Employee',
    `department_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('Active', 'Inactive', 'Suspended') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_users_department` 
        FOREIGN KEY (`department_id`) 
        REFERENCES `departments` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_users_role` ON `users` (`role`);
CREATE INDEX `idx_users_department_id` ON `users` (`department_id`);
CREATE INDEX `idx_users_status` ON `users` (`status`);

-- Tasks
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `assigned_to` INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED NOT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    `status` ENUM('Pending', 'In Progress', 'Completed', 'Overdue') NOT NULL DEFAULT 'Pending',
    `due_date` DATE NOT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_tasks_assigned_to` 
        FOREIGN KEY (`assigned_to`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
        
    CONSTRAINT `fk_tasks_assigned_by` 
        FOREIGN KEY (`assigned_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_tasks_assigned_to` ON `tasks` (`assigned_to`);
CREATE INDEX `idx_tasks_assigned_by` ON `tasks` (`assigned_by`);
CREATE INDEX `idx_tasks_status` ON `tasks` (`status`);
CREATE INDEX `idx_tasks_priority` ON `tasks` (`priority`);
CREATE INDEX `idx_tasks_due_date` ON `tasks` (`due_date`);
CREATE INDEX `idx_tasks_assigned_status` ON `tasks` (`assigned_to`, `status`);

-- Daily updates
DROP TABLE IF EXISTS `daily_updates`;
CREATE TABLE `daily_updates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `update_date` DATE NOT NULL,
    `todo_tasks` TEXT NOT NULL,
    `completed_work` TEXT NOT NULL,
    `pending_blockers` TEXT DEFAULT NULL,
    `status` ENUM('Submitted', 'Reviewed') NOT NULL DEFAULT 'Submitted',
    `reviewed_by` INT UNSIGNED DEFAULT NULL,
    `review_notes` TEXT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `uq_employee_update_date` 
        UNIQUE (`employee_id`, `update_date`),
        
    CONSTRAINT `fk_daily_updates_employee` 
        FOREIGN KEY (`employee_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
        
    CONSTRAINT `fk_daily_updates_reviewer` 
        FOREIGN KEY (`reviewed_by`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_daily_updates_employee_id` ON `daily_updates` (`employee_id`);
CREATE INDEX `idx_daily_updates_date` ON `daily_updates` (`update_date`);
CREATE INDEX `idx_daily_updates_status` ON `daily_updates` (`status`);

-- Performance evaluations
DROP TABLE IF EXISTS `performance_evaluations`;
CREATE TABLE `performance_evaluations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `evaluator_id` INT UNSIGNED NOT NULL,
    `evaluation_period` VARCHAR(20) NOT NULL,
    `task_completion_score` DECIMAL(5, 2) NOT NULL,
    `punctuality_score` DECIMAL(5, 2) NOT NULL,
    `quality_score` DECIMAL(5, 2) NOT NULL,
    `overall_score` DECIMAL(5, 2) GENERATED ALWAYS AS (
        ROUND((`task_completion_score` + `punctuality_score` + `quality_score`) / 3, 2)
    ) STORED,
    `manager_remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `uq_employee_evaluation_period` 
        UNIQUE (`employee_id`, `evaluation_period`),
        
    CONSTRAINT `fk_evaluations_employee` 
        FOREIGN KEY (`employee_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
        
    CONSTRAINT `fk_evaluations_evaluator` 
        FOREIGN KEY (`evaluator_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
        
    CONSTRAINT `chk_task_score` CHECK (`task_completion_score` BETWEEN 0.00 AND 100.00),
    CONSTRAINT `chk_punctuality_score` CHECK (`punctuality_score` BETWEEN 0.00 AND 100.00),
    CONSTRAINT `chk_quality_score` CHECK (`quality_score` BETWEEN 0.00 AND 100.00)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_evaluations_employee_id` ON `performance_evaluations` (`employee_id`);
CREATE INDEX `idx_evaluations_evaluator_id` ON `performance_evaluations` (`evaluator_id`);
CREATE INDEX `idx_evaluations_period` ON `performance_evaluations` (`evaluation_period`);

SET FOREIGN_KEY_CHECKS = 1;

-- Seed data
INSERT INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'Engineering', 'Software development, QA, and devops'),
(2, 'Operations', 'Quality control and regulatory compliance'),
(3, 'Human Resources', 'Talent acquisition, management, and relations')
ON DUPLICATE KEY UPDATE `name`=`name`;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `department_id`) VALUES
(1, 'System Administrator', 'admin@pharmadem.local', '$2y$12$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Admin', 1),
(2, 'Sarah Jenkins', 'manager@pharmadem.local', '$2y$12$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Manager', 1),
(3, 'Alex Mercer', 'employee@pharmadem.local', '$2y$12$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Employee', 1)
ON DUPLICATE KEY UPDATE `email`=`email`;
