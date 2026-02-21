-- database/setup.sql
-- Yeh file sirf ek baar run karo jab database setup kar rahe ho

-- Check total countries
SELECT COUNT(*) as total_countries FROM countries;

-- Check active countries
SELECT COUNT(*) as active_countries FROM countries WHERE is_active = 1;

-- List all countries alphabetically
SELECT code, name FROM countries ORDER BY name;