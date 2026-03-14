<?php
/**
 * 固定ページ用テンプレート（事業者について・お問い合わせなど）
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="section">
	<div class="container">
		<?php
		while ( have_posts() ) {
			the_post();
			?>
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<?php the_content(); ?>
			<?php
		}
		?>
	</div>
</section>

<?php
get_footer();
