<?php
namespace KG_Core\Newsletter;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * NewsletterRESTController - REST API endpoints for newsletter
 * 
 * Handles newsletter subscription, confirmation, and unsubscription via REST API
 */
class NewsletterRESTController extends WP_REST_Controller {
    
    private $service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = 'kg/v1';
        $this->rest_base = 'newsletter';
        $this->service = new NewsletterService();
        
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Subscribe endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/subscribe', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_subscribe'],
                'permission_callback' => '__return_true',
                'args' => [
                    'email' => [
                        'required' => true,
                        'type' => 'string',
                        'format' => 'email',
                        'description' => __('E-posta adresi', 'kg-core'),
                        'sanitize_callback' => 'sanitize_email',
                    ],
                    'name' => [
                        'required' => false,
                        'type' => 'string',
                        'description' => __('İsim (opsiyonel)', 'kg-core'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'source' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'website',
                        'description' => __('Kaynak', 'kg-core'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
        
        // Confirm endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/confirm/(?P<token>[a-zA-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'handle_confirm'],
                'permission_callback' => '__return_true',
                'args' => [
                    'token' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => __('Onay token', 'kg-core'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
        
        // Unsubscribe endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/unsubscribe', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_unsubscribe'],
                'permission_callback' => '__return_true',
                'args' => [
                    'email' => [
                        'required' => true,
                        'type' => 'string',
                        'format' => 'email',
                        'description' => __('E-posta adresi', 'kg-core'),
                        'sanitize_callback' => 'sanitize_email',
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Handle subscribe request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_subscribe(WP_REST_Request $request) {
        $email = $request->get_param('email');
        $name = $request->get_param('name');
        $source = $request->get_param('source') ?? 'website';
        
        $result = $this->service->subscribe($email, $name, $source);
        
        if ($result['success']) {
            return new WP_REST_Response($result, 200);
        } else {
            return new WP_REST_Response($result, 400);
        }
    }
    
    /**
     * Handle confirm request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_confirm(WP_REST_Request $request) {
        $token = $request->get_param('token');
        
        $confirmed = $this->service->confirm($token);
        
        if ($confirmed) {
            // Redirect to success page or return JSON
            $redirect_url = get_site_url() . '/?newsletter=confirmed';
            
            // Check if Accept header is JSON
            $accept = $request->get_header('accept');
            if (strpos($accept, 'application/json') !== false) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Aboneliğiniz başarıyla onaylandı! Teşekkür ederiz.', 'kg-core'),
                ], 200);
            }
            
            // HTML redirect for browser
            wp_redirect($redirect_url);
            exit;
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Geçersiz veya süresi dolmuş onay linki.', 'kg-core'),
                'code' => 'invalid_token',
            ], 400);
        }
    }
    
    /**
     * Handle unsubscribe request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_unsubscribe(WP_REST_Request $request) {
        $email = $request->get_param('email');
        
        $unsubscribed = $this->service->unsubscribe($email);
        
        if ($unsubscribed) {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Bülten aboneliğiniz iptal edildi.', 'kg-core'),
            ], 200);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('E-posta adresi bulunamadı.', 'kg-core'),
                'code' => 'not_found',
            ], 404);
        }
    }
}
