# KG Core - Testing Guide

## Quick Test Commands

### Prerequisites
- WordPress installed and running
- KG Core plugin activated
- REST API accessible

## Test with cURL

### 1. Test Public Endpoints

#### Get All Recipes
```bash
curl -X GET "http://localhost/wp-json/kg/v1/recipes" \
  -H "Content-Type: application/json"
```

#### Get Recipe by Slug
```bash
curl -X GET "http://localhost/wp-json/kg/v1/recipes/test-recipe" \
  -H "Content-Type: application/json"
```

#### Get Recipes by Age Group
```bash
curl -X GET "http://localhost/wp-json/kg/v1/recipes/by-age/6-12-ay" \
  -H "Content-Type: application/json"
```

#### Get Featured Recipes
```bash
curl -X GET "http://localhost/wp-json/kg/v1/recipes/featured" \
  -H "Content-Type: application/json"
```

#### Get All Ingredients
```bash
curl -X GET "http://localhost/wp-json/kg/v1/ingredients" \
  -H "Content-Type: application/json"
```

#### Get Ingredient by Slug
```bash
curl -X GET "http://localhost/wp-json/kg/v1/ingredients/havuc" \
  -H "Content-Type: application/json"
```

#### Search Ingredients
```bash
curl -X GET "http://localhost/wp-json/kg/v1/ingredients/search?q=havuc" \
  -H "Content-Type: application/json"
```

#### Global Search
```bash
curl -X GET "http://localhost/wp-json/kg/v1/search?q=kabak&type=all" \
  -H "Content-Type: application/json"
```

### 2. Test Authentication

#### Register User
```bash
curl -X POST "http://localhost/wp-json/kg/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123",
    "name": "Test User"
  }'
```

Expected response includes JWT token.

#### Login
```bash
curl -X POST "http://localhost/wp-json/kg/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123"
  }'
```

Save the token from response for authenticated requests.

### 3. Test Protected Endpoints

Replace `YOUR_TOKEN` with the token from login/register response.

#### Get Current User
```bash
curl -X GET "http://localhost/wp-json/kg/v1/auth/me" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Get User Profile
```bash
curl -X GET "http://localhost/wp-json/kg/v1/user/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Update Profile
```bash
curl -X PUT "http://localhost/wp-json/kg/v1/user/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "phone": "+90 555 123 4567"
  }'
```

#### Add Child Profile
```bash
curl -X POST "http://localhost/wp-json/kg/v1/user/children" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ali",
    "birth_date": "2024-06-15",
    "allergens": ["süt", "yumurta"],
    "notes": "Laktozsuz süt kullanılmalı"
  }'
```

#### Get Children List
```bash
curl -X GET "http://localhost/wp-json/kg/v1/user/children" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Add to Favorites
```bash
curl -X POST "http://localhost/wp-json/kg/v1/user/favorites" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "recipe_id": 123
  }'
```

#### Get Favorites
```bash
curl -X GET "http://localhost/wp-json/kg/v1/user/favorites" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Add to Shopping List
```bash
curl -X POST "http://localhost/wp-json/kg/v1/user/shopping-list" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "item": "Havuç",
    "quantity": "500g"
  }'
```

#### Get Shopping List
```bash
curl -X GET "http://localhost/wp-json/kg/v1/user/shopping-list" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

#### Logout
```bash
curl -X POST "http://localhost/wp-json/kg/v1/auth/logout" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

## Testing with JavaScript (Fetch API)

### Basic Setup
```javascript
const BASE_URL = 'http://localhost/wp-json/kg/v1';
let authToken = null;

// Helper function
async function apiRequest(endpoint, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
  };
  
  if (authToken && !headers.Authorization) {
    headers.Authorization = `Bearer ${authToken}`;
  }
  
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Request failed');
  }
  
  return response.json();
}
```

### Register and Login
```javascript
// Register
async function register() {
  const data = await apiRequest('/auth/register', {
    method: 'POST',
    body: JSON.stringify({
      email: 'test@example.com',
      password: 'SecurePass123',
      name: 'Test User'
    })
  });
  
  authToken = data.token;
  console.log('Registered:', data);
  return data;
}

// Login
async function login() {
  const data = await apiRequest('/auth/login', {
    method: 'POST',
    body: JSON.stringify({
      email: 'test@example.com',
      password: 'SecurePass123'
    })
  });
  
  authToken = data.token;
  console.log('Logged in:', data);
  return data;
}
```

### Get Recipes
```javascript
async function getRecipes(filters = {}) {
  const params = new URLSearchParams(filters);
  const data = await apiRequest(`/recipes?${params}`);
  console.log('Recipes:', data);
  return data;
}

// Example usage
getRecipes({ age_group: '6-12-ay', per_page: 10 });
```

