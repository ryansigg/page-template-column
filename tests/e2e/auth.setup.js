/**
 * Signs in once and saves the session for the admin tests.
 */
const { test: setup, expect } = require( '@playwright/test' );

setup( 'sign in as admin', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( process.env.WP_USERNAME || 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( process.env.WP_PASSWORD || 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	await page.context().storageState( { path: 'artifacts/admin.json' } );
} );
