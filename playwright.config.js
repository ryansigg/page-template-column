/**
 * End-to-end tests for the Template column.
 *
 * Point WP_BASE_URL at a WordPress site with this plugin, the test theme and the sample
 * content from bin/seed.php. `npm run site:setup && npm run site:start` builds one locally.
 */
const { defineConfig, devices } = require( '@playwright/test' );

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8890';

module.exports = defineConfig( {
	testDir: './tests/e2e',
	outputDir: './artifacts/test-results',
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never', outputFolder: 'artifacts/report' } ] ] : [ [ 'list' ] ],
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{ name: 'setup', testMatch: /auth\.setup\.js/ },
		{
			name: 'admin',
			use: { ...devices[ 'Desktop Chrome' ], storageState: 'artifacts/admin.json' },
			dependencies: [ 'setup' ],
		},
	],
} );
