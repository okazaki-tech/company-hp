<?php
/**
 * Template Name: 利用規約
 * 固定ページ用テンプレート（推奨スラッグ: terms）
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
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?>（以下「当サイト」）のご利用にあたり、以下の規約に同意いただいたものとみなします。</p>

					<h2>第1条（適用）</h2>
					<p>本規約は、当サイトの提供する情報およびサービス（ウェブサイトの閲覧、お問い合わせ等）の利用条件を定めるものです。</p>

					<h2>第2条（禁止事項）</h2>
					<p>利用者は、当サイトの利用にあたり、法令または公序良俗に反する行為、当サイトまたは第三者の権利を侵害する行為、その他当サイトが不適切と判断する行為を行ってはなりません。</p>

					<h2>第3条（免責）</h2>
					<p>当サイトに掲載する情報の正確性・完全性については保証しません。当サイトの情報に基づく判断・行動により生じた損害について、当サイトは責任を負いかねます。</p>

					<h2>第4条（規約の変更）</h2>
					<p>当サイトは、必要に応じて本規約を変更することがあります。変更後の規約は、当サイトに掲載した時点から効力を生じるものとします。</p>

					<h2>第5条（お問い合わせ）</h2>
					<p>本規約に関するお問い合わせは、<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせページ</a>よりご連絡ください。</p>

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
