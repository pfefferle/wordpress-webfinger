<?php
/**
 * Profile settings template for WebFinger.
 *
 * @package Webfinger
 */

?>
<h2 id="webfinger"><?php \esc_html_e( 'WebFinger', 'webfinger' ); ?></h2>
<p><?php \esc_html_e( 'WebFinger is a way to attach information to an email address, or other online resource.', 'webfinger' ); ?></p>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label for="webfinger_resource"><?php \esc_html_e( 'WebFinger resource', 'webfinger' ); ?></label>
			</th>
			<td>
				<p>
					<?php \wp_nonce_field( 'webfinger_profile_settings', 'webfinger_profile_nonce' ); ?>
					<input name="webfinger_resource" id="webfinger_resource" type="text" style="text-align: right;" value="<?php echo \esc_attr( \get_webfinger_username( $args['user']->ID ) ); ?>" />@<?php echo \esc_html( \wp_parse_url( \home_url(), \PHP_URL_HOST ) ); ?>
				</p>
				<p class="description">
					<?php \esc_html_e( 'Your main WebFinger resource', 'webfinger' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label><?php \esc_html_e( 'WebFinger aliases', 'webfinger' ); ?></label>
			</th>
			<td>
				<ul>
				<?php foreach ( \Webfinger\User::get_resources( $args['user']->ID ) as $resource ) { ?>
					<li>
						<?php echo \esc_html( $resource ); ?>
						<small>(<a href="https://webfinger.net/lookup/?resource=<?php echo \esc_url( \rawurlencode( $resource ) ); ?>" target="_blank" rel="noopener noreferrer">verify</a>)</small>
					</li>
				<?php } ?>
				</ul>
			</td>
		</tr>
	</tbody>
</table>
