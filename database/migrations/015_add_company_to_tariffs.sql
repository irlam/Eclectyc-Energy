-- eclectyc-energy/database/migrations/015_add_company_to_tariffs.sql
-- Add company scoping to tariffs for confidentiality
-- Last updated: 10/11/2025

-- Add company_id to tariffs table
ALTER TABLE tariffs 
ADD COLUMN company_id INT UNSIGNED NULL AFTER supplier_id,
ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
ADD INDEX idx_company (company_id);

-- Add a comment explaining the company_id column
ALTER TABLE tariffs 
MODIFY COLUMN company_id INT UNSIGNED NULL COMMENT 'NULL means public/shared tariff, otherwise private to company';
