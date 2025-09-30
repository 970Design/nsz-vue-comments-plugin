# 970 Design Headless Comments

Secure REST API endpoints for managing WordPress comments in headless applications.

## Features

- Secure API key authentication
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

## API Endpoints

Base URL: `{your-wp-site}/wp-json/headless-comments/v1`

Authentication via header: `X-API-Key: your_api_key`

### GET Comments
```
GET /posts/{post_id}/comments
```

Returns array of approved comments.

### POST Comment
```
POST /posts/{post_id}/comments
Content-Type: application/json

{
  "author_name": "John Doe",
  "author_email": "john@example.com",
  "content": "Great post!",
  "parent": 0
}
```

Returns:
```json
{
  "success": true,
  "comment_id": 123,
  "message": "Comment submitted successfully!",
  "approved": true
}
```

## Frontend Example

```vue
<script setup>
const fetchComments = async () => {
  const response = await fetch(
    `${endpoint}/wp-json/headless-comments/v1/posts/${postId}/comments`,
    { headers: { 'X-API-Key': apiKey } }
  );
  return await response.json();
};

const submitComment = async (data) => {
  const response = await fetch(
    `${endpoint}/wp-json/headless-comments/v1/posts/${postId}/comments`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey
      },
      body: JSON.stringify(data)
    }
  );
  return await response.json();
};
</script>
```

## Environment Variables

```
COMMENTS_API_KEY=your_api_key_from_wordpress
WP_ENDPOINT=https://your-wordpress-site.com
```

## Troubleshooting

**CORS errors:** Add your domain to Allowed Origins in plugin settings

**401 errors:** Verify API key matches the one in WordPress settings

**Comments not appearing:** Check if comments are approved in WordPress admin

## License

GPLv2 or later

## Author

[970 Design](https://970design.com/)
