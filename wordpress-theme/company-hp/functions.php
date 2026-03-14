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

// テーマ有効化時・デプロイ後（管理画面初回表示時）に必要な固定ページを自動作成
add_action( 'after_switch_theme', 'company_hp_create_default_pages' );
add_action( 'admin_init', 'company_hp_maybe_create_default_pages' );

/**
 * テーマバージョンが上がったとき（デプロイ後など）に未作成の固定ページを作成
 */
function company_hp_maybe_create_default_pages() {
	$current_version = wp_get_theme()->get( 'Version' ) ?: '0.1.0';
	$saved_version   = get_option( 'company_hp_pages_version', '' );
	if ( $saved_version !== $current_version ) {
		company_hp_create_default_pages();
		update_option( 'company_hp_pages_version', $current_version );
	}
}

/**
 * デフォルトの固定ページが無ければ作成する。テーマ有効化時および init で未作成なら実行。
 */
function company_hp_create_default_pages() {
	$pages = array(
		array(
			'post_name'  => 'home',
			'post_title' => __( 'ホーム', 'company-hp' ),
			'meta'       => array(), // トップは front-page.php が使われる
		),
		array(
			'post_name'  => 'about',
			'post_title' => __( '事業者について', 'company-hp' ),
			'meta'       => array( '_wp_page_template' => 'page-about.php' ),
		),
		array(
			'post_name'  => 'contact',
			'post_title' => __( 'お問い合わせ', 'company-hp' ),
			'meta'       => array( '_wp_page_template' => 'page-contact.php' ),
		),
		array(
			'post_name'  => 'privacy-policy',
			'post_title' => __( 'プライバシーポリシー', 'company-hp' ),
			'meta'       => array( '_wp_page_template' => 'page-privacy-policy.php' ),
		),
		array(
			'post_name'  => 'terms',
			'post_title' => __( '利用規約', 'company-hp' ),
			'meta'       => array( '_wp_page_template' => 'page-terms.php' ),
		),
	);

	$home_page_id = null;

	foreach ( $pages as $p ) {
		$existing = get_page_by_path( $p['post_name'], OBJECT, 'page' );
		if ( $existing ) {
			if ( $p['post_name'] === 'home' ) {
				$home_page_id = $existing->ID;
			}
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_type'   => 'page',
			'post_title'  => $p['post_title'],
			'post_name'   => $p['post_name'],
			'post_status' => 'publish',
			'post_author' => 1,
		) );

		if ( ! is_wp_error( $post_id ) && ! empty( $p['meta'] ) ) {
			foreach ( $p['meta'] as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( $p['post_name'] === 'home' ) {
			$home_page_id = $post_id;
		}
	}

	// 表示設定が「最新の投稿」のままなら、作成した「ホーム」をトップに設定
	if ( $home_page_id && get_option( 'show_on_front' ) === 'posts' ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_page_id );
	}
}

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
