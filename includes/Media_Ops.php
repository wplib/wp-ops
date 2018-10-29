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
	const URL_ATTACH_TYPE = 'url';
	const FILE_ATTACH_TYPE = 'file';

	const IMAGE_TYPES = array(
		IMAGETYPE_GIF,
		IMAGETYPE_JPEG,
		IMAGETYPE_PNG
	);

	/**
	 * @var string[]
	 */
	private $_uploads_base;

	/**
	 * @var mixed
	 */
	private $_last_result;

	/**
	 * @param array $args {
	 *      @type string $path
	 *      @type bool $uploads_only
	 *      @type bool $dirs_only
	 * }
	 * @return null[]|Media[]
	 */
	function delete_images( $args = array() ) {
		$args = Util::parse_args( $args );
		$args[ 'images_only' ] = true;
		return $this->_last_result = $this->delete_uploads( $args );
	}

	/**
	 * @param array $args {
	 *      @type string $path
	 *      @type bool $uploads_only
	 *      @type bool $dirs_only
	 * }
	 * @return null[]|Media[]
	 */
	function delete_uploads( $args = array() ) {
		$args[ 'path' ] = '';
		$args[ 'dirs_only' ] = true;
		$args[ 'uploads_only' ] = true;
		return $this->_last_result = $this->delete_many( $args );
	}

	/**
	 * @param array $args {
	 *      @type string $path
	 *      @type bool $uploads_only
	 *      @type bool $dirs_only
	 * }
	 * @return null[]|Media[]
	 */
	function delete_many( $args = array() ) {
		$args = Util::parse_args( $args, array(
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

	/**
	 * @param string $path
	 * @param array $args {
	 *      @type bool $images_only
	 *      @type bool $check_guid
	 * }
	 *
	 * @return Media|null
	 */
	function delete_path( $path, $args = array() ) {
		$args = Util::parse_args($args, array(
			'images_only' => false,
			'check_guid'  => true,
		));
		$path = $this->_maybe_make_uploads_path_absolute( $path );
		$iterator = new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new RecursiveIteratorIterator( $iterator, RecursiveIteratorIterator::CHILD_FIRST );
		$dirpaths = array();
		$result = null;
		foreach( $files as $file ) {
			$filepath = $file->getRealPath();
			if ( $file->isDir() ) {
				$dirpaths[] = $filepath;
				continue;
			}
			if ( $args[ 'images_only' ] && ! $this->_is_image_file( $filepath ) ) {
				continue;
			}
			$media = new Media( $filepath );
			if ( $args[ 'check_guid' ] && WP_Ops::post()->find_by( 'guid', $media->url() ) ) {
				continue;
			}
			@unlink( $filepath );
			$result[ $filepath ] = $media;
		}
		foreach( $dirpaths as $dirpath ) {
			@rmdir( $dirpath );
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

			$args = Util::parse_args($args, array(
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
	function attach_many( $medias, $args = array() ) {
		$results = array();
		foreach( $medias as $media ) {
			$media = $this->attach( $media, $args );
			$results[ $media->uploads_filepath() ] = $media;
		}
		return $results;
	}

	/**
	 * @param Media|string $media
	 * @param array $args
	 */
	function attach( $media, $args = array() ) {

		do {

			$args = Util::parse_args( $args, array(
				'post_id'           => false,
				'featured_image'    => false,
				'log_status'        => true,
				'post_slug'         => null,
				'attach_type'       => Media_Ops::FILE_ATTACH_TYPE,
			));

			if ( $args[ 'post_id' ] && ! get_post( $args[ 'post_id' ] ) ) {
				WP_Ops::logger()->log( 'Invalid post_id. Cannot import.' );
				break;
			}

			$media = $this->normalize_media( $media );

			switch ( $args[ 'attach_type' ] ) {
				case Media_Ops::FILE_ATTACH_TYPE:
					$attachments = WP_Ops::post()->find_by( 'guid', $media->url() );
					break;
				case Media_Ops::URL_ATTACH_TYPE:
					$attachments = WP_Ops::post()->find_by( 'attached_file', $media->uploads_filepath() );
					$regex = '#^' . preg_quote( $this->get_uploads_baseurl() ) . '#';
					foreach( $attachments as $index => $attachment ) {
						if ( ! $attachment = get_post( $attachment->post_id() ) ) {
							continue;
						}
						if ( ! preg_match( $regex, $attachment->guid ) ) {
							/**
							 * Attachments web page URLs should not match
							 * the base uploads URL so keep them
							 */
							continue;
						}
						/**
						 * For URLs that DO match the base base uploads URL
						 * they are Media_Ops::FILE_ATTACH_TYPE so remove.
						 */
						unset( $attachments[ $index ] );
					}
					break;
			}

			if ( 0 < count( $attachments ) ) {
				WP_Ops::logger()->log(
					"NOTE: Media [%s] already as attached as attachment ID %d.\n",
					$media->uploads_filepath(),
					$attachment_id = key( $attachments )
				);
			} else if ( is_null( $attachment_id = $this->insert_attachment( $media, $args ) ) ) {
				break;
			}

			$media->set_attachment_id( $attachment_id );

			/**
			 * Set as featured image
			 */
			do {

				if ( ! $args[ 'post_id' ] ) {
					break;
				}
				if ( ! $args[ 'featured_image' ] ) {
					break;
				}
				update_post_meta( $args['post_id'], '_thumbnail_id', $attachment_id );

			} while ( false );

			if ( $args[ 'log_status' ] ) {

				$message = "Attachment ID %d";
				if ( $args[ 'post_id' ] ) {
					$message .= sprintf( " for post ID %d", $args[ 'post_id' ] );
					$message .= $args[ 'featured_image' ]
						? ' set to featured image [%s]'
						: ' attached image [%s]';
					$message .= sprintf( " (post slug is '%s')", $args[ 'post_slug' ] );

				}
				WP_Ops::logger()->log( "{$message}.\n", $attachment_id, $media->uploads_filepath() );

			}

		} while ( false );

		return $this->_last_result = $media;

	}

	/**
	 * @param Media|string $media
	 * @param array $args
	 * @return int|null
	 */
	function insert_attachment( $media, $args = array() ) {

		do {

			$attachment_id = null;

			$args = Util::parse_args( $args, array(
				'post_id'           => false,
				'title'             => null,
				'caption'           => null,
				'alt'               => null,
				'desc'              => null,
				'attach_type'       => Media_Ops::FILE_ATTACH_TYPE,
				'preserve_filetime' => false,
				'prefer_exif'       => false,
			));

			if ( $args[ 'post_id' ] && ! get_post( $args[ 'post_id' ] ) ) {
				WP_Ops::logger()->log( 'Invalid post_id. Cannot import.' );
				break;
			}

			$filepath = $media->filepath();

			if ( ! is_file( $filepath ) ) {
				WP_Ops::logger()->log(  "File '%s' does not exist. Cannot import.", $filepath );
				break;
			}

			$file_time = $args[ 'preserve_filetime' ]
				? @filemtime( $filepath )
				: null;

			$post_arr = array(
				'post_title'   => $args[ 'title' ],
				'post_excerpt' => $args[ 'caption' ],
				'post_content' => $args[ 'desc' ],
			);

			/**
			 * Extract image exif/iptc data for title and caption
			 */
			list( $title, $excerpt ) = $this->_extract_title_excerpt( $filepath, $post_arr );
			if ( $args[ 'prefer_exif' ] ) {
				if ( ! empty ( $title ) ) {
					$post_arr[ 'post_title' ] = $title;
				}
				if ( ! empty ( $excerpt ) ) {
					$post_arr[ 'post_excerpt' ] = $excerpt;
				}
			} else {
				if ( empty ( $post_arr[ 'post_title' ] ) ) {
					$post_arr[ 'post_title' ] = $title;
				}
				if ( empty ( $post_arr[ 'post_excerpt' ] ) ) {
					$post_arr[ 'post_excerpt' ] = $excerpt;
				}
			}

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

			if ( empty( $post_arr[ 'post_title' ] ) ) {
				$post_arr[ 'post_title' ] = preg_replace( '/\.[^.]+$/', '', $this->_basename( $filepath ) );
			}

			/**
			 * Import the attachment
			 */
			switch ( $args[ 'attach_type' ] ) {
				case Media_Ops::FILE_ATTACH_TYPE:
					$attachment_id = $this->_insert_attachment_as_file(
						$media,
						$args[ 'post_id' ],
						$post_arr
					);
					break;

				case Media_Ops::URL_ATTACH_TYPE:
					$attachment_id = $this->_insert_attachment_as_url(
						$media,
						$args[ 'post_id' ],
						$post_arr
					);
					break;
			}

			if ( $args[ 'alt' ] ) {
				update_post_meta(
					$attachment_id,
					'_wp_attachment_image_alt',
					wp_slash( $args[ 'alt' ] )
				);
			}

		} while ( false );


		return $this->_last_result = $attachment_id;

	}

	/**
	 * Attaches media as a URL, e.g. guid = URL is a web page for the attachment
	 *
	 * @param Media $media
	 * @param int $post_id
	 * @param array $post_arr
	 *
	 * @return int|null
	 */
	private function _insert_attachment_as_url( $media, $parent_id, $post_arr ) {
		do {
			$filepath = $media->filepath();

			$wp_filetype = wp_check_filetype( $filepath, null );
			$post_arr[ 'post_mime_type' ] = $wp_filetype[ 'type' ];
			$post_arr[ 'post_status' ] = 'inherit';

			$attachment_id = wp_insert_attachment( $post_arr, $filepath, $parent_id );
			if ( is_wp_error( $attachment_id ) ) {
				WP_Ops::logger()->log(
					"Unable to insert file '%s'. Reason: %s",
					$filepath,
					implode( ', ', $attachment_id->get_error_messages() )
				);
				$attachment_id = null;
				break;
			}

			wp_update_attachment_metadata(
				$attachment_id,
				wp_generate_attachment_metadata( $attachment_id, $filepath )
			);

		} while ( false );

		return $this->_last_result = $attachment_id;

	}

	/**
	 * Attaches media as a file, e.g. guid = actual file URL
	 *
	 * @param Media $media
	 * @param int $post_id
	 * @param array $post_arr
	 * @param array $args {
	 *      @type bool $new_filename
	 * }
	 *
	 * @return int|null
	 */
	private function _insert_attachment_as_file( $media, $parent_id, $post_arr, $args = array() ) {
		do {
			$attachment_id = null;

			$args = wp_parse_args( $args, array(
				'skip_copy'   => false,
				'new_filename' => false,
			));

			$filepath = $media->filepath();

			$temp_file = ! $args[ 'skip_copy' ]
				? $this->_copy_file( $filepath )
				: $filepath;

			$file_arr = array(
				'tmp_name' => $temp_file,
				'name'     => $this->_basename( $filepath ),
			);

			if ( ! $args[ 'new_filename' ] ) {
				add_filter( 'upload_dir', $hook1 = function ( $uploads_dir ) use ( $media ) {
					$uploads_dir[ 'path' ]   = $media->dirpath();
					$uploads_dir[ 'url' ]    = $media->url_path();
					$uploads_dir[ 'subdir' ] = preg_replace(
						'#^' . preg_quote( $uploads_dir[ 'baseurl' ] ) . '(.+)$#',
						'$1',
						$media->url_path()
					);

					return $uploads_dir;
				} );

				add_filter( 'wp_unique_filename', $hook2 = function ( $filename ) use ( $media ) {
					return $media->basename();
				} );
			}

			$attachment_id = media_handle_sideload(
				$file_arr,
				$parent_id,
				$post_arr[ 'post_title' ],
				$post_arr
			);

			if ( ! $args[ 'new_filename' ] ) {
				remove_filter( 'wp_unique_filename', $hook2 );
				remove_filter( 'upload_dir', $hook1 );
			}

			if ( is_wp_error( $attachment_id ) ) {
				WP_Ops::logger()->log(
					"Unable to import file '%s'. Reason: %s",
					$media->filepath(),
					implode( ', ', $attachment_id->get_error_messages() )
				);
				$attachment_id = null;
				break;
			}

		} while ( false );
		return $attachment_id;
	}

	/**
	 * Use image exif/iptc data for title and caption
	 *
	 * @param string $filepath
	 * @param array $post_arr
	 * @return array[]
	 */
	function _extract_title_excerpt( $filepath, $post_arr ) {
		do {
			$post_arr = wp_parse_args( $post_arr, array(
				'post_title'   => null,
				'post_excerpt' => null,
			));

			$image_meta = @wp_read_image_metadata( $filepath );

			if ( empty( $image_meta ) ) {
				break;
			}

			$title = empty( $image_meta[ 'title' ] )
				? $post_arr[ 'post_title' ]
				: $image_meta[ 'title' ];

			$caption = empty( $image_meta[ 'caption' ] )
				? $post_arr[ 'post_excerpt' ]
				: $image_meta[ 'caption' ];

		} while ( false );
		return [ $title, $caption ];
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
			WP_Ops::logger()->log( 'Could not create temporary file in for %s.', $filepath );
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

	/**
	 * @param Media $media
	 *
	 * @return Media
	 */
	function normalize_media( $media ) {
		do {
			global $wpdb;
			if ( is_string( $media ) ) {
				$media = new Media( $media );
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
			$media = new Media( $filepath );
		} while ( false );
		return $media;
	}

	/**
	 * @param string $url
	 *
	 * @return string|null
	 */
	function extract_uploads_path( $url ) {
		$regex = '#^' . preg_quote( $this->base_uploads_url() ) . '(.+)$#';
		$regex = preg_replace( '~#\^https?\\\\://~', '#^https?\\://', $regex );
		return preg_match( $regex, $url, $match )
			? $match[ 1 ]
			: null;
	}

	/**
	 * @return string
	 */
	function base_uploads_url() {
		return $this->get_base_uploads_url( '' );
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	function get_base_uploads_url( $path ) {
		$uploads_dir = wp_upload_dir();
		$path = ! empty( $path )
			? '/' . ltrim( $path, '/' )
			: '';
		return "{$uploads_dir[ 'baseurl' ]}{$path}";
	}



}

