/**
 * Form Handler WP — Front-end form handler
 *
 * Automatically intercepts any <form data-fhw-form="action_name"> on the page,
 * fetches a nonce, and handles AJAX submission with inline success/error feedback.
 *
 * No JavaScript required from the developer. Just:
 *   <form data-fhw-form="your_action_name"> ... </form>
 *
 * Optional attributes on the <form> element:
 *   data-fhw-success   — Custom success message (overrides plugin setting)
 *   data-fhw-error     — Custom generic error message
 *   data-fhw-reset     — Set to "false" to prevent form reset on success
 *
 * Optional status container (placed anywhere inside or near the form):
 *   <div data-fhw-status></div>
 *   If absent, one is created and appended inside the form automatically.
 *
 * @package Form_Handler_WP
 */

( function () {
	'use strict';

	/**
	 * Site root URL injected by wp_localize_script().
	 * Falls back to current origin if not available.
	 */
	var AJAX_URL  = ( window.fhwData && window.fhwData.ajaxUrl )  ? window.fhwData.ajaxUrl  : '/wp-admin/admin-ajax.php';
	var NONCE_URL = ( window.fhwData && window.fhwData.nonceUrl ) ? window.fhwData.nonceUrl : '/?fhw_get_nonce=';

	/**
	 * Initialise all forms on the page.
	 */
	function initForms() {
		var forms = document.querySelectorAll( '[data-fhw-form]' );
		if ( ! forms.length ) {
			return;
		}
		Array.prototype.forEach.call( forms, function ( form ) {
			initForm( form );
		} );
	}

	/**
	 * Set up a single form.
	 *
	 * @param {HTMLFormElement} form
	 */
	function initForm( form ) {
		var action = form.getAttribute( 'data-fhw-form' );
		if ( ! action ) {
			return;
		}

		// Create a hidden nonce input.
		var nonceInput       = document.createElement( 'input' );
		nonceInput.type      = 'hidden';
		nonceInput.name      = 'fhw_' + action + '_nonce';
		nonceInput.id        = 'fhw-nonce-' + action;
		nonceInput.value     = '';
		form.appendChild( nonceInput );

		// Ensure there is a status element.
		var statusEl = form.querySelector( '[data-fhw-status]' );
		if ( ! statusEl ) {
			statusEl = document.createElement( 'div' );
			statusEl.setAttribute( 'data-fhw-status', '' );
			statusEl.setAttribute( 'role', 'alert' );
			statusEl.setAttribute( 'aria-live', 'polite' );
			form.appendChild( statusEl );
		}

		// Ensure there is a submit button reference.
		var submitBtn = form.querySelector( '[type="submit"]' );

		// Pre-fetch nonce.
		fetchNonce( action, nonceInput );

		// Intercept submit.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			handleSubmit( form, action, nonceInput, submitBtn, statusEl );
		} );
	}

	/**
	 * Fetch a fresh nonce for the given action and populate the input.
	 *
	 * @param {string}          action
	 * @param {HTMLInputElement} nonceInput
	 */
	function fetchNonce( action, nonceInput ) {
		fetch( NONCE_URL + encodeURIComponent( action ) )
			.then( function ( r ) { return r.json(); } )
			.then( function ( d ) {
				if ( d && d.nonce ) {
					nonceInput.value = d.nonce;
				}
			} )
			.catch( function () {
				// Nonce fetch failed silently — server will return 403 on submit.
			} );
	}

	/**
	 * Handle form submission.
	 *
	 * @param {HTMLFormElement} form
	 * @param {string}          action
	 * @param {HTMLInputElement} nonceInput
	 * @param {HTMLElement|null} submitBtn
	 * @param {HTMLElement}     statusEl
	 */
	function handleSubmit( form, action, nonceInput, submitBtn, statusEl ) {
		var originalBtnText = submitBtn ? submitBtn.textContent : '';

		// Disable button while submitting.
		setSubmitting( submitBtn, true );
		hideStatus( statusEl );

		// Collect all form fields into URLSearchParams.
		var data = new URLSearchParams();
		data.append( 'action', action );
		data.append( nonceInput.name, nonceInput.value );

		// Collect all named inputs (except submit buttons).
		var elements = form.elements;
		Array.prototype.forEach.call( elements, function ( el ) {
			if ( ! el.name || el.disabled ) {
				return;
			}
			if ( 'submit' === el.type || 'button' === el.type ) {
				return;
			}
			if ( 'checkbox' === el.type || 'radio' === el.type ) {
				if ( el.checked ) {
					data.append( el.name, el.value );
				}
				return;
			}
			// Skip our injected nonce — already added above.
			if ( el === nonceInput ) {
				return;
			}
			data.append( el.name, el.value );
		} );

		fetch( AJAX_URL, {
			method : 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body   : data.toString()
		} )
		.then( function ( r ) { return r.json(); } )
		.then( function ( response ) {
			if ( response.success ) {
				var msg = form.getAttribute( 'data-fhw-success' )
					|| ( response.data && response.data.message )
					|| 'Thank you! Your message has been sent.';
				showStatus( statusEl, 'success', msg );

				// Reset form unless opted out.
				if ( 'false' !== form.getAttribute( 'data-fhw-reset' ) ) {
					form.reset();
					// Re-fetch nonce after reset so subsequent submissions work.
					fetchNonce( action, nonceInput );
				}
			} else {
				var errMsg = form.getAttribute( 'data-fhw-error' )
					|| ( response.data && response.data.message )
					|| 'Something went wrong. Please try again.';
				showStatus( statusEl, 'error', errMsg );
			}
		} )
		.catch( function () {
			showStatus( statusEl, 'error',
				form.getAttribute( 'data-fhw-error' ) || 'A network error occurred. Please try again.'
			);
		} )
		.finally( function () {
			setSubmitting( submitBtn, false, originalBtnText );
		} );
	}

	/**
	 * Toggle the submitting state on the submit button.
	 *
	 * @param {HTMLElement|null} btn
	 * @param {boolean}          isSubmitting
	 * @param {string}           [originalText]
	 */
	function setSubmitting( btn, isSubmitting, originalText ) {
		if ( ! btn ) {
			return;
		}
		btn.disabled = isSubmitting;
		if ( isSubmitting ) {
			btn.setAttribute( 'data-fhw-original-text', btn.textContent );
			btn.textContent = btn.getAttribute( 'data-fhw-loading-text' ) || 'Sending\u2026';
		} else {
			btn.textContent = originalText || btn.getAttribute( 'data-fhw-original-text' ) || btn.textContent;
		}
	}

	/**
	 * Show a status message.
	 *
	 * Adds a BEM-style class fhw-status--success / fhw-status--error
	 * plus a legacy plain class for easy CSS targeting.
	 *
	 * @param {HTMLElement} el
	 * @param {string}      type    'success' | 'error'
	 * @param {string}      message
	 */
	function showStatus( el, type, message ) {
		el.className     = 'fhw-status fhw-status--' + type;
		el.innerHTML     = message; // Allow HTML in success messages (e.g. links).
		el.style.display = 'block';
	}

	/**
	 * Hide the status element.
	 *
	 * @param {HTMLElement} el
	 */
	function hideStatus( el ) {
		el.style.display = 'none';
		el.className     = 'fhw-status';
		el.textContent   = '';
	}

	// Boot when DOM is ready.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initForms );
	} else {
		initForms();
	}

}() );
