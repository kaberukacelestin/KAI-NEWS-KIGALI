# SETTING UP THE 4-IMAGE ADVERTISEMENT GRID

## Overview
Your KAI News website already has the layout and CSS configured for a **4-image advertisement grid** (25% width each on desktop). You just need to:
1. Prepare 4 advertisement images
2. Run the SQL setup script
3. Upload images to the server
4. Refresh the website

---

## Step-by-Step Setup

### Step 1: Prepare Your Images
- Create or gather **4 images** for advertisements
- **Recommended dimensions**: 400 × 200 pixels (or any 2:1 aspect ratio)
- **Supported formats**: JPG, PNG, GIF
- **Image names**:
  - `ad-1.jpg` (Top-Left position)
  - `ad-2.jpg` (Top-Right position)
  - `ad-3.jpg` (Bottom-Left position)
  - `ad-4.jpg` (Bottom-Right position)

### Step 2: Upload Images to Server
1. Connect to your XAMPP/server via FTP or file manager
2. Navigate to: `uploads/ads/`
3. Upload your 4 images there
4. Verify files are present at:
   - `uploads/ads/ad-1.jpg`
   - `uploads/ads/ad-2.jpg`
   - `uploads/ads/ad-3.jpg`
   - `uploads/ads/ad-4.jpg`

### Step 3: Insert Advertisement Records
1. Open your **phpMyAdmin** or MySQL client
2. Select the **kai_news_kigali** database
3. Go to **SQL** tab
4. Copy and paste the contents of **SETUP_ADVERTISEMENT_GRID.sql**
5. Click **Execute**

### Step 4: Verify Setup
1. Visit your website homepage: `http://kaineews.infinityfreapp.com/`
2. You should see 4 images displayed in a grid below the main banner
3. Layout:
   - **Desktop**: 4 columns (25% width each)
   - **Tablet**: 2×2 grid (50% width each)
   - **Mobile**: 1 column (full width)

---

## Responsive Layout

The 4-image grid automatically adjusts based on screen size:

| Screen Size | Layout | Image Width |
|-------------|--------|-------------|
| Desktop (>1000px) | 4 columns in 1 row | 25% each |
| Tablet (600-1000px) | 2×2 grid | 50% each |
| Mobile (<600px) | 1 column | 100% width |

---

## Advanced: Edit Advertisements

To change an ad after setup:

1. Go to your **Admin Dashboard** → **Advertisements**
2. Find the ad you want to modify
3. Update:
   - **Title**: Display name
   - **Image**: Upload new image (replaces old one)
   - **Link URL**: Where clicking leads
   - **Active**: Toggle on/off
   - **Animation**: Choose fade, slide, zoom, etc.
   - **Date Range**: Set start/end dates

---

## Troubleshooting

**Q: Images aren't showing?**
- ✓ Check images exist in `uploads/ads/`
- ✓ Check file names exactly match: `ad-1.jpg`, `ad-2.jpg`, etc.
- ✓ Verify file permissions (644 or 755)
- ✓ Check database has 4 records with positions `banner_top_photo_1` through `banner_top_photo_4`

**Q: Grid shows only some images?**
- ✓ SQL script may not have run. Re-run SETUP_ADVERTISEMENT_GRID.sql
- ✓ Some images might not have uploaded. Check uploads/ads/ folder

**Q: Images look stretched or distorted?**
- ✓ Use correct aspect ratio: 2:1 (width:height)
- ✓ For 400×200px, image won't distort
- ✓ CSS uses `background-size: cover` - images will be cropped if aspect ratio differs

**Q: How do I add more than 4 images?**
- Currently, the grid is fixed at 4 images
- To add more positions, ask your developer to modify the CSS and add more positions to the ads table

---

## File References

- **SQL Setup**: [SETUP_ADVERTISEMENT_GRID.sql](SETUP_ADVERTISEMENT_GRID.sql)
- **Database**: kai_news_kigali (advertisements table)
- **Upload Directory**: uploads/ads/
- **CSS**: assets/css/style.css (lines 749-800)
- **Homepage Code**: index.php (lines 250-263)

---

## Next Steps

After setup, you can:
- 📊 Track ad clicks in the Admin Dashboard → Advertisements
- 🔄 Change ads anytime without editing code
- ⏰ Set expiration dates for limited-time promotions
- 🎬 Add animation effects (fade, slide, bounce, zoom, etc.)
- 📱 View different screen layouts in browser DevTools

