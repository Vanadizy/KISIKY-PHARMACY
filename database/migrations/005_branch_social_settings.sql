-- Move public contact/location and social profile data to each branch.
-- Back up first and apply after migrations 002 through 004.
ALTER TABLE branches ADD COLUMN social_whatsapp VARCHAR(80) NULL AFTER map_link;
ALTER TABLE branches ADD COLUMN social_instagram VARCHAR(500) NULL AFTER social_whatsapp;
