/**
 * Web Component <wppa-donation-widget> — Paywall per attachment protetti.
 *
 * Stati:
 *   A — paywall (default): anteprima sfocata + CTA donazione + link free-view
 *   B — inserimento codice: form input + link per richiedere nuovo codice
 *   C — immagine sbloccata: img HD + pulsante download
 *   D — visualizzazione gratuita: img HD + banner "stai usando la visione gratuita"
 *
 * Slice 5 implementa lo Stato A. Gli stati B/C/D arrivano in Slice 6 e 7.
 */

const TEMPLATE = `
<style>
  :host {
    display: block;
    font-family: inherit;
    --wppa-primary: #2271b1;
    --wppa-primary-hover: #135e96;
    --wppa-radius: 8px;
    --wppa-shadow: 0 2px 12px rgba(0,0,0,.15);
  }

  .widget {
    max-width: 640px;
    margin: 0 auto;
    border-radius: var(--wppa-radius);
    box-shadow: var(--wppa-shadow);
    overflow: hidden;
    background: #fff;
  }

  /* ── Stato A: paywall ── */
  .state-a { display: block; }
  .state-b,
  .state-c,
  .state-d { display: none; }

  .preview {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: #f0f0f0;
  }
  .preview__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(8px) brightness(.85);
    transform: scale(1.05);
    display: block;
  }
  .preview__overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,.35);
  }
  .preview__lock {
    color: #fff;
    font-size: 2.5rem;
    line-height: 1;
  }

  .body {
    padding: 1.5rem;
    text-align: center;
  }
  .body__title {
    margin: 0 0 .5rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1d2327;
  }
  .body__text {
    margin: 0 0 1.25rem;
    color: #646970;
    font-size: .95rem;
    line-height: 1.5;
  }

  /* Pillole importi */
  .amounts {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: center;
    margin-bottom: 1rem;
  }
  .amounts__pill {
    padding: .35rem .85rem;
    border: 2px solid var(--wppa-primary);
    border-radius: 100px;
    background: transparent;
    color: var(--wppa-primary);
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, color .15s;
  }
  .amounts__pill:hover,
  .amounts__pill--selected {
    background: var(--wppa-primary);
    color: #fff;
  }

  /* CTA pulsante donazione */
  .btn-donate {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .65rem 1.5rem;
    background: var(--wppa-primary);
    color: #fff;
    border: none;
    border-radius: var(--wppa-radius);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    text-decoration: none;
  }
  .btn-donate:hover { background: var(--wppa-primary-hover); }
  .btn-donate:disabled {
    opacity: .5;
    cursor: not-allowed;
  }

  .divider {
    margin: 1rem 0;
    color: #a7aaad;
    font-size: .85rem;
  }

  .link-unlock,
  .link-free-view {
    background: none;
    border: none;
    color: var(--wppa-primary);
    cursor: pointer;
    font-size: .9rem;
    text-decoration: underline;
    padding: 0;
  }
  .link-unlock:hover,
  .link-free-view:hover { color: var(--wppa-primary-hover); }

  /* ── Stato B: inserimento codice ── */
  .state-b .body { text-align: left; }
  .form-row { margin-bottom: 1rem; }
  .form-row label {
    display: block;
    font-weight: 600;
    margin-bottom: .3rem;
    font-size: .9rem;
  }
  .form-row input[type="text"] {
    width: 100%;
    padding: .5rem .75rem;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    font-size: 1rem;
    box-sizing: border-box;
    letter-spacing: .05em;
  }
  .form-row input[type="text"]:focus {
    outline: 2px solid var(--wppa-primary);
    border-color: transparent;
  }
  .form-error {
    color: #d63638;
    font-size: .85rem;
    margin-top: .25rem;
  }

  /* ── Stato C/D: immagine sbloccata ── */
  .unlocked-img {
    width: 100%;
    display: block;
  }
  .banner-free {
    background: #ffe8c0;
    color: #8a6200;
    padding: .6rem 1rem;
    font-size: .85rem;
    text-align: center;
  }
  .btn-download {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .55rem 1.25rem;
    background: #00a32a;
    color: #fff;
    border: none;
    border-radius: var(--wppa-radius);
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s;
  }
  .btn-download:hover { background: #008a20; }
</style>

<!-- Stato A -->
<div class="widget state-a" part="widget">
  <div class="preview">
    <img class="preview__img" src="" alt="" />
    <div class="preview__overlay">
      <span class="preview__lock" aria-hidden="true">🔒</span>
    </div>
  </div>
  <div class="body">
    <h2 class="body__title"></h2>
    <p class="body__text"></p>
    <div class="amounts"></div>
    <button class="btn-donate" type="button" disabled>Dona per sbloccare</button>
    <div class="divider">oppure</div>
    <button class="link-unlock" type="button">Ho già un codice di sblocco</button>
    <br><br>
    <button class="link-free-view" type="button" style="display:none">Continua senza donare (visione gratuita)</button>
  </div>
</div>

<!-- Stato B: inserimento codice -->
<div class="widget state-b" part="widget">
  <div class="body">
    <h2 class="body__title">Inserisci il codice di sblocco</h2>
    <div class="form-row">
      <label for="wppa-code-input">Codice (formato XXXX-XXXX-XXXX)</label>
      <input type="text" id="wppa-code-input" autocomplete="off" spellcheck="false" maxlength="50" placeholder="XXXX-XXXX-XXXX" />
      <div class="form-error" style="display:none"></div>
    </div>
    <button class="btn-donate" type="button">Valida codice</button>
    <div class="divider">oppure</div>
    <button class="link-unlock" type="button">← Torna al paywall</button>
  </div>
</div>

<!-- Stato C: sbloccato -->
<div class="widget state-c" part="widget">
  <img class="unlocked-img" src="" alt="" />
  <div class="body">
    <a class="btn-download" href="#" download>⬇ Scarica immagine HD</a>
  </div>
</div>

<!-- Stato D: free view -->
<div class="widget state-d" part="widget">
  <div class="banner-free">👁 Stai usando la visione gratuita — supporta l'autore con una donazione!</div>
  <img class="unlocked-img" src="" alt="" />
  <div class="body">
    <a class="btn-download" href="#" download>⬇ Scarica immagine HD</a>
    <div style="margin-top:.75rem">
      <button class="btn-donate" type="button">Dona per supportare l'autore</button>
    </div>
  </div>
</div>
`;

