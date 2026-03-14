<?php
/**
 * インデックス（フォールバック）
 *
 * @package Company_HP
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="section">
	<div class="container">
		<?php
		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();
				?>
				<article>
					<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
					<?php the_excerpt(); ?>
				</article>
				<?php
			}
		} else {
			echo '<p>コンテンツがありません。</p>';
		}
		?>
	</div>
</section>

<?php
get_footer();
