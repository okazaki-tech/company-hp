<?php
/**
 * 会社HP テーマ用 functions
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

// タイトルタグ（ブラウザタブにページタイトルを表示）
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
} );

// テーマの style.css とフォントを読み込み
add_action( 'wp_enqueue_scripts', function () {
	$version = wp_get_theme()->get( 'Version' ) ?: '0.1.0';
	wp_enqueue_style(
		'company-hp-fonts',
		'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'company-hp-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'company-hp-fonts' ),
		$version
	);
}, 10 );

// メニュー登録
register_nav_menus( array(
	'primary' => __( 'メインメニュー', 'company-hp' ),
	'footer'  => __( 'フッター（プライバシーポリシー等）', 'company-hp' ),
) );

// ファビコン・ホーム画面アイコン（Axiola ロゴ）。管理画面でサイトアイコン未設定の場合のみ表示
add_action( 'wp_head', function () {
	if ( get_option( 'site_icon' ) ) {
		return;
	}
	$theme_uri = get_stylesheet_directory_uri();
	$favicon_svg = $theme_uri . '/assets/images/favicon.svg';
	$icon_png   = $theme_uri . '/assets/images/apple-touch-icon.png';
	?>
	<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( $favicon_svg ); ?>">
	<link rel="icon" type="image/png" sizes="192x192" href="<?php echo esc_url( $icon_png ); ?>">
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $icon_png ); ?>">
	<link rel="apple-touch-icon" sizes="192x192" href="<?php echo esc_url( $icon_png ); ?>">
	<?php
}, 5 );
