-- Migration: Add split name fields and sex to requests table
-- Run this file once on existing installations to add the new columns.
-- Safe to run multiple times (uses IF NOT EXISTS where supported).

ALTER TABLE requests
  ADD COLUMN IF NOT EXISTS requester_firstname VARCHAR(75) DEFAULT NULL AFTER requester_name,
  ADD COLUMN IF NOT EXISTS requester_middlename VARCHAR(10) DEFAULT NULL AFTER requester_firstname,
  ADD COLUMN IF NOT EXISTS requester_lastname VARCHAR(75) DEFAULT NULL AFTER requester_middlename,
  ADD COLUMN IF NOT EXISTS requester_sex ENUM('Male','Female') DEFAULT NULL AFTER requester_lastname;
