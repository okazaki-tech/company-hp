<?php
/**
 * トップページ用テンプレート
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="hero">
	<div class="container">
		<h1><?php bloginfo( 'name' ); ?></h1>
		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<p class="lead"><?php bloginfo( 'description' ); ?></p>
		<?php else : ?>
			<p class="lead">個人事業主としてのキャッチコピーや事業の一言説明をここに記載します。</p>
		<?php endif; ?>
	</div>
</section>

<section class="section">
	<div class="container">
		<h2>サービス・事業内容</h2>
		<?php
		$has_content = false;
		while ( have_posts() ) {
			the_post();
			if ( get_the_content() ) {
				$has_content = true;
			}
			the_content();
		}
		if ( ! $has_content ) {
			echo '<p>提供しているサービスや事業内容の概要を記載してください。Stripe の申請時にも、サイト上で事業内容が分かるとよいです。</p>';
		}
		?>
	</div>
</section>

<?php
get_footer();
