const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );

const multisite = process.env.TESTBOOK_MULTISITE === '1';
const base = multisite
	? '/wp-admin/network/admin.php?page=updatronix'
	: '/wp-admin/tools.php?page=updatronix';

test( 'schedule picker boundaries', async ( { page } ) => {
	await page.goto( `${ base }&tab=schedule` );
	await expect(
		page.getByRole( 'heading', { level: 1, name: 'Schedule', exact: true } )
	).toBeVisible();

	const hour = page.getByLabel( 'Hour', { exact: true } );
	const minute = page.getByLabel( 'Minute', { exact: true } );
	await expect( hour ).toBeVisible();
	await expect( minute ).toBeVisible();

	// The time pickers are disabled until the recurrence uses a wall clock.
	const frequency = page.getByLabel( 'Frequency', { exact: true } );
	await frequency.click();
	const frequencies = await page
		.getByRole( 'option' )
		.evaluateAll( ( options ) =>
			options.map( ( option ) => ( option.textContent || '' ).trim() )
		);
	await page.getByRole( 'option', { name: 'Daily', exact: true } ).click();
	await expect( hour ).toBeEnabled();
	await expect( minute ).toBeEnabled();

	await hour.click();
	const hourValues = await page
		.getByRole( 'option' )
		.evaluateAll( ( options ) =>
			options.map( ( option ) => ( option.textContent || '' ).trim() )
		);
	await page.keyboard.press( 'Escape' );

	await minute.click();
	const minuteValues = await page
		.getByRole( 'option' )
		.evaluateAll( ( options ) =>
			options.map( ( option ) => ( option.textContent || '' ).trim() )
		);
	await page.keyboard.press( 'Escape' );

	const hours = hourValues.length;
	const minutes = minuteValues.length;

	expect( hours ).toBe( 24 );
	expect( minutes ).toBe( 60 );
	expect( hourValues[ 0 ] ).toMatch( /^0?0$/ );
	expect( hourValues[ hourValues.length - 1 ] ).toMatch( /^23$/ );
	expect( minuteValues[ 0 ] ).toMatch( /^0?0$/ );
	expect( minuteValues[ minuteValues.length - 1 ] ).toMatch( /^59$/ );

	const target = process.env.TESTBOOK_SCHEDULE_PATH;
	if ( target ) {
		fs.mkdirSync( path.dirname( target ), { recursive: true } );
		fs.writeFileSync(
			target,
			JSON.stringify(
				{ hours, minutes, hourValues, minuteValues, frequencies },
				null,
				2
			)
		);
	}
} );
