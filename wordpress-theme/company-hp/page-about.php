<?php
/**
 * Template Name: 事業者について
 * 固定ページ用テンプレート（推奨スラッグ: about）
 * 個人事業主である旨の説明をページ上部に表示します。
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="section section-notice" aria-label="事業者についての注記">
	<div class="container">
		<p class="business-notice">当サイトは法人（会社）ではなく、<strong>個人事業主</strong>が屋号を届け出たうえで運営しています。</p>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php
		while ( have_posts() ) {
			the_post();
			?>
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<?php if ( get_the_content() ) : ?>
				<?php the_content(); ?>
			<?php else : ?>
				<div class="policy-body">
					<p>以下、事業者情報です。必要に応じて編集してください。</p>
					<dl class="about-list">
						<dt>屋号</dt>
						<dd><?php echo esc_html( get_bloginfo( 'name' ) ); ?></dd>
						<dt>事業者</dt>
						<dd>個人事業主（法人ではありません）</dd>
						<dt>所在地</dt>
						<dd>東京都世田谷区船橋</dd>
						<dt>連絡先</dt>
						<dd>support@axiola.jp<br><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせページ</a>からもご連絡いただけます。</dd>
					</dl>
				</div>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
