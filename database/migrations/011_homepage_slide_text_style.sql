ALTER TABLE homepage_slides
  ADD COLUMN text_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF' AFTER is_active,
  ADD COLUMN text_alignment VARCHAR(10) NOT NULL DEFAULT 'left' AFTER text_color;
