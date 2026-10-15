<?php
namespace KG_Core\Admin;

/**
 * Focused Gutenberg sidebar for KidsGourmet recipes.
 * Native WordPress document panels remain available at all times.
 */
class RecipeWorkflowSidebar {
    public function __construct() {
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets() {
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'recipe') {
            return;
        }

        global $post;
        $post_id = $post ? (int) $post->ID : 0;
        if (!current_user_can('edit_posts') && (!$post_id || !current_user_can('edit_post', $post_id))) {
            return;
        }

        $taxonomies = [];
        foreach ([
            'age-group' => 'Yaş Grupları',
            'allergen' => 'Alerjenler',
            'diet-type' => 'Diyet Tipleri',
            'meal-type' => 'Öğün Tipleri',
            'special-condition' => 'Özel Durumlar',
            'community_circle' => 'Odaklar',
        ] as $slug => $label) {
            $taxonomy = get_taxonomy($slug);
            if (!$taxonomy || !$taxonomy->show_in_rest) {
                continue;
            }
            $taxonomies[] = [
                'slug' => $slug,
                'key' => !empty($taxonomy->rest_base) ? $taxonomy->rest_base : $slug,
                'label' => $label,
            ];
        }

        $warning = null;
        if ($post_id && class_exists('\KG_Core\Services\RecipePublicationGuard')) {
            $ages = wp_get_post_terms($post_id, 'age-group', ['fields' => 'slugs']);
            $ingredients = get_post_meta($post_id, '_kg_ingredients', true);
            $warning = \KG_Core\Services\RecipePublicationGuard::assess(
                $ingredients,
                is_wp_error($ages) ? [] : $ages
            );
        }
        $ack = $post_id ? get_post_meta($post_id, '_kg_recipe_safety_acknowledgment', true) : null;

        $asset = 'assets/admin/js/recipe-workflow-sidebar.js';
        wp_enqueue_script(
            'kg-recipe-workflow-sidebar',
            KG_CORE_URL . $asset,
            ['wp-element', 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-core-data'],
            (string) filemtime(KG_CORE_PATH . $asset),
            true
        );

        wp_localize_script('kg-recipe-workflow-sidebar', 'kgRecipeWorkflow', [
            'postId' => $post_id,
            'canPublish' => current_user_can('publish_posts'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'reviewNonce' => $post_id ? wp_create_nonce('kg_recipe_safety_ack_' . $post_id) : '',
            'warning' => $warning,
            'approved' => is_array($ack) && !empty($ack['user_id']),
            'taxonomies' => $taxonomies,
        ]);

        $css = 'assets/admin/css/recipe-workflow-sidebar.css';
        wp_enqueue_style(
            'kg-recipe-workflow-sidebar',
            KG_CORE_URL . $css,
            ['wp-edit-blocks'],
            (string) filemtime(KG_CORE_PATH . $css)
        );
    }
}
