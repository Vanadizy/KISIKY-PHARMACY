ALTER TABLE homepage_slides
  ADD COLUMN heading_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF' AFTER text_alignment,
  ADD COLUMN description_color VARCHAR(7) NOT NULL DEFAULT '#F5F8F5' AFTER heading_color,
  ADD COLUMN button_color VARCHAR(7) NOT NULL DEFAULT '#07856D' AFTER description_color;
