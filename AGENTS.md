# AGENTS.md - Development Workflow Guide

## 🚀 Quick Start
This document serves as a comprehensive guide for AI-assisted development workflows, local development setup, CI/CD processes, and common task templates.

## 📁 Project Structure
```
wp-content/
├── plugins/
│   ├── sg-humanitix-api-importer/
│   ├── sg-eventbrite-course-importer/
│   └── [other plugins]
├── themes/
└── AGENTS.md
```

## 🛠️ Local Development Workflow

### Prerequisites
- XAMPP installed and running
- WordPress installed in `C:\Program Files\xampp\htdocs\wordpress\`
- Git for version control
- PHP 8.0+ (via XAMPP)
- Composer (for dependency management)

### Development Environment Setup
1. **Start XAMPP Services**
   ```bash
   # Start Apache and MySQL services
   # Access via XAMPP Control Panel or:
   net start Apache2.4
   net start MySQL
   ```

2. **WordPress Access**
   - Local URL: `http://localhost/wordpress/`
   - Admin: `http://localhost/wordpress/wp-admin/`

3. **Plugin Development**
   - Plugins located in: `wp-content/plugins/`
   - Each plugin should have its own directory
   - Follow WordPress Plugin API standards

### Development Best Practices
- **Code Standards**: Follow WordPress Coding Standards (WPCS)
- **Version Control**: Use Git with meaningful commit messages
- **Testing**: Test on local environment before deployment
- **Documentation**: Document all custom functions and hooks
- **Security**: Sanitize inputs, escape outputs, use nonces

## 🔄 CI/CD Pipeline Process

### Git Workflow
1. **Feature Branches**
   ```bash
   git checkout -b feature/plugin-name
   git add .
   git commit -m "feat: add new functionality"
   git push origin feature/plugin-name
   ```

2. **Pull Request Process**
   - Create PR from feature branch to main
   - Include detailed description of changes
   - Request code review
   - Run automated tests (if available)

3. **Deployment**
   - Merge to main branch
   - Tag releases: `git tag v1.0.0`
   - Deploy to staging/production

### Automated Checks
- Code linting (PHPCS)
- Security scanning
- Unit tests (if implemented)
- Plugin activation tests

## 📋 Common Task Templates

### 1. Plugin Development
```markdown
## Task: Create New WordPress Plugin

### Requirements
- [ ] Plugin name and description
- [ ] Main functionality requirements
- [ ] Database schema (if needed)
- [ ] Admin interface requirements
- [ ] Frontend display requirements

### Implementation Steps
1. Create plugin directory structure
2. Create main plugin file with header
3. Implement activation/deactivation hooks
4. Add admin menu and settings
5. Implement core functionality
6. Add frontend display
7. Test and debug
8. Document code

### Files to Create
- `plugin-name.php` (main plugin file)
- `includes/` (core functionality)
- `admin/` (admin interface)
- `assets/` (CSS/JS)
- `languages/` (translations)
```

### 2. API Integration
```markdown
## Task: Integrate External API

### Requirements
- [ ] API endpoint and authentication
- [ ] Data mapping requirements
- [ ] Error handling strategy
- [ ] Caching strategy
- [ ] Rate limiting considerations

### Implementation Steps
1. Research API documentation
2. Set up authentication
3. Create API client class
4. Implement data fetching
5. Add error handling
6. Implement caching
7. Add admin settings for API keys
8. Test with real data
```

### 3. Database Operations
```markdown
## Task: Custom Database Operations

### Requirements
- [ ] Table structure
- [ ] CRUD operations needed
- [ ] Data validation rules
- [ ] Migration strategy

### Implementation Steps
1. Design database schema
2. Create migration functions
3. Implement CRUD operations
4. Add data validation
5. Create admin interface
6. Test all operations
7. Document database structure
```

### 4. Frontend Customization
```markdown
## Task: Frontend Customization

### Requirements
- [ ] Design specifications
- [ ] Responsive requirements
- [ ] Browser compatibility
- [ ] Performance requirements

### Implementation Steps
1. Create custom CSS/JS files
2. Enqueue scripts and styles
3. Implement responsive design
4. Add browser compatibility
5. Optimize for performance
6. Test across devices
7. Validate HTML/CSS
```

### 5. Adding New Features
```markdown
## Task: Add New Feature

### Requirements
- [ ] Feature description and scope
- [ ] User interface requirements
- [ ] Database changes needed
- [ ] Integration points
- [ ] Testing requirements

### Implementation Steps
1. Analyze existing codebase
2. Plan feature architecture
3. Create/update database schema
4. Implement backend functionality
5. Create frontend interface
6. Add configuration options
7. Implement error handling
8. Test thoroughly
9. Update documentation
10. Deploy and monitor
```

### 6. Debugging Issues
```markdown
## Task: Debug Issue

### Requirements
- [ ] Error description and symptoms
- [ ] Steps to reproduce
- [ ] Expected vs actual behavior
- [ ] Environment details
- [ ] Error logs (if available)

### Implementation Steps
1. Reproduce the issue
2. Check error logs
3. Enable debug mode
4. Add logging statements
5. Test in isolation
6. Identify root cause
7. Implement fix
8. Test fix thoroughly
9. Document solution
10. Prevent regression
```

