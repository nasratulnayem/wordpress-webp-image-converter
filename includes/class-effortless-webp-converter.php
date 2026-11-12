<?php

if (! defined('ABSPATH')) {
	exit;
}

class Effortless_WebP_Converter {
	private const OPTION_STATE = 'ewc_state';
	private const OPTION_SETTINGS = 'ewc_settings';

	private static ?Effortless_WebP_Converter $instance = null;

	public static function instance(): Effortless_WebP_Converter {
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action('admin_menu', [$this, 'register_admin_page']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('admin_init', [$this, 'register_settings']);

		add_action('wp_ajax_ewc_scan', [$this, 'ajax_scan']);
		add_action('wp_ajax_ewc_convert_batch', [$this, 'ajax_convert_batch']);
		add_action('wp_ajax_ewc_reset', [$this, 'ajax_reset']);

		add_filter('wp_get_attachment_url', [$this, 'filter_attachment_url'], 10, 2);
		add_filter('wp_calculate_image_srcset', [$this, 'filter_srcset']);
		add_filter('the_content', [$this, 'filter_content_images'], 20);
		add_filter('post_thumbnail_html', [$this, 'filter_content_images'], 20);
	}

	public function register_admin_page(): void {
		add_management_page(
			__('Effortless WebP Converter', 'effortless-webp-converter'),
			__('WebP Converter', 'effortless-webp-converter'),
			'manage_options',
			'effortless-webp-converter',
			[$this, 'render_admin_page']
		);
	}

	public function enqueue_admin_assets(string $hook): void {
		if ('tools_page_effortless-webp-converter' !== $hook) {
			return;
		}

		wp_enqueue_style(
			'ewc-admin',
			EWC_URL . 'assets/admin.css',
			[],
			EWC_VERSION
		);

		wp_enqueue_script(
			'ewc-admin',
			EWC_URL . 'assets/admin.js',
			[],
			EWC_VERSION,
			true
		);

		wp_localize_script(
			'ewc-admin',
			'EWC',
			[
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('ewc_admin'),
				'state'   => $this->build_state_payload($this->get_state()),
			]
		);
	}

	public function register_settings(): void {
		register_setting(
			'ewc',
			self::OPTION_SETTINGS,
			[
				'type'              => 'array',
				'sanitize_callback' => [$this, 'sanitize_settings'],
				'default'           => $this->default_settings(),
			]
		);
	}

	public function sanitize_settings($input): array {
		$defaults = $this->default_settings();

		return [
			'batch_size'        => max(1, min(50, absint($input['batch_size'] ?? $defaults['batch_size']))),
			'quality'           => max(40, min(100, absint($input['quality'] ?? $defaults['quality']))),
			'include_png'       => ! empty($input['include_png']) ? 1 : 0,
			'serve_in_content'  => ! empty($input['serve_in_content']) ? 1 : 0,
		];
	}

	public function render_admin_page(): void {
		if (! current_user_can('manage_options')) {
			return;
		}

		$settings = $this->get_settings();
		$state    = $this->get_state();
		$support  = $this->conversion_support();
		?>
		<div class="wrap ewc">
			<h1><?php esc_html_e('Effortless WebP Converter', 'effortless-webp-converter'); ?></h1>
			<p><?php esc_html_e('Convert images to WebP from the dashboard and serve them automatically to supported browsers. Originals stay untouched — your site never breaks.', 'effortless-webp-converter'); ?></p>

			<div class="ewc-grid">

				<div class="ewc-card">
					<h2><?php esc_html_e('Status', 'effortless-webp-converter'); ?></h2>
					<ul class="ewc-stats">
						<li><strong><?php esc_html_e('Total images', 'effortless-webp-converter'); ?>:</strong> <span data-stat="total"><?php echo esc_html((string) $state['total']); ?></span></li>
						<li><strong><?php esc_html_e('Processed', 'effortless-webp-converter'); ?>:</strong> <span data-stat="processed"><?php echo esc_html((string) $state['processed']); ?></span></li>
						<li><strong><?php esc_html_e('Converted', 'effortless-webp-converter'); ?>:</strong> <span data-stat="converted"><?php echo esc_html((string) $state['converted']); ?></span></li>
						<li><strong><?php esc_html_e('Skipped', 'effortless-webp-converter'); ?>:</strong> <span data-stat="skipped"><?php echo esc_html((string) $state['skipped']); ?></span></li>
						<li><strong><?php esc_html_e('Failed', 'effortless-webp-converter'); ?>:</strong> <span data-stat="failed"><?php echo esc_html((string) $state['failed']); ?></span></li>
					</ul>

					<div class="ewc-progress">
						<div class="ewc-progress-bar" data-progress-bar style="width: <?php echo esc_attr((string) $this->progress_percent($state)); ?>%"></div>
					</div>
					<p data-progress-label><?php echo esc_html($this->progress_label($state)); ?></p>

					<div class="ewc-actions">
						<button type="button" class="button button-secondary" data-action="scan"><?php esc_html_e('Scan Library', 'effortless-webp-converter'); ?></button>
						<button type="button" class="button button-primary" data-action="convert"><?php esc_html_e('Start Conversion', 'effortless-webp-converter'); ?></button>
						<button type="button" class="button" data-action="reset"><?php esc_html_e('Reset Report', 'effortless-webp-converter'); ?></button>
					</div>

					<p class="description"><?php esc_html_e('Safe mode: the plugin only creates .webp copies and serves them automatically when available. Original files are not deleted.', 'effortless-webp-converter'); ?></p>
				</div>

			<div class="ewc-card">
					<h2><?php esc_html_e('Settings', 'effortless-webp-converter'); ?></h2>
					<form method="post" action="options.php">
						<?php settings_fields('ewc'); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="webp-batch-size"><?php esc_html_e('Batch size', 'effortless-webp-converter'); ?></label></th>
								<td><input id="webp-batch-size" name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[batch_size]" type="number" min="1" max="50" value="<?php echo esc_attr((string) $settings['batch_size']); ?>" class="small-text" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="webp-quality"><?php esc_html_e('WebP quality', 'effortless-webp-converter'); ?></label></th>
								<td><input id="webp-quality" name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[quality]" type="number" min="40" max="100" value="<?php echo esc_attr((string) $settings['quality']); ?>" class="small-text" /></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('PNG conversion', 'effortless-webp-converter'); ?></th>
								<td><label><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[include_png]" type="checkbox" value="1" <?php checked(1, $settings['include_png']); ?> /> <?php esc_html_e('Generate WebP for PNG uploads too', 'effortless-webp-converter'); ?></label></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e('Hardcoded content images', 'effortless-webp-converter'); ?></th>
								<td><label><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[serve_in_content]" type="checkbox" value="1" <?php checked(1, $settings['serve_in_content']); ?> /> <?php esc_html_e('Swap upload URLs inside post content HTML when a WebP copy exists', 'effortless-webp-converter'); ?></label></td>
							</tr>
						</table>
						<?php submit_button(__('Save Settings', 'effortless-webp-converter')); ?>
					</form>
				</div>

				<div class="ewc-card">
					<h2><?php esc_html_e('Environment', 'effortless-webp-converter'); ?></h2>
					<ul class="ewc-stats">
						<li><strong><?php esc_html_e('Imagick', 'effortless-webp-converter'); ?>:</strong> <?php echo esc_html($support['imagick'] ? __('Available', 'effortless-webp-converter') : __('Unavailable', 'effortless-webp-converter')); ?></li>
						<li><strong><?php esc_html_e('GD WebP', 'effortless-webp-converter'); ?>:</strong> <?php echo esc_html($support['gd'] ? __('Available', 'effortless-webp-converter') : __('Unavailable', 'effortless-webp-converter')); ?></li>
					</ul>
					<?php if (! $support['imagick'] && ! $support['gd']) : ?>
						<p class="notice-inline notice-error"><?php esc_html_e('This server cannot create WebP files yet. Enable Imagick with WebP support or GD with imagewebp().', 'effortless-webp-converter'); ?></p>
					<?php endif; ?>
				</div>

				<div class="ewc-card ewc-log-card">
					<h2><?php esc_html_e('Recent Log', 'effortless-webp-converter'); ?></h2>
					<pre data-log><?php echo esc_html(implode("\n", $state['log'])); ?></pre>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_scan(): void {
		$this->check_ajax_permissions();

		$state = $this->fresh_state();
		$total = $this->count_convertible_attachments();

		$state['total'] = $total;
		$state['log'][] = sprintf(
			/* translators: %d: attachment count */
			__('Scan complete. %d convertible images found.', 'effortless-webp-converter'),
			$total
		);

		$this->update_state($state);
		wp_send_json_success($this->build_state_payload($state));
	}

	public function ajax_convert_batch(): void {
		$this->check_ajax_permissions();

		$state    = $this->get_state();
		$settings = $this->get_settings();

		if (0 === $state['total']) {
			$state['total'] = $this->count_convertible_attachments();
		}

		$attachments = $this->get_attachment_batch($state['offset'], $settings['batch_size']);

		if (empty($attachments)) {
			$state['completed'] = true;
			$state['log'][]     = __('Conversion complete. No more images left in the queue.', 'effortless-webp-converter');
			$this->update_state($state);
			wp_send_json_success($this->build_state_payload($state));
		}

		foreach ($attachments as $attachment) {
			$result = $this->convert_attachment((int) $attachment->ID, $settings);

			$state['processed']++;
			$state['offset']++;

			if ('converted' === $result['status']) {
				$state['converted']++;
			} elseif ('skipped' === $result['status']) {
				$state['skipped']++;
			} else {
				$state['failed']++;
			}

			$state['log'][] = $result['message'];
		}

		$state['log'] = array_slice($state['log'], -40);

		if ($state['processed'] >= $state['total']) {
			$state['completed'] = true;
			$state['log'][]     = __('Conversion complete. Review your pages and keep originals in place for safety.', 'effortless-webp-converter');
		}

		$this->update_state($state);
		wp_send_json_success($this->build_state_payload($state));
	}

	public function ajax_reset(): void {
		$this->check_ajax_permissions();
		$state = $this->fresh_state();
		$this->update_state($state);
		wp_send_json_success($this->build_state_payload($state));
	}

	public function filter_attachment_url(string $url, int $attachment_id): string {
		if (is_admin() || ! $this->browser_supports_webp()) {
			return $url;
		}

		return $this->maybe_swap_url_to_webp($url, $attachment_id);
	}

	public function filter_srcset(array $sources): array {
		if (is_admin() || ! $this->browser_supports_webp()) {
			return $sources;
		}

		foreach ($sources as $width => $source) {
			if (empty($source['url'])) {
				continue;
			}

			$webp_url = $this->url_to_existing_webp($source['url']);

			if ($webp_url) {
				$sources[ $width ]['url'] = $webp_url;
			}
		}

		return $sources;
	}

	public function filter_content_images(string $content): string {
		$settings = $this->get_settings();

		if (is_admin() || ! $settings['serve_in_content'] || ! $this->browser_supports_webp()) {
			return $content;
		}

		$content = preg_replace_callback('/src=(["\'])([^"\']+)\1/i', function (array $matches): string {
			$webp_url = $this->url_to_existing_webp($matches[2]);

			if (! $webp_url) {
				return $matches[0];
			}

			return 'src=' . $matches[1] . $webp_url . $matches[1];
		}, $content);

		$content = preg_replace_callback('/srcset=(["\'])([^"\']+)\1/i', function (array $matches): string {
			$candidates = array_map('trim', explode(',', $matches[2]));

			foreach ($candidates as &$candidate) {
				$parts = preg_split('/\s+/', $candidate);
				$url   = $parts[0] ?? '';

				if (! $url) {
					continue;
				}

				$webp_url = $this->url_to_existing_webp($url);

				if ($webp_url) {
					$parts[0] = $webp_url;
					$candidate = implode(' ', $parts);
				}
			}

			return 'srcset=' . $matches[1] . implode(', ', $candidates) . $matches[1];
		}, $content);

		return $content;
	}

	private function check_ajax_permissions(): void {
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('You do not have permission to run this action.', 'effortless-webp-converter')], 403);
		}

