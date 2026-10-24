( function () {
	'use strict';

	var live = document.getElementById( 'live-region' );
	var announceTimer;

	function announce( message ) {
		if ( ! live || ! message ) {
			return;
		}

		window.clearTimeout( announceTimer );
		live.textContent = '';
		announceTimer = window.setTimeout( function () {
			live.textContent = message;
		}, 60 );
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then(
				function () {
					return true;
				},
				function () {
					return legacyCopy( text );
				}
			);
		}

		return Promise.resolve( legacyCopy( text ) );
	}

	function legacyCopy( text ) {
		var area = document.createElement( 'textarea' );
		var copied = false;

		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.top = '-1000px';
		document.body.appendChild( area );
		area.select();

		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			copied = false;
		}

		document.body.removeChild( area );
		return copied;
	}

	function flash( element, duration ) {
		element.classList.add( 'is-copied' );
		window.setTimeout( function () {
			element.classList.remove( 'is-copied' );
		}, duration || 1400 );
	}

	// Mobile navigation
	var sidebar = document.getElementById( 'sidebar' );
	var navToggle = document.getElementById( 'nav-toggle' );

	function setNavOpen( open ) {
		if ( ! sidebar || ! navToggle ) {
			return;
		}

		sidebar.classList.toggle( 'is-open', open );
		navToggle.setAttribute( 'aria-expanded', String( open ) );
		navToggle.setAttribute( 'aria-label', open ? 'Close guide contents' : 'Open guide contents' );
	}

	if ( navToggle ) {
		navToggle.addEventListener( 'click', function () {
			setNavOpen( navToggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}

		if ( sidebar && sidebar.classList.contains( 'is-open' ) ) {
			setNavOpen( false );
			navToggle.focus();
		}
	} );

	window.addEventListener( 'resize', function () {
		if ( window.innerWidth > 920 ) {
			setNavOpen( false );
		}
	} );

	// Copy tokens
	Array.prototype.forEach.call( document.querySelectorAll( '[data-copy]' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var value = button.getAttribute( 'data-copy' );

			copyText( value ).then( function ( copied ) {
				if ( ! copied ) {
					announce( 'Could not copy ' + value );
					return;
				}

				flash( button );
				announce( 'Copied ' + value );
			} );
		} );
	} );

	// Copy snippets
	Array.prototype.forEach.call( document.querySelectorAll( '.copy-button[data-copy-target]' ), function ( button ) {
		var defaultMarkup = button.innerHTML;

		button.addEventListener( 'click', function () {
			var target = document.getElementById( button.getAttribute( 'data-copy-target' ) );
			var name = target ? target.id : 'snippet';

			if ( ! target ) {
				return;
			}

			copyText( target.textContent ).then( function ( copied ) {
				if ( ! copied ) {
					announce( 'Could not copy the snippet' );
					return;
				}

				button.classList.add( 'is-copied' );
				button.innerHTML = '<svg class="icon" aria-hidden="true"><use href="#i-check"/></svg> Copied';
				announce( 'Snippet copied' );

				window.setTimeout( function () {
					button.classList.remove( 'is-copied' );
					button.innerHTML = defaultMarkup;
				}, 1600 );
			} );
		} );
	} );

	// Segmented controls
	Array.prototype.forEach.call( document.querySelectorAll( '[data-segmented]' ), function ( group ) {
		var buttons = Array.prototype.slice.call( group.querySelectorAll( 'button' ) );

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				buttons.forEach( function ( sibling ) {
					sibling.setAttribute( 'aria-pressed', String( sibling === button ) );
				} );

				announce( ( group.getAttribute( 'aria-label' ) || 'View' ) + ': ' + button.textContent.trim() );
			} );
		} );
	} );

	// Tabs for sub-views inside a page
	Array.prototype.forEach.call( document.querySelectorAll( '[role="tablist"]' ), function ( list ) {
		var tabs = Array.prototype.slice.call( list.querySelectorAll( '[role="tab"]' ) );

		if ( ! tabs.length ) {
			return;
		}

		function select( tab, focus ) {
			tabs.forEach( function ( item ) {
				var selected = item === tab;
				var panel = document.getElementById( item.getAttribute( 'aria-controls' ) );

				item.setAttribute( 'aria-selected', String( selected ) );
				item.tabIndex = selected ? 0 : -1;

				if ( panel ) {
					panel.hidden = ! selected;
				}
			} );

			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				select( tab, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;
				var forward = event.key === 'ArrowRight';
				var backward = event.key === 'ArrowLeft';

				if ( forward || backward ) {
					event.preventDefault();
					next = tabs[ ( index + ( forward ? 1 : -1 ) + tabs.length ) % tabs.length ];
				} else if ( event.key === 'Home' ) {
					event.preventDefault();
					next = tabs[ 0 ];
				} else if ( event.key === 'End' ) {
					event.preventDefault();
					next = tabs[ tabs.length - 1 ];
				}

				if ( next ) {
					select( next, true );
				}
			} );
		} );
	} );

	// Shell section buttons. The preview switches panels in place; links in the
	// same rail go to the standalone admin pages.
	var shellNav = document.querySelector( '[data-shell-nav]' );

	if ( shellNav ) {
		var shellTabs = Array.prototype.slice.call( shellNav.querySelectorAll( 'button.shell-tab' ) );

		shellTabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				shellTabs.forEach( function ( item ) {
					var active = item === tab;
					var panel = document.getElementById( item.getAttribute( 'aria-controls' ) );

					if ( active ) {
						item.setAttribute( 'aria-current', 'page' );
					} else {
						item.removeAttribute( 'aria-current' );
					}

					if ( panel ) {
						panel.hidden = ! active;
					}
				} );

				var label = tab.cloneNode( true );
				Array.prototype.forEach.call( label.querySelectorAll( '.pro-badge' ), function ( badge ) {
					badge.parentNode.removeChild( badge );
				} );

				announce( label.textContent.replace( /\s+/g, ' ' ).trim() + ' section' );
			} );
		} );
	}

	// Sidebar theme presets
	var shell = document.getElementById( 'upd-shell-demo' );
	var presets = Array.prototype.slice.call( document.querySelectorAll( '.preset[data-preset]' ) );

	presets.forEach( function ( preset ) {
		preset.addEventListener( 'click', function () {
			presets.forEach( function ( sibling ) {
				sibling.setAttribute( 'aria-pressed', String( sibling === preset ) );
			} );

			if ( shell ) {
				shell.style.setProperty( '--shell-primary', preset.getAttribute( 'data-primary' ) );
				shell.style.setProperty( '--shell-sidebar', preset.getAttribute( 'data-sidebar' ) );
			}

			announce( preset.textContent.replace( /\s+/g, ' ' ).trim() + ' sidebar theme applied' );
		} );
	} );

	// Switch-style toggles inside the shell preview
	Array.prototype.forEach.call( document.querySelectorAll( '.shell-switch' ), function ( button ) {
		button.addEventListener( 'click', function () {
			var next = button.getAttribute( 'aria-checked' ) !== 'true';
			var label = button.getAttribute( 'aria-label' ) || 'Setting';

			button.setAttribute( 'aria-checked', String( next ) );
			announce( label + ': ' + ( next ? 'on' : 'off' ) );
		} );
	} );

	// Highlight the section currently in view
	var navLinks = Array.prototype.slice.call( document.querySelectorAll( '.side-nav a[href^="#"]' ) );
	var sections = [];

	navLinks.forEach( function ( link ) {
		var section = document.getElementById( link.getAttribute( 'href' ).slice( 1 ) );

		if ( section ) {
			sections.push( { id: section.id, link: link } );
		}

		link.addEventListener( 'click', function () {
			if ( window.innerWidth <= 920 ) {
				setNavOpen( false );
			}
		} );
	} );

	if ( 'IntersectionObserver' in window && sections.length ) {
		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}

					sections.forEach( function ( item ) {
						if ( item.link.getAttribute( 'aria-current' ) === 'true' ) {
							item.link.removeAttribute( 'aria-current' );
						}
					} );

					var current = sections.filter( function ( item ) {
						return item.id === entry.target.id;
					} )[ 0 ];

					if ( current ) {
						current.link.setAttribute( 'aria-current', 'true' );
					}
				} );
			},
			{ rootMargin: '-40% 0px -55% 0px', threshold: 0 }
		);

		sections.forEach( function ( item ) {
			observer.observe( document.getElementById( item.id ) );
		} );
	}
} )();
