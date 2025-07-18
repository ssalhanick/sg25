# WordPress Theme and Plugin Development

This repository contains a custom WordPress theme based on Twenty Twenty-Five and custom plugins.

## Development Setup

### Prerequisites
- Node.js and npm
- Composer
- Docker Desktop
- wp-env (WordPress development environment)

### Installation

1. Clone the repository:
```bash
git clone [your-repo-url]
cd [your-project-name]
```

2. Install dependencies:
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

3. Start the development environment:
```bash
wp-env start
```

4. Access your local WordPress installation at:
- WordPress: http://localhost:8888
- WordPress Tests: http://localhost:8889

### Development Workflow

1. Make your changes locally
2. Test using wp-env
3. Commit and push to GitHub
4. Changes will be automatically deployed to Flywheel staging/production

## Project Structure

```
├── wp-content/
│   ├── plugins/          # Custom plugins
│   └── themes/          # Custom theme based on Twenty Twenty-Five
├── .wp-env.json         # wp-env configuration
├── .gitignore          # Git ignore rules
├── composer.json       # PHP dependencies
└── package.json        # Node.js dependencies
```

## Deployment

This project uses GitHub Actions for automated deployment to Flywheel. The workflow is:

1. Push to `main` branch → Deploy to production
2. Push to `staging` branch → Deploy to staging

## Contributing

1. Create a new branch for your feature
2. Make your changes
3. Submit a pull request

## License

[Your License] #   T e s t   d e p l o y m e n t  
 