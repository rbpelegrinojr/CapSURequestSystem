-- Migration: Add Word template upload support to document_templates
-- Run this file once on existing installations to add the new column.
-- Safe to run multiple times (uses IF NOT EXISTS where supported).

ALTER TABLE document_templates
  ADD COLUMN IF NOT EXISTS template_docx_path VARCHAR(255) DEFAULT NULL;
