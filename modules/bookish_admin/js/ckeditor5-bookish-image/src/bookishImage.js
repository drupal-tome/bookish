import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';

import ckeditor5Icon from '../theme/icons/ckeditor.svg';

export default class BookishImage extends Plugin {
	static get pluginName() {
		return 'BookishImage';
	}

	init() {
		const editor = this.editor;
		const t = editor.t;
		const model = editor.model;
		const writer = editor.conversion;

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
				const src = selectedElement.getAttribute( 'src' );
				const dataEntityUuid = selectedElement.getAttribute( 'dataEntityUuid' );
				const dataEntityType = selectedElement.getAttribute( 'dataEntityType' );
				if (!src || !dataEntityType || dataEntityType != 'file' || !dataEntityUuid) {
					return;
				}
				const imageStyle = 'social_card';
				const element_settings = {
          url: drupalSettings.path.baseUrl + drupalSettings.path.pathPrefix +
						`admin/bookish-image-effect-form/${dataEntityUuid}/${imageStyle}`,
          event: 'click',
					dialogType: 'modal',
					dialog: {
						width: 1280,
						height: 800,
					},
          progress: {
            type: 'none'
          }
        };
				Drupal.ajax(element_settings).execute();
				window.bookishImageAjaxCallback = function (src) {
					model.change(writer => {
						writer.setAttribute('src', src, selectedElement);
					});
				}});

			return view;
		} );
	}
}