class WppaDonationWidget extends HTMLElement {
	static get observedAttributes() {
		return [
			'attachment-id',
			'thumbnail',
			'title',
			'amounts',
			'currency',
			'allow-free-view',
			'api-root',
			'nonce',
		];
	}

	constructor() {
		super();
		this.attachShadow( { mode: 'open' } );
		this.shadowRoot.innerHTML = TEMPLATE;
		this._state = 'A';
		this._selectedAmount = null;
		this._unlockToken = null;
	}

	connectedCallback() {
		this._render();
		this._bindEvents();
		this._checkCookieOrAutoUnlock();
	}

	attributeChangedCallback() {
		if ( this.shadowRoot.querySelector( '.state-a' ) ) {
			this._render();
		}
	}

	/* ── Getters attributi ─────────────────────────────── */

	get attachmentId() { return parseInt( this.getAttribute( 'attachment-id' ) || '0', 10 ); }
	get thumbnail()    { return this.getAttribute( 'thumbnail' ) || ''; }
	get title()        { return this.getAttribute( 'title' ) || ''; }
	get amounts()      {
		try { return JSON.parse( this.getAttribute( 'amounts' ) || '[]' ); }
		catch { return []; }
	}
	get currency()      { return this.getAttribute( 'currency' ) || 'EUR'; }
	get allowFreeView() { return this.getAttribute( 'allow-free-view' ) !== 'false'; }
	get apiRoot()       { return this.getAttribute( 'api-root' ) || ''; }
	get nonce()         { return this.getAttribute( 'nonce' ) || ''; }

	/* ── Render Stato A ────────────────────────────────── */

	_render() {
		const sr = this.shadowRoot;

		// Preview thumbnail (sfocata).
		const img = sr.querySelector( '.preview__img' );
		img.src = this.thumbnail;
		img.alt = this.title;

		// Titolo e testo.
		sr.querySelector( '.body__title' ).textContent = this.title;
		sr.querySelector( '.state-a .body__text' ).textContent =
			'Questo contenuto è disponibile dopo una donazione libera.';

		// Pillole importi.
		const amountsContainer = sr.querySelector( '.amounts' );
		amountsContainer.innerHTML = '';
		this.amounts.forEach( ( amount ) => {
			const pill = document.createElement( 'button' );
			pill.className = 'amounts__pill';
			pill.type = 'button';
			pill.textContent = `${ this.currency === 'EUR' ? '€' : '$' }${ amount }`;
			pill.dataset.amount = amount;
			pill.addEventListener( 'click', () => this._selectAmount( amount, pill ) );
			amountsContainer.appendChild( pill );
		} );

		// CTA donate — abilitato solo dopo selezione importo.
		const btnDonate = sr.querySelector( '.state-a .btn-donate' );
		btnDonate.disabled = true;

		// Link free view.
		const freeViewBtn = sr.querySelector( '.link-free-view' );
		freeViewBtn.style.display = this.allowFreeView ? '' : 'none';
	}

