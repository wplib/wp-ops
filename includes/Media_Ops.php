<?php

namespace WP_Ops;

use WP_Ops;
use WP_Query;
use WP_Post;
use WP_Error;
use WP_Ops\Media;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Media_Ops {
	const IMAGE_TYPES = array(
		IMAGETYPE_GIF,
		IMAGETYPE_JPEG,
		IMAGETYPE_PNG
	);

	private $_uploads_base;

	private $_last_result;

	function delete_images( $args = array() ) {
		$args[ 'images_only' ] = true;
		return $this->_last_result = $this->delete_uploads( $args );
	}

	function delete_uploads( $args = array() ) {
		$args[ 'path' ] = '';
		$args[ 'dirs_only' ] = true;
		$args[ 'uploads_only' ] = true;
		return $this->_last_result = $this->delete_many( $args );
	}

	function delete_many( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'path'         => '',
			'uploads_only' => false,
			'dirs_only'    => false,
		));

		$path = $this->_maybe_make_uploads_path_absolute( $args[ 'path' ] );

		$result = array();

		foreach( new DirectoryIterator( $path ) as $path ) {
			if ( $args[ 'dirs_only' ] && ! $path->isDir() ) {
				continue;
			}
			$dirname = $path->getFilename();
			if ( '.' === $dirname[ 0 ] ) {
				continue;
			}
			if ( $args[ 'uploads_only' ] && ! $this->_is_uploads_path( $dirname ) ) {
				continue;
			}
			$filepath = $path->getPathname();
			$result[ $filepath ] = $this->delete_path( $filepath, $args );
		}
		return $this->_last_result = $result;
	}

	function delete_path( $path, $args = array() ) {
		$args = wp_parse_args($args, array(
			'images_only'    => false,
		));
		$path = $this->_maybe_make_uploads_path_absolute( $path );
		$iterator = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );
		foreach( $files as $file ) {
			$filepath = $file->getRealPath();
			if ( $file->isDir() ) {
				@rmdir( $filepath );
			} elseif ( $args[ 'images_only' ] || ! $this->_is_image_file( $dirname ) ) {
				@unlink( $filepath );
			} else {
				continue;
			}
			$result[ $filepath ] = new Media( $filepath );
		}
		@rmdir( $path );
		return $this->_last_result = $result;
	}

	/**
	 * @param array[] $image_arr
	 *
	 * @example:
	 *
	 *      $image_arr = [
	 *           [ 'count',  'path',     'type',  'background',  'size',   'filename' ],
	 *           [    2,    '2018/08',   'png',    '#000000',   '100x100',   'black' ],
	 *           [    5,    '2018/08',   'gif',    '#ffffff',    '50x50',    'white' ],
	 *           [    9,    '2018/08',   'jpg',    '#ff0000',   '250x250',   'red' ],
	 *           [    3,    '2018/08',   'png',    '#00ff00',   '500x500',   'lime' ],
	 *       ]
	 *
	 * @return Media[]
	 */
	function create_images_from( $image_arr ) {
		$image_objs = WP_Ops::transform_test_data( $image_arr );
		$image_files = array();
		foreach( $image_objs as $image_obj ) {
			for( $index = 0; $index < $image_obj->count; $index++ ) {

				$filepath = "{$image_obj->path}/{$image_obj->filename}";
				if ( 0 < $index ) {
					$filepath .= "-{$index}";
				}
				$filepath .= ".{$image_obj->type}";

				$image = $this->create_image( $filepath, (array)$image_obj );
				$image_files[ $image->uploads_filepath() ] = $image;

			}
		}
		return $this->_last_result = $image_files;
	}

	/**
	 * @param string $filepath
	 * @param array $args
	 *
	 * @return Media
	 */
	function create_image( $filepath, $args = array() ) {

		do {

			$filepath = $this->_maybe_make_uploads_path_absolute( $filepath );

			$args = wp_parse_args($args, array(
				'type'       => 'png',
				'height'     => 100,
				'width'      => 100,
				'size'       => null,
				'background' => '#00000',
				'color'      => '#fffff',
				'font'       => 1,
				'x'          => 0,
				'y'          => 0,
				'text'       => null,
			));

			if ( ! is_null( $args[ 'size' ] ) ) {
				list( $args[ 'height' ], $args[ 'width' ] ) = explode( 'x', $args[ 'size' ] );
			}

			if ( ! $image = @imagecreate( $args[ 'height' ], $args[ 'width' ] ) ) {
				break;
			}
			list( $red, $green, $blue ) = $this->_parse_colors( $args[ 'background' ] );

			/**
			 * Set background with first call.
			 */
			imagecolorallocate( $image, $red, $green, $blue );

			if ( ! is_null( $args[ 'text' ] ) ) {
				list( $red, $green, $blue ) = $this->_parse_colors( $args[ 'color' ] );
				$text_color = imagecolorallocate( $image, $red, $green, $blue);
				imagestring($im, $args[ 'font' ], $args[ 'x' ], $args[ 'y' ],  $args[ 'text' ], $text_color );
			}

			@mkdir( dirname( $filepath ), 0777, $recursive = true );

			switch ( $args[ 'type' ] ) {
				case 'gif':
					imagegif( $image, $filepath );
					break;
				case 'jpg':
				case 'jpeg':
					imagejpeg( $image, $filepath );
					break;
				case 'png':
				default:
					imagepng( $image, $filepath );
					break;
			}
			imagedestroy( $image );

		} while ( false );

		return $this->_last_result = new Media( $filepath );

	}

	/**
	 * @param string|string[] $filepaths
	 * @param array $args
	 * @return Media[]
	 */
	function import_many( $medias, $args = array() ) {
		$results = array();
		foreach( $medias as $media ) {
			$media = $this->import( $media, $args );
			$results[ $media->uploads_filepath() ] = $media;
		}
		return $results;
	}

	/**
	 * @param Media|string $media
	 * @param array $args
	 */
	function import( $media, $args = array() ) {

		$args = wp_parse_args( $args, array(
			'post_id'           => false,
			'title'             => null,
			'caption'           => null,
			'alt'               => null,
			'desc'              => null,
			'skip-copy'         => false,
			'preserve-filetime' => false,
			'featured-image'    => false,
		));

		if ( $args[ 'post_id' ] && ! get_post( $args[ 'post_id' ] ) ) {
			WP_Ops::add_error( 'Invalid post_id. Cannot import.' );
		}

		do {

			$media = self::normalize_media( $media );

			$filepath = $media->filepath();

			if ( ! is_file( $filepath ) ) {
				WP_Ops::add_error( sprintf(
					"File '%s' does not exist. Cannot import.",
					$filepath
				));
				continue;
			}

			$temp_file = ! $args[ 'skip-copy' ]
				? $this->_copy_file( $filepath )
				: $filepath;

			$file_time = $args[ 'preserve-filetime' ]
				? @filemtime( $filepath )
				: null;

			$file_arr = array(
				'tmp_name' => $temp_file,
				'name'     => $this->_basename( $filepath ),
			);

			$post_arr = array(
				'post_title'   => $args[ 'title' ],
				'post_excerpt' => $args[ 'caption' ],
				'post_content' => $args[ 'desc' ],
			);

			if ( ! is_null( $file_time ) ) {
				$gmt_offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
				$gmtdate = gmdate( 'Y-m-d H:i:s', $file_time + $gmt_offset );
				$date = gmdate( 'Y-m-d H:i:s', $file_time );
				$post_arr = array_merge( $post_arr, array(
					'post_date'         => $date,
					'post_date_gmt'     => $gmtdate,
					'post_modified'     => $date,
					'post_modified_gmt' => $gmtdate,
				));
			}

			$post_arr = wp_slash( $post_arr );

			/**
			 * Use image exif/iptc data for title and caption
			 */
			do {

				if ( ! $post_arr[ 'post_title' ] && ! empty( $post_arr[ 'post_excerpt' ] ) ) {
					break;
				}

				$image_meta = @wp_read_image_metadata( $temp_file );

				if ( empty( $image_meta ) ) {
					break;
				}

				foreach( [ 'title:post_title', 'caption:post_excerpt' ] as $fields ) {
					list( $image_field, $post_field ) = explode( ':', $fields );
					if ( ! empty( $post_arr[ $post_field ] ) ) {
						continue;
					}
					if ( empty( $image_meta[ $image_field ] ) ) {
						continue;
					}
					$post_arr[ $post_field ] = $image_meta[ $image_field ];
				}

			} while ( false );

			if ( empty( $post_array[ 'post_title' ] ) ) {
				$post_array[ 'post_title' ] = preg_replace( '/\.[^.]+$/', '', $this->_basename( $filepath ) );
			}

			/**
			 * Import the attachment
			 */
			do {

				$error_msg = null;

				if ( ! $args[ 'skip-copy' ] ) {

					$attachment_id = media_handle_sideload(
						$file_arr,
						$args[ 'post_id' ],
						$args[ 'title' ],
						$post_arr
					);

					if ( is_wp_error( $attachment_id ) ) {
						$error_msg = "Unable to import file '%s'. Reason: %s";
					}
					break;

				}

				$wp_filetype = wp_check_filetype( $filepath, null );
				$post_arr[ 'post_mime_type' ] = $wp_filetype[ 'type' ];
				$post_arr[ 'post_status' ] = 'inherit';

				$attachment_id = wp_insert_attachment( $post_arr, $filepath, $args[ 'post_id' ] );
				if ( is_wp_error( $attachment_id ) ) {
					$error_msg = "Unable to insert file '%s'. Reason: %s";
					break;
				}

				wp_update_attachment_metadata(
					$attachment_id,
					wp_generate_attachment_metadata( $attachment_id, $filepath )
				);

			} while ( false );

			if ( empty( $error_msg ) ) {
				$media->set_attachment_id( $attachment_id );
			} else {
				WP_Ops::add_error( sprintf(
					$error_msg,
					$filepath,
					implode( ', ', $result->get_error_messages() )
				));
				continue;
			}

			if ( $args[ 'alt' ] ) {
				update_post_meta(
					$attachment_id,
					'_wp_attachment_image_alt',
					wp_slash( $args[ 'alt' ] )
				);
			}

			/**
			 * Set as featured image
			 */
			do {
				if ( ! $args[ 'post_id' ] ) {
					break;
				}
				if ( ! $args[ 'featured-image' ] ) {
					break;
				}
				update_post_meta( $args['post_id'], '_thumbnail_id', $attachment_id );
			} while ( false );

			$message = "Imported file [%s] as attachment ID %d";
			if ( $args['post_id'] ) {
				$message .= sprintf( " and attached to post %d", $args[ 'post_id' ] );
				if ( $args[ 'featured-image' ] ) {
					$message .= ' as featured image';
				}
			}

			$media = new Media( $filepath );
			WP_Ops::logger()->log(
				"{$message}.\n",
				$media->uploads_filepath(),
				$attachment_id
			);

		} while ( false );
		return $this->_last_result = $media;
	}

	/**
	 * @param string $path
	 *
	 * @return bool
	 */
	private function _is_uploads_path( $path ) {
		do {
			$is_uploads_path = false;
			if ( 4 !== strlen( $path ) ) {
				continue;
			}
			if ( date( 'Y' ) < $path ) {
				continue;
			}
			if ( '1980' > $path ) {
				continue;
			}
			$is_uploads_path = true;

		} while ( false );

		return $is_uploads_path;

	}

	/**
	 * @param string|string[] $filepaths
	 * @param array $args
	 *
	 */
	private function _parse_colors( $colors ) {
		$colors = array_map( 'hexdec', str_split( trim( $colors, '#' ), 2 ) );
		$decimals = array();
		foreach( [ 'red', 'green', 'blue' ] as $index => $color ) {
			$colors[ $color ] = $colors[ $index ];
		}
		return $colors;
	}

	private function _copy_file( $filepath ) {
		$temp_file = wp_tempnam();
		$ext = pathinfo( $filepath, PATHINFO_EXTENSION );
		$temp_file = dirname( $temp_file ) . $this->_basename( $temp_file, 'tmp' ) . ".{$ext}";
		if ( ! copy( $filepath, $temp_file ) ) {
			WP_Ops::add_error( sprintf( 'Could not create temporary file in for %s.', $filepath ) );
		}
		return $temp_file;
	}

	private function _basename( $path, $suffix = '' ) {
		return urldecode( \basename( str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ), $suffix ) );
	}

	private function _maybe_make_uploads_path_absolute( $path ) {
		do {
			$dir_sep = DIRECTORY_SEPARATOR;
			if ( $dir_sep === ( $path[ 0 ] ?? null ) ) {
				break;
			}
			if ( '\\' === $dir_sep && ':' === ( $path[ 1 ] ?? null ) ) {
				break;
			}
			$path = $this->get_uploads_basedir( $path );
		} while ( false );
		return $path;
	}

	function uploads_basedir() {
		return $this->get_uploads_basedir( '' );
	}

	function get_uploads_basedir( $path = '' ) {
		return $this->get_uploads_base( 'dir', $path );
	}

	function uploads_baseurl() {
		return $this->get_uploads_baseurl( '' );
	}

	function get_uploads_baseurl( $path = '' ) {
		return $this->get_uploads_base( 'url', $path );
	}

	/**
	 * @param string $type 'dir' or 'url'
	 * @param string $path
	 *
	 * @return string
	 */
	function get_uploads_base( $type, $path = '' ) {
		$dir_sep = DIRECTORY_SEPARATOR;
		do {
			if ( ! preg_match( '#^(dir|url)$#', $type ) ) {
				trigger_error( sprintf(
					"%s() called with %s as first parameter: expects 'dir' or 'url'.",
					__METHOD__,
					$type
				));
				die(1);
			}
			if ( isset( $this->_uploads_base[ $type ] ) ) {
				break;
			}
			$upload_dir = wp_upload_dir();
			$this->_uploads_base[ 'dir' ] = $upload_dir[ 'basedir' ];
			$this->_uploads_base[ 'url' ] = $upload_dir[ 'baseurl' ];
		} while ( false );
		$path = ltrim( $path, $dir_sep );
		$base = rtrim( "{$this->_uploads_base[ $type ]}{$dir_sep}{$path}", $dir_sep );
		return $base;
	}

	private function _is_image_file( $path ) {
		do {
			$is_image_file = false;
			if ( ! is_file( $path ) ) {
				break;
			}
			if ( ! $result = getimagesize( $path ) ) {
				break;
			}
			if ( ! isset( $result[ 2 ] ) ) {
				break;
			}
			if ( ! isset( $result[ 'mime' ] ) ) {
				break;
			}
			if ( ! preg_match( '#^image/(.+)$#', $result[ 'mime' ], $match ) ) {
				break;
			}
			$is_image_file = in_array( $result[ 2 ], self::IMAGE_TYPES );
		} while ( false );
		return $is_image_file;
	}

	static function normalize_media( $media ) {
		do {
			global $wpdb;
			if ( is_string( $media ) ) {
				$media = new WP_Ops\Media( $media );
				break;
			}
			if ( is_object( $media ) ) {
				break;
			}
			if ( ! is_numeric( $media ) ) {
				break;
			}
			$sql = "SELECT guid FROM {$wpdb->posts} WHERE post_type='attachment' AND ID=%d";
			$filepath = $wpdb->get_var( $wpdb->prepare( $sql, $media ) );
			$media = new WP_Ops\Media( $filepath );
		} while ( false );
		return $media;
	}

}

