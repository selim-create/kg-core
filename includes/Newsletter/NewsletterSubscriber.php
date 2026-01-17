<?php
namespace KG_Core\Newsletter;

/**
 * NewsletterSubscriber - Model class for newsletter subscribers
 * 
 * Represents a newsletter subscriber with all associated data
 */
class NewsletterSubscriber {
    
    public $id;
    public $email;
    public $name;
    public $status; // pending, active, unsubscribed
    public $source;
    public $interests;
    public $confirmation_token;
    public $ip_address;
    public $user_agent;
    public $subscribed_at;
    public $confirmed_at;
    public $unsubscribed_at;
    public $created_at;
    public $updated_at;
    
    /**
     * Constructor
     * 
     * @param array $data Subscriber data
     */
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->fill($data);
        }
    }
    
    /**
     * Fill model with data
     * 
     * @param array $data Subscriber data
     */
    public function fill($data) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
    
    /**
     * Convert model to array
     * 
     * @return array
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'source' => $this->source,
            'interests' => $this->interests,
            'confirmation_token' => $this->confirmation_token,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'subscribed_at' => $this->subscribed_at,
            'confirmed_at' => $this->confirmed_at,
            'unsubscribed_at' => $this->unsubscribed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    /**
     * Check if subscriber is active
     * 
     * @return bool
     */
    public function is_active() {
        return $this->status === 'active';
    }
    
    /**
     * Check if subscriber is pending confirmation
     * 
     * @return bool
     */
    public function is_pending() {
        return $this->status === 'pending';
    }
    
    /**
     * Check if subscriber has unsubscribed
     * 
     * @return bool
     */
    public function is_unsubscribed() {
        return $this->status === 'unsubscribed';
    }
}
