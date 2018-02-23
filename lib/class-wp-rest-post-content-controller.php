<?php
/**
 * Shortcode Blocks REST API: WP_REST_Post_Content_Controller class
 *
 * @package gutenberg
 * @since 2.0.0
 */

/**
 * Controller which provides a REST endpoint for Gutenberg to preview shortcode blocks.
 *
 * @since 2.0.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Post_Content_Controller extends WP_REST_Controller {
	/**
	 * Constructs the controller.
	 *
	 * @since 2.0.0
	 * @access public
	 */
	public function __construct() {
		// @codingStandardsIgnoreLine - PHPCS mistakes $this->namespace for the namespace keyword
		$this->namespace = 'gutenberg/v1';
		$this->rest_base = 'content';
	}

	/**
	 * Registers the necessary REST API routes.
	 *
	 * @since 0.10.0
	 * @access public
	 */
	public function register_routes() {
		// @codingStandardsIgnoreLine - PHPCS mistakes $this->namespace for the namespace keyword
		$namespace = $this->namespace;

		register_rest_route( $namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_output' ),
				'permission_callback' => array( $this, 'get_output_permissions_check' ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Checks if a given request has access to read content_output blocks.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_output_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'gutenberg_block_cannot_read',
				__(
					'Sorry, you are not allowed to read content blocks as this user.',
					'gutenberg'
				),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Filters content through hooks.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_output( $request ) {
		global $post;
		global $wp_embed;
		$output  = '';
		$args    = $request->get_params();
		$post    = isset( $args['postId'] ) ? get_post( $args['postId'] ) : null;
		$content = isset( $args['content'] ) ? trim( $args['content'] ) : '';

		// Initialize $data.
		$data = array(
			'html' => $output,
		);

		if ( empty( $content ) ) {
			$data['html'] = __( 'Enter something to preview', 'gutenberg' );
			return rest_ensure_response( $data );
		}

		if ( ! empty( $post ) ) {
			setup_postdata( $post );
		}

		$output = apply_filters( 'the_content', $content );

		if ( empty( $output ) ) {
			$data['html'] = __( 'Sorry, couldn\'t render a preview', 'gutenberg' );
			return rest_ensure_response( $data );
		}

		$data = array(
			'html' => $this->surround_with_theme( $output ),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Surrounds content with theme to cope with JS & Stylesheet issues
	 *
	 * @since 3.0.0
	 * @access private
	 *
	 * @param string $content content to be surrounded by theme.
	 * @return string content surrounded by theme header & footer
	 */
	private function surround_with_theme( $content ) {
		ob_start();
?>
<!DOCTYPE html>
<html>
	<head>
		<?php wp_head(); ?>
		<style>
			body {
				margin: 0;
			}
			body,
			body > div,
			body > div > iframe {
				width: 100%;
				height: 100%;
			}

			body > div > * {
				margin-top: 0 !important;	/* has to have !important to override inline styles */
				margin-bottom: 0 !important;
			}
		</style>
	</head>
	<body data-resizable-iframe-connected="data-resizable-iframe-connected" className="sandboxContent">
		<div id="content">
			<?php echo $content; ?>
		</div>
		<?php wp_footer(); ?>
		<script>
		( function() {
				var observer;

				if ( ! window.MutationObserver || ! document.body || ! window.parent ) {
					return;
				}

				function sendResize() {
					var clientBoundingRect = document.body.getBoundingClientRect();
					window.parent.postMessage( {
						action: 'resize',
						width: clientBoundingRect.width,
						height: ${ heightCalculation }
					}, '*' );
				}

				observer = new MutationObserver( sendResize );
				observer.observe( document.body, {
					attributes: true,
					attributeOldValue: false,
					characterData: true,
					characterDataOldValue: false,
					childList: true,
					subtree: true
				} );

				window.addEventListener( 'load', sendResize, true );

				// Hack: Remove viewport unit styles, as these are relative
				// the iframe root and interfere with our mechanism for
				// determining the unconstrained page bounds.
				function removeViewportStyles( ruleOrNode ) {
					[ 'width', 'height', 'minHeight', 'maxHeight' ].forEach( function( style ) {
						if ( /^\\d+(vmin|vmax|vh|vw)$/.test( ruleOrNode.style[ style ] ) ) {
							ruleOrNode.style[ style ] = '';
						}
					} );
				}

				Array.prototype.forEach.call( document.querySelectorAll( '[style]' ), removeViewportStyles );
				Array.prototype.forEach.call( document.styleSheets, function( stylesheet ) {
					Array.prototype.forEach.call( stylesheet.cssRules || stylesheet.rules, removeViewportStyles );
				} );

				document.body.style.position = 'absolute';
				document.body.style.width = '100%';
				document.body.setAttribute( 'data-resizable-iframe-connected', '' );

				sendResize();
		} )();
		</script>
	</body>
</html>
<?php
		return ob_get_clean();
	}

	/**
	 * Retrieves a shortcode block's schema, conforming to JSON Schema.
	 *
	 * @since 0.10.0
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => 'shortcode-block',
			'type'       => 'object',
			'properties' => array(
				'html' => array(
					'description' => __( 'The block\'s content filtered through hooks, surrounded by theme header & footer styles & scripts.', 'gutenberg' ),
					'type'        => 'string',
					'required'    => true,
				),
			),
		);
	}
}
