<?php
/**
 * Template Name: プライバシーポリシー
 * 固定ページ用テンプレート（推奨スラッグ: privacy-policy）
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
				<div class="policy-body">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?>（以下「当サイト」）では、お問い合わせやサービスのご利用において、以下のとおり個人情報を取り扱います。</p>

					<h2>1. 収集する情報</h2>
					<p>当サイトでは、お問い合わせフォームの送信時に、お名前・メールアドレス・お問い合わせ内容を収集することがあります。また、アクセス解析のため Cookie を使用している場合があります。</p>

					<h2>2. 利用目的</h2>
					<p>収集した情報は、お問い合わせへの回答、サービスに関する連絡、およびサイトの改善目的でのみ利用します。</p>

					<h2>3. 第三者提供</h2>
					<p>収集した個人情報は、法令に基づく場合を除き、ご本人の同意なく第三者に提供することはありません。</p>

					<h2>4. お問い合わせ</h2>
					<p>個人情報の取り扱いに関するお問い合わせは、<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせページ</a>よりご連絡ください。</p>

					<p><small>制定日：<?php echo esc_html( date( 'Y年n月j日' ) ); ?></small></p>
				</div>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
