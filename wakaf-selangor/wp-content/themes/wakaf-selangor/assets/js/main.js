( function () {
	var toggle = document.querySelector( '.nav-toggle' );
	var nav = document.getElementById( 'primary-nav' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( ! open ) );
			document.body.classList.toggle( 'nav-open', ! open );
		} );
	}

	// Dropdowns: tap/click toggles on touch and keyboard, hover is handled in CSS.
	document.querySelectorAll( '.primary-nav .menu-item-has-children > a' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			var item = link.parentElement;
			if ( link.getAttribute( 'href' ) === '#' || window.matchMedia( '(max-width: 960px)' ).matches ) {
				e.preventDefault();
				var open = item.classList.toggle( 'is-open' );
				link.setAttribute( 'aria-expanded', String( open ) );
			}
		} );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}
		document.querySelectorAll( '.menu-item.is-open' ).forEach( function ( item ) {
			item.classList.remove( 'is-open' );
			item.firstElementChild.setAttribute( 'aria-expanded', 'false' );
		} );
		if ( toggle && document.body.classList.contains( 'nav-open' ) ) {
			toggle.click();
			toggle.focus();
		}
	} );

	// Hero slider.
	var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var hero = document.querySelector( '[data-hero]' );

	if ( hero ) {
		var slides = Array.prototype.slice.call( hero.querySelectorAll( '.slide' ) );
		var dots = Array.prototype.slice.call( hero.querySelectorAll( '.dot' ) );
		var pauseBtn = hero.querySelector( '.hero__pause' );
		var INTERVAL = 7000;
		var index = 0;
		var timer = null;
		var userPaused = reduce;   // honour reduced motion: no autoplay
		var hovering = false;

		hero.style.setProperty( '--interval', INTERVAL + 'ms' );

		var countUp = function ( slide ) {
			slide.querySelectorAll( '.count' ).forEach( function ( el ) {
				var to = parseFloat( el.dataset.to );
				var dec = parseInt( el.dataset.dec || '0', 10 );
				var final = to.toFixed( dec );
				if ( reduce ) { el.textContent = final; return; }
				var start = null;
				el.textContent = ( 0 ).toFixed( dec );
				var tick = function ( t ) {
					start = start || t;
					var p = Math.min( ( t - start ) / 1600, 1 );
					el.textContent = ( to * ( 1 - Math.pow( 1 - p, 3 ) ) ).toFixed( dec );
					if ( p < 1 ) { requestAnimationFrame( tick ); }
				};
				setTimeout( function () { requestAnimationFrame( tick ); }, 700 );
				// Never leave a wrong figure on screen if frames are throttled.
				setTimeout( function () { el.textContent = final; }, 3000 );
			} );
		};

		var setPlaying = function () {
			var playing = ! userPaused && ! hovering && ! document.hidden;
			hero.classList.toggle( 'is-playing', playing );
			clearTimeout( timer );
			if ( playing ) { timer = setTimeout( function () { go( index + 1 ); }, INTERVAL ); }
		};

		var go = function ( n ) {
			n = ( n + slides.length ) % slides.length;
			slides.forEach( function ( s, i ) {
				var on = i === n;
				s.classList.toggle( 'is-active', on );
				s.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
				s.querySelectorAll( 'a' ).forEach( function ( a ) { a.tabIndex = on ? 0 : -1; } );
			} );
			dots.forEach( function ( d, i ) {
				d.classList.toggle( 'is-active', i === n );
				d.setAttribute( 'aria-selected', i === n ? 'true' : 'false' );
			} );
			index = n;
			countUp( slides[ n ] );
			setPlaying();
		};

		dots.forEach( function ( d, i ) { d.addEventListener( 'click', function () { go( i ); } ); } );

		pauseBtn.addEventListener( 'click', function () {
			userPaused = ! userPaused;
			pauseBtn.dataset.state = userPaused ? 'paused' : 'playing';
			pauseBtn.setAttribute( 'aria-label', userPaused ? 'Main slaid' : 'Jeda slaid' );
			setPlaying();
		} );
		pauseBtn.dataset.state = userPaused ? 'paused' : 'playing';
		pauseBtn.setAttribute( 'aria-label', userPaused ? 'Main slaid' : 'Jeda slaid' );

		// Pause while the pointer or keyboard focus is inside the hero.
		var stage = hero.querySelector( '.hero__stage' );
		stage.addEventListener( 'mouseenter', function () { hovering = true; setPlaying(); } );
		stage.addEventListener( 'mouseleave', function () { hovering = false; setPlaying(); } );
		stage.addEventListener( 'focusin', function () { hovering = true; setPlaying(); } );
		stage.addEventListener( 'focusout', function () { hovering = false; setPlaying(); } );
		document.addEventListener( 'visibilitychange', setPlaying );

		// Swipe on touch screens.
		var x0 = null;
		stage.addEventListener( 'pointerdown', function ( e ) { if ( e.pointerType === 'touch' ) { x0 = e.clientX; } } );
		stage.addEventListener( 'pointerup', function ( e ) {
			if ( x0 === null ) { return; }
			var dx = e.clientX - x0;
			x0 = null;
			if ( Math.abs( dx ) > 50 ) { go( index + ( dx < 0 ? 1 : -1 ) ); }
		} );

		// Arrow keys move between slides when a dot has focus.
		hero.querySelector( '.dots' ).addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' ) { return; }
			go( index + ( e.key === 'ArrowRight' ? 1 : -1 ) );
			dots[ index ].focus();
		} );

		// Pointer parallax (fine pointers only).
		if ( ! reduce && window.matchMedia( '(hover: hover) and (min-width: 901px)' ).matches ) {
			hero.addEventListener( 'pointermove', function ( e ) {
				var r = hero.getBoundingClientRect();
				hero.style.setProperty( '--mx', ( ( e.clientX - r.left ) / r.width - .5 ).toFixed( 3 ) );
				hero.style.setProperty( '--my', ( ( e.clientY - r.top ) / r.height - .5 ).toFixed( 3 ) );
			} );
			hero.addEventListener( 'pointerleave', function () {
				hero.style.setProperty( '--mx', 0 );
				hero.style.setProperty( '--my', 0 );
			} );
		}

		countUp( slides[ 0 ] );
		setPlaying();
	}

	// Taburan hartanah: peta dan senarai daerah saling menyerlah, togol metrik, kira naik jumlah.
	var hart = document.querySelector( '[data-hart]' );
	if ( hart ) {
		var rows = Array.prototype.slice.call( hart.querySelectorAll( '.drow' ) );
		var spots = Array.prototype.slice.call( hart.querySelectorAll( '.spot' ) );

		var activate = function ( i ) {
			rows.forEach( function ( r ) { r.classList.toggle( 'is-active', r.dataset.i === i ); } );
			spots.forEach( function ( s ) { s.classList.toggle( 'is-active', s.dataset.i === i ); } );
		};
		rows.forEach( function ( r ) {
			var btn = r.querySelector( '.drow__btn' );
			[ 'mouseenter', 'focus', 'click' ].forEach( function ( ev ) {
				btn.addEventListener( ev, function () { activate( r.dataset.i ); } );
			} );
		} );
		spots.forEach( function ( s ) {
			[ 'mouseenter', 'click' ].forEach( function ( ev ) {
				s.addEventListener( ev, function () { activate( s.dataset.i ); } );
			} );
		} );

		var segBtns = Array.prototype.slice.call( hart.querySelectorAll( '.seg button' ) );
		segBtns.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				hart.dataset.metric = b.dataset.metric;
				segBtns.forEach( function ( o ) { o.setAttribute( 'aria-pressed', o === b ? 'true' : 'false' ); } );
			} );
		} );

		var counted = false;
		var runCounts = function () {
			if ( counted ) { return; }
			counted = true;
			hart.querySelectorAll( '.count' ).forEach( function ( el ) {
				var to = parseFloat( el.dataset.to );
				var dec = parseInt( el.dataset.dec || '0', 10 );
				var finalText = to.toFixed( dec );
				if ( reduce ) { return; }
				var start = null;
				el.textContent = ( 0 ).toFixed( dec );
				var tick = function ( t ) {
					start = start || t;
					var p = Math.min( ( t - start ) / 1400, 1 );
					el.textContent = ( to * ( 1 - Math.pow( 1 - p, 3 ) ) ).toFixed( dec );
					if ( p < 1 ) { requestAnimationFrame( tick ); }
				};
				requestAnimationFrame( tick );
				setTimeout( function () { el.textContent = finalText; }, 2600 );
			} );
		};
		if ( 'IntersectionObserver' in window ) {
			var hio = new IntersectionObserver( function ( entries ) {
				if ( entries[ 0 ].isIntersecting ) { runCounts(); hio.disconnect(); }
			}, { threshold: 0.25 } );
			hio.observe( hart.querySelector( '.hart__totals' ) );
		}
	}

	// Salin Biller Code JomPAY.
	document.querySelectorAll( '[data-copy-target]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var text = document.getElementById( btn.dataset.copyTarget ).textContent.trim();
			var done = function () {
				var old = btn.textContent;
				btn.textContent = 'Disalin ✓';
				btn.classList.add( 'is-done' );
				setTimeout( function () { btn.textContent = old; btn.classList.remove( 'is-done' ); }, 1800 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done );
			} else {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild( ta );
				ta.select();
				try { document.execCommand( 'copy' ); done(); } catch ( err ) { /* abaikan */ }
				document.body.removeChild( ta );
			}
		} );
	} );

	// Wizard Sumbang Wakaf.
	var wiz = document.querySelector( '[data-wizard]' );
	if ( wiz ) {
		var wForm = wiz.querySelector( '[data-wiz-form]' );
		var wPanels = Array.prototype.slice.call( wiz.querySelectorAll( '.wstep' ) );
		var wItems = Array.prototype.slice.call( wiz.querySelectorAll( '.wiz__steps li' ) );
		var wSteps = wiz.querySelector( '.wiz__steps' );
		var wStatus = wiz.querySelector( '[data-wiz-status]' );
		var wAmount = wForm.querySelector( 'input[name="amount"]' );
		var wNama = wForm.querySelector( 'input[name="nama"]' );
		var wIc = wForm.querySelector( 'input[name="icno"]' );
		var wConsent = wForm.querySelector( '.consent input' );
		var wChips = Array.prototype.slice.call( wiz.querySelectorAll( '.chip-btn' ) );
		var wMethods = Array.prototype.slice.call( wiz.querySelectorAll( 'input[name="cara"]' ) );
		var wSubmit = wForm.querySelector( '[data-submit]' );
		var wSubmitText = wSubmit.innerHTML;
		var wLabels = [ 'Cara sumbangan', 'Jumlah', 'Maklumat pewakaf', 'Semak sumbangan' ];
		var wCur = 1;

		var method = function () {
			var m = wMethods.filter( function ( r ) { return r.checked; } )[ 0 ];
			return m ? m.value : 'fpx';
		};
		var money = function ( v ) { return 'RM ' + ( parseFloat( v ) || 0 ).toFixed( 2 ); };

		var syncMethod = function () {
			var m = method();
			wiz.querySelectorAll( '[data-extra]' ).forEach( function ( el ) { el.hidden = el.dataset.extra !== m; } );
			wiz.querySelectorAll( '[data-for-method]' ).forEach( function ( el ) { el.hidden = el.dataset.forMethod !== m; } );
			// Hanya laluan dalam talian meneruskan ke langkah seterusnya; JomPAY memaparkan butiran sahaja.
		};

		var syncChipsW = function () {
			var v = parseFloat( wAmount.value );
			wChips.forEach( function ( c ) { c.setAttribute( 'aria-pressed', parseFloat( c.dataset.amount ) === v ? 'true' : 'false' ); } );
		};

		var fillSummary = function () {
			var names = { fpx: 'Sumbangan sekali (dalam talian)', dd: 'Direct Debit bulanan', jompay: 'JomPAY' };
			wiz.querySelector( '[data-sum="cara"]' ).textContent = names[ method() ];
			wiz.querySelector( '[data-sum="amount"]' ).textContent = money( wAmount.value );
			wiz.querySelector( '[data-sum="nama"]' ).textContent = wNama.value.trim() || '—';
			var ic = wIc.value;
			wiz.querySelector( '[data-sum="icno"]' ).textContent = ic ? '••••••••' + ic.slice( -4 ) : '—';
		};

		var show = function ( n, opts ) {
			opts = opts || {};
			var back = n < wCur;
			wCur = n;
			wPanels.forEach( function ( p ) {
				var on = parseInt( p.dataset.step, 10 ) === n;
				p.hidden = ! on;
				p.classList.remove( 'is-active', 'is-back' );
				if ( on ) {
					// mulakan semula animasi masuk
					void p.offsetWidth;
					p.classList.add( 'is-active' );
					if ( back ) { p.classList.add( 'is-back' ); }
				}
			} );
			wItems.forEach( function ( li, i ) {
				var step = i + 1;
				li.classList.toggle( 'is-current', step === n );
				li.classList.toggle( 'is-done', step < n );
				if ( step === n ) { li.setAttribute( 'aria-current', 'step' ); } else { li.removeAttribute( 'aria-current' ); }
				li.querySelector( '.wnode' ).disabled = ! ( step < n );
			} );
			wSteps.style.setProperty( '--p', ( ( n - 1 ) / 3 ).toFixed( 3 ) );
			wStatus.textContent = 'Langkah ' + n + ' daripada 4: ' + wLabels[ n - 1 ];
			if ( n === 4 ) { fillSummary(); }
			if ( opts.focus !== false ) {
				var title = wiz.querySelector( '.wstep.is-active .wstep__title' );
				var top = wiz.getBoundingClientRect().top;
				if ( top < 0 || top > window.innerHeight * 0.4 ) {
					wiz.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'start' } );
				}
				if ( title ) { title.focus( { preventScroll: true } ); }
			}
		};

		// Sahkan medan bagi langkah semasa; kembalikan true jika sah.
		var validate = function ( n ) {
			var fields = [];
			if ( n === 2 ) { fields = [ wAmount ]; }
			if ( n === 3 ) { fields = [ wNama, wIc ]; }
			if ( n === 4 ) { fields = [ wConsent ]; }
			for ( var i = 0; i < fields.length; i++ ) {
				var f = fields[ i ];
				if ( f === wAmount && ! ( parseFloat( f.value ) >= 1 ) ) {
					f.setCustomValidity( 'Jumlah sumbangan minimum ialah RM1.' );
				} else if ( f.validity.valueMissing || ! f.checkValidity() ) {
					if ( ! f.validationMessage ) { f.setCustomValidity( f.dataset.msg || 'Sila lengkapkan ruangan ini.' ); }
				}
				if ( ! f.checkValidity() ) {
					f.reportValidity();
					f.focus();
					return false;
				}
			}
			return true;
		};

		wiz.querySelectorAll( 'input[data-msg]' ).forEach( function ( el ) {
			el.addEventListener( 'invalid', function () {
				if ( el.validity.valueMissing ) { el.setCustomValidity( el.dataset.msg ); }
				else if ( el.validity.patternMismatch || el.validity.tooShort ) { el.setCustomValidity( el.dataset.msg ); }
			} );
			el.addEventListener( 'input', function () { el.setCustomValidity( '' ); } );
			el.addEventListener( 'change', function () { el.setCustomValidity( '' ); } );
		} );

		wAmount.addEventListener( 'input', function () {
			wAmount.value = wAmount.value.replace( /[^0-9.]/g, '' );
			syncChipsW();
		} );
		wIc.addEventListener( 'input', function () { wIc.value = wIc.value.replace( /\D/g, '' ).slice( 0, 12 ); } );
		wChips.forEach( function ( c ) {
			c.addEventListener( 'click', function () {
				wAmount.value = c.dataset.amount;
				wAmount.setCustomValidity( '' );
				syncChipsW();
			} );
		} );
		wMethods.forEach( function ( r ) { r.addEventListener( 'change', syncMethod ); } );

		wiz.addEventListener( 'click', function ( e ) {
			var next = e.target.closest( '[data-next]' );
			var prev = e.target.closest( '[data-prev]' );
			var go = e.target.closest( '[data-goto]' );
			if ( next ) {
				if ( validate( wCur ) ) { show( Math.min( wCur + 1, 4 ) ); }
			} else if ( prev ) {
				show( Math.max( wCur - 1, 1 ) );
			} else if ( go && ! go.disabled ) {
				show( parseInt( go.dataset.goto, 10 ) );
			}
		} );

		// Enter di dalam medan = Seterusnya, bukan hantar borang lebih awal.
		wForm.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'checkbox' && wCur < 4 ) {
				e.preventDefault();
				if ( validate( wCur ) ) { show( wCur + 1 ); }
			}
		} );

		wForm.addEventListener( 'submit', function ( e ) {
			if ( wCur !== 4 || method() !== 'fpx' || ! validate( 2 ) || ! validate( 3 ) ) {
				e.preventDefault();
				if ( wCur < 4 ) { return; }
				if ( ! validate( 2 ) ) { show( 2 ); } else if ( ! validate( 3 ) ) { show( 3 ); }
				return;
			}
			if ( ! validate( 4 ) ) { e.preventDefault(); return; }
			wSubmit.disabled = true;
			wSubmit.textContent = 'Mengalihkan ke portal…';
		} );
		// Pulihkan butang jika pengguna kembali melalui butang "back" pelayar.
		window.addEventListener( 'pageshow', function () { wSubmit.disabled = false; wSubmit.innerHTML = wSubmitText; } );

		// ?amount=50 dari kad laman utama: terus ke Maklumat.
		var qs = new URLSearchParams( window.location.search );
		var qAmount = parseFloat( qs.get( 'amount' ) );
		syncMethod();
		if ( qAmount >= 1 && qAmount < 1000000 ) {
			wAmount.value = String( Math.round( qAmount * 100 ) / 100 );
			syncChipsW();
			show( 3, { focus: false } );
		} else {
			show( 1, { focus: false } );
		}
	}

	// Pautan pantas terapung: gelembung jumlah pantas, nudge sekali setiap sesi.
	var fab = document.querySelector( '[data-fab]' );
	if ( fab ) {
		var fabHover = false;
		var fabTimer = null;
		var markSeen = function () { try { sessionStorage.setItem( 'pwsFab', '1' ); } catch ( err ) { /* abaikan */ } };
		var wasSeen = function () { try { return sessionStorage.getItem( 'pwsFab' ) === '1'; } catch ( err ) { return false; } };
		var setOpen = function ( v ) {
			fab.classList.toggle( 'is-open', v );
			if ( v ) { markSeen(); }
		};
		var closeLater = function ( ms ) {
			clearTimeout( fabTimer );
			fabTimer = setTimeout( function () {
				if ( ! fabHover && ! fab.contains( document.activeElement ) ) { setOpen( false ); }
			}, ms );
		};

		if ( window.matchMedia( '(hover: hover)' ).matches ) {
			fab.addEventListener( 'mouseenter', function () { fabHover = true; clearTimeout( fabTimer ); setOpen( true ); } );
			fab.addEventListener( 'mouseleave', function () { fabHover = false; closeLater( 500 ); } );
		}
		fab.addEventListener( 'focusin', function () { clearTimeout( fabTimer ); setOpen( true ); } );
		fab.addEventListener( 'focusout', function () { closeLater( 300 ); } );
		fab.querySelector( '[data-fab-close]' ).addEventListener( 'click', function () { fabHover = false; setOpen( false ); document.activeElement.blur(); } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { setOpen( false ); } } );
		document.addEventListener( 'click', function ( e ) { if ( ! fab.contains( e.target ) ) { setOpen( false ); } } );

		// Nudge sekali sahaja setiap sesi (tidak untuk pengguna yang mahu kurang gerakan).
		if ( ! reduce && ! wasSeen() ) {
			setTimeout( function () { setOpen( true ); closeLater( 9000 ); }, 6000 );
		}

		// Sembunyi apabila seruan sumbangan pada footer kelihatan.
		var footCta = document.querySelector( '.fcta' );
		if ( footCta && 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				fab.classList.toggle( 'is-away', entries[ 0 ].isIntersecting );
				if ( entries[ 0 ].isIntersecting ) { setOpen( false ); }
			}, { threshold: 0.15 } ).observe( footCta );
		}
	}

	// Scroll reveal.
	var items = document.querySelectorAll( '.reveal' );
	if ( ! items.length ) {
		return;
	}
	if ( ! ( 'IntersectionObserver' in window ) || window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		items.forEach( function ( el ) { el.classList.add( 'is-visible' ); } );
		return;
	}
	document.documentElement.classList.add( 'js-reveal' );
	var io = new IntersectionObserver( function ( entries ) {
		entries.forEach( function ( entry ) {
			if ( entry.isIntersecting ) {
				entry.target.classList.add( 'is-visible' );
				io.unobserve( entry.target );
			}
		} );
	}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' } );
	items.forEach( function ( el, i ) {
		el.style.setProperty( '--d', ( i % 4 ) * 70 + 'ms' );
		io.observe( el );
	} );
} )();
