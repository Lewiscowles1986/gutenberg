/**
 * External dependencies
 */
import { connect } from 'react-redux';

/**
 * WordPress dependencies
 */
import { withAPIData, Spinner, SandBox } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/element';

function ShortcodePreview( { response } ) {
	if ( response.isLoading || ! response.data ) {
		return (
			<div key="loading" className="wp-block-embed is-loading">
				<Spinner />
				<p>{ __( 'Loading...' ) }</p>
			</div>
		);
	}

	const html = response.data.html;
	return (
		<figure className="wp-block-embed" key="embed">
			<SandBox
				html={ html }
			/>
		</figure>
	);
}

const applyConnect = connect(
	( state ) => {
		return {
			postId: state.currentPost.id,
		};
	},
);

const applyWithAPIData = withAPIData( ( props ) => {
	const { shortcode, postId } = props;
	return {
		response: `/gutenberg/v1/content?content=${ encodeURIComponent( shortcode ) }&postId=${ postId }`,
	};
} );

export default compose( [
	applyConnect,
	applyWithAPIData,
] )( ShortcodePreview );