	/* ── Selezione importo ─────────────────────────────── */

	_selectAmount( amount, pill ) {
		const sr = this.shadowRoot;
		sr.querySelectorAll( '.amounts__pill' ).forEach( ( p ) => p.classList.remove( 'amounts__pill--selected' ) );
		pill.classList.add( 'amounts__pill--selected' );
		this._selectedAmount = amount;
		sr.querySelector( '.state-a .btn-donate' ).disabled = false;
	}

	/* ── Binding eventi ────────────────────────────────── */

	_bindEvents() {
		const sr = this.shadowRoot;

		// Stato A → B (inserimento codice).
		sr.querySelector( '.state-a .link-unlock' ).addEventListener( 'click', () => this._goTo( 'B' ) );

		// Stato A → Free view (Stato D) — Slice 7.
		sr.querySelector( '.link-free-view' ).addEventListener( 'click', () => this._requestFreeView() );

		// Stato A → Donazione — Slice 8/9.
		sr.querySelector( '.state-a .btn-donate' ).addEventListener( 'click', () => this._startDonation() );

		// Stato B: valida codice.
		sr.querySelector( '.state-b .btn-donate' ).addEventListener( 'click', () => this._validateCode() );

		// Stato B → A (torna indietro).
		sr.querySelector( '.state-b .link-unlock' ).addEventListener( 'click', () => this._goTo( 'A' ) );

		// Formato automatico codice XXXX-XXXX-XXXX (solo per codici ≤12 alfanumerici).
		const codeInput = sr.querySelector( '#wppa-code-input' );
		codeInput.addEventListener( 'input', () => {
			let v = codeInput.value.replace( /[^A-Z0-9a-z]/gi, '' ).toUpperCase();
			// Auto-formatta solo se rientra nel pattern XXXX-XXXX-XXXX.
			if ( v.length <= 12 ) {
				if ( v.length > 4 ) v = v.slice( 0, 4 ) + '-' + v.slice( 4 );
				if ( v.length > 9 ) v = v.slice( 0, 9 ) + '-' + v.slice( 9 );
				codeInput.value = v.slice( 0, 14 );
			}
		} );
	}

	/* ── Navigazione stati ─────────────────────────────── */

	_goTo( state ) {
		const sr = this.shadowRoot;
		[ 'A', 'B', 'C', 'D' ].forEach( ( s ) => {
			sr.querySelector( `.state-${ s.toLowerCase() }` ).style.display = s === state ? 'block' : 'none';
		} );
		this._state = state;

		// Ripristina stati interni.
		if ( state === 'B' ) {
			sr.querySelector( '#wppa-code-input' ).value = '';
			this._hideError();
		}
	}

	/* ── Validazione codice (Stato B → C) ─────────────── */

	async _validateCode() {
		const sr    = this.shadowRoot;
		const input = sr.querySelector( '#wppa-code-input' );
		const code  = input.value.trim();

		if ( code.length < 4 ) {
			this._showError( 'Inserisci un codice valido.' );
			return;
		}

		sr.querySelector( '.state-b .btn-donate' ).disabled = true;
		this._hideError();

		try {
			const res = await fetch( `${ this.apiRoot }/unlock`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   this.nonce,
				},
				body: JSON.stringify( {
					attachment_id: this.attachmentId,
					code,
				} ),
			} );

			const data = await res.json();

			if ( ! res.ok ) {
				this._showError( data.message || 'Codice non valido.' );
				return;
			}

