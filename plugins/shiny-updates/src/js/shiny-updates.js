/* global pagenow */
/**
 *
 * @param {jQuery}  $                                   jQuery object.
 * @param {object}  wp                                  WP object.
 * @param {object}  settings                            WP Updates settings.
 * @param {object}  shinySettings                       Shiny Updates settings.
 * @param {object}  shinySettings.l10n                  Translation strings.
 */
(function( $, wp, settings, shinySettings ) {
	var $document = $( document );

	if ( ! wp || ! wp.updates ) {
		return;
	}

	wp.updates.l10n = _.extend( settings.l10n, shinySettings.l10n );

	/**
	 * Holds the URL the user is being redirected to after a successful core update.
	 *
	 * @since 4.X.0
	 *
	 * @type {string}
	 */
	wp.updates.coreUpdateRedirect = undefined;

	wp.updates.updatePlugin = function( args ) {
		var $updateRow, $card, $message, message;

		args = _.extend( {
			success: wp.updates.updatePluginSuccess,
			error: wp.updates.updatePluginError
		}, args );

		if ( 'update-core' === pagenow || 'update-core-network' === pagenow ) {
			$message = $( '.update-link[data-plugin="' + args.plugin + '"]' ).addClass( 'updating-message' );
			message  = wp.updates.l10n.updatingLabel.replace( '%s', $message.data( 'name' ) );
		} else if ( 'plugins' === pagenow || 'plugins-network' === pagenow ) {
			$updateRow = $( 'tr[data-plugin="' + args.plugin + '"]' );
			$message   = $updateRow.find( '.update-message' ).addClass( 'updating-message' ).find( 'p' );
			message    = wp.updates.l10n.updatingLabel.replace( '%s', $updateRow.find( '.plugin-title strong' ).text() );
		} else if ( 'plugin-install' === pagenow || 'plugin-install-network' === pagenow ) {
			$card    = $( '.plugin-card-' + args.slug );
			$message = $card.find( '.update-now' ).addClass( 'updating-message' );
			message  = wp.updates.l10n.updatingLabel.replace( '%s', $message.data( 'name' ) );

			// Remove previous error messages, if any.
			$card.removeClass( 'plugin-card-update-failed' ).find( '.notice.notice-error' ).remove();
		}

		if ( $message.html() !== wp.updates.l10n.updating ) {
			$message.data( 'originaltext', $message.html() );
		}

		$message
			.attr( 'aria-label', message )
			.text( wp.updates.l10n.updating );

		$document.trigger( 'wp-plugin-updating' );

		return wp.updates.ajax( 'update-plugin', args );
	};

	wp.updates.updateTheme = function( args ) {
		var $notice;

		args = _.extend( {
			success: wp.updates.updateThemeSuccess,
			error: wp.updates.updateThemeError
		}, args );

		if ( 'update-core' === pagenow || 'update-core-network' === pagenow ) {
			$notice = $( '.update-link', '[data-slug="' + args.slug + '"]' ).addClass( 'updating-message' );
		} else if ( 'themes-network' === pagenow ) {
			$notice = $( '[data-slug="' + args.slug + '"]' ).find( '.update-message' ).addClass( 'updating-message' ).find( 'p' );

		} else {
			$notice = $( '#update-theme' ).closest( '.notice' ).removeClass( 'notice-large' );

			$notice.find( 'h3' ).remove();

			$notice = $notice.add( $( '[data-slug="' + args.slug + '"]' ).find( '.update-message' ) );
			$notice = $notice.addClass( 'updating-message' ).find( 'p' );
		}

		if ( $notice.html() !== wp.updates.l10n.updating ) {
			$notice.data( 'originaltext', $notice.html() );
		}

		wp.a11y.speak( wp.updates.l10n.updatingMsg, 'polite' );
		$notice.text( wp.updates.l10n.updating );

		return wp.updates.ajax( 'update-theme', args );
	};

	/**
	 * Sends an Ajax request to the server to update WordPress core.
	 *
	 * @since 4.X.0
	 *
	 * @param {object}             args           Arguments.
	 * @param {string}             args.version   The version to update to.
	 * @param {string}             args.locale    The locale to get the update for.
	 * @param {boolean}            args.reinstall Whether this is a reinstall request or not.
	 * @param {updateItemSuccess=} args.success   Optional. Success callback. Default: wp.updates.updateItemSuccess
	 * @param {updateItemError=}   args.error     Optional. Error callback. Default: wp.updates.updateItemError
	 * @return {$.promise} A jQuery promise that represents the request,
	 *                     decorated with an abort() method.
	 */
	wp.updates.updateCore = function( args ) {
		var $message;

		args = _.extend( {
			success: wp.updates.updateItemSuccess,
			error: wp.updates.updateItemError
		}, args );

		$message = $( '[data-type="core"]' ).filter( function() {
			var $coreRow = $( this );

			return args.reinstall && $coreRow.is( '.wordpress-reinstall-card-item' ) ||
				! args.reinstall && ! $coreRow.is( '.wordpress-reinstall-card-item' ) && args.locale === $coreRow.data( 'locale' );
		} ).find( '.update-link' );

		if ( $message.html() !== wp.updates.l10n.updating ) {
			$message.data( 'originaltext', $message.html() );
		}

		$message.addClass( 'updating-message' )
			.attr( 'aria-label', wp.updates.l10n.updatingCoreLabel )
			.text( wp.updates.l10n.updating );

		wp.updates.addAdminNotice( {
			id:        'core-redirect',
			className: 'is-dismissible',
			message:   wp.updates.l10n.coreRedirect
		} );

		// Core updates should always come last to redirect to the about page.
		if ( wp.updates.queue.length ) {
			wp.updates.queue.push( {
				action: 'update-core',
				data:   args
			} );

			return wp.updates.queueChecker();
		}

		return wp.updates.ajax( 'update-core', args );
	};

	/**
	 * Sends an Ajax request to the server to update translations.
	 *
	 * @since 4.X.0
	 *
	 * @param {object}             args         Arguments.
	 * @param {updateItemSuccess=} args.success Optional. Success callback. Default: wp.updates.updateItemSuccess
	 * @param {updateItemError=}   args.error   Optional. Error callback. Default: wp.updates.updateItemError
	 * @return {$.promise} A jQuery promise that represents the request,
	 *                     decorated with an abort() method.
	 */
	wp.updates.updateTranslations = function( args ) {
		var $message = $( '[data-type="translations"]' ).find( '.update-link' );

		args = _.extend( {
			success: wp.updates.updateItemSuccess,
			error: wp.updates.updateItemError
		}, args );

		if ( $message.html() !== wp.updates.l10n.updating ) {
			$message.data( 'originaltext', $message.html() );
		}

		$message.addClass( 'updating-message' )
			.attr( 'aria-label', wp.updates.l10n.updatingTranslationsLabel )
			.text( wp.updates.l10n.updating );

		return wp.updates.ajax( 'update-translations', args );
	};

	/**
	 * Sends an Ajax request to the server to install a single item.
	 *
	 * Adds the update item to the queue where the right update handler will be called.
	 *
	 * @since 4.X.0
	 *
	 * @param {jQuery} $itemRow jQuery object of the item to be updated.
	 */
	wp.updates.updateItem = function( $itemRow ) {
		var type   = $itemRow.data( 'type' ),
		    update = {
			    action: 'update-' + type,
			    data:   {
				    success: wp.updates.updateItemSuccess,
				    error:   wp.updates.updateItemError
			    }
		    };

		switch ( type ) {
			case 'plugin':
				update.data.plugin = $itemRow.data( 'plugin' );
				update.data.slug   = $itemRow.data( 'slug' );
				break;

			case 'theme':
				update.data.slug = $itemRow.data( 'slug' );
				break;

			case 'core':

				// The update queue should only ever contain one core update.
				if ( _.findWhere( wp.updates.queue, { action: 'update-core' } ) ) {
					return;
				}

				update.data.version   = $itemRow.data( 'version' );
				update.data.locale    = $itemRow.data( 'locale' );
				update.data.reinstall = !! $itemRow.data( 'reinstall' );
				break;
		}

		wp.updates.queue.push( update );
		wp.updates.queueChecker();
	};

	/**
	 * Updates the UI appropriately after a successful update of an item.
	 *
	 * @since 4.X.0
	 *
	 * @typedef {object} updateItemSuccess
	 * @param {object}  response            Response from the server.
	 * @param {string}  response.update     The type of update. 'core', 'plugin', 'theme', or 'translations'.
	 * @param {string=} response.slug       Optional. Slug of the theme or plugin that was updated.
	 * @param {string=} response.reinstall  Optional. Whether this was a reinstall request or not.
	 * @param {string=} response.redirect   Optional. URL to redirect to after updating Core.
	 * @param {string=} response.plugin     Optional. Basename of the plugin that was updated.
	 * @param {string=} response.pluginName Optional. Name of the plugin that was updated.
	 * @param {string=} response.oldVersion Optional. Old version of the theme or plugin.
	 * @param {string=} response.newVersion Optional. New version of the theme or plugin.
	 * @param {string=} response.locale     Optional. The locale of the requested core upgrade.
	 */
	wp.updates.updateItemSuccess = function( response ) {
		var type = response.update,
		    $row = $( '[data-type="' + type + '"]' );

		if ( 'plugin' === type || 'theme' === type ) {
			$row = $row.filter( '[data-slug="' + response.slug + '"]' );

			wp.updates.decrementCount( type );
		} else if ( 'core' === type ) {
			$row = $row.filter( function() {
				var $coreRow = $( this );

				return 'reinstall' === response.reinstall && $coreRow.is( '.wordpress-reinstall-card-item' ) ||
					'reinstall' !== response.reinstall && ! $coreRow.is( '.wordpress-reinstall-card-item' ) && response.locale === $coreRow.data( 'locale' );
			} );
		}

		$row.find( '.update-link' )
			.removeClass( 'updating-message' )
			.addClass( 'updated-message' )
			.attr( 'aria-label', wp.updates.l10n.updated )
			.prop( 'disabled', true )
			.text( wp.updates.l10n.updated );

		wp.a11y.speak( wp.updates.l10n.updatedMsg, 'polite' );

		if ( 'core' === type && response.redirect ) {
			wp.updates.coreUpdateRedirect = response.redirect;
		}

		$document.trigger( 'wp-' + type + '-update-success', response );
	};

	/**
	 * Updates the UI appropriately after a failed update of an item.
	 *
	 * @since 4.X.0
	 *
	 * @typedef {object} updateItemError
	 * @param {object}  response              Response from the server.
	 * @param {string}  response.update       The type of update. 'core', 'plugin', 'theme', or 'translations'.
	 * @param {string}  response.errorCode    Error code for the error that occurred.
	 * @param {string}  response.errorMessage The error that occurred.
	 * @param {string=} response.slug         Optional. Slug of the theme or plugin that was updated.
	 * @param {string=} response.plugin       Optional. Basename of the plugin that was updated.
	 * @param {string=} response.pluginName   Optional. Name of the plugin that was updated.
	 * @param {string=} response.reinstall    Optional. Whether this was a reinstall request or not.
	 * @param {string=} response.locale       Optional. The locale of the requested core upgrade.
	 */
	wp.updates.updateItemError = function( response ) {
		var type = response.update,
		    $row = $( '[data-type="' + type + '"]' ),
		    errorMessage = wp.updates.l10n.updateFailed.replace( '%s', response.errorMessage );

		if ( wp.updates.maybeHandleCredentialError( response, 'update-' + response.update ) ) {
			return;
		}

		if ( 'plugin' === type || 'theme' === type ) {
			$row = $row.filter( '[data-slug="' + response.slug + '"]' );
		} else if ( 'core' === type ) {
			$row = $row.filter( function() {
				var $coreRow = $( this );

				return 'reinstall' === response.reinstall && $coreRow.is( '.wordpress-reinstall-card-item' ) ||
					'reinstall' !== response.reinstall && ! $coreRow.is( '.wordpress-reinstall-card-item' ) && response.locale === $coreRow.data( 'locale' );
			} );
		}

		$row.find( '.update-link' )
			.removeClass( 'updating-message' )
			.attr( 'aria-label', wp.updates.l10n.updateFailedShort )
			.prop( 'disabled', true )
			.text( wp.updates.l10n.updateFailedShort );

		wp.updates.addAdminNotice( {
			id:        response.errorCode,
			className: 'notice-error is-dismissible',
			message:   errorMessage
		} );

		wp.a11y.speak( errorMessage, 'assertive' );

		$document.trigger( 'wp-' + type + '-update-error', response );
	};

	wp.updates._addCallbacks = function( data, action ) {
		if ( 'update-core' === pagenow || 'update-core-network' === pagenow ) {
			data.success = wp.updates.updateItemSuccess;
			data.error   = wp.updates.updateItemError;
		} else if ( 'import' === pagenow && 'install-plugin' === action ) {
			data.success = wp.updates.installImporterSuccess;
			data.error   = wp.updates.installImporterError;
		}

		return data;
	};

	wp.updates.queueChecker = function() {
		var job;

		if ( wp.updates.ajaxLocked || ! wp.updates.queue.length ) {
			return;
		}

		job = wp.updates.queue.shift();

		// Handle a queue job.
		switch ( job.action ) {
			case 'install-plugin':
				wp.updates.installPlugin( job.data );
				break;

			case 'update-plugin':
				wp.updates.updatePlugin( job.data );
				break;

			case 'delete-plugin':
				wp.updates.deletePlugin( job.data );
				break;

			case 'install-theme':
				wp.updates.installTheme( job.data );
				break;

			case 'update-theme':
				wp.updates.updateTheme( job.data );
				break;

			case 'delete-theme':
				wp.updates.deleteTheme( job.data );
				break;

			case 'update-core':
				wp.updates.updateCore( job.data );
				break;

			case 'update-translations':
				wp.updates.updateTranslations( job.data );
				break;

			default:
				window.console.error( 'Failed to execute queued update job.', job );
				break;
		}
	};

	$( function() {
		var $theList = $( '#the-list' );

		$document.on( 'credential-modal-cancel', function() {
			if ( 'update-core' === pagenow || 'update-core-network' === pagenow ) {
				$( '.updating-message' ).removeClass( 'updating-message' ).text( function() {
					return $( this ).data( 'originaltext' );
				} );
			}
		} );

		/**
		 * Click handler for updates in the Update List Table view.
		 *
		 * Handles the re-install core button and "Update All" as well.
		 *
		 * @since 4.X.0
		 *
		 * @param {Event} event Event interface.
		 */
		$( '.update-core-php .update-link' ).on( 'click', function( event ) {
			var $message = $( event.target ),
			    $itemRow = $message.parents( '[data-type]' ),

			    /*
			     * There can be more than one WP update on localized installs.
			     *
			     * This selects the update button of the other available core update, to later determine whether that
			     * update is already running and manipulate that button accordingly.
			     */
			    $otherUpdateCoreButton = $( '.update-link[data-type="core"]' ).not( $message ),
			    $allOtherUpdateButtons = $( '.update-link:enabled' ).not( $message ),
			    type                   = $itemRow.data( 'type' ) || $message.data( 'type' );

			// Select both 'Update All' buttons.
			if ( 'all' === type ) {
				$message = $( '.update-link[data-type="all"]' );
			}

			event.preventDefault();

			// The item has already been updated, do not proceed.
			if ( ! $message.length || $message.hasClass( 'updated-message' ) || $message.hasClass( 'updating-message' ) || $message.hasClass( 'button-disabled' ) ) {
				return;
			}

			// Bail if there's already another core update going on.
			if ( $otherUpdateCoreButton.hasClass( 'updated-message' ) || $otherUpdateCoreButton.hasClass( 'updating-message' ) ) {
				return;
			}

			wp.updates.maybeRequestFilesystemCredentials( event );

			if ( 'all' === type ) {
				if ( $message.html() !== wp.updates.l10n.updating ) {
					$message.data( 'originaltext', $message.html() );
				}

				$message.addClass( 'updating-message' ).attr( 'aria-label', wp.updates.l10n.updatingAllLabel ).text( wp.updates.l10n.updating );

				// Translations first, themes and plugins afterwards before updating core at last.
				$( $theList.find( 'tr[data-type]' ).get().reverse() ).each( function( index, element ) {
					var $itemRow      = $( element ),
					    $updateButton = $itemRow.find( '.update-link' );

					if ( $updateButton.prop( 'disabled' ) ) {
						return;
					}

					// When there are two core updates (en_US + localized), only update the localized one.
					if ( 1 < $( '.update-link[data-type="core"]' ).length && 'core' === $itemRow.data( 'type' ) && 'en_US' === $itemRow.data( 'locale' ) ) {
						$updateButton.prop( 'disabled', true );

						return;
					}

					$updateButton.addClass( 'updating-message' ).text( wp.updates.l10n.updating );

					wp.updates.updateItem( $itemRow );
				} );
			} else {

				/*
				 * Disable all other update buttons if this one is a core update
				 * or if there's no other update left besides the current one.
				 */
				if ( 'core' === type || ! $allOtherUpdateButtons.length ) {
					$allOtherUpdateButtons.prop( 'disabled', true );
				}

				$message.addClass( 'updating-message' ).text( wp.updates.l10n.updating );

				wp.updates.updateItem( $itemRow );
			}
		} );

		/**
		 * Callback for update-core.php when all updates have been processed.
		 *
		 * Handles the redirect after a successful core update and changes the state
		 * of the "Update All" button after all updates have been processed and there
		 * are no new ones available.
		 *
		 * @since 4.X.0
		 */
		$document.on( 'wp-plugin-update-success wp-theme-update-success wp-core-update-success wp-translations-update-success wp-plugin-update-error wp-theme-update-error wp-core-update-error wp-translations-update-error ', function() {
			var $message = $( '.update-link[data-type="all"]' );

			if ( wp.updates.queue.length ) {
				return;
			}

			if ( $message.length && ! $theList.find( '.update-link:not(.updating-message):enabled' ).not( $message ).length ) {
				$message
					.removeClass( 'updating-message' )
					.addClass( 'updated-message' )
					.attr( 'aria-label', wp.updates.l10n.updated )
					.prop( 'disabled', true )
					.text( wp.updates.l10n.updated );
			}

			// Redirect to about page if a core update took place.
			if ( wp.updates.coreUpdateRedirect ) {
				window.location = wp.updates.coreUpdateRedirect;
			}
		} );
	} );
})( jQuery, window.wp, window._wpUpdatesSettings, window._wpShinyUpdatesSettings );
