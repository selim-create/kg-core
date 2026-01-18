<?php
namespace KG_Core\Services;

/**
 * ChildAvatarService - Handle child profile avatar uploads and management
 */
class ChildAvatarService {
    
    const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
    const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    
    /**
     * Upload and store child avatar
     * 
     * @param array $file Uploaded file from $_FILES
     * @param int $user_id Parent user ID
     * @param string $child_uuid Child profile UUID
     * @return array|WP_Error Avatar info or error
     */
    public function upload_avatar( $file, $user_id, $child_uuid ) {
        // Validate file
        $validation = $this->validate_file( $file );
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }
        
        // Generate file path
        $file_info = pathinfo( $file['name'] );
        $extension = strtolower( $file_info['extension'] );
        $timestamp = time();
        $filename = "avatar_{$timestamp}.{$extension}";
        
        // Create directory structure: private/child-avatars/{user_id}/{child_uuid}/
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/private/child-avatars/' . $user_id . '/' . $child_uuid;
        
        // Create directory if it doesn't exist
        if ( ! file_exists( $base_dir ) ) {
            if ( ! wp_mkdir_p( $base_dir ) ) {
                return new \WP_Error(
                    'directory_creation_failed',
                    'Failed to create avatar directory',
                    [ 'status' => 500 ]
                );
            }
            
            // Add .htaccess to prevent direct access
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents( $base_dir . '/.htaccess', $htaccess_content );
        }
        
        $file_path = $base_dir . '/' . $filename;
        
        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            return new \WP_Error(
                'file_upload_failed',
                'Failed to move uploaded file',
                [ 'status' => 500 ]
            );
        }
        
        // Set proper permissions
        chmod( $file_path, 0600 );
        
        // Generate relative path for storage
        $relative_path = 'private/child-avatars/' . $user_id . '/' . $child_uuid . '/' . $filename;
        
        return [
            'path' => $relative_path,
            'filename' => $filename,
            'size' => $file['size'],
            'mime_type' => $file['type'],
            'uploaded_at' => current_time( 'c' )
        ];
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file Uploaded file
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    private function validate_file( $file ) {
        // Check if file was uploaded
        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new \WP_Error(
                'no_file_uploaded',
                'No file was uploaded',
                [ 'status' => 400 ]
            );
        }
        
        // Check file size
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error(
                'file_too_large',
                'File size exceeds 2MB limit',
                [ 'status' => 400 ]
            );
        }
        
        // Check file extension
        $file_info = pathinfo( $file['name'] );
        $extension = strtolower( $file_info['extension'] ?? '' );
        
        if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS ) ) {
            return new \WP_Error(
                'invalid_file_type',
                'File type not allowed. Allowed types: jpg, jpeg, png, webp',
                [ 'status' => 400 ]
            );
        }
        
        // Check MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        
        if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES ) ) {
            return new \WP_Error(
                'invalid_mime_type',
                'Invalid file content. Only image files are allowed',
                [ 'status' => 400 ]
            );
        }
        
        return true;
    }
    
    /**
     * Delete avatar file
     * 
     * @param string $avatar_path Relative path to avatar
     * @return bool True if deleted, false otherwise
     */
    public function delete_avatar( $avatar_path ) {
        if ( empty( $avatar_path ) ) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $avatar_path;
        
        if ( file_exists( $full_path ) ) {
            return unlink( $full_path );
        }
        
        return false;
    }
    
    /**
     * Generate temporary signed URL for avatar
     * 
     * @param string $avatar_path Relative path to avatar
     * @param int $expiration Expiration time in seconds (default: 900 = 15 minutes)
     * @return string|WP_Error Signed URL or error
     */
    public function get_signed_url( $avatar_path, $expiration = 900 ) {
        if ( empty( $avatar_path ) ) {
            return new \WP_Error(
                'no_avatar',
                'No avatar found for this profile',
                [ 'status' => 404 ]
            );
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $avatar_path;
        
        if ( ! file_exists( $full_path ) ) {
            return new \WP_Error(
                'avatar_not_found',
                'Avatar file not found',
                [ 'status' => 404 ]
            );
        }
        
        // Generate signed URL using nonce
        $expires = time() + $expiration;
        $token = wp_create_nonce( 'child_avatar_' . $avatar_path . '_' . $expires );
        
        $url = rest_url( 'kg/v1/child-profiles/avatar-file' );
        $url = add_query_arg( [
            'path' => base64_encode( $avatar_path ),
            'expires' => $expires,
            'token' => $token
        ], $url );
        
        return $url;
    }
    
    /**
     * Verify signed URL and serve file
     * 
     * @param string $encoded_path Base64 encoded avatar path
     * @param int $expires Expiration timestamp
     * @param string $token Security token
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function verify_and_serve( $encoded_path, $expires, $token ) {
        // Check expiration
        if ( time() > $expires ) {
            return new \WP_Error(
                'url_expired',
                'Signed URL has expired',
                [ 'status' => 403 ]
            );
        }
        
        // Decode path
        $avatar_path = base64_decode( $encoded_path );
        
        // Verify token
        if ( ! wp_verify_nonce( $token, 'child_avatar_' . $avatar_path . '_' . $expires ) ) {
            return new \WP_Error(
                'invalid_token',
                'Invalid security token',
                [ 'status' => 403 ]
            );
        }
        
        // Get file
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/' . $avatar_path;
        
        if ( ! file_exists( $full_path ) ) {
            return new \WP_Error(
                'file_not_found',
                'Avatar file not found',
                [ 'status' => 404 ]
            );
        }
        
        // Serve file - set headers BEFORE reading file
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $full_path );
        finfo_close( $finfo );
        
        header( 'Content-Type: ' . $mime_type );
        header( 'Content-Length: ' . filesize( $full_path ) );
        header( 'Cache-Control: private, max-age=3600' );
        readfile( $full_path );
        exit;
    }
}
