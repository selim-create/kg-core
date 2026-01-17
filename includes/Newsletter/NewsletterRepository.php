<?php
namespace KG_Core\Newsletter;

/**
 * NewsletterRepository - Database operations for newsletter subscribers
 * 
 * Handles all database interactions for newsletter subscribers
 */
class NewsletterRepository {
    
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_newsletter_subscribers';
    }
    
    /**
     * Create a new subscriber
     * 
     * @param NewsletterSubscriber $subscriber
     * @return int|false Subscriber ID or false on failure
     */
    public function create(NewsletterSubscriber $subscriber) {
        global $wpdb;
        
        $data = [
            'email' => $subscriber->email,
            'name' => $subscriber->name,
            'status' => $subscriber->status ?? 'pending',
            'source' => $subscriber->source ?? 'website',
            'interests' => !empty($subscriber->interests) ? json_encode($subscriber->interests) : null,
            'confirmation_token' => $subscriber->confirmation_token,
            'ip_address' => $subscriber->ip_address,
            'user_agent' => $subscriber->user_agent,
            'subscribed_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Find subscriber by email
     * 
     * @param string $email
     * @return NewsletterSubscriber|null
     */
    public function findByEmail($email) {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE email = %s",
                $email
            ),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        // Decode JSON fields
        if (!empty($row['interests'])) {
            $row['interests'] = json_decode($row['interests'], true);
        }
        
        return new NewsletterSubscriber($row);
    }
    
    /**
     * Find subscriber by confirmation token
     * 
     * @param string $token
     * @return NewsletterSubscriber|null
     */
    public function findByToken($token) {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE confirmation_token = %s",
                $token
            ),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        // Decode JSON fields
        if (!empty($row['interests'])) {
            $row['interests'] = json_decode($row['interests'], true);
        }
        
        return new NewsletterSubscriber($row);
    }
    
    /**
     * Update subscriber
     * 
     * @param NewsletterSubscriber $subscriber
     * @return bool
     */
    public function update(NewsletterSubscriber $subscriber) {
        global $wpdb;
        
        $data = [
            'email' => $subscriber->email,
            'name' => $subscriber->name,
            'status' => $subscriber->status,
            'source' => $subscriber->source,
            'interests' => !empty($subscriber->interests) ? json_encode($subscriber->interests) : null,
            'confirmation_token' => $subscriber->confirmation_token,
            'confirmed_at' => $subscriber->confirmed_at,
            'unsubscribed_at' => $subscriber->unsubscribed_at,
        ];
        
        $result = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $subscriber->id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete subscriber
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Get all subscribers with optional filters
     * 
     * @param array $filters Optional filters (status, search, limit, offset)
     * @return array
     */
    public function getAll($filters = []) {
        global $wpdb;
        
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(email LIKE %s OR name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $limit_clause = '';
        if (!empty($filters['limit'])) {
            $limit = intval($filters['limit']);
            $offset = !empty($filters['offset']) ? intval($filters['offset']) : 0;
            $limit_clause = "LIMIT {$offset}, {$limit}";
        }
        
        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_at DESC {$limit_clause}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $rows = $wpdb->get_results($query, ARRAY_A);
        
        $subscribers = [];
        foreach ($rows as $row) {
            // Decode JSON fields
            if (!empty($row['interests'])) {
                $row['interests'] = json_decode($row['interests'], true);
            }
            $subscribers[] = new NewsletterSubscriber($row);
        }
        
        return $subscribers;
    }
    
    /**
     * Count subscribers with optional filters
     * 
     * @param array $filters Optional filters (status, search)
     * @return int
     */
    public function count($filters = []) {
        global $wpdb;
        
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(email LIKE %s OR name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return (int) $wpdb->get_var($query);
    }
}