### 7. Code Optimization
```markdown
## Task: Optimize Code Section

### Requirements
- [ ] Performance bottlenecks identified
- [ ] Current performance metrics
- [ ] Target performance goals
- [ ] Areas to optimize

### Implementation Steps
1. Profile current performance
2. Identify bottlenecks
3. Analyze database queries
4. Optimize algorithms
5. Implement caching
6. Reduce HTTP requests
7. Optimize images/assets
8. Minify CSS/JS
9. Test performance improvements
10. Monitor and validate
```

### 8. Project Catch-up
```markdown
## Task: Catch Up on Project

### Requirements
- [ ] Time since last work
- [ ] Key changes made
- [ ] Current project state
- [ ] Outstanding issues

### Implementation Steps
1. Review recent commits/changes
2. Check current project status
3. Identify new features added
4. Review any bug fixes
5. Check for breaking changes
6. Update local environment
7. Test current functionality
8. Review documentation
9. Identify next priorities
10. Plan next steps
```

### 9. Context Reset & Summary
```markdown
## Task: Summarize Chat & Reset Context

### Requirements
- [ ] Current conversation summary
- [ ] Key decisions made
- [ ] Code changes implemented
- [ ] Next steps identified

### Implementation Steps
1. Review entire conversation
2. Extract key technical decisions
3. List all code changes made
4. Identify unresolved issues
5. Note important context
6. Create comprehensive summary
7. Reset AI context
8. Provide clear handoff
9. Document lessons learned
10. Update AGENTS.md if needed
```

## 🔧 Development Tools & Commands

### Useful Commands
```bash
# WordPress CLI (if installed)
wp plugin list
wp plugin activate plugin-name
wp db export backup.sql

# Git commands
git status
git log --oneline
git diff HEAD~1

# File operations
# Create directory structure
mkdir -p plugin-name/{includes,admin,assets,languages}

# Find files
find . -name "*.php" -type f
```

### Debugging
- Enable WordPress debug: `WP_DEBUG = true` in wp-config.php
- Use `error_log()` for debugging
- Check error logs in XAMPP logs directory
- Use browser developer tools for frontend issues

## 📚 Code Templates

### Plugin Header Template
```php
<?php
/**
 * Plugin Name: Plugin Name
 * Plugin URI: https://yourwebsite.com
 * Description: Plugin description
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: plugin-textdomain
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PLUGIN_NAME_VERSION', '1.0.0');
define('PLUGIN_NAME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PLUGIN_NAME_PLUGIN_URL', plugin_dir_url(__FILE__));
```

### Class Template
```php
<?php
class Plugin_Class_Name {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Initialization code
    }
    
    private function helper_method() {
        // Helper method implementation
    }
}
```

### Admin Menu Template
```php
add_action('admin_menu', 'add_admin_menu');

function add_admin_menu() {
    add_options_page(
        'Plugin Settings',
        'Plugin Name',
        'manage_options',
        'plugin-settings',
        'plugin_settings_page'
    );
}

function plugin_settings_page() {
    // Settings page HTML
}
```

## 🚨 Common Issues & Solutions

### Plugin Activation Issues
- Check for PHP syntax errors
- Verify plugin header format
- Check for missing dependencies
- Review error logs

### Database Issues
- Use `$wpdb` for database operations
- Always sanitize inputs
- Use prepared statements
- Check table prefixes

### Performance Issues
- Implement caching
- Optimize database queries
- Minimize HTTP requests
- Use lazy loading

## 📝 Documentation Standards

### Code Comments
```php
/**
 * Brief description of the function
 *
 * @param string $param1 Description of parameter
 * @param int $param2 Description of parameter
 * @return bool Description of return value
 */
function example_function($param1, $param2) {
    // Implementation
}
```

### README Template
- Plugin description
- Installation instructions
- Configuration options
- Usage examples
- Changelog
- Support information

## 🔐 Security Checklist

- [ ] Sanitize all inputs
- [ ] Escape all outputs
- [ ] Use nonces for forms
- [ ] Check user capabilities
- [ ] Validate file uploads
- [ ] Use prepared statements
- [ ] Implement rate limiting
- [ ] Regular security updates

## 📞 Support & Resources

### WordPress Resources
- [WordPress Codex](https://codex.wordpress.org/)
- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [WordPress Plugin API](https://developer.wordpress.org/plugins/)

### Development Tools
- [WordPress CLI](https://wp-cli.org/)
- [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer)
- [XAMPP](https://www.apachefriends.org/)

---

## 🤖 AI Assistant Guidelines

When working with AI assistants:
1. **Be Specific**: Provide clear, detailed requirements
2. **Provide Context**: Include relevant file paths and current state
3. **Iterate**: Review and refine solutions together
4. **Test**: Always test changes in development environment
5. **Document**: Keep this file updated with new patterns and solutions

### Common AI Prompts
- "Create a WordPress plugin that..."
- "Fix the error in [file] at line [number]"
- "Optimize this code for better performance"
- "Add error handling to this function"
- "Create an admin interface for..."
- "Context reset - summarize our conversation and reset your context"

---

*Last updated: [Current Date]*
*Version: 1.0.0*

**Note:** This document will continue to be updated and refined as we work together to improve our development workflow and add new patterns, solutions, and best practices that emerge from our collaboration.
