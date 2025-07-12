# Humanitix API Importer - Troubleshooting Guide

## API Connection Issues

If you're experiencing API connection failures, follow these steps to troubleshoot:

### 1. Test with the Mock Server

First, test with the Humanitix mock server to ensure your plugin is working correctly:

1. Go to your WordPress admin → Humanitix Importer → Settings
2. Set the API Endpoint to: `https://stoplight.io/mocks/humanitix/humanitix-public-api/259010741`
3. Set any dummy API key (e.g., `test-key`)
4. Click "Test API Connection"

**Important:** The mock server may return 422 status codes, which is normal and indicates the server is working. The 422 error means "endpoint not found" but confirms the server is reachable.

### 2. Check Your API Key

- Ensure you have a valid Humanitix API key
- Verify the API key is correctly copied (no extra spaces)
- Check if your API key has the necessary permissions
- **Important:** The Humanitix API uses `x-api-key` header format, not `Authorization: Bearer`

### 3. Test API Connection

Use the built-in API test functionality:

1. **Configure your API settings** in WordPress Admin → Humanitix Importer → Settings
2. **Save the settings**
3. **Click "Test API Connection"** button
4. **Check the output** for specific error messages

The plugin will automatically test multiple endpoints and provide detailed feedback.

### 4. Common Error Codes

- **401 Unauthorized**: Invalid or missing API key
- **403 Forbidden**: API key doesn't have required permissions
- **404 Not Found**: Incorrect API endpoint URL
- **422 Unprocessable Entity**: 
  - For mock server: Normal response indicating server is working but endpoint not found
  - For live API: Invalid request format or endpoint
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Server Error**: Server-side issue

### 5. Server Configuration Issues

Check if your server can make outbound HTTPS requests:

```bash
# Test basic connectivity
curl -I https://api.humanitix.com/v1/events

# Test with your API key
curl -H "x-api-key: YOUR_API_KEY" https://api.humanitix.com/v1/events
```

### 6. WordPress-Specific Issues

- **SSL Certificate Issues**: Some servers have SSL verification problems
- **Timeout Issues**: Increase timeout in wp-config.php: `define('WP_HTTP_BLOCK_EXTERNAL', false);`
- **Firewall/Proxy**: Check if your server blocks outbound requests

### 7. Debug Information

The plugin provides detailed debug information. When testing the API connection, you'll see:

- HTTP status codes
- Response headers
- Response body (for errors)
- Endpoint being used
- Whether it's a mock server or live API

### 8. Humanitix API Documentation

Refer to the official documentation for more details:
- [Humanitix Public API](https://humanitix.stoplight.io/docs/humanitix-public-api/e508a657c1467-humanitix-public-api)
- [Mock Server](https://stoplight.io/mocks/humanitix/humanitix-public-api/259010741)

### 9. Getting Help

If you're still having issues:

1. Check the WordPress debug log for errors
2. Enable WP_DEBUG in wp-config.php
3. Check the plugin's log files (if logging is enabled)
4. Contact your hosting provider about outbound HTTPS requests
5. Verify your Humanitix account has API access enabled

### 10. Alternative Testing Methods

You can also test the API using:

- **Postman**: Import the Humanitix API collection
- **cURL**: Use command line tools
- **Browser**: Test simple GET requests (without authentication)

## Plugin-Specific Issues

### Settings Page Loading Twice
- This was a known issue that has been fixed
- Ensure you're using the latest version of the plugin

### Import Not Working
- Check if The Events Calendar plugin is installed and activated
- Verify your API key has event read permissions
- Check the import logs for specific error messages

### Performance Issues
- Reduce the import limit if you're importing many events
- Consider using the mock server for testing large imports
- Check your server's memory and execution time limits

## Mock Server Specific Notes

The Humanitix mock server behaves differently from the live API:

- **422 Responses**: These are normal and indicate the server is working
- **Endpoint Paths**: May differ from the live API
- **Authentication**: May not require real API keys
- **Response Format**: May return different data structures

When testing with the mock server, focus on whether you get a response (any status code) rather than expecting specific 200 responses. 