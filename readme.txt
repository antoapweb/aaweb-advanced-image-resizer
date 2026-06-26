=== AAWEB Advanced Image Resizer ===
Contributors: antoapweb
Tags: image resize, media library, bulk resize, webp, social images
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Resize Media Library images with padding, social presets, bulk tools, create-new mode, safer replace mode and JPG/PNG/WebP export.

== Description ==

AAWEB Advanced Image Resizer adds resize tools directly inside the WordPress Media Library.

Features:

* Resize with padding to fixed canvas dimensions.
* Social/ad presets such as 1080x1080, 1080x1350, 1200x630 and 1200x628.
* WordPress.org asset presets: 1544x500, 772x250, 256x256 and 128x128.
* Row action on Media Library list view.
* Bulk resize action with size and mode selectors.
* Tool inside the WordPress image editor modal.
* Replace mode or Create New mode.
* Export resized images as JPG, PNG or WebP.
* Optional backup before replace.
* Backup manager in the image editor modal: list, restore and delete backup files.
* Maximum backups per image setting.
* JPG quality setting.
* WebP quality setting.
* Default output format setting.
* Background color setting.
* Custom presets.

== Screenshots ==

1. Modern settings dashboard with resize defaults, quality controls and built-in social media presets.
2. Bulk resize tools directly inside the WordPress Media Library with format and mode selection.
3. Integrated image editor resize panel with backups, restore tools and one-click image processing.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP file from Plugins > Add New.
2. Activate the plugin.
3. Go to Media > AAWEB Image Resizer to configure defaults.
4. Open Media Library in list view and use the row action or bulk action.

== Frequently Asked Questions ==

= Does Replace keep the same attachment ID? =

Yes. Replace updates the current attachment file and regenerates attachment metadata.

= Does Create New change the original image? =

No. Create New creates a new attachment in the selected output format: JPG, PNG or WebP.

= Can I add my own sizes? =

Yes. Go to Media > AAWEB Image Resizer and add one custom preset per line using `Label|WIDTHxHEIGHT`.

== Changelog ==

= 1.2.1 =
* Added safer permission checks per attachment for AJAX and bulk resize actions.
* Added a GD availability check before image processing to avoid fatal errors on unsupported servers.
* Improved replace mode so thumbnails are removed only after the resized file is saved successfully.

= 1.2.0 =
* Added output format selection for JPG, PNG and WebP.
* Added default output format setting.
* Added WebP quality setting.
* Added output format controls to row action, bulk resize and image editor modal.

= 1.1.0 =
* Added backup manager inside the image editor modal.
* Added restore selected backup and delete backup actions.
* Added maximum backups per image setting.

= 1.0.2 =
* Added SEO-friendly Create New filenames based on original filename, preset and dimensions.
* Added uninstall cleanup for plugin settings.

= 1.0.0 =
* Initial AAWEB release.
