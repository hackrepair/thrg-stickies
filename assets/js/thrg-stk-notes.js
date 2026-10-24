/**
 * The Hack Repair Guy's Stickies: on-page sticky notes.
 * Vanilla JavaScript, no dependencies. Note text is plain text; mentions are
 * stored as @[user:ID] tokens. User text is only ever inserted with textContent;
 * innerHTML is used only for this file's static SVG icons and the server-rendered,
 * wp_kses-filtered "rendered" field.
 */
( function () {
	'use strict';

	var C = window.thrgStickies;
	if ( ! C || ! document.body || window.thrgStickiesLoaded ) {
		return;
	}
	window.thrgStickiesLoaded = true;

	var T = C.i18n || {};
	var COLOR_NAMES = {};
	try { COLOR_NAMES = JSON.parse( T.colors || '{}' ); } catch ( e ) { COLOR_NAMES = {}; }
	var TOKEN = /@\[user:(\d{1,20})\]/g;
	var SVG = {
		plus: '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M12 8v8M8 12h8"/></svg>',
		eye: '<svg viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
		eyeOff: '<svg viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.9 8.4 2 12 2 12s3.6 7 10 7a9.8 9.8 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>',
		list: '<svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>',
		x: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>',
		check: '<svg viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5"/></svg>',
		circleCheck: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg>',
		undo: '<svg viewBox="0 0 24 24"><path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg>'
	};

	var state = {
		notes: [],
		hidden: pref( 'hidden' ) === '1',
		showResolved: pref( 'resolved' ) === '1',
		small: false,
		panelOpen: false,
		expanded: 0,
		front: null
	};
	var els = {};
	var edgeToolbar = C.toolbar !== 'adminbar';

	/* ---------- helpers ---------- */

	function pref( key, value ) {
		try {
			if ( arguments.length > 1 ) {
				window.localStorage.setItem( 'thrg-stk-' + key, value );
				return value;
			}
			return window.localStorage.getItem( 'thrg-stk-' + key );
		} catch ( e ) {
			return null;
		}
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( k === 'text' ) {
					node.textContent = attrs[ k ];
				} else if ( k === 'svg' ) {
					node.innerHTML = SVG[ attrs[ k ] ]; // Static markup from this file.
				} else if ( k === 'class' ) {
					node.className = attrs[ k ];
				} else if ( attrs[ k ] !== null && attrs[ k ] !== undefined && attrs[ k ] !== false ) {
					node.setAttribute( k, attrs[ k ] === true ? '' : attrs[ k ] );
				}
			} );
		}
		( children || [] ).forEach( function ( c ) {
			if ( c ) {
				node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
			}
		} );
		return node;
	}

	function sprintf( str, val ) {
		return String( str ).replace( /%[sd]/, val );
	}

	function ago( iso ) {
		var t = Date.parse( iso );
		if ( isNaN( t ) ) {
			return '';
		}
		var s = Math.max( 0, ( Date.now() - t ) / 1000 );
		if ( s < 60 ) {
			return T.justNow;
		}
		if ( s < 3600 ) {
			return sprintf( T.minutesAgo, Math.floor( s / 60 ) );
		}
		if ( s < 86400 ) {
			return sprintf( T.hoursAgo, Math.floor( s / 3600 ) );
		}
		if ( s < 86400 * 30 ) {
			return sprintf( T.daysAgo, Math.floor( s / 86400 ) );
		}
		return new Date( t ).toLocaleDateString();
	}

	function viewportWidth() {
		return document.documentElement.clientWidth || window.innerWidth;
	}

	function pageUri() {
		return window.location.pathname + window.location.search;
	}

	function restUrl( path, params ) {
		var base = C.rest;
		var url;
		var qs = params ? Object.keys( params ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
		} ).join( '&' ) : '';
		if ( base.indexOf( 'rest_route=' ) !== -1 ) {
			url = base + path;
			return qs ? url + '&' + qs : url;
		}
		url = base + path;
		return qs ? url + '?' + qs : url;
	}

	function api( method, path, body, params ) {
		return window.fetch( restUrl( path, params ), {
			method: method,
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json', Accept: 'application/json' },
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( data ) {
				if ( ! res.ok ) {
					var err = new Error( ( data && data.message ) || T.errorGeneric );
					err.status = res.status;
					err.code = data && data.code;
					err.data = data && data.data;
					if ( err.code === 'rest_cookie_invalid_nonce' ) {
						err.message = T.errorSession;
					}
					throw err;
				}
				return data;
			} );
		}, function () {
			var err = new Error( T.errorNetwork );
			err.status = 0;
			throw err;
		} );
	}

	/* ---------- text <-> editor ---------- */

	function nameMap( note ) {
		var map = {};
		( note.data.mentions || [] ).forEach( function ( m ) {
			map[ m.id ] = m.name;
		} );
		Object.keys( note.names || {} ).forEach( function ( id ) {
			map[ id ] = note.names[ id ];
		} );
		return map;
	}

	function chip( id, name ) {
		return el( 'span', { class: 'thrg-stk-mention', 'data-user': String( id ), contenteditable: 'false', text: '@' + ( name || 'user' ) } );
	}

	function fillEditor( body, text, names ) {
		body.textContent = '';
		var last = 0;
		var m;
		TOKEN.lastIndex = 0;
		while ( ( m = TOKEN.exec( text ) ) !== null ) {
			appendText( body, text.slice( last, m.index ) );
			body.appendChild( chip( m[ 1 ], names[ m[ 1 ] ] ) );
			last = m.index + m[ 0 ].length;
		}
		appendText( body, text.slice( last ) );
	}

	function appendText( parent, text ) {
		var parts = text.split( '\n' );
		parts.forEach( function ( part, i ) {
			if ( i > 0 ) {
				parent.appendChild( document.createElement( 'br' ) );
			}
			if ( part ) {
				parent.appendChild( document.createTextNode( part ) );
			}
		} );
	}

	function serialize( node ) {
		var out = '';
		node.childNodes.forEach( function ( child, i ) {
			if ( child.nodeType === 3 ) {
				out += child.nodeValue;
			} else if ( child.nodeType === 1 ) {
				var tag = child.tagName;
				if ( child.classList.contains( 'thrg-stk-mention' ) && child.getAttribute( 'data-user' ) ) {
					out += '@[user:' + parseInt( child.getAttribute( 'data-user' ), 10 ) + ']';
				} else if ( tag === 'BR' ) {
					out += '\n';
				} else if ( tag === 'DIV' || tag === 'P' ) {
					if ( i > 0 && out.slice( -1 ) !== '\n' ) {
						out += '\n';
					}
					out += serialize( child );
				} else {
					out += serialize( child );
				}
			}
		} );
		return out.replace( / /g, ' ' );
	}

	function plainText( note ) {
		var names = nameMap( note );
		return ( note.data.content || '' ).replace( TOKEN, function ( all, id ) {
			return '@' + ( names[ id ] || 'user' );
		} );
	}

	/* ---------- the note object ---------- */

	function Note( data ) {
		this.data = data;
		this.id = data.id || 0;
		this.dirty = {};
		this.editing = false;
		this.names = {};
		this.build();
		this.update( data );
	}

	Note.prototype.build = function () {
		var self = this;
		var d = this.data;
		var canEdit = !! d.can_edit;

		this.el = el( 'div', { class: 'thrg-stk-note', role: 'note', tabindex: '0' } );
		this.grip = el( 'div', { class: 'thrg-stk-grip', title: canEdit ? T.move : '' } );
		this.xBtn = el( 'button', { type: 'button', class: 'thrg-stk-x', 'aria-label': T.delete, title: T.delete, svg: 'x' } );
		this.body = el( 'div', { class: 'thrg-stk-body', 'data-placeholder': T.placeholder, 'aria-label': T.placeholder } );
		this.okBtn = el( 'button', { type: 'button', class: 'thrg-stk-ok', 'aria-label': T.save, title: T.save, svg: 'check' } );
		this.meta = el( 'span', { class: 'thrg-stk-meta' } );
		this.colorBtn = el( 'button', { type: 'button', 'aria-label': T.color, title: T.color, 'aria-expanded': 'false' }, [ el( 'span', { class: 'thrg-stk-dot' } ) ] );
		this.vis = el( 'select', { class: 'thrg-stk-vis', 'aria-label': T.visibility, title: T.visibility }, [
			el( 'option', { value: 'team', text: T.visTeam } ),
			el( 'option', { value: 'mentioned', text: T.visMentioned } ),
			el( 'option', { value: 'private', text: T.visPrivate } )
		] );
		this.help = el( 'button', { type: 'button', class: 'thrg-stk-help', 'aria-label': T.visHelp, 'data-tip': T.visHelp, text: '?' } );
		this.resolveBtn = el( 'button', { type: 'button', class: 'thrg-stk-resolve', svg: 'circleCheck' } );
		this.tools = el( 'span', { class: 'thrg-stk-tools' }, canEdit ? [ this.vis, this.help, this.resolveBtn ] : [ this.resolveBtn ] );
		this.foot = el( 'div', { class: 'thrg-stk-foot' }, [ canEdit ? this.okBtn : null, this.meta, this.tools ] );
		this.colors = el( 'div', { class: 'thrg-stk-colors', role: 'group', 'aria-label': T.color } );
		( C.colors || [] ).forEach( function ( c ) {
			var b = el( 'button', { type: 'button', 'data-color': c, 'aria-label': COLOR_NAMES[ c ] || c, title: COLOR_NAMES[ c ] || c }, [ el( 'span', { class: 'thrg-stk-dot thrg-stk-c-' + c } ) ] );
			b.firstChild.style.background = getSwatch( c );
			b.addEventListener( 'click', function () {
				self.setColor( c );
				self.colors.classList.remove( 'is-open' );
				self.colorBtn.setAttribute( 'aria-expanded', 'false' );
				self.colorBtn.focus();
			} );
			self.colors.appendChild( b );
		} );
		this.resize = el( 'div', { class: 'thrg-stk-resize', 'aria-hidden': 'true' } );
		this.msg = el( 'div', { class: 'thrg-stk-msg', role: 'status', 'aria-live': 'polite' } );
		this.suggest = el( 'div', { class: 'thrg-stk-suggest', role: 'listbox', 'aria-label': T.mentionList } );
		this.confirm = el( 'div', { class: 'thrg-stk-confirm', role: 'alertdialog', 'aria-label': T.confirmDelete }, [
			el( 'span', { text: T.confirmDelete } ),
			el( 'div', null, [
				el( 'button', { type: 'button', class: 'is-danger', 'data-act': 'yes', text: T.yesDelete } ),
				el( 'button', { type: 'button', 'data-act': 'no', text: T.cancel } )
			] )
		] );
		this.collapsed = el( 'div', { class: 'thrg-stk-collapsed', svg: 'circleCheck' } );

		this.grip.appendChild( el( 'div', { class: 'thrg-stk-pin', 'aria-hidden': 'true', title: canEdit ? T.move : '' } ) );
		this.grip.appendChild( canEdit ? this.xBtn : document.createTextNode( '' ) );
		if ( canEdit ) {
			this.grip.appendChild( this.colorBtn );
		}
		[ this.grip, this.body, this.foot, this.colors, this.resize, this.msg, this.suggest, this.confirm, this.collapsed ].forEach( function ( n ) {
			self.el.appendChild( n );
		} );
		if ( canEdit ) {
			this.el.classList.add( 'can-edit' );
		}

		this.bind();
	};

	function getSwatch( c ) {
		return { yellow: '#ffefb1', blue: '#cfe8fb', green: '#d4f2cf', pink: '#fbd3e3', gray: '#e6e8ea' }[ c ] || '#ffefb1';
	}

	Note.prototype.update = function ( data ) {
		this.data = data;
		this.id = data.id || 0;
		var d = data;
		this.el.id = this.id ? 'thrg-stk-' + this.id : '';
		this.el.setAttribute( 'aria-label', sprintf( T.note, d.author ? d.author.name : C.user.name ) );
		this.el.style.width = ( d.width || 270 ) + 'px';
		this.el.style.height = ( d.height || 190 ) + 'px';
		this.setColorClass( d.color || 'yellow' );
		this.vis.value = d.visibility || 'team';
		this.el.classList.toggle( 'is-resolved', d.status === 'resolved' );
		this.el.classList.toggle( 'is-new', ! this.id );
		this.resolveBtn.hidden = ! this.id || ! d.can_resolve;
		var resolved = d.status === 'resolved';
		this.resolveBtn.innerHTML = SVG[ resolved ? 'undo' : 'circleCheck' ];
		this.resolveBtn.setAttribute( 'aria-label', resolved ? T.reopen : T.resolve );
		this.resolveBtn.title = resolved ? T.reopen : T.resolve;
		this.collapsed.title = resolved && d.resolved_by ? sprintf( T.resolvedBy, d.resolved_by ) : T.resolved;
		this.renderMeta();
		if ( ! this.editing ) {
			this.showView();
		}
		this.markSaved();
		this.place();
	};

	Note.prototype.renderMeta = function () {
		var d = this.data;
		var bits = [];
		if ( ! this.id ) {
			bits.push( T.unsaved );
		} else {
			bits.push( d.author && d.author.id === C.user.id ? T.you : ( d.author ? d.author.name : '' ) );
			bits.push( ago( d.updated || d.created ) + ( d.edited ? ' (' + T.edited + ')' : '' ) );
		}
		if ( d.status === 'resolved' ) {
			bits.push( d.resolved_by ? sprintf( T.resolvedBy, d.resolved_by ) : T.resolved );
		}
		this.meta.textContent = bits.filter( Boolean ).join( ' · ' );
		this.meta.title = this.meta.textContent;
	};

	Note.prototype.setColorClass = function ( c ) {
		var self = this;
		( C.colors || [] ).forEach( function ( x ) {
			self.el.classList.remove( 'thrg-stk-c-' + x );
		} );
		this.el.classList.add( 'thrg-stk-c-' + c );
		this.colorBtn.firstChild.style.background = getSwatch( c );
		Array.prototype.forEach.call( this.colors.children, function ( b ) {
			b.setAttribute( 'aria-pressed', b.getAttribute( 'data-color' ) === c ? 'true' : 'false' );
		} );
	};

	Note.prototype.showView = function () {
		this.body.removeAttribute( 'contenteditable' );
		this.body.removeAttribute( 'role' );
		this.body.removeAttribute( 'aria-multiline' );
		// Server output, filtered by wp_kses to a, span.thrg-stk-mention and br.
		this.body.innerHTML = this.data.rendered || '';
		if ( ! this.data.content && this.data.can_edit ) {
			this.body.textContent = '';
		}
	};

	Note.prototype.startEdit = function ( atEnd ) {
		if ( ! this.data.can_edit || this.editing ) {
			return;
		}
		this.editing = true;
		fillEditor( this.body, this.data.content || '', nameMap( this ) );
		this.body.setAttribute( 'contenteditable', 'true' );
		this.body.setAttribute( 'role', 'textbox' );
		this.body.setAttribute( 'aria-multiline', 'true' );
		this.body.focus();
		if ( atEnd !== false ) {
			caretToEnd( this.body );
		}
	};

	Note.prototype.stopEdit = function () {
		if ( ! this.editing ) {
			return;
		}
		this.editing = false;
		this.closeSuggest();
		this.showView();
	};

	Note.prototype.currentText = function () {
		return this.editing ? serialize( this.body ).replace( /^\s+|\s+$/g, '' ) : ( this.data.content || '' );
	};

	Note.prototype.markDirty = function ( field ) {
		this.dirty[ field ] = true;
		this.markSaved();
		if ( C.autosave && this.id ) {
			var self = this;
			window.clearTimeout( this.autoTimer );
			this.autoTimer = window.setTimeout( function () {
				self.save( true );
			}, 1500 );
		}
	};

	Note.prototype.markSaved = function () {
		var clean = this.id && ! Object.keys( this.dirty ).length;
		this.el.classList.toggle( 'is-saved', !! clean );
	};

	Note.prototype.say = function ( text, info, action ) {
		var self = this;
		if ( info && text && ! action ) {
			// Confirmations go in the footer line so they never cover the note.
			window.clearTimeout( this.metaTimer );
			this.meta.textContent = text;
			this.metaTimer = window.setTimeout( function () {
				self.renderMeta();
			}, 2500 );
			announce( text );
			return;
		}
		this.msg.textContent = text || '';
		this.msg.classList.toggle( 'is-info', !! info );
		if ( action ) {
			var b = el( 'button', { type: 'button', text: action.label } );
			b.addEventListener( 'click', action.run );
			this.msg.appendChild( b );
		}
		window.clearTimeout( this.msgTimer );
		if ( text && ! action ) {
			this.msgTimer = window.setTimeout( function () {
				self.msg.textContent = '';
			}, info ? 2500 : 7000 );
		}
	};

	/* Position: top in document pixels; horizontal as an offset from the viewport centre. */
	Note.prototype.place = function () {
		var d = this.data;
		var vw = viewportWidth();
		var w = this.el.offsetWidth || d.width || 270;
		var left = vw / 2 - ( d.pos_center || 0 );
		left = Math.max( 0, Math.min( left, vw - Math.min( w, vw ) ) );
		var top = Math.max( 0, d.pos_top || 0 );
		var o = origin();
		this.el.style.left = Math.round( left - o.x ) + 'px';
		this.el.style.top = Math.round( top - o.y ) + 'px';
	};

	Note.prototype.docPos = function () {
		var r = this.el.getBoundingClientRect();
		return { left: r.left + window.pageXOffset, top: r.top + window.pageYOffset };
	};

	Note.prototype.setDocPos = function ( left, top ) {
		var o = origin();
		this.el.style.left = Math.round( left - o.x ) + 'px';
		this.el.style.top = Math.round( top - o.y ) + 'px';
	};

	Note.prototype.captureGeometry = function () {
		var p = this.docPos();
		this.data.pos_top = Math.round( p.top );
		this.data.pos_center = Math.round( viewportWidth() / 2 - p.left );
		this.data.width = Math.round( this.el.offsetWidth );
		this.data.height = Math.round( this.el.offsetHeight );
		this.data.viewport_w = viewportWidth();
	};

	Note.prototype.setColor = function ( c ) {
		if ( this.data.color === c ) {
			return;
		}
		this.data.color = c;
		this.setColorClass( c );
		this.markDirty( 'color' );
		// A single soft sheen confirms the new color.
		var noteEl = this.el;
		noteEl.classList.remove( 'is-recolor' );
		void noteEl.offsetWidth; // Restart the animation.
		noteEl.classList.add( 'is-recolor' );
		window.clearTimeout( this.recolorTimer );
		this.recolorTimer = window.setTimeout( function () {
			noteEl.classList.remove( 'is-recolor' );
		}, 900 );
	};

	Note.prototype.front = function () {
		if ( state.front && state.front !== this ) {
			state.front.el.classList.remove( 'is-front' );
		}
		state.front = this;
		this.el.classList.add( 'is-front' );
	};

	Note.prototype.bind = function () {
		var self = this;
		var el0 = this.el;

		el0.addEventListener( 'pointerdown', function () {
			self.front();
		} );

		// Drag by the top strip.
		this.grip.addEventListener( 'pointerdown', function ( e ) {
			if ( ! self.data.can_edit || e.button !== 0 || e.target.closest( 'button' ) ) {
				return;
			}
			e.preventDefault();
			var start = self.docPos();
			var sx = e.pageX;
			var sy = e.pageY;
			var moved = false;
			self.grip.setPointerCapture( e.pointerId );
			function move( ev ) {
				var dx = ev.pageX - sx;
				var dy = ev.pageY - sy;
				if ( ! moved && Math.abs( dx ) + Math.abs( dy ) < 3 ) {
					return;
				}
				moved = true;
				self.el.classList.remove( 'is-saved' );
				var vw = viewportWidth();
				var left = Math.max( 0, Math.min( start.left + dx, vw - 40 ) );
				self.setDocPos( left, Math.max( 0, start.top + dy ) );
			}
			function up() {
				self.grip.removeEventListener( 'pointermove', move );
				self.grip.removeEventListener( 'pointerup', up );
				self.grip.removeEventListener( 'pointercancel', up );
				if ( moved ) {
					self.captureGeometry();
					self.markDirty( 'geometry' );
				}
			}
			self.grip.addEventListener( 'pointermove', move );
			self.grip.addEventListener( 'pointerup', up );
			self.grip.addEventListener( 'pointercancel', up );
		} );

		// Resize from the corner.
		this.resize.addEventListener( 'pointerdown', function ( e ) {
			if ( ! self.data.can_edit || e.button !== 0 ) {
				return;
			}
			e.preventDefault();
			var sw = self.el.offsetWidth;
			var sh = self.el.offsetHeight;
			var sx = e.pageX;
			var sy = e.pageY;
			self.resize.setPointerCapture( e.pointerId );
			function move( ev ) {
				self.el.classList.remove( 'is-saved' );
				self.el.style.width = Math.max( 180, Math.min( 900, sw + ev.pageX - sx ) ) + 'px';
				self.el.style.height = Math.max( 100, Math.min( 900, sh + ev.pageY - sy ) ) + 'px';
			}
			function up() {
				self.resize.removeEventListener( 'pointermove', move );
				self.resize.removeEventListener( 'pointerup', up );
				self.resize.removeEventListener( 'pointercancel', up );
				self.captureGeometry();
				self.markDirty( 'geometry' );
			}
			self.resize.addEventListener( 'pointermove', move );
			self.resize.addEventListener( 'pointerup', up );
			self.resize.addEventListener( 'pointercancel', up );
		} );

		// Keyboard: arrows move, Shift+arrows resize (when the note itself has focus).
		el0.addEventListener( 'keydown', function ( e ) {
			if ( e.target !== el0 ) {
				return;
			}
			if ( self.el.classList.contains( 'is-resolved' ) && ! self.el.classList.contains( 'is-peek' ) && ( e.key === 'Enter' || e.key === ' ' ) ) {
				e.preventDefault();
				self.el.classList.add( 'is-peek' );
				return;
			}
			if ( ! self.data.can_edit ) {
				return;
			}
			var dirs = { ArrowLeft: [ -10, 0 ], ArrowRight: [ 10, 0 ], ArrowUp: [ 0, -10 ], ArrowDown: [ 0, 10 ] };
			if ( dirs[ e.key ] ) {
				e.preventDefault();
				var d = dirs[ e.key ];
				if ( e.shiftKey ) {
					self.el.style.width = Math.max( 180, self.el.offsetWidth + d[ 0 ] ) + 'px';
					self.el.style.height = Math.max( 100, self.el.offsetHeight + d[ 1 ] ) + 'px';
				} else {
					var p = self.docPos();
					self.setDocPos( Math.max( 0, p.left + d[ 0 ] ), Math.max( 0, p.top + d[ 1 ] ) );
				}
				self.captureGeometry();
				self.markDirty( 'geometry' );
			} else if ( e.key === 'Enter' ) {
				e.preventDefault();
				self.startEdit();
			} else if ( e.key === 'Delete' ) {
				e.preventDefault();
				self.askDelete();
			}
		} );

		// Click text to edit (links still work).
		this.body.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) && ! self.editing ) {
				return;
			}
			self.startEdit( ! self.editing );
		} );

		this.body.addEventListener( 'input', function () {
			self.markDirty( 'content' );
			self.checkMention();
		} );

		this.body.addEventListener( 'paste', function ( e ) {
			if ( ! self.editing ) {
				return;
			}
			e.preventDefault();
			var text = ( e.clipboardData || window.clipboardData ).getData( 'text/plain' ) || '';
			document.execCommand( 'insertText', false, text );
		} );

		this.body.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
		} );

		this.body.addEventListener( 'keydown', function ( e ) {
			if ( ! self.editing ) {
				return;
			}
			if ( self.suggestOpen() && self.suggestKey( e ) ) {
				return;
			}
			if ( e.key === 'Enter' && ! e.shiftKey && ! e.isComposing ) {
				e.preventDefault();
				if ( ! document.execCommand( 'insertLineBreak' ) ) {
					insertNode( document.createElement( 'br' ) );
				}
			} else if ( e.key === 'Escape' ) {
				e.preventDefault();
				if ( self.id && ! self.dirty.content ) {
					self.stopEdit();
					self.el.focus();
				}
			}
		} );

		this.body.addEventListener( 'blur', function () {
			window.setTimeout( function () {
				if ( ! self.el.contains( document.activeElement ) ) {
					self.closeSuggest();
				}
			}, 150 );
		} );

		this.okBtn.addEventListener( 'click', function () {
			self.save();
		} );

		this.xBtn.addEventListener( 'click', function () {
			self.askDelete();
		} );

		this.confirm.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b ) {
				return;
			}
			if ( b.getAttribute( 'data-act' ) === 'yes' ) {
				self.destroy();
			} else {
				self.confirm.classList.remove( 'is-open' );
				self.xBtn.focus();
			}
		} );

		this.colorBtn.addEventListener( 'click', function () {
			var open = ! self.colors.classList.contains( 'is-open' );
			self.colors.classList.toggle( 'is-open', open );
			self.colorBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			if ( open ) {
				var cur = self.colors.querySelector( '[aria-pressed="true"]' ) || self.colors.firstChild;
				cur.focus();
			}
		} );

		this.vis.addEventListener( 'change', function () {
			self.data.visibility = self.vis.value;
			self.markDirty( 'visibility' );
		} );

		this.resolveBtn.addEventListener( 'click', function () {
			self.setStatus( self.data.status === 'resolved' ? 'open' : 'resolved' );
		} );

		this.collapsed.addEventListener( 'click', function () {
			self.el.classList.add( 'is-peek' );
		} );
		el0.addEventListener( 'focusout', function () {
			window.setTimeout( function () {
				if ( ! self.el.contains( document.activeElement ) ) {
					self.el.classList.remove( 'is-peek' );
					self.colors.classList.remove( 'is-open' );
				}
			}, 200 );
		} );
	};

	Note.prototype.askDelete = function () {
		if ( ! this.data.can_edit ) {
			return;
		}
		if ( ! this.id && ! this.currentText() ) {
			this.remove();
			return;
		}
		this.confirm.classList.add( 'is-open' );
		this.confirm.querySelector( '[data-act="no"]' ).focus();
	};

	Note.prototype.remove = function () {
		var i = state.notes.indexOf( this );
		if ( i !== -1 ) {
			state.notes.splice( i, 1 );
		}
		if ( this.el.parentNode ) {
			this.el.parentNode.removeChild( this.el );
		}
		refreshLists();
	};

	Note.prototype.destroy = function () {
		var self = this;
		if ( ! this.id ) {
			this.remove();
			return;
		}
		this.el.classList.add( 'is-loading' );
		api( 'DELETE', 'notes/' + this.id ).then( function () {
			self.remove();
			announce( T.yesDelete );
		}, function ( err ) {
			self.el.classList.remove( 'is-loading' );
			self.confirm.classList.remove( 'is-open' );
			self.say( err.message );
		} );
	};

	Note.prototype.setStatus = function ( status ) {
		var self = this;
		if ( ! this.id ) {
			return;
		}
		this.el.classList.add( 'is-loading' );
		api( 'PATCH', 'notes/' + this.id, { status: status } ).then( function ( data ) {
			self.el.classList.remove( 'is-loading' );
			self.update( mergeLocal( self, data ) );
			self.el.classList.remove( 'is-peek' );
			refreshLists();
			announce( status === 'resolved' ? T.resolved : T.reopen );
			if ( status === 'resolved' ) {
				toast( T.toastResolved, T.undo, function () {
					self.setStatus( 'open' );
				} );
			} else {
				toast( T.toastReopened );
			}
		}, function ( err ) {
			self.el.classList.remove( 'is-loading' );
			self.say( err.message );
		} );
	};

	/* Keep unsaved local edits when the server answers a different change. */
	function mergeLocal( note, data ) {
		if ( note.dirty.geometry ) {
			[ 'pos_top', 'pos_center', 'width', 'height', 'viewport_w' ].forEach( function ( k ) {
				data[ k ] = note.data[ k ];
			} );
		}
		if ( note.dirty.color ) {
			data.color = note.data.color;
		}
		if ( note.dirty.visibility ) {
			data.visibility = note.data.visibility;
		}
		return data;
	}

	Note.prototype.save = function ( auto ) {
		var self = this;
		if ( this.saving ) {
			this.saveAgain = true;
			return;
		}
		var text = this.currentText();
		if ( ! text ) {
			if ( ! auto ) {
				this.say( Array.isArray( T.errorEmpty ) ? T.errorEmpty[ Math.floor( Math.random() * T.errorEmpty.length ) ] : T.errorEmpty );
				if ( ! this.editing ) {
					this.startEdit();
				}
			}
			return;
		}
		if ( text.length > C.maxLength ) {
			this.say( sprintf( T.tooLong, C.maxLength ) );
			return;
		}
		if ( this.id && ! Object.keys( this.dirty ).length ) {
			this.stopEdit();
			return;
		}
		this.captureGeometry();
		var d = this.data;
		var body = {};
		var dirty = this.dirty;
		if ( ! this.id || dirty.content ) {
			body.content = text;
		}
		if ( ! this.id || dirty.geometry ) {
			body.pos_top = d.pos_top;
			body.pos_center = d.pos_center;
			body.width = d.width;
			body.height = d.height;
			body.viewport_w = d.viewport_w;
		}
		if ( ! this.id || dirty.color ) {
			body.color = d.color;
		}
		if ( ! this.id || dirty.visibility ) {
			body.visibility = d.visibility;
		}
		var req;
		if ( this.id ) {
			body.updated_at = d.updated_at;
			body.revision = d.revision;
			req = api( 'PATCH', 'notes/' + this.id, body );
		} else {
			body.uri = pageUri();
			body.context = C.context;
			body.title = document.title;
			req = api( 'POST', 'notes', body );
		}
		var sentDirty = this.dirty;
		this.dirty = {};
		this.saving = true;
		this.el.classList.add( 'is-loading' );
		req.then( function ( data ) {
			self.saving = false;
			self.el.classList.remove( 'is-loading' );
			var keepEditing = self.dirty.content || ( self.editing && auto );
			if ( keepEditing ) {
				self.editing = true;
			}
			if ( ! keepEditing ) {
				self.editing = false;
				self.closeSuggest();
			}
			self.update( mergeLocal( self, data ) );
			if ( ! auto ) {
				self.say( T.saved, true );
			}
			refreshLists();
			if ( self.saveAgain ) {
				self.saveAgain = false;
				if ( Object.keys( self.dirty ).length ) {
					self.save( true );
				}
			}
		}, function ( err ) {
			self.saving = false;
			self.el.classList.remove( 'is-loading' );
			Object.keys( sentDirty ).forEach( function ( k ) {
				self.dirty[ k ] = true;
			} );
			self.markSaved();
			if ( err.status === 409 && err.data && err.data.note ) {
				var latest = err.data.note;
				self.say( err.message, false, {
					label: T.reload,
					run: function () {
						self.dirty = {};
						self.editing = false;
						self.update( latest );
						self.say( '' );
					}
				} );
			} else {
				self.say( err.message );
			}
		} );
	};

	/* ---------- mentions ---------- */

	Note.prototype.checkMention = function () {
		var self = this;
		var sel = window.getSelection();
		if ( ! sel.rangeCount || ! C.caps.create ) {
			return;
		}
		var range = sel.getRangeAt( 0 );
		var node = range.startContainer;
		if ( node.nodeType !== 3 || ! this.body.contains( node ) ) {
			this.closeSuggest();
			return;
		}
		var before = node.nodeValue.slice( 0, range.startOffset );
		var m = before.match( /(?:^|[\s(])@([^\s@[\]]{1,30})$/ );
		if ( ! m ) {
			this.closeSuggest();
			return;
		}
		var term = m[ 1 ];
		this.mention = { node: node, end: range.startOffset, start: range.startOffset - term.length - 1, term: term };
		window.clearTimeout( this.mentionTimer );
		this.mentionTimer = window.setTimeout( function () {
			api( 'GET', 'users', null, { search: term } ).then( function ( res ) {
				if ( ! self.mention || self.mention.term !== term ) {
					return;
				}
				self.showSuggest( res.users || [] );
			}, function () {
				self.closeSuggest();
			} );
		}, 180 );
	};

	Note.prototype.showSuggest = function ( users ) {
		var self = this;
		this.suggest.textContent = '';
		this.suggestIndex = 0;
		if ( ! users.length ) {
			this.suggest.appendChild( el( 'div', { class: 'thrg-stk-none', text: T.noMatches } ) );
			return;
		}
		users.forEach( function ( u, i ) {
			var opt = el( 'div', { role: 'option', id: 'thrg-stk-opt-' + i, 'aria-selected': i === 0 ? 'true' : 'false', 'data-id': String( u.id ) }, [
				u.avatar ? el( 'img', { src: u.avatar, alt: '' } ) : null,
				el( 'span', { text: u.label || u.name } )
			] );
			opt.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault();
				self.pick( u );
			} );
			self.suggest.appendChild( opt );
		} );
		this.suggestUsers = users;
		this.body.setAttribute( 'aria-activedescendant', 'thrg-stk-opt-0' );
	};

	Note.prototype.suggestOpen = function () {
		return !! ( this.suggestUsers && this.suggestUsers.length && this.suggest.firstChild );
	};

	Note.prototype.suggestKey = function ( e ) {
		var opts = this.suggest.querySelectorAll( '[role="option"]' );
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			e.preventDefault();
			this.suggestIndex = ( this.suggestIndex + ( e.key === 'ArrowDown' ? 1 : -1 ) + opts.length ) % opts.length;
			Array.prototype.forEach.call( opts, function ( o, i ) {
				o.setAttribute( 'aria-selected', i === this.suggestIndex ? 'true' : 'false' );
			}, this );
			this.body.setAttribute( 'aria-activedescendant', opts[ this.suggestIndex ].id );
			return true;
		}
		if ( e.key === 'Enter' || e.key === 'Tab' ) {
			e.preventDefault();
			this.pick( this.suggestUsers[ this.suggestIndex ] );
			return true;
		}
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			this.closeSuggest();
			return true;
		}
		return false;
	};

	Note.prototype.pick = function ( user ) {
		var m = this.mention;
		if ( ! m || ! user || ! m.node.parentNode ) {
			this.closeSuggest();
			return;
		}
		this.names[ user.id ] = user.name;
		var text = m.node.nodeValue;
		var after = document.createTextNode( ' ' + text.slice( m.end ) );
		m.node.nodeValue = text.slice( 0, m.start );
		var c = chip( user.id, user.name );
		m.node.parentNode.insertBefore( after, m.node.nextSibling );
		m.node.parentNode.insertBefore( c, after );
		var range = document.createRange();
		range.setStart( after, 1 );
		range.collapse( true );
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( range );
		this.closeSuggest();
		this.markDirty( 'content' );
	};

	Note.prototype.closeSuggest = function () {
		this.mention = null;
		this.suggestUsers = null;
		this.suggest.textContent = '';
		this.body.removeAttribute( 'aria-activedescendant' );
		window.clearTimeout( this.mentionTimer );
	};

	function caretToEnd( node ) {
		var range = document.createRange();
		range.selectNodeContents( node );
		range.collapse( false );
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( range );
	}

	function insertNode( node ) {
		var sel = window.getSelection();
		if ( ! sel.rangeCount ) {
			return;
		}
		var range = sel.getRangeAt( 0 );
		range.deleteContents();
		range.insertNode( node );
		range.setStartAfter( node );
		range.collapse( true );
		sel.removeAllRanges();
		sel.addRange( range );
	}

	/* ---------- page-level UI ---------- */

	function origin() {
		var r = els.root.getBoundingClientRect();
		return { x: r.left + window.pageXOffset, y: r.top + window.pageYOffset };
	}

	function announce( text ) {
		els.live.textContent = '';
		window.setTimeout( function () {
			els.live.textContent = text;
		}, 50 );
	}

	var toastTimer = 0;

	/* A short visible confirmation, with an optional action such as Undo. */
	function toast( text, actionLabel, action ) {
		if ( ! els.toast ) {
			return;
		}
		window.clearTimeout( toastTimer );
		els.toast.textContent = '';
		els.toast.appendChild( el( 'span', { text: text } ) );
		if ( actionLabel && action ) {
			var b = el( 'button', { type: 'button', text: actionLabel } );
			b.addEventListener( 'click', function () {
				els.toast.classList.remove( 'is-open' );
				action();
			} );
			els.toast.appendChild( b );
		}
		els.toast.classList.add( 'is-open' );
		toastTimer = window.setTimeout( function () {
			els.toast.classList.remove( 'is-open' );
		}, 7000 );
	}

	function addNote() {
		if ( ! C.caps.create ) {
			return;
		}
		if ( state.hidden ) {
			setHidden( false );
		}
		if ( state.small ) {
			return;
		}
		var vw = viewportWidth();
		var left = C.context === 'admin' ? Math.max( 24, vw - 340 ) : ( edgeToolbar ? 60 : 24 );
		var data = {
			id: 0,
			content: '',
			rendered: '',
			mentions: [],
			pos_top: Math.round( window.pageYOffset + 120 + ( state.notes.filter( function ( n ) {
				return ! n.id;
			} ).length * 24 ) ),
			pos_center: Math.round( vw / 2 - left ),
			width: 270,
			height: 190,
			viewport_w: vw,
			color: 'yellow',
			visibility: 'team',
			status: 'open',
			author: { id: C.user.id, name: C.user.name },
			can_edit: true,
			can_resolve: true,
			can_delete: true
		};
		var note = new Note( data );
		state.notes.push( note );
		els.root.appendChild( note.el );
		note.place();
		note.front();
		note.startEdit();
		refreshLists();
	}

	function setHidden( hidden ) {
		state.hidden = hidden;
		pref( 'hidden', hidden ? '1' : '0' );
		els.root.classList.toggle( 'is-hidden', hidden );
		if ( els.hideBtn ) {
			els.hideBtn.innerHTML = SVG[ hidden ? 'eyeOff' : 'eye' ];
			els.hideBtn.setAttribute( 'aria-label', hidden ? T.show : T.hide );
			els.hideBtn.title = hidden ? T.show : T.hide;
			els.hideBtn.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
		}
		refreshLists();
	}

	function togglePanel( open ) {
		state.panelOpen = typeof open === 'boolean' ? open : ! state.panelOpen;
		els.panel.classList.toggle( 'is-open', state.panelOpen );
		if ( els.listBtn ) {
			els.listBtn.setAttribute( 'aria-expanded', state.panelOpen ? 'true' : 'false' );
		}
		if ( state.panelOpen ) {
			refreshLists();
			els.panel.querySelector( 'h2' ).focus();
		}
	}

	function buildChrome() {
		els.root = el( 'div', { class: 'thrg-stk-root', id: 'thrg-stk-root' } );
		if ( ! edgeToolbar ) {
			els.root.classList.add( 'no-edge' );
		}
		if ( C.context === 'admin' ) {
			els.root.classList.add( 'is-admin' );
		}
		els.live = el( 'div', { class: 'thrg-stk-sr', 'aria-live': 'polite' } );
		els.root.appendChild( els.live );
		els.toast = el( 'div', { class: 'thrg-stk-toast', role: 'status' } );
		els.root.appendChild( els.toast );

		if ( edgeToolbar ) {
			els.toolbar = el( 'div', { class: 'thrg-stk-toolbar', role: 'toolbar', 'aria-label': T.toolbar } );
			if ( C.caps.create ) {
				els.addBtn = el( 'button', { type: 'button', class: 'thrg-stk-add', 'aria-label': T.add, title: T.add, svg: 'plus' } );
				els.addBtn.addEventListener( 'click', addNote );
				els.toolbar.appendChild( els.addBtn );
			}
			els.hideBtn = el( 'button', { type: 'button' } );
			els.hideBtn.addEventListener( 'click', function () {
				setHidden( ! state.hidden );
			} );
			els.listBtn = el( 'button', { type: 'button', 'aria-label': T.list, title: T.list, 'aria-expanded': 'false', 'aria-controls': 'thrg-stk-panel', svg: 'list' } );
			els.badge = el( 'span', { class: 'thrg-stk-badge', 'aria-hidden': 'true' } );
			els.listBtn.appendChild( els.badge );
			els.listBtn.addEventListener( 'click', function () {
				togglePanel();
			} );
			els.toolbar.appendChild( els.hideBtn );
			els.toolbar.appendChild( els.listBtn );
			els.root.appendChild( els.toolbar );
		}

		els.panel = el( 'section', { class: 'thrg-stk-panel', id: 'thrg-stk-panel', 'aria-labelledby': 'thrg-stk-panel-title' } );
		var head = el( 'header', null, [ el( 'h2', { id: 'thrg-stk-panel-title', tabindex: '-1', text: T.list } ) ] );
		if ( C.caps.create ) {
			var add = el( 'button', { type: 'button', class: 'thrg-stk-panel-add' }, [ el( 'span', { svg: 'plus' } ), T.add ] );
			add.addEventListener( 'click', function () {
				addNote();
				togglePanel( false );
			} );
			head.appendChild( add );
		}
		var close = el( 'button', { type: 'button', 'aria-label': T.close, title: T.close, svg: 'x' } );
		close.addEventListener( 'click', function () {
			togglePanel( false );
			if ( els.listBtn ) {
				els.listBtn.focus();
			}
		} );
		head.appendChild( close );
		els.panelNote = el( 'p', { class: 'thrg-stk-panel-note', text: T.smallScreen } );
		els.list = el( 'ul' );
		var resolvedBox = el( 'input', { type: 'checkbox' } );
		resolvedBox.checked = state.showResolved;
		resolvedBox.addEventListener( 'change', function () {
			state.showResolved = resolvedBox.checked;
			pref( 'resolved', state.showResolved ? '1' : '0' );
			els.root.classList.toggle( 'show-resolved', state.showResolved );
			refreshLists();
		} );
		var foot = el( 'footer', null, [ el( 'label', null, [ resolvedBox, T.showResolved ] ) ] );
		if ( C.listUrl ) {
			foot.appendChild( el( 'a', { href: C.listUrl, text: T.manageAll } ) );
		}
		[ head, els.panelNote, els.list, foot ].forEach( function ( n ) {
			els.panel.appendChild( n );
		} );
		els.panel.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				togglePanel( false );
				if ( els.listBtn ) {
					els.listBtn.focus();
				}
			}
		} );
		els.root.appendChild( els.panel );
		els.root.classList.toggle( 'show-resolved', state.showResolved );

		document.body.appendChild( els.root );
		setHidden( state.hidden );

		// Admin bar entry.
		var ab = document.querySelector( '#wp-admin-bar-thrg-stickies > a' );
		if ( ab ) {
			ab.setAttribute( 'role', 'button' );
			ab.setAttribute( 'aria-controls', 'thrg-stk-panel' );
			ab.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				togglePanel();
			} );
			els.abCount = ab.querySelector( '.thrg-stk-ab-count' );
		}
	}

	function refreshLists() {
		var open = state.notes.filter( function ( n ) {
			return n.id && n.data.status !== 'resolved';
		} ).length;
		if ( els.badge ) {
			els.badge.textContent = open ? String( open ) : '';
			els.badge.setAttribute( 'data-n', String( open ) );
		}
		if ( els.abCount ) {
			els.abCount.textContent = String( open );
			els.abCount.setAttribute( 'data-n', String( open ) );
		}
		if ( ! state.panelOpen ) {
			return;
		}
		els.list.textContent = '';
		els.panelNote.hidden = ! state.small;
		var shown = state.notes.filter( function ( n ) {
			return n.id && ( state.showResolved || n.data.status !== 'resolved' );
		} );
		if ( ! shown.length ) {
			els.list.appendChild( el( 'li', { class: 'thrg-stk-panel-empty', text: state.hidden ? T.emptyHidden : T.empty } ) );
			return;
		}
		shown.forEach( function ( n ) {
			var d = n.data;
			var text = plainText( n ).replace( /\s+/g, ' ' ).trim();
			var dot = el( 'span', { class: 'thrg-stk-dot' } );
			dot.style.background = getSwatch( d.color );
			var item = el( 'button', { type: 'button', class: 'thrg-stk-item' + ( d.status === 'resolved' ? ' is-resolved' : '' ), 'aria-expanded': state.small ? ( state.expanded === n.id ? 'true' : 'false' ) : null }, [
				dot,
				el( 'span', { class: 'thrg-stk-item-text' }, [
					el( 'strong', { text: text || '…' } ),
					el( 'small', { text: [ d.author ? d.author.name : '', ago( d.updated ), d.status === 'resolved' ? T.resolved : '' ].filter( Boolean ).join( ' · ' ) } )
				] )
			] );
			item.addEventListener( 'click', function () {
				if ( state.small ) {
					state.expanded = state.expanded === n.id ? 0 : n.id;
					refreshLists();
					return;
				}
				focusNote( n );
			} );
			var li = el( 'li', null, [ item ] );
			if ( state.small && state.expanded === n.id ) {
				var detail = el( 'div', { class: 'thrg-stk-detail' } );
				detail.style.background = getSwatch( d.color );
				var content = el( 'div' );
				content.innerHTML = d.rendered || ''; // Server output filtered by wp_kses.
				detail.appendChild( content );
				if ( d.can_resolve ) {
					var r = el( 'button', { type: 'button', text: d.status === 'resolved' ? T.reopen : T.resolve } );
					r.addEventListener( 'click', function () {
						n.setStatus( d.status === 'resolved' ? 'open' : 'resolved' );
					} );
					detail.appendChild( el( 'div', { class: 'thrg-stk-detail-actions' }, [ r ] ) );
				}
				li.appendChild( detail );
			}
			els.list.appendChild( li );
		} );
	}

	function focusNote( n ) {
		if ( state.hidden ) {
			setHidden( false );
		}
		if ( n.data.status === 'resolved' ) {
			n.el.classList.add( 'is-target', 'is-peek' );
		}
		n.front();
		n.el.scrollIntoView( { block: 'center', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
		n.el.classList.remove( 'is-pulse' );
		void n.el.offsetWidth; // Restart the animation.
		n.el.classList.add( 'is-pulse' );
		window.setTimeout( function () {
			n.el.focus( { preventScroll: true } );
		}, 50 );
	}

	function checkSmall() {
		var small = C.breakpoint > 0 && viewportWidth() < C.breakpoint;
		if ( small !== state.small ) {
			state.small = small;
			els.root.classList.toggle( 'is-small', small );
			refreshLists();
		}
	}

	function layout() {
		state.notes.forEach( function ( n ) {
			n.place();
		} );
	}

	function deepLink() {
		var m = window.location.hash.match( /^#thrg-stk-(\d+)$/ );
		if ( ! m ) {
			return;
		}
		var id = parseInt( m[ 1 ], 10 );
		var n = state.notes.filter( function ( x ) {
			return x.id === id;
		} )[ 0 ];
		if ( ! n ) {
			return;
		}
		if ( state.small ) {
			state.expanded = id;
			togglePanel( true );
			return;
		}
		n.el.classList.add( 'is-target' );
		focusNote( n );
	}

	function load() {
		api( 'GET', 'notes', null, { uri: pageUri(), context: C.context } ).then( function ( res ) {
			( res.notes || [] ).forEach( function ( data ) {
				var n = new Note( data );
				state.notes.push( n );
				els.root.appendChild( n.el );
				n.place();
			} );
			refreshLists();
			deepLink();
		}, function ( err ) {
			if ( window.console ) {
				window.console.warn( 'Stickies:', err.message );
			}
		} );
	}

	function init() {
		buildChrome();
		checkSmall();
		load();

		var raf = 0;
		window.addEventListener( 'resize', function () {
			window.cancelAnimationFrame( raf );
			raf = window.requestAnimationFrame( function () {
				checkSmall();
				layout();
			} );
		} );
		window.addEventListener( 'hashchange', deepLink );

		// Ctrl+S / Cmd+S saves the note being worked on.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && ! e.altKey && ( e.key === 's' || e.key === 'S' ) ) {
				var noteEl = document.activeElement && document.activeElement.closest ? document.activeElement.closest( '.thrg-stk-note' ) : null;
				var n = noteEl ? state.notes.filter( function ( x ) {
					return x.el === noteEl;
				} )[ 0 ] : state.front;
				if ( n && n.data.can_edit && ( noteEl || Object.keys( n.dirty ).length ) ) {
					e.preventDefault();
					n.save();
				}
			}
		} );

		// Refresh relative times now and then.
		window.setInterval( function () {
			state.notes.forEach( function ( n ) {
				n.renderMeta();
			} );
		}, 60000 );

		// Warn before leaving with unsaved notes.
		window.addEventListener( 'beforeunload', function ( e ) {
			var unsaved = state.notes.some( function ( n ) {
				return ( ! n.id && n.currentText() ) || ( n.id && Object.keys( n.dirty ).length );
			} );
			if ( unsaved ) {
				e.preventDefault();
				e.returnValue = '';
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
