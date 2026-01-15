# KG Core - API Endpoints Quick Reference

## Base URL
`/wp-json/kg/v1`

## Public Endpoints (No Authentication Required)

### Recipes
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/recipes` | List all recipes (supports pagination, filtering) |
| GET | `/recipes/{slug}` | Get single recipe by slug (full details) |
| GET | `/recipes/by-age/{age}` | Get recipes for specific age group |
| GET | `/recipes/featured` | Get featured recipes (max 5) |
| GET | `/recipes/filter` | Advanced recipe filtering |

### Ingredients
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ingredients` | List all ingredients |
| GET | `/ingredients/{slug}` | Get single ingredient (full details) |
| GET | `/ingredients/search?q={query}` | Search ingredients |

### Search
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/search?q={query}&type={type}` | Global search (recipes & ingredients) |

### Authentication (Public)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login and get JWT token |

## Protected Endpoints (Require JWT Authentication)

**Header Required:** `Authorization: Bearer {token}`

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/logout` | Logout current user |
| GET | `/auth/me` | Get current user info |

### User Profile
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/user/profile` | Get user profile |
| PUT | `/user/profile` | Update user profile |

### Children Management
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/user/children` | Get all children profiles |
| POST | `/user/children` | Add new child profile |
| PUT | `/user/children/{id}` | Update child profile |
| DELETE | `/user/children/{id}` | Delete child profile |

### Favorites
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/user/favorites` | Get favorite recipes |
| POST | `/user/favorites` | Add recipe to favorites |
| DELETE | `/user/favorites/{id}` | Remove recipe from favorites |

### Shopping List
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/user/shopping-list` | Get shopping list |
| POST | `/user/shopping-list` | Add item to shopping list |
| DELETE | `/user/shopping-list/{id}` | Remove item from shopping list |

## Query Parameters

### Recipes Endpoint
- `page` (int): Page number
- `per_page` (int): Results per page
- `age_group` (string): Filter by age group slug
- `diet_type` (string): Filter by diet type slug
- `allergen` (string): Exclude allergen

### Search Endpoint
- `q` (string): Search query (required)
- `type` (string): 'recipe', 'ingredient', or 'all'
- `age_group` (string): Filter by age (recipes only)

## Response Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 404 | Not Found |
| 409 | Conflict (e.g., email exists) |

## Example Usage

### Get All Recipes
```bash
curl https://yoursite.com/wp-json/kg/v1/recipes
```

### Get Recipe by Slug
```bash
curl https://yoursite.com/wp-json/kg/v1/recipes/bal-kabakli-bebek-corbasi
```

### Register User
```bash
curl -X POST https://yoursite.com/wp-json/kg/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "securepass",
    "name": "User Name"
  }'
```

### Login
```bash
curl -X POST https://yoursite.com/wp-json/kg/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "securepass"
  }'
```

### Get Favorites (Authenticated)
```bash
curl https://yoursite.com/wp-json/kg/v1/user/favorites \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
```

### Search
```bash
curl "https://yoursite.com/wp-json/kg/v1/search?q=kabak&type=recipe"
```

## Total Endpoints: 31

- **Public:** 11 endpoints
- **Protected:** 20 endpoints
