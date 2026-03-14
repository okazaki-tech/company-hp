<?php
/**
 * フッター
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;
?>

</main>

<footer class="site-footer">
	<div class="container">
		<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved.</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
