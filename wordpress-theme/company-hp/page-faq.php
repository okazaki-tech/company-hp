<?php
/**
 * Template Name: FAQ
 * 固定ページ用テンプレート（推奨スラッグ: faq）
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
					<p class="page-lead">ご相談時によくいただく質問をまとめています。個別条件によって最適解は変わるため、詳細はお問い合わせください。</p>

					<h2>見積もり・契約について</h2>
					<dl class="faq-list">
						<dt>Q. 見積もりは無料ですか？</dt>
						<dd>A. はい。初回ヒアリングと概算見積もりは無料です。</dd>

						<dt>Q. 依頼前に相談だけでも可能ですか？</dt>
						<dd>A. 可能です。現状整理や優先順位の相談から対応します。</dd>

						<dt>Q. 契約後の追加費用は発生しますか？</dt>
						<dd>A. 仕様追加や大幅な方針変更がある場合は、事前合意のうえで追加見積もりとなります。</dd>
					</dl>

					<h2>制作・納期について</h2>
					<dl class="faq-list">
						<dt>Q. 納期の目安はどのくらいですか？</dt>
						<dd>A. 小規模サイトで2〜4週間、中規模で1〜2か月が目安です。要件により前後します。</dd>

						<dt>Q. 修正は何回まで対応できますか？</dt>
						<dd>A. 見積もり時に修正回数の目安を提示します。通常は主要な確認工程で複数回対応します。</dd>

						<dt>Q. 既存サイトの一部改修だけでも依頼できますか？</dt>
						<dd>A. 可能です。部分改善や表示崩れ修正、速度改善などスポット対応も承ります。</dd>
					</dl>

					<h2>保守・運用について</h2>
					<dl class="faq-list">
						<dt>Q. 公開後の保守もお願いできますか？</dt>
						<dd>A. 可能です。更新対応・障害一次対応・改善提案まで、必要に応じてプラン化できます。</dd>

						<dt>Q. CMSやプラグインの更新は対応してもらえますか？</dt>
						<dd>A. はい。バックアップを取りつつ、検証のうえで更新対応します。</dd>

						<dt>Q. 運用担当がいない場合でも大丈夫ですか？</dt>
						<dd>A. 問題ありません。更新代行や運用フロー整理まで含めて支援します。</dd>
					</dl>

					<p>上記以外のご質問は、<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせ</a>からお気軽にご連絡ください。</p>
				</div>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
