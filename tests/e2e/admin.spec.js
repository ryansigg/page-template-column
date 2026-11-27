/**
 * The Template column on the Pages, Books and Notes screens, against the content in bin/seed.php.
 */
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const titles = ( page ) => page.locator( '#the-list .row-title' ).allInnerTexts();
const cell = ( page, title ) =>
	page.locator( '#the-list tr', { has: page.getByRole( 'link', { name: title, exact: true } ) } ).locator( 'td.column-template' );

test.describe( 'Pages', () => {
	test( 'shows each page’s template right after the title', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );

		const headers = await page.locator( 'thead th, thead td' ).evaluateAll( ( cells ) => cells.map( ( c ) => c.id ) );
		expect( headers.indexOf( 'template' ) ).toBe( headers.indexOf( 'title' ) + 1 );

		await expect( cell( page, 'About' ) ).toHaveText( 'Default template' );
		await expect( cell( page, 'Careers' ) ).toHaveText( 'Default template' );
		await expect( cell( page, 'Home' ) ).toHaveText( 'Landing Page' );
		await expect( cell( page, 'Contact' ) ).toHaveText( 'Full Width' );
	} );

	test( 'flags a template the theme no longer has', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const missing = cell( page, 'Old Promo' ).locator( 'a.page-template-column-missing' );
		await expect( missing ).toHaveText( 'templates/retired-promo.php (missing)' );
		await expect( missing ).toHaveAttribute( 'title', /no longer has it/ );
	} );

	test( 'dropdown lists every template with a count, and filters the list', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const select = page.getByLabel( 'Filter by template' );
		await expect( select.locator( 'option' ) ).toHaveText( [
			'All templates',
			'Default template (2)',
			'Full Width (1)',
			'Landing Page (2)',
			'templates/retired-promo.php (missing) (1)',
		] );

		await select.selectOption( { label: 'Landing Page (2)' } );
		await page.getByRole( 'button', { name: 'Filter', exact: true } ).click();

		await expect( page ).toHaveURL( /template_filter=templates%2Flanding\.php/ );
		expect( await titles( page ) ).toEqual( [ 'Home', 'Pricing' ] );
		await expect( select ).toHaveValue( 'templates/landing.php' );
	} );

	test( 'Default covers pages with no template meta and pages saved as "default"', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page&template_filter=default' );
		expect( ( await titles( page ) ).sort() ).toEqual( [ 'About', 'Careers' ] );
	} );

	test( 'clicking a template name filters to that template', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		await cell( page, 'Old Promo' ).getByRole( 'link' ).click();
		expect( await titles( page ) ).toEqual( [ 'Old Promo' ] );
	} );

	test( 'an unknown template in the URL is ignored, not an empty list', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page&template_filter=templates/nope.php' );
		expect( await titles( page ) ).toHaveLength( 6 );
		await expect( page.getByLabel( 'Filter by template' ) ).toHaveValue( '' );
	} );

	test( 'sorts by template name: default first, missing last, then reverses', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		await page.locator( 'thead #template a' ).click();
		await expect( page ).toHaveURL( /orderby=template&order=asc/ );
		expect( await titles( page ) ).toEqual( [ 'About', 'Careers', 'Contact', 'Home', 'Pricing', 'Old Promo' ] );

		await page.locator( 'thead #template a' ).click();
		await expect( page ).toHaveURL( /order=desc/ );
		expect( await titles( page ) ).toEqual( [ 'Old Promo', 'Home', 'Pricing', 'Contact', 'About', 'Careers' ] );
	} );

	test( 'sorting and filtering work together', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page&template_filter=templates%2Flanding.php&orderby=template&order=desc' );
		expect( await titles( page ) ).toEqual( [ 'Home', 'Pricing' ] );
	} );

	test( 'Quick Edit keeps the column, and shows the new template', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );

		const quickEdit = async ( title, template ) => {
			const row = page.locator( '#the-list tr', { has: page.getByRole( 'link', { name: title, exact: true } ) } );
			await row.hover();
			await row.getByRole( 'button', { name: /Quick edit/ } ).click();
			const editor = page.locator( '#the-list tr.inline-editor' );
			await editor.locator( 'select[name="page_template"]' ).selectOption( { label: template } );
			await editor.getByRole( 'button', { name: 'Update' } ).click();
			await expect( editor ).toHaveCount( 0 );
		};

		await quickEdit( 'Contact', 'Landing Page' );
		await expect( cell( page, 'Contact' ) ).toHaveText( 'Landing Page' );

		// Put it back for the other tests.
		await quickEdit( 'Contact', 'Full Width' );
		await expect( cell( page, 'Contact' ) ).toHaveText( 'Full Width' );
	} );

	test( 'has no accessibility violations in the column or the dropdown', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const results = await new AxeBuilder( { page } ).include( '.column-template' ).include( '#page-template-column-filter' ).analyze();
		expect( results.violations ).toEqual( [] );
	} );
} );

test.describe( 'Custom post types', () => {
	test( 'Books get the column, including templates no book uses yet', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=book' );
		await expect( cell( page, 'Field Guide' ) ).toHaveText( 'Book Feature' );
		await expect( cell( page, 'Short Stories' ) ).toHaveText( 'Default template' );
		await expect( page.getByLabel( 'Filter by template' ).locator( 'option' ) ).toHaveText( [
			'All templates',
			'Default template (1)',
			'Book Feature (2)',
			'Full Width (0)',
		] );
	} );

	test( 'Notes, with no templates at all, get no column and no dropdown', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=note' );
		await expect( page.locator( '#the-list .row-title' ) ).toHaveText( [ 'A note' ] );
		await expect( page.locator( 'th#template' ) ).toHaveCount( 0 );
		await expect( page.locator( '#page-template-column-filter' ) ).toHaveCount( 0 );
	} );
} );
