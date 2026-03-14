<?php
/**
 * トップページ用テンプレート
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

get_header();

$theme_dir = get_stylesheet_directory();
$theme_uri = get_stylesheet_directory_uri();
$hero_webp = $theme_dir . '/assets/images/hero.webp';
$hero_jpg  = $theme_dir . '/assets/images/hero.jpg';
$hero_url  = '';
if ( file_exists( $hero_webp ) ) {
	$hero_url = $theme_uri . '/assets/images/hero.webp';
} elseif ( file_exists( $hero_jpg ) ) {
	$hero_url = $theme_uri . '/assets/images/hero.jpg';
}
?>

<section class="hero">
	<?php if ( $hero_url ) : ?>
	<div class="hero-image">
		<img src="<?php echo esc_url( $hero_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" loading="eager" decoding="async">
	</div>
	<?php endif; ?>
	<div class="container hero-content">
		<p class="hero-badge">Web Design &amp; Development</p>
		<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<p class="lead"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
		<?php else : ?>
			<p class="lead">ウェブサイトの制作から運用まで。設計・デザイン・実装まで一貫して。</p>
		<?php endif; ?>
	</div>
</section>

<section class="section section-strengths">
	<div class="container">
		<h2 class="section-heading">技術とデザインで、伝えるを形に</h2>
		<ul class="strength-list">
			<li class="strength-card strength-card--tech">
				<span class="strength-icon" aria-hidden="true">&lt;/&gt;</span>
				<h3>技術力</h3>
				<p>堅牢な実装と保守しやすいコード。WordPress・静的サイト・軽量なWebアプリまで、目的に合わせた構成をご提案します。</p>
			</li>
			<li class="strength-card strength-card--design">
				<span class="strength-icon strength-icon--design" aria-hidden="true">◇</span>
				<h3>デザイン力</h3>
				<p>見やすさと使いやすさを両立したUI。ブランドに合ったビジュアルと、レスポンシブ・アクセシビリティを意識した設計です。</p>
			</li>
		</ul>
	</div>
</section>

<section class="section">
	<div class="container">
		<h2>サービス・事業内容</h2>
		<?php
		$show_default_service = true;
		while ( have_posts() ) {
			the_post();
			$content = get_the_content();
			$content_plain = wp_strip_all_tags( $content );
			// 空、または WordPress の初期投稿・固定ページのデフォルト文の場合はテーマのデフォルトを表示
			$is_wp_default = ( $content_plain && (
				strpos( $content_plain, 'WordPress へようこそ' ) !== false ||
				strpos( $content_plain, 'こちらは最初の投稿' ) !== false ||
				strpos( $content_plain, 'コンテンツ作成を始めてください' ) !== false ||
				strpos( $content_plain, 'Welcome to WordPress' ) !== false ||
				strpos( $content_plain, 'This is your first post' ) !== false ||
				strpos( $content_plain, 'Edit or delete it, then start writing' ) !== false
			) );
			if ( $content && ! $is_wp_default ) {
				$show_default_service = false;
				the_content();
			}
		}
		if ( $show_default_service ) {
			echo '<p><strong>ウェブサイト作成・運営</strong>を手がけています。企画・デザイン・制作から、公開後の更新・保守・運用まで一貫してサポートします。個人事業主や小規模事業者向けに、わかりやすい提案と継続的なサポートをご提供しています。お気軽に<a href="' . esc_url( home_url( '/contact/' ) ) . '">お問い合わせ</a>ください。</p>';
		}
		?>
	</div>
</section>

<?php
$services = array(
	array(
		'title' => 'AxiTools（便利ウェブアプリ集）',
		'url'   => 'https://tools.axiola.jp/',
		'desc'  => '日常を、ワンクリックで快適に。インストール不要・登録不要の便利ウェブアプリ集。',
	),
	array(
		'title' => 'IQ テスト',
		'url'   => 'https://iq.axiola.jp/',
		'desc'  => 'オンラインでIQを測定。正式な知能検査ではなく、あくまで参考としてご利用ください。',
	),
);
?>
<section class="section section-services">
	<div class="container">
		<h2>運営サービス一覧</h2>
		<ul class="service-list">
			<?php foreach ( $services as $service ) : ?>
			<li class="service-item">
				<a href="<?php echo esc_url( $service['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="service-link">
					<span class="service-title"><?php echo esc_html( $service['title'] ); ?></span>
					<span class="service-desc"><?php echo esc_html( $service['desc'] ); ?></span>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<section class="section section-cta">
	<div class="container">
		<div class="cta-block">
			<h2 class="cta-heading">制作・運用のご相談はこちら</h2>
			<p class="cta-text">企画からデザイン・実装・公開後の更新まで、一気通貫でサポートします。</p>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="cta-button">お問い合わせ</a>
		</div>
	</div>
</section>

<?php
get_footer();
