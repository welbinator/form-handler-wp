/**
 * Form Handler WP — Admin JavaScript
 *
 * Handles:
 * - Tab navigation persistence via URL hash
 * - Dynamic field schema rows (add / remove)
 * - Test email AJAX request
 * - Delete form confirmation
 * - Clear log confirmation
 *
 * @package Form_Handler_WP
 */

/* global fhwAdmin, jQuery */
( function ( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Tab navigation
	// -----------------------------------------------------------------------
	function initTabs() {
		var $tabs = $( '.fhw-tab-nav a' );

		$tabs.on( 'click', function () {
			$tabs.removeClass( 'fhw-tab-active' );
			$( this ).addClass( 'fhw-tab-active' );
		} );
	}

	// -----------------------------------------------------------------------
	// Dynamic field schema rows
	// -----------------------------------------------------------------------
	var fieldRowIndex = $( '.fhw-field-row' ).length;

	function buildFieldRow( index ) {
		var typeOptions =
			'<option value="text">text</option>' +
			'<option value="email">email</option>' +
			'<option value="textarea">textarea</option>' +
			'<option value="url">url</option>' +
			'<option value="number">number</option>' +
			'<option value="checkbox">checkbox</option>';

		return (
			'<div class="fhw-field-row">' +
				'<input type="text" ' +
					'name="field_schema[' + index + '][field_name]" ' +
					'placeholder="field_name" ' +
					'pattern="[a-z0-9_]+" ' +
					'title="Lowercase letters, numbers, and underscores only" />' +
				'<select name="field_schema[' + index + '][field_type]">' +
					typeOptions +
				'</select>' +
				'<button type="button" class="fhw-remove-field" aria-label="Remove field">' +
					'&times;' +
				'</button>' +
			'</div>'
		);
	}

	function initFieldSchema() {
		var $container = $( '#fhw-field-rows' );
		var $addBtn    = $( '#fhw-add-field-btn' );

		if ( ! $container.length ) {
			return;
		}

		$addBtn.on( 'click', function () {
			$container.append( buildFieldRow( fieldRowIndex ) );
			fieldRowIndex++;
		} );

		$container.on( 'click', '.fhw-remove-field', function () {
			$( this ).closest( '.fhw-field-row' ).remove();
		} );
	}

	// -----------------------------------------------------------------------
	// Test email
	// -----------------------------------------------------------------------
	function initTestEmail() {
		var $btn    = $( '#fhw-test-email-btn' );
		var $input  = $( '#fhw-test-email-address' );
		var $result = $( '#fhw-test-result' );

		if ( ! $btn.length ) {
			return;
		}

		$btn.on( 'click', function () {
			var email = $input.val().trim();

			if ( ! email ) {
				$result
					.removeClass( 'fhw-success' )
					.addClass( 'fhw-error' )
					.text( 'Please enter an email address.' );
				return;
			}

			$btn.prop( 'disabled', true );
			$result.removeClass( 'fhw-success fhw-error' ).text( fhwAdmin.sending );

			$.post(
				fhwAdmin.ajaxUrl,
				{
					action  : 'fhw_send_test_email',
					nonce   : fhwAdmin.testEmailNonce,
					to_email: email,
				},
				function ( response ) {
					if ( response.success ) {
						$result
							.removeClass( 'fhw-error' )
							.addClass( 'fhw-success' )
							.text( response.data.message || fhwAdmin.testSuccess );
					} else {
						$result
							.removeClass( 'fhw-success' )
							.addClass( 'fhw-error' )
							.text(
								( response.data && response.data.message )
									? response.data.message
									: fhwAdmin.testFail
							);
					}
				}
			).fail( function () {
				$result
					.removeClass( 'fhw-success' )
					.addClass( 'fhw-error' )
					.text( fhwAdmin.testFail );
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Delete form confirmation
	// -----------------------------------------------------------------------
	function initDeleteConfirm() {
		$( document ).on( 'submit', '.fhw-delete-form', function () {
			return window.confirm( fhwAdmin.confirmDelete );
		} );
	}

	// -----------------------------------------------------------------------
	// Clear log confirmation
	// -----------------------------------------------------------------------
	function initClearLogConfirm() {
		$( document ).on( 'submit', '#fhw-clear-log-form', function () {
			return window.confirm( fhwAdmin.confirmClearLog );
		} );
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------
	$( function () {
		initTabs();
		initFieldSchema();
		initTestEmail();
		initDeleteConfirm();
		initClearLogConfirm();
	} );

} )( jQuery );
