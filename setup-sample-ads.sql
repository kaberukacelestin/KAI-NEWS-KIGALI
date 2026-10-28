-- ============================================
-- INSERT SAMPLE ADVERTISEMENTS FOR 4-IMAGE GRID
-- ============================================
-- This script populates the 4 advertisement slots shown on the homepage
-- Positions: banner_top_photo_1, banner_top_photo_2, banner_top_photo_3, banner_top_photo_4

INSERT INTO `advertisements` 
(`title`, `image`, `link_url`, `position`, `is_active`, `is_animated`, `animation_type`, `start_date`, `end_date`) 
VALUES 
('Ad Image 1', 'ad-placeholder-1.jpg', 'https://example.com', 'banner_top_photo_1', 1, 0, 'fade', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
('Ad Image 2', 'ad-placeholder-2.jpg', 'https://example.com', 'banner_top_photo_2', 1, 0, 'fade', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
('Ad Image 3', 'ad-placeholder-3.jpg', 'https://example.com', 'banner_top_photo_3', 1, 0, 'fade', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY)),
('Ad Image 4', 'ad-placeholder-4.jpg', 'https://example.com', 'banner_top_photo_4', 1, 0, 'fade', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY));
