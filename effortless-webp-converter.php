<?php
/**
 * Plugin Name: Effortless WebP Converter
 * Plugin URI:  https://github.com/nasratulnayem/effortless-webp-converter
 * Description: Effortlessly convert WordPress images to WebP from the dashboard. Auto-serve WebP to supported browsers — safe, fast, no originals lost.
 * Version:     0.1.0
 * Author:      Nasratul Nayem
 * Author URI:  https://github.com/nasratulnayem
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: effortless-webp-converter
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
	exit;
}

define('EWC_VERSION', '0.1.0');
define('EWC_FILE', __FILE__);
define('EWC_PATH', plugin_dir_path(__FILE__));
define('EWC_URL', plugin_dir_url(__FILE__));

require_once EWC_PATH . 'includes/class-effortless-webp-converter.php';

Effortless_WebP_Converter::instance();
