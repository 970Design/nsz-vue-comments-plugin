# 970 Design Headless Comments

Secure REST API endpoints for managing WordPress comments in headless applications with optional reCAPTCHA v3 spam protection.

## Features

- Secure API key authentication
- **Optional reCAPTCHA v3 spam protection**
- CORS support for headless apps
- Fetch approved comments
- Submit new comments
- Automatic moderation support
- Email validation & spam prevention

## Installation

1. Upload plugin to `/wp-content/plugins/970-design-headless-comments/`
2. Activate through WordPress admin
3. Go to **Settings → Headless Comments** to get your API key

## Configuration

### API Key
Auto-generated on activation. Find it at **Settings → Headless Comments**.

### Allowed Origins
Add your frontend domains (one per line):
```
http://localhost:4321
https://example.com
```

### reCAPTCHA v3 (Optional)
1. Get keys from [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Select **reCAPTCHA v3** when registering
3. Add your **Site Key** in plugin settings
4. Enable the checkbox to activate verification

## API Endpoints

Base URL: `{your-wp-site}/wp-json/headless-comments/v1`

Authentication: `X-API-Key: your_api_key` header

### GET reCAPTCHA Config
```
GET /recaptcha/config
```

Returns whether reCAPTCHA is enabled and the site key.

### GET Comments
```
GET /posts/{post_id}/comments
```

Returns approved comments with rendered HTML.

### POST Comment
```
POST /posts/{post_id}/comments
Content-Type: application/json

{
  "author_name": "John Doe",
  "author_email": "john@example.com",
  "content": "Great post!",
  "parent": 0,
  "recaptcha_token": "token-from-google-recaptcha"
}
```

**Note:** `recaptcha_token` is required only when reCAPTCHA is enabled.

## Frontend Implementation

### Basic Usage

```javascript
// Fetch comments
const comments = await fetch(
  `${endpoint}/wp-json/headless-comments/v1/posts/${postId}/comments`,
  { headers: { 'X-API-Key': apiKey } }
).then(res => res.json());

// Submit comment
const result = await fetch(
  `${endpoint}/wp-json/headless-comments/v1/posts/${postId}/comments`,
  {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': apiKey
    },
    body: JSON.stringify({
      author_name: 'John Doe',
      author_email: 'john@example.com',
      content: 'Great post!'
    })
  }
).then(res => res.json());
```

### With reCAPTCHA v3

```javascript
// 1. Check if reCAPTCHA is enabled
const config = await fetch(
  `${endpoint}/wp-json/headless-comments/v1/recaptcha/config`,
  { headers: { 'X-API-Key': apiKey } }
).then(res => res.json());

// 2. Load reCAPTCHA script (in your HTML)
// <script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>

// 3. Generate token and submit
if (config.enabled) {
  const token = await grecaptcha.execute(config.site_key, { action: 'submit' });
  // Include token in your comment submission
  body: JSON.stringify({
    author_name: 'John Doe',
    author_email: 'john@example.com',
    content: 'Great post!',
    recaptcha_token: token
  })
}
```

## Environment Variables

```env
COMMENTS_API_KEY=your_api_key_from_wordpress
WP_ENDPOINT=https://your-wordpress-site.com
```

## Troubleshooting

- **CORS errors:** Add your domain to Allowed Origins in plugin settings
- **401 errors:** Verify API key matches WordPress settings
- **reCAPTCHA errors:** Check site key, secret key, and domain registration
- **Comments not appearing:** Check if comments are approved in WordPress admin

## License

GPLv2 or later

## Author

[970 Design](https://970design.com/)
