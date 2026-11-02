#!/usr/bin/env python3
"""Regression: a multisite full/state reset must settle to one digest.

The fast full reset imports a single-site base dump. If the multisite
conversion runs after the stability digests, the first digest sees a
single-site site and the second sees the converted one. This test pins the
conversion ahead of the digests.
"""

from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert( 0, str( Path( __file__ ).resolve().parent ) )

from testbook_site import sites_from_env  # noqa: E402


class MultisiteResetStabilityTest( unittest.TestCase ):
	"""Two consecutive digests must match after each reset mode."""

	@classmethod
	def setUpClass( cls ) -> None:
		cls.site = sites_from_env()[ "multisite" ]

	def _assert_stable( self, mode: str ) -> None:
		self.site.reset( mode )
		first = self.site.control.digest()
		second = self.site.control.digest()
		self.assertEqual(
			first.get( "options_hash" ),
			second.get( "options_hash" ),
			f"{mode}: options hash did not stabilize",
		)
		self.assertEqual(
			first.get( "option_keys_hash" ),
			second.get( "option_keys_hash" ),
			f"{mode}: option keys hash did not stabilize",
		)
		self.assertEqual( first.get( "log_rows" ), 0 )
		self.assertTrue( first.get( "multisite" ), f"{mode}: lost multisite" )
		self.assertTrue( second.get( "multisite" ), f"{mode}: lost multisite" )

	def test_full_reset_is_stable( self ) -> None:
		self._assert_stable( "full" )

	def test_state_reset_is_stable( self ) -> None:
		self._assert_stable( "state" )


if __name__ == "__main__":
	unittest.main( verbosity=2 )
