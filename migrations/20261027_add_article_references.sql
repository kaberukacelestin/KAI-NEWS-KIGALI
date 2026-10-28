ALTER TABLE `news`
  ADD COLUMN `article_references` JSON DEFAULT NULL AFTER `inline_images`;
