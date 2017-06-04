( function() {

	// Bail if we don't have the JSON, which is passed in via `wp_localize_script()`.
	if ( _.isUndefined( members_cp_data ) ) {
		return;
	}

	/* === Models === */

	// Section model (each section belongs to a manager).
	var Section = Backbone.Model.extend( {
		defaults : {
			name        : '',
			label       : '',
			description : '',
			icon        : '',
			selected    : false
		}
	} );

	// Control model (each control belongs to a manager and section).
	var Control = Backbone.Model.extend( {
		defaults : {
			name        : '',
			type        : '',
			label       : '',
			description : '',
			value       : '',
			choices     : {},
			attr        : '',
			section     : ''
		}
	} );

	/* === Collections === */

	// Collection of sections.
	var Sections = Backbone.Collection.extend( {
		model : Section
	} );

	/* === Views === */

	// Section view.  Handles the output of a section.
	var Section_View = Backbone.View.extend( {
		tagName : 'div',
		template : wp.template( 'members-cp-section' ),
		attributes : function() {
			return {
				'id'          : 'members-cp-section-' + this.model.get( 'name' ),
				'class'       : 'members-cp-section',
				'aria-hidden' : ! this.model.get( 'selected' )
			};
		},
		initialize : function( options ) {
			this.model.on( 'change', this.onchange, this );
		},
		render : function() {

			this.el.innerHTML = this.template( this.model.toJSON() );

			return this;
		},
		onchange : function() {

			// Set the view's `aria-hidden` attribute based on whether the model is selected.
			this.el.setAttribute( 'aria-hidden', ! this.model.get( 'selected' ) );
		},
	} );

	// Nav view.
	var Nav_View = Backbone.View.extend( {
		template : wp.template( 'members-cp-nav' ),
		tagName : 'li',
		attributes : function() {
			return {
				'aria-selected' : this.model.get( 'selected' )
			};
		},
		initialize : function() {
			this.model.on( 'change', this.render, this );
			this.model.on( 'change', this.onchange, this );
		},
		render : function() {

			this.el.innerHTML = this.template( this.model.toJSON() );

			return this;
		},
		events : {
			'click a' : 'onselect'
		},
		onchange : function() {

			// Set the `aria-selected` attibute based on the model selected state.
			this.el.setAttribute( 'aria-selected', this.model.get( 'selected' ) );
		},
		onselect : function( event ) {
			event.preventDefault();

			// Loop through each of the models in the collection and set them to inactive.
			_.each( this.model.collection.models, function( m ) {

				m.set( 'selected', false );
			}, this );

			// Set this view's model to selected.
			this.model.set( 'selected', true );
		}
	} );

	// Control view. Handles the output of a control.
	var Control_View = Backbone.View.extend( {
		tagName : 'div',
		template : wp.template( 'members-cp-control' ),
		attributes : function() {
			return {
				'id'    : 'members-cp-control-' + this.model.get( 'name' ),
				'class' : 'members-cp-control'
			};
		},
		render : function() {

			this.el.innerHTML = this.template( this.model.toJSON() );
			return this;
		}
	} );

	var sections = new Sections();

	_.each( members_cp_data.sections ), function( data ) {

		sections.add( new Section( data ) );
	}

	sections.forEach( function( section, i ) ) {

		var nav_view     = new Nav_View(     { model : section } );
		var section_view = new Section_View( { model : section } );

		document.querySelector( '#members-cp .members-cp-nav' ).appendChild( nav_view.render().el );
		document.querySelector( '#members-cp .members-cp-content' ).appendChild( section_view.render().el );

		// If the first model, set it to selected.
		section.set( 'selected', 0 == i );
	}, this );

	_.each( members_cp_data.controls ), function( data ) {

		var control = new Control( data );

		var view = new Control_View( { model : control } );

		document.getElementById( '#members-cp-section-' + control.get( 'section' ) ).appendChild( view.render().el );
	} );

}() );
