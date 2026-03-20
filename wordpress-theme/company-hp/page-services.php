<?php
/**
 * Template Name: サービス詳細
 * 固定ページ用テンプレート（推奨スラッグ: services）
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
					<p class="page-lead">企画から公開後の運用まで、目的に合わせて必要な範囲を選べるようにしています。以下は標準的な対応範囲です。</p>

					<h2>1. 制作（新規構築・リニューアル）</h2>
					<ul>
						<li>要件整理・情報設計（サイト構成、導線設計）</li>
						<li>デザイン設計（PC/スマホ対応、UI改善）</li>
						<li>実装（WordPress / 静的サイト / 軽量Webアプリ）</li>
						<li>初期SEO設定（title / description / サイトマップ / 基本速度改善）</li>
						<li>公開作業（ドメイン・SSL・サーバ設定）</li>
					</ul>

					<h2>2. 保守（安定運用のための維持管理）</h2>
					<ul>
						<li>CMS・プラグイン・依存パッケージの更新対応</li>
						<li>バックアップ運用と復旧支援</li>
						<li>不具合調査・軽微修正</li>
						<li>セキュリティ基本対応（不要機能停止、権限見直し）</li>
					</ul>

					<h2>3. 運用（改善・更新支援）</h2>
					<ul>
						<li>テキスト・画像の差し替え、ページ追加</li>
						<li>アクセス状況の確認と改善提案</li>
						<li>問い合わせ導線・CV導線の改善</li>
						<li>継続的な軽微改善（表示速度・UX）</li>
					</ul>

					<h2>4. 進め方（標準フロー）</h2>
					<ol>
						<li>ヒアリング（目的・課題・納期・予算）</li>
						<li>ご提案・お見積り</li>
						<li>制作・確認（必要に応じて複数回レビュー）</li>
						<li>公開・初期運用サポート</li>
					</ol>

					<p>ご希望の範囲のみのご依頼も可能です。まずは<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせ</a>ください。</p>
				</div>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
