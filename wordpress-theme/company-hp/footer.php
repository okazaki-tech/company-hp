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
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<nav class="footer-nav" aria-label="<?php esc_attr_e( 'フッターメニュー', 'company-hp' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'footer-links',
			) );
			?>
		</nav>
		<?php else : ?>
		<nav class="footer-nav" aria-label="<?php esc_attr_e( '法的情報', 'company-hp' ); ?>">
			<ul class="footer-links">
				<li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'プライバシーポリシー', 'company-hp' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( '利用規約', 'company-hp' ); ?></a></li>
			</ul>
		</nav>
		<?php endif; ?>
		<p class="footer-copy">&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>（個人事業主）</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
