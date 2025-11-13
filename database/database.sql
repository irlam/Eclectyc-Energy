-- =====================================================
-- ECLECTYC ENERGY - DATABASE CLEANUP SCRIPT
-- =====================================================
-- Remove all data associated with import ID: 758ca034
-- This script will clean up old import data that cannot be deleted via the UI
-- Last updated: 13/11/2025
-- =====================================================

-- WARNING: This will permanently delete data
-- Make sure you have a backup before running this script

-- =====================================================
-- Delete meter readings associated with import 758ca034
-- =====================================================
DELETE FROM meter_readings 
WHERE batch_id = '758ca034' 
   OR import_batch_id = '758ca034';

-- =====================================================
-- Delete meters created by import 758ca034 (only if they have no other data)
-- =====================================================
-- First, check if any meters were created by this import and have no other readings
-- Using a temporary table to avoid MySQL limitation with subquery
DELETE m FROM meters m
LEFT JOIN (
    SELECT DISTINCT meter_id 
    FROM meter_readings 
    WHERE batch_id != '758ca034' 
       OR batch_id IS NULL
) AS keep_meters ON m.id = keep_meters.meter_id
WHERE m.batch_id = '758ca034'
  AND keep_meters.meter_id IS NULL;

-- =====================================================
-- Delete audit log entries for import 758ca034
-- =====================================================
DELETE FROM audit_logs 
WHERE batch_id = '758ca034'
   OR parent_batch_id = '758ca034';

-- =====================================================
-- Delete import job record for 758ca034
-- =====================================================
DELETE FROM import_jobs 
WHERE batch_id = '758ca034';

-- =====================================================
-- Verify deletion
-- =====================================================
-- You can run these queries to verify the cleanup:
-- SELECT COUNT(*) FROM meter_readings WHERE batch_id = '758ca034' OR import_batch_id = '758ca034';
-- SELECT COUNT(*) FROM meters WHERE batch_id = '758ca034';
-- SELECT COUNT(*) FROM audit_logs WHERE batch_id = '758ca034';
-- SELECT COUNT(*) FROM import_jobs WHERE batch_id = '758ca034';
