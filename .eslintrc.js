module.exports = {
    root: true,
    extends: [
        '@wordpress/eslint-plugin/recommended',
        'plugin:jsdoc/recommended'
    ],
    env: {
        browser: true,
        es6: true,
        jquery: true
    },
    globals: {
        wp: true,
        jQuery: true
    },
    rules: {
        'jsdoc/require-jsdoc': 'warn',
        'jsdoc/require-param': 'warn',
        'jsdoc/require-returns': 'warn',
        'no-console': ['warn', { allow: ['warn', 'error'] }]
    }
}; 