		check_ajax_referer('ewc_admin', 'nonce');
	}

	private function convert_attachment(int $attachment_id, array $settings): array {
		$file = get_attached_file($attachment_id);

		if (! $file || ! file_exists($file)) {
			return [
				'status'  => 'failed',
				'message' => sprintf(__('Attachment %d skipped because the file is missing.', 'effortless-webp-converter'), $attachment_id),
			];
		}

		$mime = get_post_mime_type($attachment_id);

		if ('image/jpeg' !== $mime && (! $settings['include_png'] || 'image/png' !== $mime)) {
			return [
				'status'  => 'skipped',
				'message' => sprintf(__('Attachment %d skipped because its MIME type is not enabled.', 'effortless-webp-converter'), $attachment_id),
			];
		}

		$metadata = wp_get_attachment_metadata($attachment_id);
		$uploads  = wp_upload_dir();
		$paths    = [$file];

		if (! empty($metadata['sizes']) && ! empty($metadata['file'])) {
			$base_dir = trailingslashit(path_join($uploads['basedir'], dirname($metadata['file'])));

			foreach ($metadata['sizes'] as $size) {
				if (! empty($size['file'])) {
					$paths[] = $base_dir . $size['file'];
				}
			}
		}

		$converted = 0;

		foreach (array_unique($paths) as $path) {
			if (! file_exists($path)) {
				continue;
			}

			$target = $path . '.webp';

			if (file_exists($target) && filesize($target) > 0) {
				continue;
			}

			if (! $this->convert_file_to_webp($path, $target, (int) $settings['quality'])) {
				return [
					'status'  => 'failed',
					'message' => sprintf(__('Attachment %d failed during WebP generation for %s.', 'effortless-webp-converter'), $attachment_id, wp_basename($path)),
				];
			}

			$converted++;
		}

		if (0 === $converted) {
			return [
				'status'  => 'skipped',
				'message' => sprintf(__('Attachment %d already had WebP copies for all discovered sizes.', 'effortless-webp-converter'), $attachment_id),
			];
		}

		update_post_meta($attachment_id, '_ewc_generated', current_time('mysql'));

		return [
			'status'  => 'converted',
			'message' => sprintf(__('Attachment %d converted successfully. %d WebP file(s) generated.', 'effortless-webp-converter'), $attachment_id, $converted),
		];
	}

	private function convert_file_to_webp(string $source, string $target, int $quality): bool {
		if (class_exists('Imagick')) {
			try {
				$image = new Imagick();
				$image->readImage($source);
				$image->setImageFormat('webp');
				$image->setImageCompressionQuality($quality);

				if ('image/png' === wp_get_image_mime($source) && method_exists($image, 'setImageBackgroundColor')) {
					$image->setImageBackgroundColor(new ImagickPixel('transparent'));
				}

				$written = $image->writeImage($target);
				$image->clear();
				$image->destroy();

				if ($written && file_exists($target) && filesize($target) > 0) {
					return true;
				}
			} catch (Throwable $e) {
			}
		}

		if (function_exists('imagewebp')) {
			$mime = wp_get_image_mime($source);

			if ('image/jpeg' === $mime && function_exists('imagecreatefromjpeg')) {
				$image = imagecreatefromjpeg($source);
			} elseif ('image/png' === $mime && function_exists('imagecreatefrompng')) {
				$image = imagecreatefrompng($source);
				if ($image) {
					imagepalettetotruecolor($image);
					imagealphablending($image, true);
					imagesavealpha($image, true);
				}
			} else {
				$image = false;
			}

			if ($image) {
				$written = imagewebp($image, $target, $quality);
				imagedestroy($image);

				if ($written && file_exists($target) && filesize($target) > 0) {
					return true;
				}
			}
		}

		return false;
	}

	private function maybe_swap_url_to_webp(string $url, int $attachment_id): string {
		$webp_url = $this->url_to_existing_webp($url);

		if ($webp_url) {
			return $webp_url;
		}

		$file = get_attached_file($attachment_id);

		if ($file) {
			$file_webp = $file . '.webp';
			if (file_exists($file_webp) && filesize($file_webp) > 0) {
				return $url . '.webp';
			}
		}

		return $url;
	}

	private function url_to_existing_webp(string $url): string {
		$uploads = wp_upload_dir();

		if (strpos($url, $uploads['baseurl']) !== 0) {
			return '';
		}

		$relative = ltrim(substr($url, strlen($uploads['baseurl'])), '/');
		$path     = path_join($uploads['basedir'], $relative);
		$target   = $path . '.webp';

		if (file_exists($target) && filesize($target) > 0) {
			return $url . '.webp';
		}

		return '';
	}

	private function count_convertible_attachments(): int {
		global $wpdb;

		$settings = $this->get_settings();
		$mimes    = ['image/jpeg'];

		if ($settings['include_png']) {
			$mimes[] = 'image/png';
		}

		$placeholders = implode(',', array_fill(0, count($mimes), '%s'));
		$sql          = $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ($placeholders)",
			$mimes
		);

		return (int) $wpdb->get_var($sql);
	}

	private function get_attachment_batch(int $offset, int $limit): array {
		$settings = $this->get_settings();
		$mimes    = ['image/jpeg'];

		if ($settings['include_png']) {
			$mimes[] = 'image/png';
		}

		return get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => $mimes,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);
	}

	private function browser_supports_webp(): bool {
		if (empty($_SERVER['HTTP_ACCEPT'])) {
			return false;
		}

		return false !== strpos((string) wp_unslash($_SERVER['HTTP_ACCEPT']), 'image/webp');
	}

	private function conversion_support(): array {
		return [
			'imagick' => class_exists('Imagick'),
			'gd'      => function_exists('imagewebp'),
		];
	}

	private function progress_percent(array $state): int {
		if (empty($state['total'])) {
			return 0;
		}

		return (int) min(100, round(($state['processed'] / $state['total']) * 100));
	}

	private function progress_label(array $state): string {
		if (empty($state['total'])) {
			return __('Run a scan to count convertible images.', 'effortless-webp-converter');
		}

		return sprintf(
			/* translators: 1: processed count, 2: total count */
			__('Processed %1$d of %2$d images.', 'effortless-webp-converter'),
			$state['processed'],
			$state['total']
		);
	}

	private function build_state_payload(array $state): array {
		$state['progress_percent'] = $this->progress_percent($state);
		$state['progress_label']   = $this->progress_label($state);

		return $state;
	}

	private function get_state(): array {
		$state = get_option(self::OPTION_STATE, []);

		return wp_parse_args($state, $this->fresh_state());
	}

	private function update_state(array $state): void {
		update_option(self::OPTION_STATE, $state, false);
	}

	private function get_settings(): array {
		$settings = get_option(self::OPTION_SETTINGS, []);

		return wp_parse_args($settings, $this->default_settings());
	}

	private function default_settings(): array {
		return [
			'batch_size'       => 10,
			'quality'          => 82,
			'include_png'      => 1,
			'serve_in_content' => 1,
		];
	}

	private function fresh_state(): array {
		return [
			'total'     => 0,
			'processed' => 0,
			'converted' => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'offset'    => 0,
			'completed' => false,
			'log'       => [
				__('Ready. Scan the library, then run conversion in safe mode.', 'effortless-webp-converter'),
			],
		];
	}
}
