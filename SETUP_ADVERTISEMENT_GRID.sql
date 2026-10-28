-- ============================================
-- KAI NEWS: SETUP 4-IMAGE ADVERTISEMENT GRID
-- ============================================
-- This SQL script populates 4 advertisement slots for the homepage
-- 
-- BEFORE RUNNING:
-- 1. Prepare 4 images (recommended size: 400x200px)
-- 2. Save them as: ad-1.jpg, ad-2.jpg, ad-3.jpg, ad-4.jpg
-- 3. Upload them to: /uploads/ads/ folder
-- 4. Run this SQL script in your database
-- 5. Refresh your website homepage

-- Insert 4 sample advertisements into the grid
INSERT INTO `advertisements` 
(`title`, `image`, `link_url`, `position`, `is_active`, `is_animated`, `animation_type`, `start_date`, `end_date`) 
VALUES 
(
    'Advertisement 1',
    'ad-1.jpg',
    'https://example.com',
    'banner_top_photo_1',
    1,
    0,
    'fade',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 365 DAY)
),
(
    'Advertisement 2',
    'ad-2.jpg',
    'https://example.com',
    'banner_top_photo_2',
    1,
    0,
    'fade',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 365 DAY)
),
(
    'Advertisement 3',
    'ad-3.jpg',
    'https://example.com',
    'banner_top_photo_3',
    1,
    0,
    'fade',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 365 DAY)
),
(
    'Advertisement 4',
    'ad-4.jpg',
    'https://example.com',
    'banner_top_photo_4',
    1,
    0,
    'fade',
    CURDATE(),
    DATE_ADD(CURDATE(), INTERVAL 365 DAY)
);

-- ============================================
-- SUCCESS: 4 advertisement slots created!
-- ============================================
-- The grid is now enabled with these 4 positions:
-- - banner_top_photo_1 (Top Left)
-- - banner_top_photo_2 (Top Right)
-- - banner_top_photo_3 (Bottom Left)
-- - banner_top_photo_4 (Bottom Right)
--
-- Layout on Desktop: 4 columns (25% width each)
-- Layout on Tablet: 2x2 grid (50% width each)
-- Layout on Mobile: 1 column (100% width)
-- ============================================
