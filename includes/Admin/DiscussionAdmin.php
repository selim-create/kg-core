<?php
namespace KG_Core\Admin;

class DiscussionAdmin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
        add_filter( 'manage_discussion_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_discussion_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        add_action( 'wp_ajax_kg_approve_discussion', [ $this, 'ajax_approve_discussion' ] );
        add_action( 'wp_ajax_kg_reject_discussion', [ $this, 'ajax_reject_discussion' ] );
        add_action( 'wp_ajax_kg_feature_discussion', [ $this, 'ajax_feature_discussion' ] );
    }

    public function add_admin_menu() {
        $pending_count = $this->get_pending_count();
        
        add_submenu_page(
            'edit.php?post_type=discussion',
            __( 'Moderasyon', 'kg-core' ),
            __( 'Moderasyon', 'kg-core' ) . ' (' . $pending_count . ')',
            'manage_options',
            'discussion-moderation',
            [ $this, 'render_moderation_page' ]
        );
    }

    private function get_pending_count() {
        $count = wp_count_posts( 'discussion' );
        return isset( $count->pending ) ? (int) $count->pending : 0;
    }

    public function enqueue_admin_styles( $hook ) {
        if ( strpos( $hook, 'discussion' ) === false ) {
            return;
        }

        $custom_css = '
            .kg-moderation-card {
                background:  #fff;
                border: 1px solid #ddd;
                border-left: 4px solid #f0ad4e;
                padding: 15px;
                margin-bottom: 15px;
                border-radius:  4px;
            }
            .kg-moderation-card. approved {
                border-left-color: #5cb85c;
            }
            .kg-moderation-card h3 {
                margin: 0 0 10px 0;
                font-size: 16px;
            }
            .kg-moderation-card .meta {
                color: #666;
                font-size: 13px;
                margin-bottom: 10px;
            }
            . kg-moderation-card .content {
                background: #f9f9f9;
                padding: 10px;
                border-radius: 3px;
                margin-bottom: 10px;
            }
            .kg-moderation-card .actions {
                display: flex;
                gap: 10px;
            }
            .kg-circle-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
            }
            .kg-expert-badge {
                background: #28a745;
                color: white;
                padding:  2px 8px;
                border-radius: 3px;
                font-size: 11px;
            }
        ';

        wp_add_inline_style( 'wp-admin', $custom_css );
    }

    public function add_custom_columns( $columns ) {
        $new_columns = [];
        
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            
            if ( $key === 'title' ) {
                $new_columns['kg_circle'] = __( 'Çember', 'kg-core' );
                $new_columns['kg_expert'] = __( 'Uzman Cevabı', 'kg-core' );
                $new_columns['kg_featured'] = __( 'Öne Çıkan', 'kg-core' );
            }
        }
        
        return $new_columns;
    }

    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'kg_circle':
                $circles = wp_get_object_terms( $post_id, 'community_circle' );
                if ( ! empty( $circles ) && ! is_wp_error( $circles ) ) {
                    $circle = $circles[0];
                    $color = get_term_meta( $circle->term_id, '_kg_circle_color_code', true );
                    $color = $color ? $color : '#E8E8E8';
                    $icon = get_term_meta( $circle->term_id, '_kg_circle_icon', true );
                    $icon = $icon ? $icon : '💬';
                    echo '<span class="kg-circle-badge" style="background: ' . esc_attr( $color ) . '">';
                    echo esc_html( $icon . ' ' . $circle->name );
                    echo '</span>';
                }
                break;
                
            case 'kg_expert':
                $expert = get_post_meta( $post_id, '_expert_answered', true );
                if ( $expert ) {
                    echo '<span class="kg-expert-badge">✓ Cevaplandı</span>';
                } else {
                    echo '-';
                }
                break;
                
            case 'kg_featured':
                $featured = get_post_meta( $post_id, '_is_featured_question', true );
                echo $featured ? '⭐' : '-';
                break;
        }
    }

    public function render_moderation_page() {
        $pending_discussions = get_posts( [
            'post_type'      => 'discussion',
            'post_status'    => 'pending',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ] );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Soru Moderasyonu', 'kg-core' ) . '</h1>';

        if ( empty( $pending_discussions ) ) {
            echo '<div class="notice notice-success"><p>';
            echo esc_html__( 'Onay bekleyen soru bulunmuyor. ', 'kg-core' );
            echo '</p></div>';
        } else {
            $count = count( $pending_discussions );
            echo '<p>' . sprintf( esc_html__( '%d adet soru onay bekliyor.', 'kg-core' ), $count ) . '</p>';

            foreach ( $pending_discussions as $discussion ) {
                $this->render_moderation_card( $discussion );
            }

            $this->render_moderation_scripts();
        }

        echo '</div>';
    }

    private function render_moderation_card( $discussion ) {
        $author = get_user_by( 'id', $discussion->post_author );
        $circles = wp_get_object_terms( $discussion->ID, 'community_circle' );
        $circle = ( ! empty( $circles ) && ! is_wp_error( $circles ) ) ? $circles[0] : null;
        $is_anonymous = get_post_meta( $discussion->ID, '_is_anonymous', true );

        echo '<div class="kg-moderation-card" id="discussion-' . esc_attr( $discussion->ID ) . '">';
        
        // Title
        echo '<h3>' .  esc_html( $discussion->post_title ) . '</h3>';
        
        // Meta info
        echo '<div class="meta">';
        echo '<strong>' . esc_html__( 'Yazar:', 'kg-core' ) . '</strong> ';
        
        if ( $is_anonymous ) {
            echo esc_html__( 'Anonim', 'kg-core' );
        } else {
            echo esc_html( $author->display_name );
        }
        
        echo ' (' . esc_html( $author->user_email ) . ')';
        echo ' &bull; ';
        echo '<strong>' . esc_html__( 'Tarih:', 'kg-core' ) . '</strong> ';
        echo esc_html( get_the_date( 'd. m.Y H:i', $discussion ) );

        if ( $circle ) {
            echo ' &bull; ';
            echo '<strong>' .  esc_html__( 'Çember:', 'kg-core' ) . '</strong> ';
            $icon = get_term_meta( $circle->term_id, '_kg_circle_icon', true );
            $icon = $icon ? $icon :  '💬';
            echo esc_html( $icon . ' ' . $circle->name );
        }
        
        echo '</div>';

        // Content
        echo '<div class="content">';
        echo wp_kses_post( wpautop( $discussion->post_content ) );
        echo '</div>';

        // Action buttons
        echo '<div class="actions">';
        
        echo '<button class="button button-primary kg-approve" data-id="' . esc_attr( $discussion->ID ) . '">';
        echo '✓ ' . esc_html__( 'Onayla', 'kg-core' );
        echo '</button>';
        
        echo '<button class="button kg-reject" data-id="' . esc_attr( $discussion->ID ) . '">';
        echo '✗ ' . esc_html__( 'Reddet', 'kg-core' );
        echo '</button>';
        
        echo '<button class="button kg-feature" data-id="' . esc_attr( $discussion->ID ) . '">';
        echo '⭐ ' . esc_html__( 'Öne Çıkar + Onayla', 'kg-core' );
        echo '</button>';
        
        echo '<a href="' . esc_url( get_edit_post_link( $discussion->ID ) ) . '" class="button">';
        echo esc_html__( 'Düzenle', 'kg-core' );
        echo '</a>';
        
        echo '</div>';
        echo '</div>';
    }

    private function render_moderation_scripts() {
        $nonce = wp_create_nonce( 'kg_moderation' );
        $confirm_text = esc_js( __( 'Bu soruyu silmek istediğinize emin misiniz?', 'kg-core' ) );
        
        $script = "
        jQuery(document).ready(function($) {
            
            $('.kg-approve').on('click', function() {
                var button = $(this);
                var id = button.data('id');
                
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action:  'kg_approve_discussion',
                    post_id: id,
                    _wpnonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        $('#discussion-' + id).addClass('approved').fadeOut(400);
                    } else {
                        alert('Hata oluştu');
                        button.prop('disabled', false);
                    }
                });
            });

            $('.kg-reject').on('click', function() {
                if (! confirm('{$confirm_text}')) {
                    return;
                }
                
                var button = $(this);
                var id = button.data('id');
                
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'kg_reject_discussion',
                    post_id: id,
                    _wpnonce:  '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        $('#discussion-' + id).fadeOut(400);
                    } else {
                        alert('Hata oluştu');
                        button.prop('disabled', false);
                    }
                });
            });

            $('.kg-feature').on('click', function() {
                var button = $(this);
                var id = button.data('id');
                
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'kg_feature_discussion',
                    post_id: id,
                    _wpnonce: '{$nonce}'
                }, function(response) {
                    if (response.success) {
                        $('#discussion-' + id).addClass('approved').fadeOut(400);
                    } else {
                        alert('Hata oluştu');
                        button.prop('disabled', false);
                    }
                });
            });
            
        });
        ";

        echo '<script>' . $script . '</script>';
    }

    public function ajax_approve_discussion() {
        check_ajax_referer( 'kg_moderation', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Yetkisiz işlem' ] );
            return;
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        
        if ( !  $post_id ) {
            wp_send_json_error( [ 'message' => 'Geçersiz ID' ] );
            return;
        }

        $result = wp_update_post( [
            'ID'          => $post_id,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }

        wp_send_json_success( [ 'message' => 'Soru onaylandı' ] );
    }

    public function ajax_reject_discussion() {
        check_ajax_referer( 'kg_moderation', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Yetkisiz işlem' ] );
            return;
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Geçersiz ID' ] );
            return;
        }

        $result = wp_trash_post( $post_id );

        if ( !  $result ) {
            wp_send_json_error( [ 'message' => 'Silinemedi' ] );
            return;
        }

        wp_send_json_success( [ 'message' => 'Soru silindi' ] );
    }

    public function ajax_feature_discussion() {
        check_ajax_referer( 'kg_moderation', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Yetkisiz işlem' ] );
            return;
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Geçersiz ID' ] );
            return;
        }

        // Update post status
        $result = wp_update_post( [
            'ID'          => $post_id,
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
            return;
        }

        // Set as featured
        update_post_meta( $post_id, '_is_featured_question', true );

        wp_send_json_success( [ 'message' => 'Soru öne çıkarıldı ve onaylandı' ] );
    }
}