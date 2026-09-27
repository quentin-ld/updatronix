<?php
/**
 * Unit tests for Updatronix_AutoUpdateDelay ledger pipeline.
 *
 * Tests stable_ledger_hash for all four types (plugin, theme, core, translation)
 * including edge cases with missing fields and empty data.
 *
 * @package updatronix
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Covers the ledger hash generation.
 *
 * @covers \Updatronix_AutoUpdateDelay
 */
final class AutoUpdateDelayLedgerTest extends TestCase {
	/**
	 * Reflection method for stable_ledger_hash.
	 *
	 * @var \ReflectionMethod|null
	 */
	private static ?\ReflectionMethod $stable_ledger_hash = null;

	/**
	 * Prepare the reflection method for stable_ledger_hash.
	 */
	public static function setUpBeforeClass(): void {
		self::$stable_ledger_hash = new \ReflectionMethod(
			Updatronix_AutoUpdateDelay::class,
			'stable_ledger_hash'
		);
		self::$stable_ledger_hash->setAccessible( true );
	}

	/**
	 * Invoke stable_ledger_hash with given type and item.
	 *
	 * @param string $type 'plugin'|'theme'|'core'|'translation'.
	 * @param object $item Offer object.
	 * @return string Hash string.
	 */
	private function hash( string $type, object $item ): string {
		return self::$stable_ledger_hash->invoke( null, $type, $item );
	}

	/**
	 * The ledger hash of the exact part string the source must build.
	 *
	 * The assertions below name that string -- type, separators and
	 * case-normalisation included -- because it is the decision
	 * `stable_ledger_hash()` makes. A 64-character length does not distinguish a
	 * correct row from the `$parts === ''` fallback, which is 64 characters too.
	 *
	 * @param string $parts Part string the source is expected to build.
	 * @return string Hash the ledger must store for it.
	 */
	private function parts_hash( string $parts ): string {
		return hash( 'sha256', $parts );
	}

	// --- Plugin ---

	/**
	 * Tests that a plugin offer with name and version produces a 64-char hash.
	 */
	public function test_plugin_hash_with_plugin_and_new_version(): void {
		$item = (object) array(
			'plugin'      => 'akismet/akismet.php',
			'new_version' => '5.3',
		);
		$hash = $this->hash( 'plugin', $item );
		self::assertSame( $this->parts_hash( 'plugin|akismet/akismet.php|5.3' ), $hash );
		// Same input must produce same hash.
		self::assertSame( $hash, $this->hash( 'plugin', $item ) );
	}

	/**
	 * Tests that hashing is case-insensitive for plugin slugs.
	 */
	public function test_plugin_hash_is_case_insensitive(): void {
		$upper = $this->hash(
			'plugin',
			(object) array(
				'plugin'      => 'AKISMET/AKISMET.php',
				'new_version' => '5.3',
			)
		);
		$lower = $this->hash(
			'plugin',
			(object) array(
				'plugin'      => 'akismet/akismet.php',
				'new_version' => '5.3',
			)
		);
		self::assertSame( $lower, $upper );
		self::assertSame( $this->parts_hash( 'plugin|akismet/akismet.php|5.3' ), $upper );
	}

	/**
	 * Tests that a plugin offer without a new version still produces a hash.
	 */
	public function test_plugin_hash_without_new_version_falls_back(): void {
		$complete = $this->hash(
			'plugin',
			(object) array(
				'plugin'      => 'hello.php',
				'new_version' => '1.1',
			)
		);
		$partial  = $this->hash( 'plugin', (object) array( 'plugin' => 'hello.php' ) );
		self::assertSame( 64, strlen( $partial ) );
		self::assertNotSame( $complete, $partial );
	}

	/**
	 * Tests that a plugin offer without a plugin field still produces a hash.
	 */
	public function test_plugin_hash_without_plugin_falls_back(): void {
		$complete = $this->hash(
			'plugin',
			(object) array(
				'plugin'      => 'hello.php',
				'new_version' => '1.1',
			)
		);
		$partial  = $this->hash( 'plugin', (object) array( 'new_version' => '1.0' ) );
		self::assertSame( 64, strlen( $partial ) );
		self::assertNotSame( $complete, $partial );
	}

	// --- Theme ---

	/**
	 * Tests that a theme offer with name and version produces a 64-char hash.
	 */
	public function test_theme_hash_with_theme_and_new_version(): void {
		$item = (object) array(
			'theme'       => 'twentytwentyfive',
			'new_version' => '2.0',
		);
		$hash = $this->hash( 'theme', $item );
		self::assertSame( $this->parts_hash( 'theme|twentytwentyfive|2.0' ), $hash );
		// Same input must produce same hash.
		self::assertSame( $hash, $this->hash( 'theme', $item ) );
	}

