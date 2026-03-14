<?php
/**
 * 会社HP テーマ用 functions
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

// テーマの style.css を読み込み
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'company-hp-style',
		get_stylesheet_directory_uri() . '/style.css',
		array(),
		wp_get_theme()->get( 'Version' ) ?: '0.1.0'
	);
}, 10 );

// メニュー登録（管理画面で「ホーム」「事業者について」「お問い合わせ」などを設定可能に）
register_nav_menus( array(
	'primary' => __( 'メインメニュー', 'company-hp' ),
) );
