<?php

class Debug_Bar_Rewrite_Testing_Panel extends Debug_Bar_Panel {

	public $summary;

	public function init() {
		$this->title( __( 'Rewrite Testing', 'rewrite-testing' ) );
	}

	public function prerender() {
		$this->summary = Rewrite_Testing()->get_summary();
		$this->set_visible( ! empty( $this->summary['error_count'] ) );
	}

	public function render() {
		?>

		<div id="debug-bar-rewrite-resting">

			<?php if ( ! empty( $this->summary['status'] ) ) : ?>
				<h2><span><?php esc_html_e( 'Status:', 'debug-bar-remote-requests' ); ?></span> <?php echo esc_html( $this->summary['status'] ) ?></h2>
			<?php endif;

			if ( ! empty( $this->summary['error_count'] ) ) : ?>
				<h2><span><?php esc_html_e( 'Total Errors:', 'debug-bar-remote-requests' ); ?></span> <?php echo absint( $this->summary['error_count'] ) ?></h2>
			<?php endif; ?>

			<div class="clear"></div>

			<?php if ( ! empty( $this->summary['details'] ) ) : ?>

				<style type="text/css">
				#debug-bar-rewrite-resting h3 { float: none; clear: both; font-family: georgia,times,serif; font-size: 22px; margin: 15px 10px 15px 0 !important; }
				#debug-bar-rewrite-resting table { background: #fff; border-collapse: collapse; font: 13px/20px 'Open Sans', sans-serif; border: 1px solid #e5e5e5; -webkit-box-shadow: 0 1px 1px rgba(0,0,0,.04); box-shadow: 0 1px 1px rgba(0,0,0,.04); border-spacing: 0; width: 100%; clear: both; margin: 0; }
				#debug-bar-rewrite-resting thead th { border-bottom: 1px solid #e1e1e1; }
				#debug-bar-rewrite-resting td, #debug-bar-rewrite-resting th { padding: 8px 10px; }
				#debug-bar-rewrite-resting tr:nth-child(even) { background-color: #f9f9f9; }
				</style>

				<h3><?php esc_html_e( 'Errors', 'rewrite-testing' ); ?></h3>

				<table>
					<?php Rewrite_Testing()->results_table_head() ?>
					<tbody>
						<?php array_walk( $this->summary['details'], array( Rewrite_Testing(), 'results_row' ) ) ?>
					</tbody>
				</table>

			<?php endif; ?>

		</div>

		<?php
	}
}
