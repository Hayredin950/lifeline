-- =============================================================================
-- Migration 002: Replace all Indian seed/test data with Ethiopian equivalents
-- Run once against the live database:
--   mysql -u root -p lifeline_db_mysql < schema/002_ethiopian_localization.sql
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Blood banks — replace all 8 Indian hospitals with Ethiopian ones
-- -----------------------------------------------------------------------------
DELETE FROM `blood_banks` WHERE `id` IN (1,2,3,4,5,6,7,8);

INSERT INTO `blood_banks`
  (`id`,`name`,`address`,`city`,`state`,`phone`,`email`,`license_number`,`working_hours`,`has_24h_service`,`latitude`,`longitude`,`created_at`)
VALUES
  (1,'Ethiopian Red Cross Society Blood Bank','Ras Desta Damtew Street, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 551 5166',NULL,NULL,'24 Hours',1,9.0350,38.7469,CURRENT_TIMESTAMP),
  (2,'Tikur Anbessa (Black Lion) Hospital Blood Bank','Siddist Kilo, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 551 4016',NULL,NULL,'24 Hours',1,9.0399,38.7638,CURRENT_TIMESTAMP),
  (3,'Yekatit 12 Hospital Blood Bank','Piassa, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 155 3800',NULL,NULL,'24 Hours',1,9.0250,38.7458,CURRENT_TIMESTAMP),
  (4,'St. Paul''s Hospital Millennium Medical College Blood Bank','Gulele Sub-City, Addis Ababa','Addis Ababa','Addis Ababa','+251 11 276 5298',NULL,NULL,'24 Hours',1,9.0491,38.7431,CURRENT_TIMESTAMP),
  (5,'Bahir Dar University Teaching Hospital Blood Bank','Bahir Dar, Amhara Region','Bahir Dar','Amhara','+251 58 220 7230',NULL,NULL,'8am-6pm',0,11.5930,37.3868,CURRENT_TIMESTAMP),
  (6,'Mekelle University Hospital Blood Bank','Mekelle, Tigray Region','Mekelle','Tigray','+251 34 441 6680',NULL,NULL,'8am-6pm',0,13.4968,39.4753,CURRENT_TIMESTAMP),
  (7,'Hawassa University Comprehensive Specialized Hospital Blood Bank','Hawassa, Sidama Region','Hawassa','Sidama','+251 46 220 9038',NULL,NULL,'8am-6pm',0,7.0598,38.4791,CURRENT_TIMESTAMP),
  (8,'Jimma University Medical Centre Blood Bank','Jimma, Oromia Region','Jimma','Oromia','+251 47 111 4100',NULL,NULL,'8am-6pm',0,7.6782,36.8344,CURRENT_TIMESTAMP);

-- -----------------------------------------------------------------------------
-- 2. Testimonials — replace Indian names/stories with Ethiopian ones
-- -----------------------------------------------------------------------------
UPDATE `testimonials` SET
  `recipient_name` = 'Abebe Girma''s Family',
  `story`          = 'My father needed O- blood urgently after his accident. Within 2 hours, LifeLine matched us with a donor in Addis Ababa. He survived because of this platform. Forever grateful.'
WHERE `id` = 1;

UPDATE `testimonials` SET
  `recipient_name` = 'Dr. Tigist Haile',
  `story`          = 'As a hospital administrator at Tikur Anbessa Hospital, LifeLine has transformed how we handle emergency blood needs. The matching system is incredibly fast and reliable.'
WHERE `id` = 2;

UPDATE `testimonials` SET
  `recipient_name` = 'Meron Tadesse',
  `story`          = 'I donated blood for the first time through LifeLine. The process was so simple and knowing I helped save a life is the best feeling in the world.'
WHERE `id` = 3;

-- -----------------------------------------------------------------------------
-- 3. Donor profiles — update all Indian city/state/country references
-- -----------------------------------------------------------------------------
UPDATE `donor_profiles` SET
  `city`    = 'Addis Ababa',
  `state`   = 'Addis Ababa',
  `country` = 'Ethiopia'
WHERE `city`    IN ('Mumbai','New Delhi','Delhi','Kolkata','Chennai','Bangalore','Hyderabad','Pune','Lucknow','Chandigarh','Jaipur','Ahmedabad','Surat')
   OR `state`   IN ('Maharashtra','Delhi','NCR','Karnataka','Tamil Nadu','Telangana','Uttar Pradesh','Punjab','Rajasthan','Gujarat','West Bengal')
   OR `country` = 'India';

-- -----------------------------------------------------------------------------
-- 4. Hospital profiles — update all Indian city/state/country references
-- -----------------------------------------------------------------------------
UPDATE `hospital_profiles` SET
  `city`    = 'Addis Ababa',
  `state`   = 'Addis Ababa',
  `country` = 'Ethiopia'
WHERE `city`    IN ('Mumbai','New Delhi','Delhi','Kolkata','Chennai','Bangalore','Hyderabad','Pune','Lucknow','Chandigarh','Jaipur','Ahmedabad','Surat')
   OR `state`   IN ('Maharashtra','Delhi','NCR','Karnataka','Tamil Nadu','Telangana','Uttar Pradesh','Punjab','Rajasthan','Gujarat','West Bengal')
   OR `country` = 'India';

-- -----------------------------------------------------------------------------
-- 5. Blood requests — update Indian location fields
-- -----------------------------------------------------------------------------
UPDATE `blood_requests` SET
  `city`  = 'Addis Ababa',
  `state` = 'Addis Ababa'
WHERE `city`  IN ('Mumbai','New Delhi','Delhi','Kolkata','Chennai','Bangalore','Hyderabad','Pune','Lucknow','Chandigarh','Jaipur','Ahmedabad','Surat')
   OR `state` IN ('Maharashtra','Delhi','NCR','Karnataka','Tamil Nadu','Telangana','Uttar Pradesh','Punjab','Rajasthan','Gujarat','West Bengal');

-- Also fix the hospital_address text field in blood_requests
UPDATE `blood_requests` SET
  `hospital_address` = REPLACE(`hospital_address`, 'Maharashtra', 'Addis Ababa')
WHERE `hospital_address` LIKE '%Maharashtra%';

UPDATE `blood_requests` SET
  `hospital_address` = REPLACE(`hospital_address`, 'Mumbai', 'Addis Ababa')
WHERE `hospital_address` LIKE '%Mumbai%';

-- -----------------------------------------------------------------------------
-- 6. Mark migration as applied
-- -----------------------------------------------------------------------------
INSERT INTO `schema_migrations` (`version`) VALUES ('002_ethiopian_localization')
ON DUPLICATE KEY UPDATE `version` = `version`;
