<?php

namespace WPEmergeTheme\Assets;

use Theme\Path;

class Assets {
	/**
	 * Bundle manifest.
	 *
	 * @var array|null
	 */
	protected static $manifest = null;

	/**
	 * Remove the protocol from an http/https url.
	 *
	 * @param  string $url
	 * @return string
	 */
	protected function removeProtocol( $url ) {
		return preg_replace( '~^https?:~i', '', $url );
	}

	/**
	 * Get if a url is external or not.
	 *
	 * @param  string  $url
	 * @return boolean
	 */
	protected function isExternalUrl( $url, $home_url ) {
		$delimiter = '~';
		$regex_quoted_home_url = preg_quote( $home_url, $delimiter );
		$regex = $delimiter . '^' . $regex_quoted_home_url . $delimiter . 'i';
		return ! preg_match( $regex, $url );
	}

	/**
	 * Generate a version for a given asset src.
	 *
	 * @param  string $src
	 * @return string
	 */
	protected function generateFileVersion( $src ) {
		// Normalize both URLs in order to avoid problems with http, https
		// and protocol-less cases
		$src = $this->removeProtocol($src);
		$home_url = $this->removeProtocol( site_url( '/' ) );
		$version = false;

		if ( ! $this->isExternalUrl( $src, $home_url ) ) {
			# Generate the absolute path to the file
			$file_path = str_replace(
				[$home_url, '/'],
				[ABSPATH, DIRECTORY_SEPARATOR],
				$src
			);

			# Check if the given file really exists
			if ( file_exists( $file_path ) ) {
				# Use the last modified time of the file as a version
				$version = filemtime( $file_path );
			}
		}

		# Return version
		return $version;
	}

	/**
	 * Get the public URI to the current theme directory root.
	 *
	 * @return string
	 */
	public function getThemeUri() {
		$template_uri = get_template_directory_uri();
		$template_uri = preg_replace( '~/theme/?$~', '', $template_uri );
		return $template_uri;
	}

	/**
	 * Get the path to a versioned bundle relative to the theme directory.
	 *
	 * @param  string $path Asset path.
	 * @return string
	 */
	public function getBundlePath( $path ) {
		if ( is_null( static::$manifest ) ) {
			$manifest_path = Path::normalize( WPMT_THEME_DIR . 'dist/manifest.json' );

			if ( file_exists( $manifest_path ) ) {
				static::$manifest = json_decode( file_get_contents( $manifest_path ), true );
			} else {
				static::$manifest = array();
			}
		}

		$path = isset( static::$manifest[ $path ] ) ? static::$manifest[ $path ] : $path;

		return '/dist/' . $path;
	}

	/**
	 * Enqueue a style, dynamically generating a version for it.
	 *
	 * @param  string   $handle
	 * @param  string   $src
	 * @param  string[] $dependencies
	 * @param  string   $media
	 * @return void
	 */
	public function enqueueStyle( $handle, $src, $dependencies = [], $media = 'all' ) {
		wp_enqueue_style( $handle, $src, $dependencies, $this->generateFileVersion( $src ), $media );
	}

	/**
	 * Enqueue a script, dynamically generating a version for it.
	 *
	 * @param  string    $handle
	 * @param  string    $src
	 * @param  stringp[] $dependencies
	 * @param  boolean   $in_footer
	 * @return void
	 */
	public function enqueueScript( $handle, $src, $dependencies = [], $in_footer = false ) {
		wp_enqueue_script( $handle, $src, $dependencies, $this->generateFileVersion( $src ), $in_footer );
	}

	/**
	 * Add favicon meta.
	 *
	 * @return void
	 */
	public function addFavicon() {
		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			// allow users to override the favicon using the WordPress Customizer
			return;
		}

		# Theme and favicon URI
		$theme_uri = $this->getThemeUri();
		$favicon_uri = apply_filters( 'wpmt_theme_favicon_uri', $theme_uri . '/dist/images/favicon.ico' );

		# Determine version based on file modified time.
		# If the $version is false, the file does not exist
		$version = $this->generateFileVersion( $favicon_uri );

		# Display the favicon only if it exists
		if ( $version ) {

			# Add the version string to the favicon URI
			$favicon_uri = add_query_arg( 'ver', $version, $favicon_uri );

			echo '<link rel="shortcut icon" href="' . $favicon_uri . '" />' . "\n";
		}
	}
}