### Get Recipe by Slug
```javascript
async function getRecipe(slug) {
  const data = await apiRequest(`/recipes/${slug}`);
  console.log('Recipe:', data);
  return data;
}

// Example usage
getRecipe('bal-kabakli-bebek-corbasi');
```

### Add Child Profile
```javascript
async function addChild(childData) {
  const data = await apiRequest('/user/children', {
    method: 'POST',
    body: JSON.stringify(childData)
  });
  console.log('Child added:', data);
  return data;
}

// Example usage
addChild({
  name: 'Ali',
  birth_date: '2024-06-15',
  allergens: ['süt', 'yumurta'],
  notes: 'Laktozsuz süt kullanılmalı'
});
```

## Expected Responses

### Success Responses

#### Recipe List
```json
{
  "recipes": [
    {
      "id": 123,
      "title": "Bal Kabaklı Çorba",
      "slug": "bal-kabakli-corba",
      "image": "http://example.com/image.jpg",
      "prep_time": "15",
      "age_groups": ["6-12 ay"],
      "is_featured": true
    }
  ],
  "total": 45,
  "pages": 5
}
```

#### Login Response
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user_id": 1,
  "email": "test@example.com",
  "name": "Test User"
}
```

#### Children List
```json
[
  {
    "id": "unique-id-123",
    "name": "Ali",
    "birth_date": "2024-06-15",
    "allergens": ["süt", "yumurta"],
    "notes": "Laktozsuz süt kullanılmalı"
  }
]
```

### Error Responses

#### 400 Bad Request
```json
{
  "code": "missing_fields",
  "message": "Email and password are required",
  "data": {
    "status": 400
  }
}
```

#### 401 Unauthorized
```json
{
  "code": "invalid_credentials",
  "message": "Invalid email or password",
  "data": {
    "status": 401
  }
}
```

#### 404 Not Found
```json
{
  "code": "recipe_not_found",
  "message": "Recipe not found",
  "data": {
    "status": 404
  }
}
```

## Testing Checklist

### Public Endpoints
- [ ] Get all recipes
- [ ] Get recipe by slug
- [ ] Get recipes by age group
- [ ] Get featured recipes
- [ ] Get all ingredients
- [ ] Get ingredient by slug
- [ ] Search ingredients
- [ ] Global search

### Authentication
- [ ] Register with valid data
- [ ] Register with weak password (should fail)
- [ ] Register with existing email (should fail)
- [ ] Login with valid credentials
- [ ] Login with invalid credentials (should fail)
- [ ] Get current user info
- [ ] Logout

### User Management
- [ ] Get user profile
- [ ] Update user profile
- [ ] Add child profile
- [ ] Get children list
- [ ] Update child profile
- [ ] Delete child profile

### Favorites
- [ ] Add recipe to favorites
- [ ] Get favorites list
- [ ] Remove from favorites

### Shopping List
- [ ] Add item to shopping list
- [ ] Get shopping list
- [ ] Remove item from shopping list

### CORS
- [ ] Verify CORS headers in responses
- [ ] Test from allowed origin
- [ ] Test OPTIONS preflight request

## Troubleshooting

### Token Invalid
- Token may have expired (24h lifetime)
- Get new token by logging in again

### CORS Errors
- Check allowed origins in kg-core.php
- Use filter to add your domain:
  ```php
  add_filter('kg_core_allowed_origins', function($origins) {
      return array_merge($origins, ['http://your-domain.com']);
  });
  ```

### 404 on Endpoints
- Check WordPress permalink settings
- Flush rewrite rules (Settings > Permalinks > Save)

### No Data Returned
- Create test recipes/ingredients in WordPress admin
- Check post status is 'publish'

## Performance Testing

### Load Testing with Apache Bench
```bash
# Test recipe list endpoint
ab -n 100 -c 10 http://localhost/wp-json/kg/v1/recipes

# Test with authentication
ab -n 100 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/wp-json/kg/v1/user/favorites
```

## Security Testing

### Test Password Complexity
```bash
# Should fail - too short
curl -X POST "http://localhost/wp-json/kg/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"Short1","name":"Test"}'

# Should fail - no uppercase
curl -X POST "http://localhost/wp-json/kg/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"lowercase123","name":"Test"}'

# Should succeed
curl -X POST "http://localhost/wp-json/kg/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"SecurePass123","name":"Test"}'
```

### Test Unauthorized Access
```bash
# Should fail without token
curl -X GET "http://localhost/wp-json/kg/v1/user/profile" \
  -H "Content-Type: application/json"

# Should fail with invalid token
curl -X GET "http://localhost/wp-json/kg/v1/user/profile" \
  -H "Authorization: Bearer invalid-token" \
  -H "Content-Type: application/json"
```

## Next Steps

1. Create test recipes and ingredients in WordPress admin
2. Run through the testing checklist
3. Integrate with frontend application
4. Monitor WordPress debug.log for any errors
5. Verify CORS configuration for production domains
