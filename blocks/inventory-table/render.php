<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$search   = isset( $attributes['search'] ) ? sanitize_text_field( $attributes['search'] ) : '';
$category = isset( $attributes['category'] ) ? sanitize_text_field( $attributes['category'] ) : '';
$per_page = isset( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 10;
$page     = isset( $attributes['page'] ) ? absint( $attributes['page'] ) : 1;

$args = array(
    'post_type'      => 'pit_item',
    'posts_per_page' => $per_page,
    'paged'          => $page,
);

if ( $search ) {
    $args['s'] = $search;
}

if ( $category ) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'pit_category',
            'field'    => 'slug',
            'terms'    => $category,
        ),
    );
}

$query = new WP_Query( $args );
ob_start();

if ( $query->have_posts() ) {
    echo '<table class="pit-inventory-table">';
    echo '<thead><tr><th>' . esc_html__( 'Item', 'personal-inventory-tracker' ) . '</th></tr></thead>';
    echo '<tbody>';
    while ( $query->have_posts() ) {
        $query->the_post();
        echo '<tr><td>' . esc_html( get_the_title() ) . '</td></tr>';
    }
    echo '</tbody></table>';
    wp_reset_postdata();
} else {
    echo '<p>' . esc_html__( 'No items found.', 'personal-inventory-tracker' ) . '</p>';
}

return ob_get_clean();
