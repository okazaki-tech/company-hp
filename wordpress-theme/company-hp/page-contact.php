<?php
/**
 * Template Name: お問い合わせ
 * 固定ページ用テンプレート（推奨スラッグ: contact）
 * 本文が空の場合は案内文を表示。Contact Form 7 等のショートコードは本文に記載してください。
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="section page-content">
	<div class="container">
		<?php
		while ( have_posts() ) {
			the_post();
			?>
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<?php if ( get_the_content() ) : ?>
				<?php the_content(); ?>
			<?php else : ?>
				<p>ウェブサイトの制作・運用に関するご相談、お見積りのご依頼など、お気軽にご連絡ください。</p>
				<!-- <p>下記のメールアドレスまでお送りいただくか、お問い合わせフォーム（Contact Form 7 等のプラグインでフォームを設置後、この固定ページの本文にショートコードを追加してください）をご利用ください。</p> -->
				<p>メールアドレスは、<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">事業者について</a>のページに記載しています。</p>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
