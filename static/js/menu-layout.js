/**
 * Admin Skin — the Admin Menu layout screen.
 *
 * One job: make the list sortable. The order that matters is the DOM order of
 * the <li>s at submit time — the server reads it as the index of each row —
 * so dragging IS the model, and there is no hidden field to keep in step.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var $list = $( '.fw-ams-list' );

		if ( ! $list.length || ! $.fn.sortable ) {
			return;
		}

		$list.sortable( {
			handle: '.fw-ams-handle',
			axis: 'y',
			containment: 'parent',
			tolerance: 'pointer',
			placeholder: 'fw-ams-placeholder',
			forcePlaceholderSize: true
		} );

		// The row dims as soon as Hide is ticked, so the effect of the choice
		// is visible before the page is saved.
		$list.on( 'change', 'input[type="checkbox"]', function () {
			$( this ).closest( '.fw-ams-item' ).toggleClass( 'is-hidden', this.checked );
		} );
	} );
}( window.jQuery ) );
