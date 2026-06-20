-- 006_spatial_geo.sql — POINT column + SPATIAL index for geo-distance matching (FR-20)
--
-- Adds a generated POINT column (SRID 4326 = WGS-84) to donor_profiles and
-- hospital_profiles so we can rank matches with ST_Distance_Sphere() instead of
-- a city-string LIKE. The column is stored (not virtual) so the SPATIAL index can be built.
--
-- COALESCE(longitude,0) / COALESCE(latitude,0) maps missing coords to the null-island
-- sentinel (0,0); matching queries MUST filter `WHERE latitude IS NOT NULL` to exclude
-- these sentinels from distance results while still allowing the NOT NULL constraint
-- required by the SPATIAL index.

-- MySQL requires NOT NULL after STORED for generated spatial columns, and does not
-- allow SRID functions in generated column expressions on this version (8.0.x).
-- Use plain POINT(lon, lat); ST_Distance_Sphere accepts non-SRID points in 8.0.
ALTER TABLE `donor_profiles`
    ADD COLUMN `geo` POINT
        GENERATED ALWAYS AS (POINT(IFNULL(`longitude`,0), IFNULL(`latitude`,0)))
        STORED NOT NULL;
ALTER TABLE `donor_profiles`
    ADD SPATIAL INDEX `sx_donor_geo` (`geo`);

ALTER TABLE `hospital_profiles`
    ADD COLUMN `geo` POINT
        GENERATED ALWAYS AS (POINT(IFNULL(`longitude`,0), IFNULL(`latitude`,0)))
        STORED NOT NULL;
ALTER TABLE `hospital_profiles`
    ADD SPATIAL INDEX `sx_hospital_geo` (`geo`);

INSERT IGNORE INTO schema_migrations (version) VALUES ('006');
