import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import { first } from 'ckeditor5/src/utils';

import ckeditor5Icon from '../theme/icons/bookishImage.svg';

export default class BookishImage extends Plugin {
	static get pluginName() {
		return 'BookishImage';
	}

	init() {
		const editor = this.editor;
		const t = editor.t;
		const model = editor.model;
		const { schema } = editor.model;

		if ( schema.isRegistered( 'imageInline' ) ) {
			schema.extend( 'imageInline', {
				allowAttributes: [
					'dataBookishImageStyle'
				]
			} );
		}

		if ( schema.isRegistered( 'imageBlock' ) ) {
			schema.extend( 'imageBlock', {
				allowAttributes: [
					'dataBookishImageStyle'
				]
			} );
		}

		editor.conversion.for( 'upcast' ).add( dispatcher => {
			dispatcher.on( 'element:img', ( evt, data, conversionApi ) => {
				const { viewItem } = data;
				const { writer } = conversionApi;
				const imageStyle = viewItem.getAttribute( 'data-bookish-image-style' );
				const uuid = viewItem.getAttribute( 'data-entity-uuid' );
				if ( !imageStyle || !uuid ) {
					return;
				}
				const modelRange = data.modelRange;
				const item = modelRange && modelRange.start.nodeAfter;
				if ( !item ) {
					return;
				}
				if ( ( item.name === 'imageInline' || item.name === 'imageBlock' ) && item.getAttribute( 'dataEntityUuid' ) === uuid ) {
					writer.setAttribute(
						'dataBookishImageStyle',
						imageStyle,
						item
					);
					conversionApi.consumable.consume( viewItem, { attributes: 'data-bookish-image-style' } );
				}
			} );
		} );

		editor.conversion.for( 'downcast' ).add( dispatcher => {
			dispatcher.on( 'attribute:dataBookishImageStyle', ( evt, data, conversionApi ) => {
				const { item } = data;
				const { consumable, writer } = conversionApi;
				if ( !consumable.consume( item, evt.name ) ) {
					return;
				}

				const viewElement = conversionApi.mapper.toViewElement( item );
				const imageInFigure = Array.from( viewElement.getChildren() ).find(
					child => child.name === 'img'
				);

				if ( data.attributeNewValue !== null ) {
					writer.setAttribute( 'data-bookish-image-style', data.attributeNewValue, imageInFigure || viewElement );
				} else {
					writer.removeAttribute( 'data-bookish-image-style', imageInFigure || viewElement );
				}
			} );
		} );

		editor.ui.componentFactory.add( 'bookishImageButton', locale => {
			const view = new ButtonView( locale );

			view.set( {
				label: t( 'Edit image' ),
				icon: ckeditor5Icon,
				tooltip: true,
				isToggleable: true
			} );

			this.listenTo( view, 'execute', () => {
				const selection = model.document.selection;
				const selectedElement = selection.getSelectedElement() || first( selection.getSelectedBlocks() );
				const dataEntityUuid = selectedElement.getAttribute( 'dataEntityUuid' );
				const dataEntityType = selectedElement.getAttribute( 'dataEntityType' );
				const imageStyle = selectedElement.getAttribute( 'dataBookishImageStyle' );
				if ( !dataEntityType || dataEntityType != 'file' || !dataEntityUuid ) {
					return;
				}
				const elementSettings = {
					url: window.drupalSettings.path.baseUrl + window.drupalSettings.path.pathPrefix +
                        `admin/bookish-image-effect-form/${ dataEntityUuid }`,
					event: 'click',
					dialogType: 'modal',
					dialog: {
						// width: '90%',
						width: 1280,
						height: 850
					},
					progress: {
						type: 'none'
					}
				};
				if ( imageStyle ) {
					elementSettings.url += `?imageStyle=${ encodeURIComponent( imageStyle ) }`;
				}
				window.Drupal.ajax( elementSettings ).execute();
				window.bookishImageAjaxCallback = function( src, imageStyle ) {
					model.change( writer => {
						const url = new URL( src );
						url.searchParams.set( 't', ( new Date() ).getTime() );
						writer.setAttribute( 'src', url.toString(), selectedElement );
						writer.setAttribute( 'dataBookishImageStyle', imageStyle, selectedElement );
					} );
				}; } );
			return view;
		} );
	}
}
