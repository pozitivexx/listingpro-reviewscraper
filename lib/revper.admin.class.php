<?php

/**
 * Created by PhpStorm.
 * User: alper
 * Date: 25-Feb-21
 * Time: 11:39
 */
class revper_admin extends RevperController {

	public function __construct() {
		parent::__construct();
		// enqueue admin side css and js
		add_action( 'admin_enqueue_scripts', function () {
			wp_enqueue_style( 'revpercustomreviews',
				plugins_url( '', __FILE__ ) . '/../assests/css/revper-custom-reviews.css', [],
				'1.0.0' );
			wp_enqueue_script( 'revpercustomreviews',
				plugins_url( '', __FILE__ ) . '/../assests/js/revper-custom-reviews.js', [],
				'2.0.0' );
		} );

		add_action( 'init', function () {
			//first init
			add_action( 'add_meta_boxes', [ $this, 'revper_add_meta_box' ] );
			add_action( 'save_post_listing', [ $this, 'revper_save_meta_box' ] );

			add_action( 'wp_ajax_revper_get_reviews', [ $this, 'revper_get_reviews' ] );


		} );
	}

	public function revper_meta_box_content() {
		include WP_PLUGIN_DIR . '/listingpro-reviewscraper/views/revper-meta-box-display.php';
	}

	public function revper_save_meta_box( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( $this->revper_meta_fields as $field ) {
			if ( isset( $_POST[ $field['slug'] ] ) ) {
				$url = esc_attr( $_POST[ $field['slug'] ] );

				if ( get_post_meta( $post_id, $field['slug'], true ) != $url ) {
					// send url to api if it changed,
					update_post_meta( $post_id, $field['slug'], $url );

					$this->GetApiKey( str_replace( 'revper_', '', $field['slug'] ), $post_id, true );
				};

			}
		}
	}

	public function revper_add_meta_box() {

		add_meta_box(
			'revper_reviews-revper-reviews',
			__( 'External Reviews', 'revper_reviews' ),
			[ $this, 'revper_meta_box_content' ],
			'listing',
			'normal',
			'core'
		);
	}

	public $revirews_Handler;


	public function revper_get_reviews() {
		$post_id = $_POST['post_id'];
		$product = $_POST['product'];

		if ( ! in_array( $product, array_keys( $this->revper_meta_fields ) ) ) {
			wp_send_json( [ 'result' => false, 'content' => "product is incorrect" ] );
			wp_die();
		}

		$key = json_decode( get_post_meta( $post_id, $this->revper_meta_fields[ $product ]['slug'] . "_key", true ),
			true );
		if ( ! $key || ! isset( $key['result'] ) || ! $key['result'] ) {
			dbg( $product . " key is invalid. requesting again: " . var_export( $key, true ) );
			$key = $this->GetApiKey( $product, $post_id, true );
			dbg( $key );
			dbg( $product . " response requested new key: " . var_export( $key, true ) );
			//wp_send_json( [ 'result' => false, 'content' => 'key is incorrect' ] );
			//wp_die();

			if ( ! $key['result'] ) {
				wp_send_json( [ 'result' => false, 'content' => $key['content']??'key is incorrect' ] );
				wp_die();
			}
		}

		$result = $this->revper_get_review( $key['content'], $post_id, $product );

		if ( $result['result'] ) {
			wp_send_json( [
				'result'  => true,
				'content' => $result['content']['imported'] . "/" . $result['content']['total'] . " imported successfully (" . $result['content']['exist'] . " exists)",
				'count'   => $result['content']
			] );
		} else {
			if ( isset( $result['key'] ) && ! $result['key'] ) {
				// key is invalid
				delete_post_meta( $post_id, "revper_" . $product . "_key" );
				dbg( $product . ": removed invalid api key: post id: $post_id meta_key: revper_" . $product );
			}

			wp_send_json( [ 'result' => false, 'content' => $result['content']??null ] );
			wp_die();
		}
		wp_die();

	}

}