	/**
	 * Tests that hashing is case-insensitive for theme slugs.
	 */
	public function test_theme_hash_is_case_insensitive(): void {
		$upper = $this->hash(
			'theme',
			(object) array(
				'theme'       => 'TWENTYTWENTYFIVE',
				'new_version' => '2.0',
			)
		);
		$lower = $this->hash(
			'theme',
			(object) array(
				'theme'       => 'twentytwentyfive',
				'new_version' => '2.0',
			)
		);
		self::assertSame( $lower, $upper );
		self::assertSame( $this->parts_hash( 'theme|twentytwentyfive|2.0' ), $upper );
	}

	// --- Core ---

	/**
	 * Tests that a core offer includes both current and offered versions.
	 */
	public function test_core_hash_includes_current_and_version(): void {
		$item = (object) array(
			'current' => '6.5.0',
			'version' => '6.6.1',
		);
		$hash = $this->hash( 'core', $item );
		self::assertSame( $this->parts_hash( 'core|6.5.0|6.6.1' ), $hash );
		// Same input must produce same hash.
		self::assertSame( $hash, $this->hash( 'core', $item ) );
	}

	/**
	 * Tests that differing offered core versions produce different hashes.
	 */
	public function test_core_hash_differs_when_offered_version_differs(): void {
		$hash_a = $this->hash(
			'core',
			(object) array(
				'current' => '6.5.0',
				'version' => '6.6.1',
			)
		);
		$hash_b = $this->hash(
			'core',
			(object) array(
				'current' => '6.5.0',
				'version' => '6.6.2',
			)
		);
		self::assertNotSame(
			$hash_a,
			$hash_b,
			'Two different core offers for the same running version must produce different hashes.'
		);
	}

	/**
	 * Tests that a core offer without a version falls back to current only.
	 */
	public function test_core_hash_without_version_uses_current_only(): void {
		$item = (object) array( 'current' => '6.5.0' );
		$hash = $this->hash( 'core', $item );
		// No offered version: the row keeps the trailing separator and nothing
		// after it, which is neither the fallback nor `'core|6.5.0|6.6.1'`.
		self::assertSame( $this->parts_hash( 'core|6.5.0|' ), $hash );
	}

	/**
	 * Tests that a core offer with only a version hashes the offered version.
	 *
	 * The `(string)` cast on `$item->version` is redundant with the concatenation
	 * it feeds -- PHP coerces the operand either way -- so the mutation that drops
	 * it is equivalent and survives by construction. That is stated rather than
	 * hidden; every other mutation of this line is not equivalent, and this
	 * assertion is what kills it. See `mutation-testing` under `.agents/skills/`.
	 */
	public function test_core_hash_with_only_version(): void {
		$item = (object) array( 'version' => '6.6.1' );
		$hash = $this->hash( 'core', $item );
		self::assertSame( $this->parts_hash( 'core|6.6.1' ), $hash );
	}

	/**
	 * Tests that a core offer with neither current nor version falls back.
	 */
	public function test_core_hash_without_current_or_version_falls_back(): void {
		$complete = (object) array(
			'current' => '6.5.0',
			'version' => '6.6.1',
		);
		$empty    = (object) array( 'response' => 'autoupdate' );
		$hash     = $this->hash( 'core', $empty );
		self::assertSame( 64, strlen( $hash ) );
		self::assertNotSame( $this->hash( 'core', $complete ), $hash );
	}

	// --- Translation ---

	/**
	 * Tests that a translation offer with all fields produces a hash.
	 */
	public function test_translation_hash_with_all_fields(): void {
		$item = (object) array(
			'type'     => 'plugin',
			'slug'     => 'akismet',
			'language' => 'fr_FR',
			'version'  => '1.0',
		);
		$hash = $this->hash( 'translation', $item );
		self::assertSame( $this->parts_hash( 'translation|plugin|akismet|fr_FR|1.0' ), $hash );
		self::assertSame( $hash, $this->hash( 'translation', $item ) );
	}

	/**
	 * Tests that a translation offer without known fields falls back.
	 */
	public function test_translation_hash_without_fields_falls_back(): void {
		$complete = (object) array(
			'type'     => 'plugin',
			'slug'     => 'akismet',
			'language' => 'fr_FR',
			'version'  => '1.0',
		);
		$partial  = (object) array( 'some_other' => 'data' );
		$hash     = $this->hash( 'translation', $partial );
		self::assertSame( 64, strlen( $hash ) );
		self::assertNotSame( $this->hash( 'translation', $complete ), $hash );
	}

	// --- Determinism ---

	/**
	 * Tests that identical input always produces the same hash.
	 */
	public function test_same_input_always_produces_same_hash(): void {
		$item  = (object) array(
			'plugin'      => 'hello.php',
			'new_version' => '1.1',
		);
		$first = $this->hash( 'plugin', $item );
		self::assertSame( $this->parts_hash( 'plugin|hello.php|1.1' ), $first );
		for ( $i = 0; $i < 10; $i++ ) {
			self::assertSame( $first, $this->hash( 'plugin', $item ) );
		}
	}
}