			this._unlockToken = data.token;
			this._showUnlocked( data.token, data.download_token, data.cookie_token );
		} catch {
			this._showError( 'Errore di rete. Riprova.' );
		} finally {
			sr.querySelector( '.state-b .btn-donate' ).disabled = false;
		}
	}

	/* ── Mostra immagine sbloccata (Stato C) ───────────── */

	_showUnlocked( viewToken, downloadToken, cookieToken ) {
		const sr = this.shadowRoot;
		const img = sr.querySelector( '.state-c .unlocked-img' );
		img.src = `${ this.apiRoot }/download/${ viewToken }`;
		img.alt = this.title;

		const btnDownload = sr.querySelector( '.state-c .btn-download' );
		btnDownload.href = `${ this.apiRoot }/download/${ downloadToken }`;

		if ( cookieToken ) {
			this._setCookie( `wppa_unlock_${ this.attachmentId }`, cookieToken, 86400 );
		}

		this._goTo( 'C' );
		this._dispatchUnlocked( 'code' );
	}

	/* ── Free view (Stato D) ───────────────────────────── */

	async _requestFreeView() {
		// Controlla sessionStorage — evita chiamate REST ridondanti.
		const storageKey = `wppa_freeview_${ this.attachmentId }`;
		const cached     = sessionStorage.getItem( storageKey );
		if ( cached ) {
			try {
				const parsed = JSON.parse( cached );
				this._showFreeView( parsed.token, parsed.download_token );
				return;
			} catch {
				sessionStorage.removeItem( storageKey );
			}
		}

		try {
			const res = await fetch( `${ this.apiRoot }/free-view`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   this.nonce,
				},
				body: JSON.stringify( { attachment_id: this.attachmentId } ),
			} );

			const data = await res.json();
			if ( ! res.ok ) {
				// Mostra errore sopra il pulsante free-view (non blocca l'UI).
				alert( data.message || 'Visualizzazione gratuita non disponibile.' );
				return;
			}

			sessionStorage.setItem( storageKey, JSON.stringify( {
				token:          data.token,
				download_token: data.download_token,
			} ) );
			this._showFreeView( data.token, data.download_token );
		} catch {
			alert( 'Errore di rete. Riprova.' );
		}
	}

	_showFreeView( viewToken, downloadToken ) {
		const sr  = this.shadowRoot;
		const img = sr.querySelector( '.state-d .unlocked-img' );
		img.src = `${ this.apiRoot }/download/${ viewToken }?t=fv`;
		img.alt = this.title;

		if ( downloadToken ) {
			const btnDownload = sr.querySelector( '.state-d .btn-download' );
			if ( btnDownload ) {
				btnDownload.href = `${ this.apiRoot }/download/${ downloadToken }`;
			}
		}

		this._goTo( 'D' );
		this._dispatchUnlocked( 'free_view' );
	}

	/* ── Donazione — implementata in Slice 8/9 ─────────── */

	_startDonation() {
		this._dispatchCustom( 'wppa:donate-requested', {
			amount:   this._selectedAmount,
			currency: this.currency,
		} );
	}

	/* ── Cookie + Auto-unlock (Slice 10) ────────────────── */

	async _checkCookieOrAutoUnlock() {
		const restored = await this._checkCookie();
		if ( ! restored ) {
			this._checkAutoUnlock();
		}
	}

	async _checkCookie() {
		const token = this._getCookie( `wppa_unlock_${ this.attachmentId }` );
		if ( ! token ) return false;

		try {
			const res = await fetch( `${ this.apiRoot }/unlock/resume`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   this.nonce,
				},
				body: JSON.stringify( {
					attachment_id: this.attachmentId,
					cookie_token:  token,
				} ),
			} );

			if ( ! res.ok ) {
				// Token scaduto o invalido: cancella il cookie.
				this._deleteCookie( `wppa_unlock_${ this.attachmentId }` );
				return false;
			}

			const data = await res.json();
			this._showUnlocked( data.token, data.download_token );
			return true;
		} catch {
			return false;
		}
	}

	_checkAutoUnlock() {
		const params = new URLSearchParams( window.location.search );
		const code   = params.get( 'wppa_unlock' );
		if ( code && parseInt( params.get( 'attachment_id' ) || '0', 10 ) === this.attachmentId ) {
			this._goTo( 'B' );
			this.shadowRoot.querySelector( '#wppa-code-input' ).value = code;
			this._validateCode();
		}
	}

	_setCookie( name, value, maxAge ) {
		document.cookie = `${ name }=${ encodeURIComponent( value ) }; max-age=${ maxAge }; path=/; SameSite=Strict`;
	}

	_getCookie( name ) {
		const escaped = name.replace( /([.*+?^=!:${}()|[\]/\\])/g, '\\$1' );
		const match   = document.cookie.match( new RegExp( '(?:^|; )' + escaped + '=([^;]*)' ) );
		return match ? decodeURIComponent( match[1] ) : null;
	}

	_deleteCookie( name ) {
		document.cookie = `${ name }=; max-age=0; path=/; SameSite=Strict`;
	}

	/* ── Helpers ────────────────────────────────────────── */

	_showError( msg ) {
		const el = this.shadowRoot.querySelector( '.form-error' );
		el.textContent = msg;
		el.style.display = '';
	}

	_hideError() {
		const el = this.shadowRoot.querySelector( '.form-error' );
		el.style.display = 'none';
	}

	_dispatchUnlocked( method ) {
		this._dispatchCustom( 'wppa:unlocked', { attachmentId: this.attachmentId, method } );
	}

	_dispatchCustom( type, detail ) {
		this.dispatchEvent( new CustomEvent( type, { bubbles: true, composed: true, detail } ) );
	}
}

if ( ! customElements.get( 'wppa-donation-widget' ) ) {
	customElements.define( 'wppa-donation-widget', WppaDonationWidget );
}
