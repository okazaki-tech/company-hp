<?php
/**
 * Template Name: 料金・プラン
 * 固定ページ用テンプレート（推奨スラッグ: pricing）
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
					<p class="page-lead">ご要望の規模・機能・納期により費用は変動します。以下は一般的な目安です（税抜）。</p>

					<h2>1. 制作の目安</h2>
					<div class="table-wrap">
						<table class="price-table">
							<thead>
								<tr>
									<th>プラン</th>
									<th>内容（目安）</th>
									<th>価格帯（目安）</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>ライト</td>
									<td>1〜3ページ、基本構成、スマホ対応</td>
									<td>10万円〜25万円</td>
								</tr>
								<tr>
									<td>スタンダード</td>
									<td>5〜10ページ、CMS導入、問い合わせ導線整備</td>
									<td>25万円〜60万円</td>
								</tr>
								<tr>
									<td>カスタム</td>
									<td>要件定義・機能開発・外部連携を含む案件</td>
									<td>60万円〜</td>
								</tr>
							</tbody>
						</table>
					</div>

					<h2>2. 保守・運用の目安</h2>
					<ul>
						<li>保守（月額）：1万円〜5万円（更新対応、軽微修正、障害一次対応）</li>
						<li>運用支援（月額）：3万円〜（改善提案、更新作業、レポート）</li>
						<li>スポット対応：1.5万円〜（内容と工数に応じて都度見積り）</li>
					</ul>

					<h2>3. 料金に含まれるもの / 含まれないもの</h2>
					<ul>
						<li>含まれるもの：要件確認、制作、公開作業、初期確認</li>
						<li>含まれないもの：サーバー・ドメイン費、外部有料サービス費、撮影費など</li>
					</ul>

					<h2>4. お見積りについて</h2>
					<p>初回ヒアリング後に、対応範囲・納期・費用を明記したお見積りをご案内します。まずは<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">お問い合わせ</a>ください。</p>
				</div>
			<?php endif; ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
