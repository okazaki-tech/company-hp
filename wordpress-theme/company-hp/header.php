<?php
/**
 * ヘッダー
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="container">
		<?php if ( is_front_page() ) : ?>
			<span class="site-title"><?php bloginfo( 'name' ); ?></span>
		<?php else : ?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-title"><?php bloginfo( 'name' ); ?></a>
		<?php endif; ?>
		<nav class="site-nav" aria-label="<?php esc_attr_e( 'メインメニュー', 'company-hp' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => false,
				) );
			} else {
				?>
				<ul class="menu">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'company-hp' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( '事業者について', 'company-hp' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'company-hp' ); ?></a></li>
				</ul>
				<?php
			}
			?>
		</nav>
	</div>
</header>

<main id="main" class="site-main">
