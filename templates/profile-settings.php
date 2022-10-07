<h2 id="webfinger"><?php esc_html_e( 'WebFinger', 'webfinger' ); ?></h2>
<p><?php esc_html_e( 'WebFinger is a way to attach information to an email address, or other online resource.', 'webfinger' ); ?></p>
<table class="form-table">
	<tbody>
		<tr>
			<th scope="row">
				<label><?php esc_html_e( 'WebFinger resource', 'webfinger' ); ?></label>
			</th>
			<td>
				<p>
					<input type="text" value="<?php echo esc_textarea( 'abc' ); ?>" />@<?php echo parse_url( home_url(), PHP_URL_HOST ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Your main WebFinger resource', 'webfinger' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label><?php esc_html_e( 'WebFinger aliases', 'webfinger' ); ?></label>
			</th>
			<td>
				<ul>
				<?php
				foreach ( \Webfinger\Webfinger::get_user_resources( $args['user']->ID, false ) as $key ) {
					echo "<li>$key</li>";
				}
				?>
				</ul>
			</td>
		</tr>
	</tbody>
</table>
